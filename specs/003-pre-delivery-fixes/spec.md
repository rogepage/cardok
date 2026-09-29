# Feature Specification: Correções de Pré-Entrega do Cardok

**Feature Branch**: `003-pre-delivery-fixes`  
**Created**: 2026-09-29  
**Status**: Completed  
**Input**: User description: "FASE FINAL — CORREÇÕES DE PRÉ-ENTREGA DO CARDOK: Corrigir make demo-fallback com fallback real determinístico, validar logs, ajustar Constituição sobre retry/jitter e ajustar Value Object Money para rejeitar float."

---

## Contexto e Objetivos

Esta especificação cobre a etapa final de auditoria, correção e refinamento do projeto Cardok antes da entrega, garantindo aderência estrita aos critérios de avaliação sem alterações de arquitetura ou regras de negócio:

1. **Demonstração de Resiliência (`make demo-fallback`)**: Implementar um cenário real e determinístico onde o provedor primário (REST) é colocado em falha controlada, o monólito esgota as tentativas de retry com backoff, chaveia automaticamente para o provedor secundário (SOAP) e obtém sucesso, restaurando o estado original ao final.
2. **Alinhamento da Constituição**: Atualizar a Constituição do projeto para refletir a implementação real de retry (backoff limitado, com jitter como opcional e não mandatório).
3. **Precisão Financeira no Domínio (`Money`)**: Remover o tipo `float` da assinatura de `Money::fromDecimal()`, aceitando estritamente `string|int` e garantindo isolamento na camada de infraestrutura.
4. **Resiliência na Inicialização Limpa**: Prevenir que contêineres falhem na inicialização (`exit code 255`) quando o diretório `vendor` existir previamente sem o arquivo `autoload.php`.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Demonstração de Fallback Real e Transparente (Priority: P1)

Como avaliador técnico do desafio, quero executar `make demo-fallback` e observar o fluxo completo de resiliência e chaveamento automático entre provedores sem passar parâmetros artificiais de seleção de provedor, comprovando a tolerância a falhas da aplicação.

**Why this priority**: É o requisito central da avaliação de resiliência; comprova que a aplicação comuta de provedor em tempo de execução de forma transparente.

**Independent Test**: Pode ser testado executando `make demo-fallback` e inspecionando os logs estruturados e a resposta HTTP final.

**Acceptance Scenarios**:

1. **Given** que o ambiente Cardok está ativo, **When** o usuário executa `make demo-fallback`, **Then** o provedor REST entra temporariamente em condição de falha controlada (HTTP 500) via endpoint de simulação.
2. **Given** a falha do REST, **When** a consulta padrão da placa `ABC1234` é executada (sem informar o parâmetro `provider`), **Then** o monólito executa 3 tentativas no provedor REST com backoff linear progressivo.
3. **Given** o esgotamento das tentativas do provedor REST, **When** o evento `provider.fallback` é disparado, **Then** o monólito comuta automaticamente para o provedor SOAP.
4. **Given** o acionamento do provedor SOAP, **When** a resposta é obtida com sucesso na 1ª tentativa, **Then** a API responde HTTP 200 com os débitos, totais e opções de parcelamento canônicas calculadas.
5. **Given** a finalização da consulta (ou interrupção via sinal), **When** o script encerra, **Then** o provedor REST é garantidamente restaurado ao estado operacional normal (`success`).

---

### User Story 2 - Rastreamento Claro nos Logs de Resiliência (Priority: P1)

Como avaliador técnico, quero que a execução do `make demo-fallback` exiba de forma evidente os eventos e etapas percorridas pelo monólito durante o retry e o chaveamento de provedores.

**Why this priority**: Permite diagnóstico imediato no terminal, comprovando quantas tentativas ocorreram, onde houve falha e qual provedor concluiu a requisição.

**Independent Test**: Executar `make demo-fallback` e verificar se as linhas de log formatadas contêm:
* REST attempt 1 (falha)
* REST attempt 2 (retry backoff)
* REST attempt 3 (falha definitiva / unavailable)
* Fallback REST -> SOAP
* SOAP attempt 1
* SOAP success

