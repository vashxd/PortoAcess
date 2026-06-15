# PortoAccess — Guia de Apresentação ao Cliente

> Documento de apoio para apresentação comercial.  
> Descreve todas as funcionalidades do sistema, o que destacar para o cliente e respostas para objeções comuns.

---

## O que é o PortoAccess

PortoAccess é um **sistema de controle de acesso veicular** desenvolvido sob medida para portarias de alto fluxo — portos, condomínios industriais, estacionamentos e pátios logísticos.

Ele combina **reconhecimento automático de placas por câmera** com uma plataforma de gestão completa: controle do pátio, cobrança, empresas conveniadas, faturamento, relatórios e auditoria — tudo em uma única tela, acessível do computador ou do celular.

---

## Por que o cliente vai se interessar

| Dor comum do cliente | Como o PortoAccess resolve |
|---|---|
| Operador digita a placa na mão, erra e perde tempo | Câmera lê a placa automaticamente — operador só confirma |
| Não sabe quantos veículos estão no pátio agora | Painel em tempo real com contagem e alertas |
| Visitante fica mais tempo que o permitido sem que ninguém perceba | Alerta automático de permanência excedida |
| Funcionário de empresa conveniada paga o mesmo que visitante | Desconto automático por empresa no ato da confirmação |
| Fim do mês: tem que somar manualmente o que a empresa deve | Fatura gerada com um clique, com extrato detalhado e PDF |
| "A câmera caiu e a fila parou" | Modo contingência manual: o sistema continua funcionando sem câmera |
| Operador cancelou um registro sem autorização | Cancelamento exige aprovação do administrador — toda ação é auditada |

---

## Funcionalidades do sistema

### 1. Reconhecimento automático de placas (ALPR)

- Câmeras IP comuns (inclusive celular com o app IP Webcam) leem placas em tempo real
- O sistema identifica automaticamente se o veículo já tem **cadastro**, se é **funcionário** ou **conveniado**
- Exibe **foto do veículo** capturada no momento da leitura
- Mostra o percentual de **confiança da leitura** (ex.: "97%")
- Detecta **divergência de cor ou modelo** entre o que a câmera viu e o cadastro — alerta visual de possível **placa clonada**
- O operador recebe tudo isso numa única tela: confirma a entrada ou saída com um clique

### 2. Painel da Guarita em tempo real

- Atualiza automaticamente a cada **4 segundos** sem precisar recarregar a página
- Fila de leituras pendentes das câmeras de entrada e saída
- Contagem ao vivo de **veículos no pátio**
- **Alerta destacado** para visitas que ultrapassaram o tempo máximo permitido
- Botões de acionamento manual da cancela (entrada e saída) independentemente de câmera

### 3. Registro de entrada

- Confirma a placa lida pela câmera, classifica o veículo e libera a cancela
- Preenche automaticamente dados do veículo já cadastrado (cor, marca, modelo, proprietário)
- Identifica o **tipo de entrada**: visitante, funcionário, conveniado, turista, etc. (configurável)
- Vincula a empresa conveniada e aplica o **desconto negociado automaticamente**
- Para visitas: registra nome, documento e destino do visitante
- Permite **isenção pontual** com justificativa obrigatória (fica registrada na auditoria)
- Cobrança pode ser feita **na entrada** (ex.: balsa, estacionamento pré-pago) ou **na saída**

### 4. Registro de saída

- Câmera de saída reconhece a placa → sistema encontra o registro de entrada automaticamente
- Calcula o valor a cobrar em tempo real com base no tempo de permanência
- Mostra saldo devedor, desconto aplicado e pagamentos já realizados
- Processa o pagamento e libera a cancela em sequência
- Suporta **pagamento dividido** em múltiplas formas

### 5. Formas de pagamento aceitas

- **PIX** — exibe chave configurável
- **Cartão de débito**
- **Cartão de crédito**
- **Dinheiro**
- **Faturado** — lança na conta da empresa conveniada para cobrança posterior

### 6. Modo contingência (câmera fora do ar)

