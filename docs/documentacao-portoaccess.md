# PortoAccess — Sistema de Controle de Acesso Veicular Portuário

**Documentação de Projeto — v1.0**
Data: Junho/2026 · Local: Manaus/AM

---

## 1. Visão Geral

O PortoAccess é uma aplicação web para controle de entrada e saída de veículos em um porto, com reconhecimento automático de placas (ALPR/LPR), identificação de cor e modelo do veículo via câmeras IP, cobrança por tipo de acesso e gestão financeira/operacional.

O sistema substitui o controle manual da guarita por um fluxo assistido por câmera: o veículo se aproxima, a câmera lê a placa automaticamente, o sistema identifica o veículo (se já cadastrado) e apresenta ao segurança a tela de confirmação com tipo de entrada, categoria e valor a cobrar. O administrador gerencia preços, tipos de entrada, convênios com empresas e acompanha receita e fluxo de veículos por meio de dashboards.

### 1.1 Objetivos

- Automatizar a identificação de veículos na entrada e na saída (placa, cor, modelo).
- Reduzir tempo de atendimento na guarita e erros de digitação.
- Controlar e auditar todos os acessos (quem entrou, quando, por quê, quem liberou).
- Cobrar corretamente os acessos pagos (retirada de mercadoria e embarque na balsa) com múltiplas formas de pagamento.
- Permitir faturamento posterior para empresas conveniadas (pagamento a prazo).
- Fornecer ao administrador visão de receita, volume de veículos e relatórios gerenciais.

### 1.2 Escopo

**Incluído:** portal web (guarita + administração), integração com 2 câmeras LPR (entrada e saída), tabela de preços configurável, registro de pagamentos (PIX, cartão, misto, faturado), relatórios, controle de pátio, auditoria.

**Fora do escopo (fase 1):** integração automática com adquirente de cartão (TEF), emissão de NFS-e, aplicativo mobile, balança de pesagem, reconhecimento facial de motoristas.

---

## 2. Perfis de Acesso (RBAC)

| Perfil | Quem usa | Resumo de permissões |
|---|---|---|
| **Operador (Segurança)** | Segurança na guarita | Registrar entradas/saídas, confirmar leituras da câmera, receber pagamentos, consultar veículos, abrir cancela |
| **Administrador** | Gestor do porto | Tudo do Operador + configurar preços, tipos de entrada, categorias de veículo, empresas conveniadas, usuários, ver dashboards financeiros e relatórios |
| **Financeiro** *(sugerido)* | Setor financeiro | Visualizar receitas, gerenciar faturas de empresas conveniadas, registrar baixas de pagamento a prazo. Sem acesso operacional à guarita |
| **Auditor** *(sugerido, somente leitura)* | Fiscalização/gerência | Visualiza todos os registros e relatórios, não altera nada |

### 2.1 Matriz de permissões

| Funcionalidade | Operador | Admin | Financeiro | Auditor |
|---|:-:|:-:|:-:|:-:|
| Registrar entrada/saída | ✅ | ✅ | ❌ | ❌ |
| Confirmar/corrigir leitura da câmera | ✅ | ✅ | ❌ | ❌ |
| Receber pagamento (PIX/cartão/misto) | ✅ | ✅ | ❌ | ❌ |
| Marcar acesso como "faturado" (empresa) | ✅ | ✅ | ❌ | ❌ |
| Cancelar/estornar registro | ❌ (solicita) | ✅ | ❌ | ❌ |
| Configurar preços e tipos de entrada | ❌ | ✅ | ❌ | ❌ |
| Cadastrar categorias de veículo | ❌ | ✅ | ❌ | ❌ |
| Cadastrar empresas conveniadas | ❌ | ✅ | ✅ | ❌ |
| Gerar/baixar faturas mensais | ❌ | ✅ | ✅ | ❌ |
| Dashboard de receita e volume | ❌ | ✅ | ✅ | ✅ (leitura) |
| Gerenciar usuários e perfis | ❌ | ✅ | ❌ | ❌ |
| Consultar logs de auditoria | ❌ | ✅ | ❌ | ✅ |

> **Regra importante:** o Operador nunca exclui registros. Erros são corrigidos via solicitação de cancelamento aprovada pelo Administrador, mantendo trilha de auditoria.

