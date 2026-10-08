# Feature Specification: Provedor de Débitos com IA em Formato Plaintext CSV

**Feature Branch**: `007-add-ai-csv-provider`  
**Created**: 2026-10-08  
**Status**: Draft  
**Input**: User description: "adicionar um novo provedor com AI, usando o formato plaintext com o formato CSV."

## Clarifications

### Session 2026-10-08

- Q: Como tratar formatação típica de IA (blocos de código markdown e textos explicativos)? → A: Tolerante e defensivo: ignorar delimitadores de código Markdown (ex.: ````csv ... ````) e eventuais linhas de preâmbulo ou texto explicativo, extraindo e processando com segurança apenas as linhas que correspondam à estrutura tabular de débitos (`tipo,valor,vencimento`).
- Q: Como agir ao encontrar uma linha de débito cujo tipo não corresponda aos tipos canônicos? → A: Ignorar e registrar log: ignorar linhas com categorias desconhecidas registrando log de aviso (`warning`), processando normalmente todos os débitos suportados válidos encontrados.
- Q: Qual formato e método de requisição HTTP utilizar ao chamar o serviço externo de IA? → A: GET no caminho do recurso: `GET /api/v1/debts/{plate}` com cabeçalho `Accept: text/plain, text/csv`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consulta e Normalização de Débitos via Provedor AI em Formato CSV (Priority: P1) 🎯 MVP

Como analista do sistema de débitos veiculares, quero que a plataforma consulte débitos junto a um novo serviço parceiro baseado em inteligência artificial que entrega dados em texto puro (plaintext) delimitado por vírgulas (CSV), para que esses débitos sejam convertidos no formato canônico da aplicação e submetidos aos mesmos cálculos financeiros de juros e opções de pagamento.

**Why this priority**: É o objetivo primário da funcionalidade: viabilizar a ingestão e interpretação de um terceiro formato de dados (CSV tabular em texto puro) somando-se aos já existentes (JSON e XML).

**Independent Test**: Enviar uma requisição de consulta apontando explicitamente para o provedor de IA e verificar se o corpo CSV é processado e retorna o contrato padrão de resposta com valores corrigidos e opções de pagamento.

**Acceptance Scenarios**:

1. **Given** um serviço de IA que retorna texto plano com cabeçalho e linhas CSV (ex.: tipo, valor e vencimento),  
   **When** a consulta do veículo for processada através desse provedor,  
   **Then** o sistema deve interpretar cada linha como um débito válido, aplicando os encargos correspondentes e totalizadores.

2. **Given** um veículo sem pendências financeiras registrado no provedor de IA,  
   **When** o provedor responder apenas com cabeçalho ou texto indicando ausência de débitos,  
   **Then** o sistema deve retornar a lista de débitos vazia com status de sucesso, sem registrar erro.

3. **Given** uma resposta de IA contendo blocos de código markdown (ex.: ````csv ... ````) ou comentários introdutórios/conclusivos em volta do CSV,  
   **When** o sistema processar a resposta do provedor de IA,  
   **Then** deve extrair e processar unicamente as linhas válidas de débitos, descartando a formatação markdown e comentários sem falhar.

4. **Given** uma resposta contendo débitos canônicos válidos misturados a categorias não suportadas (ex.: DPVAT),  
   **When** a resposta for processada,  
   **Then** os débitos válidos devem ser retidos e processados, e as categorias desconhecidas ignoradas com log de aviso.

---

### User Story 2 - Integração na Cadeia de Provedores e Fallback de Resiliência (Priority: P2)

Como arquiteto de resiliência, quero que o novo provedor de IA participe da cadeia de execução configurável da aplicação, permitindo ser utilizado como provedor primário, intermediário ou de contingência com retries automáticos e fallback transparente.

**Why this priority**: Garante que o provedor não seja uma solução isolada, integrando-se à política de alta disponibilidade do ecossistema (*First Success Wins*).

**Independent Test**: Configurar o provedor de IA como primário ou secundário em um cenário de falha simulada e verificar o chaveamento automático de provedor sem interrupção do atendimento ao usuário.

**Acceptance Scenarios**:

1. **Given** o provedor de IA configurado na lista de provedores prioritários,  
   **When** o provedor anterior na cadeia falhar (após esgotar tentativas de repetição),  
   **Then** o sistema deve consultar o provedor de IA automaticamente e obter os débitos.

2. **Given** o provedor de IA indisponível ou com tempo de resposta excedido,  
   **When** as tentativas configuradas se esgotarem,  
   **Then** o sistema deve acionar o próximo provedor configurado na cadeia ou responder falha caso seja o último.

---

### User Story 3 - Tratamento Defensivo de Respostas CSV Malformadas (Priority: P3)

Como operador do sistema, quero que qualquer resposta em texto puro fora do formato CSV esperado ou com campos corrompidos seja detectada de forma rápida e segura, registrando telemetria adequada sem propagar falhas internas.