- Se a câmera não leu a placa, o operador registra **manualmente** pelo mesmo painel
- O registro fica marcado como "entrada manual" nos relatórios
- A operação **não para** — zero dependência de câmera para funcionar
- Saída sem entrada correspondente também é suportada, com justificativa obrigatória

### 7. Pátio — visão geral dos veículos

- Lista todos os veículos que estão no pátio no momento
- Mostra placa, tipo, empresa/visitante, horário de entrada, tempo de permanência e valor pendente
- Destaque visual para veículos com **permanência excedida** e **divergência detectada**
- Registro de saída pode ser feito diretamente por esta tela (útil para pátios sem câmera de saída)
- Busca por placa

### 8. Consulta de histórico

- Pesquisa por placa e mostra o histórico completo de entradas e saídas daquele veículo
- Útil para conferência no portão ou atendimento ao cliente

### 9. Gestão de empresas conveniadas

- Cadastro completo: nome, CNPJ, contato, e-mail, telefone
- **Desconto percentual** negociado por empresa (aplicado automaticamente na confirmação)
- **Limite de crédito** configurável
- **Ciclo de faturamento** por empresa: semanal, quinzenal ou mensal
- Visualização do saldo faturado pendente sem fatura
- Histórico de faturas emitidas

### 10. Veículos autorizados

- Cadastro de veículos de **funcionários** (acesso isento ou com desconto especial)
- Cadastro de veículos de **empresas conveniadas** (identificados automaticamente na leitura)
- Data de **validade da autorização** — expiração automática
- Quando a câmera lê uma placa autorizada, o painel já destaca: _"FUNCIONÁRIO: João Silva"_ ou _"CONVÊNIO: Empresa XYZ"_

### 11. Faturamento de empresas

- Com um clique, o financeiro **fecha o período** e gera a fatura
- A fatura contém extrato detalhado: data, placa, tipo de acesso, valor de cada passagem
- Status automático: **Aberta → Vencida → Paga**
- Baixa manual após recebimento
- **Download do PDF** pronto para enviar ao cliente

### 12. Tabela de preços

- Preços configurados por **tipo de entrada × categoria de veículo** (carro, moto, caminhão, etc.)
- Suporte a **vigência de preços**: define de quando até quando cada preço vale
- Histórico de tabelas anteriores preservado
- Mudança de preço não afeta registros já realizados

### 13. Relatórios (com exportação para Excel)

Seis relatórios disponíveis, todos filtrável por período e exportáveis em CSV (abre direto no Excel):

| Relatório | O que mostra |
|---|---|
| **Movimento** | Cada entrada/saída do período: placa, tipo, empresa, horário, valor, operador |
| **Receita por forma de pagamento** | Totais de PIX, cartão, dinheiro e faturado |
| **Por empresa conveniada** | Quantos acessos e quanto foi faturado por empresa |
| **Permanência média** | Tempo médio de permanência por tipo de entrada |
| **Isenções concedidas** | Todas as isenções com justificativa e operador responsável |
| **Registros manuais e cancelados** | Contingência, saídas sem entrada, cancelamentos |

### 14. Dashboard executivo

- **Receita de hoje, da semana e do mês** — comparação automática com mês anterior
- **Ticket médio** por acesso pago
- **Total faturado** a receber de empresas
- **Veículos hoje e no mês**
- **Quantidade no pátio agora**
- Gráfico de **volume diário** dos últimos 30 dias
- Distribuição por **tipo de entrada** e **categoria de veículo**
- Distribuição de **receita por forma de pagamento**

### 15. Auditoria completa

- **Cada ação no sistema** gera um registro: quem fez, o que fez, quando, e quais dados mudaram
- Filtro por entidade, ação e usuário
- Inclui: aberturas de cancela, isenções, cancelamentos, login, alterações de cadastro
- Nada é apagado do histórico — cancelamento preserva o registro como "cancelado"

### 16. Controle de cancelamentos (duplo check)

- Operador **solicita** o cancelamento com um motivo
- Administrador **aprova ou rejeita** — operador não cancela sozinho
- Toda a cadeia fica registrada na auditoria
- Proteção contra fraude interna