---

## 3. Regras de Negócio

### 3.1 Tipos de entrada

Os tipos de entrada são **configuráveis pelo administrador** (nome, regras, cobrança). Os quatro tipos iniciais:

| Tipo | Cobrança | Comportamento |
|---|---|---|
| **Funcionário** | Isento | Veículo pré-cadastrado vinculado a um funcionário. Liberação automática se a placa estiver na lista. Sem limite de permanência |
| **Visita** | Isento | Entrada rápida com saída prevista em curto prazo. Exige identificação do visitante (nome, documento, destino). Alerta se permanência exceder limite configurável (ex.: 2h) |
| **Retirada de mercadoria** | **Paga** | Valor por categoria de veículo. Pagamento na saída (padrão) ou na entrada (configurável). Aceita PIX, cartão, misto ou faturamento para empresa conveniada |
| **Embarque na balsa** | **Paga** | Valor por categoria de veículo. Pagamento obrigatório antes da liberação para a área de embarque. Pode ter agenda de horários de balsa (opcional, fase 2) |

### 3.2 Categorias de veículo

Cadastráveis pelo administrador. Sugestão inicial:

1. Motocicleta
2. Carro de passeio
3. SUV
4. Caminhonete (pickup)
5. Van / Utilitário
6. Caminhão toco (2 eixos)
7. Caminhão truck (3 eixos)
8. Carreta / Bitrem
9. Ônibus / Micro-ônibus

A câmera sugere a categoria automaticamente (com base no modelo detectado), mas **o operador sempre pode corrigir** — a classificação automática é auxiliar, não definitiva.

### 3.3 Tabela de preços

O preço é definido pela combinação **tipo de entrada × categoria de veículo**, com vigência por data (histórico de preços preservado para auditoria). Exemplo:

| Categoria | Retirada de mercadoria | Embarque na balsa |
|---|---|---|
| Motocicleta | R$ 10,00 | R$ 25,00 |
| Carro de passeio | R$ 20,00 | R$ 60,00 |
| Caminhonete | R$ 30,00 | R$ 90,00 |
| Caminhão toco | R$ 50,00 | R$ 180,00 |
| Carreta | R$ 80,00 | R$ 350,00 |

*(valores ilustrativos — o administrador define os reais)*

O sistema deve permitir ainda: desconto percentual por convênio, tarifa de permanência extra (opcional) e isenções pontuais com justificativa obrigatória (registrada em auditoria).

### 3.4 Formas de pagamento

| Forma | Funcionamento no sistema |
|---|---|
| **PIX** | Sistema exibe QR Code (chave estática na fase 1; PIX dinâmico via API do banco/PSP na fase 2 para conferência automática). Operador confirma o recebimento |
| **Cartão (débito/crédito)** | Cobrança feita na maquininha física; operador registra bandeira/modalidade e o valor no sistema. Integração TEF opcional na fase 2 |
| **Misto** | Divide o valor em duas ou mais formas (ex.: parte PIX + parte cartão). O sistema valida que a soma das parcelas = valor total |
| **Faturado (empresa terceirizada)** | Acesso vinculado a uma empresa conveniada. Nenhum valor é recebido na hora; o débito acumula na conta da empresa e é cobrado no fechamento (semanal/quinzenal/mensal, configurável). Exige que o veículo/motorista esteja autorizado pela empresa ou autorização manual do operador com justificativa |

**Ciclo do faturamento:** acessos faturados → fechamento do período → geração da fatura (PDF com extrato de acessos: data, placa, tipo, valor) → envio à empresa → registro da baixa quando paga → relatório de inadimplência.

### 3.5 Fluxo de entrada

1. Veículo para diante da cancela de entrada; câmera LPR captura placa, cor e modelo.
2. Sistema busca a placa no cadastro:
   - **Funcionário autorizado** → tela mostra dados, operador confirma, cancela abre (ou abertura automática, configurável).
   - **Veículo conhecido (histórico)** → tela pré-preenchida; operador escolhe o tipo de entrada.
   - **Veículo novo** → operador completa o cadastro mínimo (categoria, motorista se exigido, tipo de entrada).
