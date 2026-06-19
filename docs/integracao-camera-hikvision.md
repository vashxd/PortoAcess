# Integração câmera Hikvision DS-TCG406-E(S) — referência

> Documento de referência para retomar a integração depois. Explica **como funciona**,
> **o que já está pronto no código** e **o que falta confirmar na câmera física**
> (endpoints ISAPI e nomes dos campos do push ANPR). Última atualização: 2026-06-19.

## 1. Contexto / por que essa câmera muda a arquitetura

A `DS-TCG406-E(S)` é uma câmera LPR *all-in-one* de cancela. Dois recursos dela mudam o
projeto:

1. **LPR a bordo** — lê a placa sozinha (multiframe, placa suja, cor/marca/tipo do
   veículo) e faz **push HTTP** do evento. Dispensa o `fast-alpr` rodando no PC.
2. **Relé de saída embutido** ("Suporta saída de relé para controlar cancela") — a câmera
   **é o próprio atuador** da cancela. Dispensa o módulo relé IP externo que estava no
   plano original (driver `http`).

Por isso adotamos o **caminho B**: câmera faz o LPR e empurra o evento; o Laravel decide
abrir (pagamento/funcionário/allowlist/Sinesp) e dispara o relé da câmera via ISAPI.

## 2. Arquitetura (caminho B)

```
            ┌─────────────────────── Câmera Hikvision DS-TCG406 ───────────────────────┐
            │  LPR a bordo (placa + cor/marca/tipo + foto)      Relé de saída embutido  │
            └───────────┬───────────────────────────────────────────────▲──────────────┘
                        │ push ANPR (XML/multipart)                      │ ISAPI trigger
                        ▼                                                │ (PUT .../outputs/N/trigger)
        POST /api/camera/hikvision                                       │
        HikvisionAnprController  ──cria──►  CameraEvent                  │
                        │                       │                        │
                        │   (operador vincula / regra de negócio decide) │
                        ▼                       ▼                        │
              AccessController / GateController ──►  GateService->open()─┘
                                                     driver 'hikvision'
```

Pontos-chave:
- O **adapter** (`/api/camera/hikvision`) só **registra** o evento — igual ao webhook
  atual do `fast-alpr`. Quem **abre** a cancela continua sendo a decisão do
  sistema/operador (`AccessController`/`GateController` → `GateService->open()`).
- O `CameraEvent` já tinha os campos `color`/`model`/`brand` (que o `fast-alpr` nunca
  preenchia) — agora vêm preenchidos pela câmera.

## 3. O que já está implementado no código

| Peça | Arquivo | O que faz |
|---|---|---|
| Driver de cancela `hikvision` | `app/Services/GateService.php` → `openViaHikvision()` | `PUT` ISAPI no relé da câmera, auth **Digest**, timeout 3s, loga falha. |
| Config das câmeras | `config/portoaccess.php` → `cameras.entrada` / `cameras.saida` | ip, user, password, relay_output por cancela. |
| Variáveis | `.env` e `.env.example` | `GATE_DRIVER=hikvision`, `CAM_ENTRADA_*`, `CAM_SAIDA_*`. |
| Adapter ANPR | `app/Http/Controllers/Api/HikvisionAnprController.php` | Recebe push XML/multipart/JSON, extrai placa/cor/marca/foto, cria `CameraEvent`. |
| Rota | `routes/api.php` | `POST /api/camera/hikvision` (`api.camera.hikvision`). |

O adapter é **tolerante a formato**: tenta JSON → XML (corpo puro, campo de form ou
arquivo `.xml`) → campos soltos, e busca a placa por vários nomes de tag conhecidos.

## 4. O que FALTA confirmar na câmera física ⚠️

Tudo abaixo depende de ter a câmera na rede (IP + login). Os valores Hikvision **variam
por firmware** — por isso o código é defensivo, mas precisa de ajuste fino com payloads
reais.

### 4.1 Endpoint/ID do relé (driver de cancela)
- Código assume: `PUT http://IP/ISAPI/System/IO/outputs/<relay_output>/trigger`
  com corpo `<IOPortData><outputState>high</outputState></IOPortData>`.
- **Confirmar:**
  - Quantas saídas de relé a câmera tem e qual ID controla a cancela
    (`GET http://IP/ISAPI/System/IO/outputs` lista os IDs). Ajustar `CAM_*_RELAY`.
  - Se a abertura precisa de **pulso** (high→low) ou se a câmera tem auto-reset
    configurável. Se precisar de pulso manual, mandar um segundo PUT com `low`.
  - Se a auth é **Digest** (padrão) ou Basic. Se Basic, trocar `withDigestAuth` por
    `withBasicAuth` em `GateService::openViaHikvision()`.
  - Há também a opção de usar a saída de relé "dedicada de cancela" da câmera em vez do
    IO genérico (menu *Vehicle Detection / Barrier Gate* na ISAPI). Verificar qual a
    DS-TCG406 expõe.