### 17. Controle de acesso por perfil

Quatro perfis de usuário com permissões distintas:

| Perfil | O que acessa |
|---|---|
| **Operador (Segurança)** | Painel da guarita, pátio, consulta de placa |
| **Financeiro** | Empresas, faturas, relatórios, dashboard |
| **Auditor** | Auditoria, relatórios (somente leitura) |
| **Administrador** | Tudo acima + cadastros + cancelamentos + usuários |

### 18. Aplicativo instalável (PWA)

- Funciona no **celular como um app nativo** — sem precisar baixar da loja
- Quando o operador abre no Chrome ou Safari, o sistema oferece **instalar o PortoAccess** na tela inicial
- Funciona em modo **tela cheia**, sem a barra do navegador
- Seguro para iOS e Android

### 19. Acesso remoto via túnel seguro

- O sistema roda **localmente na rede da empresa** (sem dados na nuvem)
- Um túnel criptografado Cloudflare permite **acesso externo pelo celular ou de qualquer lugar** sem abrir portas no roteador
- URL pública segura (HTTPS) gerada automaticamente

### 20. Integração com cancela física (relé IP)

- A abertura da cancela é acionada automaticamente ao confirmar entrada ou saída
- Compatível com **módulos relé IP** comuns no mercado
- Também pode ser acionada manualmente pelos botões do painel
- Em desenvolvimento, o acionamento fica registrado no log sem movimentar hardware real

---

## Arquitetura técnica (para perguntas técnicas do cliente)

| Componente | Tecnologia |
|---|---|
| Backend | PHP 8.3 / Laravel 11 |
| Frontend | Vue 3 + Inertia.js + Tailwind CSS |
| Banco de dados | SQLite (local, zero configuração) ou MySQL |
| Reconhecimento de placas | Python + fast-alpr (ONNX, roda offline) |
| PWA | Service Worker + Web Manifest |
| Túnel externo | Cloudflare Tunnel (gratuito, sem porta aberta) |
| Cancela | Relé IP via HTTP ou log de simulação |

**Funciona 100% offline** — só precisa de internet para o acesso externo via túnel.

---

## O que destacar na apresentação

### Abrir com o problema
> _"Hoje, quando um carro chega na portaria, o que acontece? O operador para, olha, digita a placa… e se ele errar? E se a câmera não ler? E no fim do mês, como você sabe exatamente quantos veículos da Empresa X entraram?"_

### Mostrar o fluxo principal ao vivo
1. Mostrar o painel da guarita com a câmera rodando
2. Mostrar a placa sendo lida e aparecendo na fila
3. Confirmar a entrada em dois cliques
4. Mostrar o veículo aparecer no pátio
5. Confirmar a saída com cobrança e pagamento em PIX
6. Mostrar o dashboard atualizado com a receita

### Três diferenciais que fecham venda
1. **"A câmera cai, o sistema continua"** — modo contingência. Nenhum concorrente vende isso com clareza.
2. **"Fatura com um clique"** — a empresa conveniada recebe o PDF com extrato completo, sem planilha manual.
3. **"Você sabe quem fez o quê e quando"** — auditoria completa. Nenhum operador pode cancelar sem autorização.

### Responder objeções comuns

**"Já temos uma planilha que funciona"**
> A planilha não abre a cancela, não lê a placa, não avisa que o visitante passou do tempo e não gera fatura automaticamente.

**"É caro instalar câmera?"**
> Qualquer câmera IP de R$ 150 serve. Inclusive um celular velho com o app IP Webcam já funciona.

**"E se o sistema cair?"**
> Roda local na rede — não depende de internet para funcionar. O túnel externo é só para acesso remoto.

**"Os dados ficam na nuvem?"**
> Não. Tudo fica nos servidores da sua empresa. O acesso externo é apenas um túnel criptografado.

---

## Resumo em uma frase

> **PortoAccess transforma qualquer câmera IP em uma portaria automatizada, com controle de pátio em tempo real, faturamento automático de empresas conveniadas e auditoria completa de cada acesso.**
