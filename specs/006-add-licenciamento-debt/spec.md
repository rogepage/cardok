# Feature Specification: Adição do Débito de Licenciamento

**Feature Branch**: `006-add-licenciamento-debt`  
**Created**: 2026-10-08  
**Status**: Draft  
**Input**: User description: "precisamos adicionar um novo debito, licenciamento, com a mesma regra do ipva"

## Clarifications

### Session 2026-10-08

- Q: Como o sistema deve tratar variações na nomenclatura enviada por provedores externos para o débito de Licenciamento? → A: Aceitar estritamente a nomenclatura "LICENCIAMENTO" (normalizada de forma case-insensitive via maiúsculas e sem espaços em branco), sem mapeamento de sinônimos adicionais nesta etapa.
- Q: Como o sistema deve tratar múltiplos débitos de Licenciamento de exercícios distintos para o mesmo veículo? → A: Cada débito é mantido individualmente na lista de débitos com seu próprio cálculo de encargos e todos são consolidados na opção única de pagamento "SOMENTE_LICENCIAMENTO".
- Q: Qual a fronteira de escopo para o débito de Licenciamento nesta feature? → A: Escopo restrito a consulta, normalização, cálculo de juros de mora e simulação de pagamento (à vista PIX e parcelado no cartão), mantendo emissão de CRLV ou liquidação externa fora de escopo.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consulta e Cálculo de Juros do Licenciamento em Atraso (Priority: P1)

Como proprietário de um veículo ou analista de atendimento, quero consultar os débitos do veículo e visualizar débitos do tipo Licenciamento com os devidos encargos diários calculados caso estejam em atraso, para saber o valor exato atualizado da dívida.

**Why this priority**: É o valor central da funcionalidade: reconhecer o tipo Licenciamento no catálogo de débitos e aplicar com precisão a regra de encargos por mora correspondente (mesma regra vigente para o IPVA).

**Independent Test**: Consultar débitos de um veículo que contenha Licenciamento vencido e verificar se o valor atualizado reflete a taxa diária de 0,33% até o teto de 20%.

**Acceptance Scenarios**:

1. **Given** um veículo com débito de Licenciamento de R$ 100,00 com vencimento há 10 dias,  
   **When** a consulta de débitos for realizada,  
   **Then** o débito deve ser listado com tipo "LICENCIAMENTO", valor original de R$ 100,00, 10 dias de atraso e valor atualizado com 3,3% de acréscimo (R$ 103,30).

2. **Given** um veículo com débito de Licenciamento vencido há mais de 61 dias (ex.: 100 dias),  
   **When** a consulta de débitos for realizada,  
   **Then** o acréscimo por atraso deve respeitar o teto máximo de 20%, resultando em valor atualizado limitado a R$ 120,00 para um débito original de R$ 100,00.

3. **Given** um débito de Licenciamento dentro do prazo de vencimento (vencimento hoje ou futuro),  
   **When** a consulta for processada,  
   **Then** o débito deve registrar 0 dias de atraso e o valor atualizado deve ser estritamente igual ao valor original.

---

### User Story 2 - Simulação de Pagamento com Opção Exclusiva de Licenciamento (Priority: P2)

Como usuário consultando os débitos de um veículo, quero que o Licenciamento componha o totalizador geral de pagamento e também gere uma opção de quitação exclusiva ("SOMENTE_LICENCIAMENTO"), permitindo simular quitação via PIX com desconto e parcelamento no cartão de crédito.

**Why this priority**: Permite que o motorista escolha quitar apenas o Licenciamento ou quitá-lo conjuntamente com outros débitos, usufruindo das facilidades de desconto à vista e parcelamento.

**Independent Test**: Realizar uma consulta com débitos mistos (IPVA, Multa e Licenciamento) e validar se o bloco de pagamentos contém a opção individual "SOMENTE_LICENCIAMENTO" além da opção "TOTAL".

**Acceptance Scenarios**:

1. **Given** uma consulta com débitos que incluem Licenciamento,  
   **When** o resumo financeiro for gerado,  
   **Then** o valor atualizado do Licenciamento deve ser somado ao valor totalizador de pagamento ("TOTAL").

2. **Given** a existência de um ou mais débitos de Licenciamento,  
   **When** a simulação de pagamento for calculada,  
   **Then** deve ser exibida a opção de pagamento "SOMENTE_LICENCIAMENTO" com o valor base correspondente à soma dos débitos de licenciamento atualizados.

3. **Given** a opção "SOMENTE_LICENCIAMENTO",  
   **When** as formas de pagamento forem detalhadas,  
   **Then** deve apresentar cálculo de PIX com 5% de desconto à vista e parcelas no cartão de crédito em 1x, 6x e 12x com juros de financiamento.

---

### User Story 3 - Recepção de Licenciamento de Múltiplos Fornecedores de Dados (Priority: P3)

