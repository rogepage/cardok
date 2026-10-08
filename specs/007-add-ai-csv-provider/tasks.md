# Tasks: Provedor de Débitos com IA em Plaintext CSV

**Input**: Design documents from `/specs/007-add-ai-csv-provider/` (`spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`)  
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/  
**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (`[US1]`, `[US2]`, `[US3]`)
- Include exact file paths in descriptions

---

## Phase 1: Setup

**Purpose**: Verificação do ambiente de execução e estado inicial dos contêineres

- [x] T001 Verificar a prontidão e saúde dos contêineres Docker via `docker compose ps`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Infraestrutura base de configuração e serviço satélite de mock para suportar as histórias de usuário

**⚠️ CRITICAL**: Configurações de serviço e mock são pré-requisitos para a integração do provedor e testes de resiliência.

- [x] T002 Atualizar configuração de provedores em `monolith/config/services.php` adicionando a chave `ai_url` (`PROVIDER_AI_URL`)
- [x] T003 Criar serviço satélite mock `provider-ai/` com rotas `/health`, `/api/v1/debts/{plate}`, `/simulation/mode`, suporte a `PROVIDER_MODE` e configurar o serviço no `docker-compose.yml`

**Checkpoint**: Configuração e mock preparados — implementação das histórias de usuário desbloqueada.

---

## Phase 3: User Story 1 - Consulta e Normalização de Débitos via Provedor AI em Formato CSV (Priority: P1) 🎯 MVP

**Goal**: Ingestão de texto puro (plaintext) em CSV delimitado por vírgula ou ponto e vírgula, normalizando débitos para o modelo canônico da aplicação.

**Independent Test**: Executar testes unitários comprovando parsing com vírgula (`,`), ponto e vírgula (`;`), decimais com ponto/vírgula e remoção de delimitadores de código markdown (````csv ... ````).

### Tests for User Story 1

- [x] T004 [P] [US1] Criar teste unitário para o adaptador e parser CSV em `monolith/tests/Unit/Infrastructure/Providers/AiVehicleDebtProviderTest.php`
- [x] T005 [P] [US1] Criar teste para veículo sem débitos (retornando lista vazia sem erro) em `monolith/tests/Unit/Infrastructure/Providers/AiVehicleDebtProviderZeroDebtsTest.php`

### Implementation for User Story 1

- [x] T006 [US1] Implementar o adaptador `App\Infrastructure\Providers\Ai\AiVehicleDebtProvider` em `monolith/app/Infrastructure/Providers/Ai/AiVehicleDebtProvider.php` implementando `VehicleDebtProvider`
- [x] T007 [US1] Registrar `AiVehicleDebtProvider` e associar chave `'ai'` no `ProviderResolver` em `monolith/app/Providers/AppServiceProvider.php`

**Checkpoint**: User Story 1 (MVP) concluída e testável de forma isolada no monólito.

---

## Phase 4: User Story 2 - Integração na Cadeia de Provedores e Fallback de Resiliência (Priority: P2)

**Goal**: Permitir que o provedor de IA participe da cadeia configurável de execução (`PROVIDER_ORDER`), com retries automáticos e fallback transparente (*First-Success-Wins*).

**Independent Test**: Configurar o provedor de IA como primário na cadeia de resolução e simular falha, comprovando chaveamento automático para o provedor seguinte sem falha na API do cliente.

### Tests for User Story 2

- [x] T008 [P] [US2] Criar teste unitário para o registro e ordenação da chave `ai` no `ProviderResolver` em `monolith/tests/Unit/Application/VehicleDebt/ProviderResolverAiTest.php`
- [x] T009 [US2] Criar teste de integração da API em `monolith/tests/Feature/AiProviderFallbackIntegrationTest.php` validando chaveamento para REST quando IA falha

### Implementation for User Story 2

- [x] T010 [US2] Garantir suporte completo e ordenação da chave `'ai'` em `monolith/app/Application/VehicleDebt/ProviderResolver.php`

**Checkpoint**: User Story 2 concluída — resiliência e fallback integrados com sucesso.

---

## Phase 5: User Story 3 - Tratamento Defensivo de Respostas CSV Malformadas (Priority: P3)

**Goal**: Tratamento defensivo de saídas corrompidas e filtragem com log de aviso para categorias de débito não homologadas (ex.: DPVAT, PEDAGIO).

**Independent Test**: Simular respostas corrompidas e categorias desconhecidas, comprovando descarte defensivo e acionamento de exceção apropriada.

### Tests for User Story 3

- [x] T011 [P] [US3] Criar testes unitários para respostas malformadas e categorias desconhecidas em `monolith/tests/Unit/Infrastructure/Providers/AiVehicleDebtProviderDefensiveTest.php`

### Implementation for User Story 3

- [x] T012 [US3] Implementar validações defensivas e log de aviso para categorias desconhecidas em `monolith/app/Infrastructure/Providers/Ai/AiVehicleDebtProvider.php`

**Checkpoint**: User Story 3 concluída — robustez contra respostas imperfeitas de IA assegurada.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Validação integrada, conformidade com a Constituição e integridade da suíte de testes

- [x] T013 Executar suíte completa de testes automatizados no monólito via `docker compose exec monolith php artisan test`
- [x] T014 Subir o container `provider-ai` e validar o healthcheck e comunicação via `docker compose ps`
- [x] T015 Atualizar `README.md` com as instruções de configuração e uso do provedor de IA

---

## Dependencies & Execution Order

1. **Setup & Foundational** (T001 - T003): Pré-requisito para as histórias de usuário.
2. **User Story 1** (T004 - T007): Implementa o parser e adaptador de IA (MVP).
3. **User Story 2** (T008 - T010): Integra o adaptador à cadeia de fallback e testes de integração.
4. **User Story 3** (T011 - T012): Refina o tratamento defensivo e tolerância a categorias não mapeadas.
5. **Polish** (T013 - T015): Verificação final e documentação.

---

## Implementation Strategy

- **MVP First**: Entregar primeiramente a Phase 3 (US1) permitindo consultas com o provedor de IA com dados válidos e sem débitos.
- **Resilience Layer**: Em seguida (Phase 4), conectar a cadeia de fallback e retries para manter a estabilidade da plataforma.
- **Defensive Hardening**: Na Phase 5, adicionar proteção avançada para anomalias de geração por IA e categorias não previstas.