3. Se tipo pago com cobrança na entrada (balsa): operador registra o pagamento antes de liberar.
4. Sistema grava o registro de acesso com foto, abre a cancela e o veículo entra no pátio.

**Divergência de segurança:** se a cor/modelo detectados divergirem do cadastro da placa (possível placa clonada), o sistema exibe alerta destacado e exige confirmação manual do operador.

### 3.6 Fluxo de saída

1. Câmera de saída lê a placa; sistema localiza o registro de entrada em aberto.
2. Se houver valor pendente (retirada de mercadoria com pagamento na saída): tela de cobrança → operador registra pagamento (ou faturamento) → liberação.
3. Se isento ou já pago: liberação direta.
4. Registro fechado com horário de saída e tempo de permanência calculado.
5. **Sem registro de entrada correspondente** → alerta; operador registra saída manual com justificativa (auditada).

### 3.7 Modo contingência

Se a câmera ou o reconhecimento ficar fora do ar, o operador digita a placa manualmente — o restante do fluxo permanece idêntico. Todos os registros manuais são marcados como tal nos relatórios.

---

## 4. Funcionalidades por Tela

### 4.1 Guarita (Operador)

- **Painel ao vivo:** última captura da câmera (foto + placa lida + cor/modelo), botões grandes de Confirmar Entrada / Confirmar Saída, status da cancela.
- **Fila de eventos:** leituras recentes pendentes de confirmação.
- **Cobrança:** tela de pagamento com seleção de forma(s), QR Code PIX, valor calculado automaticamente.
- **Pátio atual:** lista de veículos dentro do porto (placa, tipo, hora de entrada, tempo decorrido) com busca.
- **Consulta de placa:** histórico do veículo.
- **Visitas vencidas:** alerta de visitas que ultrapassaram o tempo limite.

### 4.2 Administração

- **Dashboard:** receita do dia/semana/mês, quantidade de veículos por dia (gráfico), distribuição por tipo de entrada e categoria, ticket médio, comparativo de períodos, veículos no pátio agora.
- **Preços:** CRUD da tabela tipo × categoria com vigência.
- **Tipos de entrada:** criar/editar tipos, definir se é pago, momento da cobrança, tempo limite.
- **Categorias de veículo:** CRUD.
- **Empresas conveniadas:** cadastro (CNPJ, contato, condições), veículos/motoristas autorizados, limite de crédito opcional.
- **Faturas:** fechamento de período, geração de PDF, registro de baixa, painel de inadimplência.
- **Funcionários e veículos autorizados:** CRUD de lista de liberação automática.
- **Usuários do sistema:** CRUD com perfis.
- **Relatórios exportáveis (PDF/Excel):** movimento diário, receita por forma de pagamento, acessos por empresa, registros manuais/cancelados, permanência média.
- **Auditoria:** trilha completa (quem fez o quê e quando), incluindo alterações de preço e cancelamentos.

---

## 5. Requisitos

### 5.1 Requisitos funcionais (resumo)

- **RF01** — Capturar automaticamente placa, cor e modelo do veículo nas câmeras de entrada e saída.
- **RF02** — Permitir confirmação/correção manual de toda leitura automática.
- **RF03** — Controlar acesso por perfis (Operador, Administrador, Financeiro, Auditor).
- **RF04** — Gerenciar tipos de entrada configuráveis, com regra de cobrança por tipo.
- **RF05** — Gerenciar categorias de veículo e tabela de preços com vigência.
- **RF06** — Registrar pagamentos PIX, cartão, misto e faturado, validando soma no pagamento misto.
- **RF07** — Gerenciar empresas conveniadas e gerar faturas por período com extrato de acessos.
- **RF08** — Manter estado do pátio (veículos dentro) em tempo real.
- **RF09** — Alertar divergência cor/modelo × placa cadastrada e saída sem entrada.
- **RF10** — Acionar a cancela (relé) a partir do sistema.
- **RF11** — Registrar trilha de auditoria imutável de todas as operações.
- **RF12** — Emitir relatórios gerenciais e exportações (PDF/Excel).
- **RF13** — Operar em modo contingência com digitação manual de placa.
- **RF14** — Armazenar foto do veículo em cada acesso.

### 5.2 Requisitos não funcionais

