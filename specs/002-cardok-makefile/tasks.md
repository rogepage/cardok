# Tasks: Makefile de Operação, Validação e Demonstração do Cardok

**Input**: Design documents from `specs/002-cardok-makefile/`  
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/makefile-targets.md`, `quickstart.md`  

## Format: `[ID] [P?] [Story] Description with file path`

- **[P]**: Pode executar em paralelo (arquivos distintos, sem dependências incompletas)
- **[Story]**: Mapeamento da história de usuário correspondente (US1, US2, US3, US4, US5)
- Caminhos absolutos/relativos exatos incluídos em cada descrição de tarefa

---

## Phase 1: Setup & Estrutura Base (Shared Infrastructure)

**Purpose**: Criação do arquivo `Makefile` com estrutura inicial, variáveis fundamentais e mecanismo auto-documentado de ajuda.

- [x] T001 Criar estrutura inicial do `Makefile` na raiz com variáveis `COMPOSE`, `MONOLITH_SERVICE`, `.SHELL` e declaração de `.PHONY` em `Makefile`
- [x] T002 Implementar o alvo padrão `make help` com extração automática e formatação em pt-BR dos comentários dos alvos em `Makefile`

---

## Phase 2: Foundational (Infraestrutura de Execução)

**Purpose**: Helpers visuais e tratamento padronizado de códigos de saída que servem de base para todos os alvos.

- [x] T003 Implementar helpers de mensagens visuais no terminal (`==> ...`) e convenções de saída para comandos em `Makefile`

---

## Phase 3: User Story 1 - Gerenciamento do Ciclo de Vida do Ambiente (Priority: P1) 🎯 MVP

**Goal**: Permitir iniciar, parar, reiniciar e monitorar os contêineres do Cardok com comandos padronizados.  
**Independent Test**: Executar `make up`, `make status`, `make logs` (interrompido) e `make down`.

- [x] T004 [US1] Implementar alvo `make up` executando `$(COMPOSE) up -d` com validação de status em `Makefile`
- [x] T005 [US1] Implementar alvo `make down` executando `$(COMPOSE) down` preservando volumes de dados em `Makefile`
- [x] T006 [US1] Implementar alvo `make restart` executando a reinicialização dos serviços em `Makefile`
- [x] T007 [US1] Implementar alvo `make status` exibindo tabela de contêineres via `$(COMPOSE) ps` em `Makefile`
- [x] T008 [US1] Implementar alvo `make logs` permitindo streaming contínuo via `$(COMPOSE) logs -f` em `Makefile`

**Checkpoint**: Ciclo de vida completo operacional de forma independente.

---

## Phase 4: User Story 2 - Verificação de Saúde e Diagnóstico Integrado (Priority: P1)

**Goal**: Permitir inspeção instantânea dos provedores externos e validação rigorosa de conformidade do ambiente.  
**Independent Test**: Executar `make health` (tabela de provedores) e `make check` (validação composta).

- [x] T009 [US2] Implementar alvo `make health` executando `$(COMPOSE) exec $(MONOLITH_SERVICE) php artisan cardok:check-services` em `Makefile`
- [x] T010 [US2] Implementar alvo `make check` com encadeamento rígido de validação (compose config, status dos contêineres, testes e saúde) com parada imediata em falha em `Makefile`

**Checkpoint**: Diagnósticos de conectividade e validação composta funcionando e reportando status reais.

---

## Phase 5: User Story 3 - Execução Determinística da Suíte de Testes (Priority: P1)

**Goal**: Rodar os 110 testes automatizados (416 asserções) com total isolamento no contêiner do monólito.  
**Independent Test**: Executar `make test` e verificar o relatório do PHPUnit com propagação do exit code real.

- [x] T011 [US3] Implementar alvo `make test` executando `$(COMPOSE) exec $(MONOLITH_SERVICE) php artisan test` preservando o exit code original em `Makefile`

**Checkpoint**: Suíte de testes automatizados integrada ao Make.

---

## Phase 6: User Story 4 - Acesso Operacional e Linha de Comando (Priority: P2)

**Goal**: Fornecer acesso interativo ao contêiner e despacho simplificado de comandos Artisan.  
**Independent Test**: Executar `make shell` e `make artisan CMD="about"`.

- [x] T012 [US4] Implementar alvo `make shell` abrindo sessão `/bin/sh` interativa em `/var/www/html` no contêiner `monolith` em `Makefile`
- [x] T013 [US4] Implementar alvo `make artisan` permitindo executar comandos arbitrários via parâmetro `CMD` com fallback para `list` em `Makefile`

**Checkpoint**: Shell e comando Artisan acessíveis sem necessidade de lembrar sintaxe Docker.

---

## Phase 7: User Story 5 - Demonstrações Rápidas de Cenários de Negócio (Priority: P3)

**Goal**: Permitir ao avaliador disparar demonstrações práticas e determinísticas das regras financeiras e resiliência via terminal.  
**Independent Test**: Executar `make demo` e conferir saídas de sucesso, fallback e erro de validação.

- [x] T014 [P] [US5] Implementar alvo `make demo-success` consultando placa `ABC1234` com cálculo de juros/multa e parcelamento via `Makefile`
- [x] T015 [P] [US5] Implementar alvo `make demo-fallback` consultando via provider SOAP demonstrando tolerância e modelo canônico em `Makefile`
- [x] T016 [P] [US5] Implementar alvo `make demo-invalid-plate` demonstrando tratamento de erro estruturado 400 em `Makefile`
- [x] T017 [US5] Implementar alvo agregador `make demo` executando sequencialmente os três cenários com cabeçalhos formatados em `Makefile`

**Checkpoint**: Demonstrações ao vivo prontas para apresentação do Home Test.

---

## Phase 8: Polish, Documentação e Validação Obrigatória

**Purpose**: Documentação das instruções em pt-BR e homologação completa de todos os alvos implementados.

- [x] T018 [P] Atualizar `README.md` incluindo a seção `## Comandos úteis` com tabela completa e descrições em pt-BR dos comandos criados em `README.md`
- [x] T019 Executar validação técnica obrigatória dos comandos `make help`, `make status`, `make test`, `make health` e `make check`
- [x] T020 Executar ciclo de vida de validação com `make up`, `make status` e `make down` validando consistência e limpeza