**Acceptance Scenarios**:

1. **Given** a execução da demonstração, **When** os logs do monólito são processados, **Then** cada tentativa e evento de chaveamento é impresso de maneira sequencial e legível.

---

### User Story 3 - Conformidade da Constituição Arquitetural (Priority: P2)

Como mantenedor do projeto ou auditor, quero que a Constituição técnica reflita com exatidão o comportamento do código em relação a políticas de retry e jitter.

**Why this priority**: Garante integridade documental, evitando discrepâncias entre especificações e código de produção.

**Independent Test**: Inspecionar a Seção III da Constituição em `.specify/memory/constitution.md`.

**Acceptance Scenarios**:

1. **Given** a especificação da Constituição, **When** consultada a regra de retry, **Then** ela estabelece: `Retry DEVE utilizar backoff limitado. Jitter pode ser utilizado quando necessário.`

---

### User Story 4 - Garantia de Não Utilização de `float` no Domínio (Priority: P2)

Como engenheiro de software, quero assegurar que o Value Object `Money` não aceite `float` como parâmetro, protegendo o domínio financeiro contra perda de precisão binária IEEE-754.

**Why this priority**: Reforça o princípio não negociável de precisão financeira em centavos inteiros (`int`).

**Independent Test**: Executar os testes unitários do `Money` (`php artisan test tests/Unit/Domain/Debt/MoneyTest.php`).

**Acceptance Scenarios**:

1. **Given** a chamada `Money::fromDecimal()`, **When** o argumento for do tipo `string` ou `int`, **Then** a instância de `Money` é criada com o valor exato em centavos inteiros.
2. **Given** a chamada `Money::fromDecimal()`, **When** o argumento for do tipo `float`, **Then** uma exceção `TypeError` é imediatamente lançada sob tipagem estrita.
3. **Given** dados provenientes de provedores legados com campos numéricos (REST JSON), **When** processados pelo adapter de infraestrutura, **Then** os valores são normalizados para string antes de entrarem no domínio.

---

### User Story 5 - Inicialização Confiável em Ambientes Limpos (Priority: P3)

Como desenvolvedor ou avaliador iniciando o projeto a partir do zero ou de um arquivo zip, quero que `docker compose up` / `make up` instale automaticamente as dependências do Composer sem falhar caso o diretório `vendor` esteja vazio.

**Why this priority**: Evita atrito inicial durante a primeira execução do avaliador.

**Independent Test**: Testar a inicialização com a pasta `vendor` presente porém sem `vendor/autoload.php`.

**Acceptance Scenarios**:

1. **Given** que o contêiner inicia e `vendor/autoload.php` não existe, **When** o `entrypoint.sh` é executado, **Then** o comando `composer install` é disparado automaticamente antes da geração da chave de aplicação.

---

## Requisitos Funcionais (FR)

- **FR-001**: O serviço `provider-rest` DEVE disponibilizar rotas para consulta e alteração do modo de simulação (`/api/simulation/mode`).
- **FR-002**: O comando `make demo-fallback` DEVE executar `./scripts/demo-fallback.sh`, alternando o `provider-rest` para modo `error`, despachando a consulta normal e restaurando o modo `success` ao final via trap.
- **FR-003**: O script `scripts/format-demo-logs.py` DEVE extrair e formatar os eventos de log estruturados do monólito para a requisição de demonstração.
- **FR-004**: O Value Object `Money::fromDecimal()` DEVE aceitar apenas `string|int`.
- **FR-005**: O adapter `RestVehicleDebtProvider` DEVE normalizar valores monetários brutos externos para string decimal formatada antes de instanciar `Money`.
- **FR-006**: Os scripts `entrypoint.sh` DEVEM verificar a existência de `vendor/autoload.php` para acionar a instalação de dependências.
- **FR-007**: A Constituição técnica em `.specify/memory/constitution.md` DEVE formalizar o uso de backoff limitado com jitter opcional.