- **RNF01 — Desempenho:** da captura da placa à exibição na tela da guarita em ≤ 2 s.
- **RNF02 — Disponibilidade:** operação local (on-premise na guarita) para funcionar mesmo com internet instável; sincronização com nuvem opcional.
- **RNF03 — Segurança:** senhas com hash (bcrypt/argon2), HTTPS, sessões com expiração, princípio do menor privilégio.
- **RNF04 — LGPD:** placas e dados de motoristas são dados pessoais. Definir política de retenção (ex.: fotos por 90 dias, registros por 5 anos), termo de aviso de monitoramento no local, acesso restrito por perfil.
- **RNF05 — Usabilidade:** tela da guarita operável com poucos cliques, botões grandes, legível sob luz solar (tema de alto contraste).
- **RNF06 — Auditabilidade:** nenhum registro financeiro é apagado fisicamente (soft delete + log).
- **RNF07 — Backup:** banco com backup diário automático local + cópia externa.

---

## 6. Modelo de Dados (entidades principais)

```
users (id, name, email, password, role, active)
vehicle_categories (id, name, active)
entry_types (id, name, is_paid, charge_moment[entrada|saida], max_stay_minutes, active)
prices (id, entry_type_id, vehicle_category_id, amount, valid_from, valid_to)
vehicles (id, plate, vehicle_category_id, brand, model, color, owner_name, notes)
authorized_vehicles (id, vehicle_id, type[funcionario|empresa], employee_name, company_id, valid_until, active)
companies (id, name, cnpj, contact, billing_cycle, credit_limit, active)
access_records (id, vehicle_id, entry_type_id, vehicle_category_id, entered_at, exited_at,
                entry_photo, exit_photo, detected_color, detected_model, plate_read_confidence,
                amount_due, status[no_patio|finalizado|cancelado], manual_entry[bool],
                operator_in_id, operator_out_id, company_id, visitor_name, visitor_document, destination)
payments (id, access_record_id, method[pix|cartao_debito|cartao_credito|faturado], amount, paid_at, user_id)
invoices (id, company_id, period_start, period_end, total, status[aberta|paga|vencida], paid_at)
invoice_items (id, invoice_id, access_record_id, amount)
camera_events (id, camera[entrada|saida], plate, color, model, confidence, photo_path,
               occurred_at, access_record_id, status[pendente|vinculado|descartado])
audit_logs (id, user_id, action, entity, entity_id, old_values, new_values, created_at)
```

Observações: pagamento **misto** = dois ou mais registros em `payments` para o mesmo `access_record`; pagamento **faturado** = `payments.method = faturado` com vínculo posterior em `invoice_items`.

---

## 7. Arquitetura Técnica

```
┌─────────────────┐   RTSP/eventos    ┌──────────────────────────┐
│ Câmera LPR       │ ────────────────► │ Servidor local (guarita) │
│ ENTRADA          │                   │                          │
└─────────────────┘                   │  • App Laravel (API+Web) │
┌─────────────────┐   RTSP/eventos    │  • PostgreSQL            │
│ Câmera LPR       │ ────────────────► │  • Serviço de captura    │
│ SAÍDA            │                   │    de eventos LPR        │
└─────────────────┘                   │  • Storage de fotos      │
                                       └─────┬────────────┬──────┘
                                             │ HTTP local │ GPIO/relé
                                      ┌──────▼─────┐ ┌────▼───────┐
                                      │ Navegador   │ │ Cancelas   │
                                      │ guarita/adm │ │ entrada/   │
                                      └────────────┘ │ saída      │
                                                     └────────────┘
```

- **Backend/Frontend:** Laravel 11 + Inertia.js + Vue 3 (stack já dominada pela equipe), PostgreSQL, filas com Redis/Horizon para processar eventos de câmera, WebSocket (Laravel Reverb) para atualizar a tela da guarita em tempo real.
- **Captura de eventos LPR — duas opções:**
  - **Opção A (recomendada): câmera com LPR embarcado.** A própria câmera lê a placa e identifica cor/marca, enviando o evento por HTTP push/API para um endpoint do Laravel. Sem custo de licença de software, menos carga no servidor. É o caso da Intelbras VIP 5460 LPR IA, que faz leitura automática de placas (inclusive Mercosul) a até 60 km/h com taxa de acerto superior a 95%, identifica cor e marca do veículo, mantém listas de liberação/bloqueio e possui entrada e saída de alarme para acionar cancelas.
  - **Opção B: câmera IP comum + software ALPR** (Plate Recognizer Stream em Docker, ou pipeline próprio YOLO + OCR). Mais flexível para modelo do veículo, porém adiciona licença mensal ou esforço alto de engenharia.
