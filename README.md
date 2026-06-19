# PortoAccess — Controle de Acesso Veicular Portuário

Aplicação web (fase 1) conforme a [documentação do projeto](docs/documentacao-portoaccess.md): controle de entrada/saída de veículos com leitura de placas por câmera LPR, cobrança por tipo de acesso, faturamento de empresas conveniadas, dashboard e auditoria.

**Stack:** Laravel 12 · Inertia.js · Vue 3 · Tailwind CSS · SQLite (dev) / PostgreSQL (produção)

## Como rodar (desenvolvimento)

```powershell
composer install
npm install
copy .env.example .env        # já configurado neste repositório
php artisan key:generate      # se .env novo
php artisan migrate --seed
php artisan storage:link
npm run build                 # ou: npm run dev (hot reload)
php artisan serve
```

Acesse http://127.0.0.1:8000

> **Nota (esta máquina):** as extensões PHP (sqlite, zip, gd, intl) foram habilitadas
> em um `php.ini` de usuário em `C:\Users\hscjr\.php`, apontado pela variável de
> ambiente `PHPRC` (nível usuário). Em novos terminais isso já vale automaticamente;
> se o PHP reclamar de extensão ausente, confira `$env:PHPRC`.

## Usuários iniciais (seed)

| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | `admin@portoaccess.local` | `password` |
| Operador (guarita) | `operador@portoaccess.local` | `password` |
| Financeiro | `financeiro@portoaccess.local` | `password` |
| Auditor | `auditor@portoaccess.local` | `password` |

O auto-registro está desabilitado: usuários são criados pelo Administrador em **Cadastros → Usuários**. Troque as senhas padrão antes de qualquer uso real.

## Simulando a câmera LPR (sem hardware)

```powershell
# Evento de entrada com placa aleatória
php artisan camera:simulate entrada

# Placa específica / veículo já cadastrado
php artisan camera:simulate entrada ABC1D23
php artisan camera:simulate saida --known

# Forçar divergência cor/modelo (teste do alerta de placa clonada)
php artisan camera:simulate entrada ABC1D23 --color=Roxo --model=Fusca
```

A leitura aparece no **Painel da Guarita** em até 4 s (polling). O painel mostra foto, placa, cor/modelo, dados do cadastro e os botões de confirmação.

### Ponte ALPR para câmera IP comum (Opção B — `alpr-bridge/`)

Para câmeras sem LPR embarcado: serviço Python que captura o vídeo (RTSP/webcam),
lê placas localmente com [fast-alpr](https://github.com/ankandrew/fast-alpr)
(open-source, sem licença mensal) e envia ao webhook.

```powershell
cd alpr-bridge
.\.venv\Scripts\python.exe bridge.py --source "rtsp://admin:SENHA@192.168.1.7:554/onvif1" --camera entrada

# Testar conexão RTSP (câmeras Yoosee: senha do dispositivo no app)
.\.venv\Scripts\python.exe test_rtsp.py SENHA_DO_APP 192.168.1.7

# Testar com webcam ou imagem
.\.venv\Scripts\python.exe bridge.py --source 0 --camera entrada --show
.\.venv\Scripts\python.exe bridge.py --source teste-placa.jpg --camera entrada --once
```

Parâmetros úteis: `--min-conf` (confiança mínima), `--cooldown` (segundos sem repetir
a mesma placa), `--any-plate` (aceita formatos não brasileiros), `--show` (janela com detecções).

### Webhook real (câmera Intelbras VIP 5460 LPR ou similar)

Configure a câmera para enviar HTTP POST para:

```
POST /api/camera/events
Header: X-Camera-Token: <CAMERA_WEBHOOK_TOKEN do .env>
Body JSON: { "camera": "entrada|saida", "plate": "ABC1D23",
             "color": "...", "model": "...", "brand": "...",
             "confidence": 97.5, "photo_base64": "..." }
```

## Funcionalidades implementadas

- **Guarita:** fila de leituras pendentes (tempo quase real), confirmação de entrada/saída, divergência cor/modelo × cadastro (alerta de placa clonada), modo contingência (digitação manual), saída sem entrada com justificativa, acionamento de cancela, pátio atual com busca e alerta de visitas vencidas, consulta de placa com histórico.
- **Cobrança:** preço por tipo × categoria com vigência histórica, PIX (QR Code de chave estática), cartão, dinheiro, pagamento misto (validação da soma), faturado para empresa conveniada (com verificação de autorização, limite de crédito e desconto de convênio), isenções pontuais com justificativa auditada.
- **Faturamento:** fechamento de período por empresa, fatura com extrato de acessos, PDF, registro de baixa, painel de inadimplência (vencidas).
- **Administração:** dashboard (receita dia/semana/mês, comparativo, ticket médio, volume diário, distribuições), CRUDs de categorias, tipos de entrada, preços, empresas, veículos autorizados (funcionário/empresa) e usuários, aprovação de cancelamentos, relatórios com exportação CSV, trilha de auditoria completa.
- **RBAC:** Operador, Administrador, Financeiro e Auditor conforme a matriz de permissões da documentação.

## Configurações (.env)

| Variável | Função |
|---|---|
| `CAMERA_WEBHOOK_TOKEN` | Token do webhook das câmeras LPR |
| `PIX_KEY`, `PIX_MERCHANT_NAME` | Chave PIX estática exibida na cobrança |
| `GATE_DRIVER` | `log` (dev), `http` (módulo relé IP) ou `hikvision` (relé embutido da câmera via ISAPI) |
| `GATE_ENTRADA_URL`, `GATE_SAIDA_URL` | URLs do relé das cancelas (driver `http`) |
| `CAM_ENTRADA_*`, `CAM_SAIDA_*` | IP/login/relé das câmeras Hikvision — ver [docs/integracao-camera-hikvision.md](docs/integracao-camera-hikvision.md) |
| `PHOTO_RETENTION_DAYS` | Retenção de fotos (LGPD — padrão 90 dias) |

## Testes

```powershell
php artisan test
```

Inclui testes de ponta a ponta: webhook da câmera, fluxo retirada com pagamento misto, balsa com cobrança na entrada, faturamento de convênio com geração de fatura e PDF, saída sem entrada, cancelamento operador→admin, RBAC e auditoria.

## Implantação (produção — servidor local na guarita)

1. Trocar `DB_CONNECTION` para `pgsql` e criar o banco PostgreSQL.
2. `APP_ENV=production`, `APP_DEBUG=false`, HTTPS atrás de proxy (Caddy/Nginx).
3. Configurar `GATE_DRIVER=http` com as URLs dos módulos relé.
4. Agendar backup diário do banco (RNF07) e job de limpeza de fotos > `PHOTO_RETENTION_DAYS`.
5. Fase 2 (ver doc): PIX dinâmico, TEF, NFS-e, WebSocket (Laravel Reverb) no lugar do polling, agenda da balsa.