Como sistema de consulta veicular, quero ser capaz de receber débitos de Licenciamento provenientes de diferentes órgãos e fornecedores parceiros (tanto em formato JSON quanto em envelopes XML), normalizando-os de forma consistente.

**Why this priority**: Garante interoperabilidade sem depender da tecnologia de transporte ou do formato específico de cada fornecedor de débitos.

**Independent Test**: Executar consultas simuladas consumindo fornecedores com diferentes formatos de protocolo e verificar se o tipo "LICENCIAMENTO" é reconhecido e integrado sem erros.

**Acceptance Scenarios**:

1. **Given** uma resposta de fornecedor contendo débito categorizado como "LICENCIAMENTO",  
   **When** o sistema realizar o processamento da resposta,  
   **Then** o registro deve ser aceito e transformado no modelo de débito padrão do sistema sem perda de dados.

---

### Edge Cases

- **Múltiplos débitos de Licenciamento para o mesmo veículo**: O sistema deve calcular os encargos de cada um conforme sua respectiva data de vencimento e agregá-los corretamente na opção "SOMENTE_LICENCIAMENTO".
- **Débito de Licenciamento com valor zerado ou data inválida**: O sistema deve rejeitar o registro inconsistente com tratamento defensivo e mensagem explicativa.
- **Consulta sem nenhum Licenciamento presente**: O sistema não deve incluir o bloco de opção "SOMENTE_LICENCIAMENTO" nas opções de pagamento.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema DEVE reconhecer "LICENCIAMENTO" como um tipo oficial e válido de débito veicular.
- **FR-002**: O sistema DEVE aplicar ao Licenciamento a mesma regra de juros diários por mora aplicável ao IPVA: taxa de 0,33% por dia de atraso.
- **FR-003**: O sistema DEVE limitar o acréscimo por mora do Licenciamento a um teto máximo de 20% do valor original do débito.
- **FR-004**: O sistema DEVE considerar zero encargos para débitos de Licenciamento cuja data de vencimento seja igual ou posterior à data de referência da consulta.
- **FR-005**: O sistema DEVE agregar os valores atualizados de Licenciamento no totalizador geral de débitos do veículo.
- **FR-006**: O sistema DEVE disponibilizar uma opção de quitação individual agrupada com o identificador "SOMENTE_LICENCIAMENTO" sempre que houver ao menos um débito desse tipo.
- **FR-007**: O sistema DEVE calcular a opção "SOMENTE_LICENCIAMENTO" com as mesmas modalidades de pagamento das demais categorias: quitação à vista via PIX com 5% de desconto e parcelamento no cartão de crédito em 1x, 6x e 12x.
- **FR-008**: O sistema DEVE normalizar o tipo de débito a partir do identificador padronizado "LICENCIAMENTO" (case-insensitive via maiúsculas e corte de espaços), tanto em fontes REST/JSON quanto SOAP/XML, rejeitando variações semânticas não mapeadas.

### Key Entities *(include if feature involves data)*

- **Débito de Licenciamento**: Representa a taxa anual de licenciamento do veículo emitida pelo órgão de trânsito. Possui tipo ("LICENCIAMENTO"), valor original, data de vencimento, dias de atraso e valor atualizado após incidência de encargos.
- **Opção de Pagamento ("SOMENTE_LICENCIAMENTO")**: Agrupamento financeiro que consolida todos os débitos de licenciamento pendentes de um veículo, expondo o valor base consolidado, o total com desconto para pagamento à vista (PIX) e a grade de parcelamento no cartão de crédito.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% das consultas de veículos com Licenciamento em atraso calculam o valor corrigido com precisão exata de centavos de acordo com a regra diária de 0,33% e teto de 20%.
- **SC-002**: Débitos de Licenciamento vencidos há mais de 60 dias nunca ultrapassam o teto legal de 20% de encargos adicionais.
- **SC-003**: Sempre que houver débito de Licenciamento, a opção "SOMENTE_LICENCIAMENTO" é incluída na resposta com simulação completa para PIX e Cartão de Crédito.
- **SC-004**: Todas as consultas com débitos de Licenciamento são processadas sem regressão nos tipos pré-existentes (IPVA e MULTA).

## Assumptions

- A regra de cálculo de encargos diários de 0,33% ao dia e teto máximo de 20% do Licenciamento é idêntica à já consolidada e auditada do IPVA.
- A política de desconto de 5% no PIX e taxas de parcelamento no cartão de crédito aplicam-se uniformemente ao Licenciamento, mantendo conformidade com as regras gerais de simulação de pagamentos.
- A identificação do débito como "LICENCIAMENTO" pelos provedores externos segue a convenção padronizada de texto em caixa alta ou equivalente normalizável.
- A funcionalidade limita-se estritamente à consulta de débitos, cálculo de mora e simulação de opções de pagamento, não contemplando liquidação bancária nem emissão do CRLV digital nesta etapa.