- **Acionamento da cancela:** saída de alarme da própria câmera LPR ou módulo relé IP/USB comandado pelo backend.
- **Hospedagem:** servidor local (mini-PC) na guarita — o porto não pode parar se a internet cair. Painel administrativo acessível na rede local e, opcionalmente, via VPN/túnel para acesso remoto do gestor.

---

## 8. Plano de Compra — Câmeras e Infraestrutura

### 8.1 Pontos de instalação

| Ponto | Equipamento | Função |
|---|---|---|
| Cancela de ENTRADA | 1× câmera LPR | Ler placa + cor/marca na chegada |
| Cancela de SAÍDA | 1× câmera LPR | Ler placa na saída e fechar o registro |
| Entrada e saída (opcional) | 2× câmera IP colorida 4MP comum | Foto de contexto/overview do veículo e motorista (a imagem da LPR é otimizada para a placa) |

**Requisitos de instalação das LPR:** distância de 3–8 m da placa, altura ~1,5–2 m, ângulo ≤ 30°, apontada para o ponto onde o veículo está parado/lento junto à cancela; iluminação IR da própria câmera cobre a leitura noturna (placas são refletivas).

### 8.2 Modelo recomendado

**Intelbras VIP 5460 LPR IA** (ou Hikvision/Dahua equivalentes): câmera 4MP desenvolvida para leitura de placas em baixa/média velocidade, com identificação de cor e marca, relatórios, cadastro de placas para liberar/bloquear acesso e I/O de alarme para controlar cancela — ou seja, cobre placa + cor + marca direto no hardware, com suporte nacional e assistência da Intelbras (relevante em Manaus). A identificação de **modelo específico** (ex.: "Hilux" vs "S10") tem precisão limitada em qualquer solução; o sistema trata modelo como dado auxiliar.

### 8.3 Lista de compras e estimativa de custos (hardware)

*Valores de referência do mercado brasileiro (jun/2026) — confirmar cotação atual antes da compra:*

| Item | Qtd | Unitário (R$) | Total (R$) |
|---|:-:|--:|--:|
| Câmera LPR Intelbras VIP 5460 LPR IA (ou similar) | 2 | 3.000 – 4.500 | 6.000 – 9.000 |
| Câmera IP overview 4MP (opcional) | 2 | 600 – 1.000 | 1.200 – 2.000 |
| Mini-PC servidor (i5, 16 GB RAM, SSD 1 TB) | 1 | 3.000 – 4.500 | 3.000 – 4.500 |
| Switch PoE 8 portas | 1 | 500 – 900 | 500 – 900 |
| Nobreak 1.500 VA (câmeras + servidor) | 1 | 800 – 1.400 | 800 – 1.400 |
| Módulo relé / interface p/ cancela | 2 | 150 – 300 | 300 – 600 |
| Postes/suportes, cabo UTP externo, conduítes, conectores | — | — | 1.000 – 2.000 |
| Instalação (eletricista/CFTV) | — | — | 1.500 – 3.000 |
| **Subtotal hardware + instalação** | | | **14.300 – 23.400** |
| Cancela automática (SE o porto ainda não tiver) | 2 | 4.000 – 8.000 | 8.000 – 16.000 |

### 8.4 Custos de software e serviços

| Item | Cenário A — LPR embarcado | Cenário B — câmera comum + software |
|---|---|---|
| Licença de reconhecimento | **R$ 0** (vem na câmera) | Plate Recognizer Stream: ≈ US$ 50–60/câmera/mês (~R$ 550–700/mês p/ 2 câmeras)* |
| Maquininha de cartão | Taxa por transação da adquirente (1,5–3,5%) | idem |
| PIX dinâmico (fase 2) | Gratuito ou taxa baixa via PSP/banco | idem |
| Domínio + VPN p/ acesso remoto | R$ 100–300/ano | idem |