### 4.2 Formato e campos do push ANPR (adapter)
- **Capturar um push real** e salvar em `docs/samples/` para referência. Caminhos para
  ligar o push na câmera (web da câmera): *Configuration → Event → Smart Event / Vehicle
  Detection → Linkage* → **Notify Surveillance Center / HTTP Listening**, apontando para
  `http://IP_DO_SERVIDOR:8000/api/camera/hikvision`.
- **Confirmar os nomes das tags** que vêm na placa/cor/marca e ajustar o mapa em
  `HikvisionAnprController::mapKeys()`. Hoje cobrimos: `licensePlate`, `vehicleColor`,
  `vehicleType`, `vehicleLogo`, `confidenceLevel`, `dateTime`, `ipAddress` (+ sinônimos).
  Se o firmware usar outros nomes, **adicionar na lista** de cada campo.
- Confirmar se a câmera consegue enviar o header `X-Camera-Token`. Se **não** conseguir,
  duas saídas (ver §5 segurança): usar `?token=` na URL, ou proteger por IP/firewall e
  deixar `CAMERA_WEBHOOK_TOKEN` vazio.
- Confirmar como o push identifica a câmera de origem. Hoje resolvemos por `?camera=` na
  URL (preferido) ou pelo IP de origem (comparado com `config('portoaccess.cameras')`).
  **Recomendado:** configurar URLs distintas por cancela —
  `.../api/camera/hikvision?camera=entrada` e `?camera=saida`.

### 4.3 Rede / infra
- IP fixo das câmeras na LAN, alcançável pelo PC do Laravel.
- Portas: **554** (RTSP, se ainda usar o `bridge.py`), **80** (ISAPI/push).
- Credenciais da câmera nos `CAM_*` do `.env`.

## 5. Segurança

- O token (`CAMERA_WEBHOOK_TOKEN`) protege o endpoint. Se a câmera não enviar header
  custom, prefira `?token=...` na URL **e** restrinja por IP no firewall/servidor.
- Não expor o endpoint à internet sem necessidade — idealmente câmera e servidor na mesma
  LAN/VPN. Se precisar via Cloudflare Tunnel, restringir por token + IP de origem.
- Senhas das câmeras só no `.env` (não commitar).

## 6. Como ativar (quando a câmera chegar)

1. Pôr a câmera na rede, anotar IP/login.
2. `.env`: preencher `CAM_ENTRADA_*` / `CAM_SAIDA_*` e `GATE_DRIVER=hikvision`.
3. `php artisan config:clear`.
4. Na web da câmera, ligar o push ANPR apontando para
   `http://IP_SERVIDOR:8000/api/camera/hikvision?camera=entrada` (e `?camera=saida`).
5. Passar um veículo e conferir em `storage/logs` + tabela `camera_events`.
6. Ajustar `mapKeys()` se algum campo vier vazio (comparar com o payload capturado).
7. Testar o relé: abrir a cancela pela tela da guarita e ver se o `openViaHikvision`
   retorna sucesso (auditado em `audit_logs` como `gate_open`).

## 7. Conviver com o `fast-alpr` (caminho A) durante a transição

- O `bridge.py` + `/api/camera/events` continuam funcionando. Dá pra rodar os dois em
  paralelo enquanto valida a câmera: a Hikvision empurra para `/api/camera/hikvision` e o
  `fast-alpr` para `/api/camera/events`. Quando o push nativo estiver confiável, desligar
  o `bridge.py` (remover do script `dev` do `composer.json`).
- Para o caminho A com a câmera nova, basta trocar `CAMERA_*_URL` no `alpr-bridge/.env`
  para o RTSP da Hikvision: `rtsp://user:senha@IP:554/Streaming/Channels/101`.

## 8. Pendências rápidas (checklist)

- [ ] Confirmar ID do relé da cancela (`GET /ISAPI/System/IO/outputs`) → `CAM_*_RELAY`.
- [ ] Confirmar Digest vs Basic auth no relé.
- [ ] Confirmar se relé precisa de pulso high→low.
- [ ] Capturar push ANPR real → `docs/samples/` e ajustar `mapKeys()`.
- [ ] Decidir identificação da câmera (recomendado `?camera=`).
- [ ] Decidir auth do push (header `X-Camera-Token` vs `?token=` + IP).
- [ ] Testar abertura ponta-a-ponta na guarita.