**Why this priority**: Evita que variações de formatação de modelos de linguagem ou saídas não conformes corrompam a consistência contábil da aplicação.

**Independent Test**: Simular o envio de textos não tabulares ou linhas com colunas faltantes e validar se o sistema aciona falha não recuperável imediata para permitir fallback rápido.

**Acceptance Scenarios**:

1. **Given** uma resposta do provedor de IA contendo texto não estruturado ou linhas com número incorreto de colunas,  
   **When** o sistema analisar a resposta,  
   **Then** deve disparar tratamento defensivo de erro e proceder para o próximo provedor disponível.

---

### Edge Cases

- **Presença de blocos de código Markdown ou comentários**: Delimitadores como ````csv` e ```` bem como linhas de texto descritivo devem ser ignorados.
- **Tipos de débitos não reconhecidos**: Linhas com categorias não homologadas (ex.: DPVAT, PEDAGIO) devem ser ignoradas gerando log de aviso, sem abortar o processamento dos débitos suportados válidos.
- **Presença de linhas em branco ou quebras de linha extras**: O leitor de CSV deve ignorar linhas vazias no início, meio ou fim do payload de texto.
- **Variações de separadores ou aspas**: Campos contendo valores entre aspas duplas com espaços em branco devem ser tratados de forma higienizada.
- **Resposta contendo cabeçalho descritivo**: O sistema deve identificar e ignorar a linha de cabeçalho (`tipo,valor,vencimento`) caso presente.
- **Valores monetários com ponto ou vírgula**: A conversão deve normalizar pontuações decimais adequadamente.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema DEVE disponibilizar suporte a um novo provedor de débitos denominado "AI" (ou "ai_csv"), comunicando-se via HTTP no endpoint `GET /api/v1/debts/{plate}` com cabeçalho `Accept: text/plain, text/csv`.
- **FR-002**: O sistema DEVE aceitar respostas do provedor no formato texto puro (`text/plain` ou `text/csv`) estruturadas como valores separados por vírgula (CSV), tolerando blocos de formatação markdown (````csv ... ````).
- **FR-003**: O sistema DEVE extrair de cada linha de débito do CSV as informações obrigatórias: tipo do débito, valor original e data de vencimento.
- **FR-004**: O sistema DEVE normalizar o tipo de débito recebido para os tipos canônicos suportados pela plataforma (IPVA, MULTA, LICENCIAMENTO) e DEVE ignorar linhas com tipos não suportados gerando log de aviso (`warning`), mantendo os débitos válidos.
- **FR-005**: O sistema DEVE ignorar linhas em branco, comentários não tabulares e linhas de cabeçalho descritivo no corpo CSV.
- **FR-006**: O sistema DEVE converter respostas sem débitos (arquivo com apenas cabeçalho, vazio ou texto indicando ausência) em uma resposta válida de zero débitos, sem acionar fallback.
- **FR-007**: O sistema DEVE integrar o novo provedor de IA na ordem de resolução configurável de provedores (`order`), permitindo chaveamento dinâmico.
- **FR-008**: O sistema DEVE aplicar as políticas existentes de timeout, retry linear com backoff e chaveamento transparente de contingência (fallback) para o provedor de IA.
- **FR-009**: O sistema DEVE rejeitar com erro defensivo imediato saídas que não contenham débitos válidos nem padrão reconhecido.

### Key Entities *(include if feature involves data)*

- **Registro de Débito CSV**: Representa uma linha tabular contendo: identificador da categoria (`tipo`), quantia numérica (`valor`) e vencimento (`vencimento` no padrão AAAA-MM-DD).
- **Provedor de Débitos AI**: Canal de integração externa que simula a consulta a um motor de IA gerador de respostas tabulares em texto simples.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% dos débitos veiculares retornados em formato CSV pelo provedor de IA são convertidos com sucesso no modelo canônico sem perda de precisão centesimal.
- **SC-002**: A consulta com o provedor de IA responde com a mesma estrutura unificada de resposta da API (débitos, resumo e opções de quitação com PIX e Cartão de Crédito).
- **SC-003**: Em caso de falha controlada no provedor de IA, o sistema realiza fallback automático para os provedores seguintes em menos de 1 segundo de acréscimo operacional.
- **SC-004**: Nenhuma regressão é observada nos provedores REST e SOAP pré-existentes.

## Assumptions

- O provedor de IA opera via requisição HTTP GET no endpoint `/api/v1/debts/{plate}` recebendo a identificação do veículo (placa) e respondendo com `Content-Type: text/plain` (ou `text/csv`).
- A ordem padrão das colunas CSV segue a convenção `tipo,valor,vencimento` (ou equivalente delimitado por vírgula).
- A formatação monetária utiliza ponto decimal e as datas seguem a norma ISO-8601 (`YYYY-MM-DD`).
- A geração real do conteúdo por IA é emulada por um serviço satélite mockado para manter os testes 100% determinísticos, reproduzíveis e desacoplados de serviços de terceiros pagos.