\* confirmar preço vigente no site do fornecedor.

### 8.5 Custo de desenvolvimento da aplicação

Estimativa de esforço para o escopo da fase 1 (desenvolvedor pleno, stack Laravel/Vue):

| Módulo | Horas |
|---|--:|
| Autenticação, perfis e auditoria | 30 – 40 |
| Cadastros (categorias, tipos, preços, empresas, veículos) | 50 – 70 |
| Fluxo guarita (entrada/saída, tempo real, contingência) | 70 – 90 |
| Pagamentos (PIX/cartão/misto) e faturamento de empresas | 60 – 80 |
| Integração câmera LPR + cancela | 40 – 70 |
| Dashboard e relatórios | 40 – 60 |
| Testes, ajustes em campo e implantação | 40 – 60 |
| **Total** | **330 – 470 h** (~3 a 5 meses, 1 dev) |

A preço de mercado (R$ 70–120/h), o desenvolvimento terceirizado ficaria em **R$ 23.000 – 56.000**. Desenvolvido internamente, o custo direto é o tempo da equipe.

### 8.6 Resumo geral de investimento

| Cenário | Investimento inicial | Custo mensal |
|---|--:|--:|
| **A — LPR embarcado, dev interno, porto já tem cancelas** | R$ 14.300 – 23.400 | ≈ R$ 100 (energia/infra) + taxas de cartão |
| **B — LPR embarcado + dev terceirizado** | R$ 37.000 – 80.000 | idem |
| **C — Câmeras comuns + software ALPR licenciado** | R$ 9.000 – 16.000 (hardware) + dev | + R$ 550–700/mês de licença |

> **Recomendação:** Cenário A. A câmera com LPR embarcado elimina mensalidade, simplifica o software e já entrega placa + cor + marca. Comprar **1 câmera primeiro**, validar em campo (taxa de leitura na iluminação real do porto) e só então comprar a segunda e o restante da infraestrutura.

---

## 9. Cronograma Sugerido

| Fase | Entregas | Duração |
|---|---|---|
| **1. Piloto técnico** | Compra de 1 câmera LPR, instalação provisória na entrada, prova de conceito do recebimento de eventos no Laravel | 3–4 semanas |
| **2. Núcleo do sistema** | Autenticação/perfis, cadastros, tabela de preços, fluxo de entrada/saída manual | 4–6 semanas |
| **3. Integração e cobrança** | Integração definitiva entrada+saída, cancelas, pagamentos e faturamento | 4–6 semanas |
| **4. Gestão** | Dashboard, relatórios, auditoria, faturas PDF | 3–4 semanas |
| **5. Implantação** | Treinamento dos seguranças, operação assistida, ajustes | 2 semanas |

---

## 10. Riscos e Mitigações

| Risco | Impacto | Mitigação |
|---|---|---|
| Placas sujas/danificadas (comum em caminhões) | Leitura falha | Fluxo de digitação manual sempre disponível |
| Reconhecimento de modelo impreciso | Dado errado no cadastro | Modelo é auxiliar; operador confirma categoria |
| Queda de internet | Sistema inacessível | Servidor local na guarita; internet só p/ acesso remoto |
| Queda de energia | Parada total | Nobreak p/ câmeras, servidor e cancela; procedimento manual documentado |
| Fraude interna (isenções indevidas) | Perda de receita | Justificativa obrigatória + auditoria + relatório de isenções p/ o admin |
| Placa clonada | Acesso indevido | Alerta de divergência cor/modelo × cadastro |
| LGPD (dados de placas/motoristas) | Risco legal | Política de retenção, aviso de monitoramento, acesso por perfil |

---

## 11. Evoluções Futuras (fase 2+)

- PIX dinâmico com confirmação automática de pagamento (API PSP/banco).
- Integração TEF com maquininha (registro automático de cartão).
- Emissão de NFS-e dos acessos pagos.
- Agendamento de horários da balsa com venda antecipada.
- App/portal para empresas conveniadas consultarem extrato e faturas.
- Pré-agendamento de visitas com QR Code.
- Balança rodoviária integrada (cobrança por peso).
- Sincronização com nuvem para BI e backup externo.
