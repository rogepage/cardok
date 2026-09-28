# Implementation Plan: Makefile de Operação, Validação e Demonstração do Cardok

**Branch**: `002-cardok-makefile` | **Date**: 2026-09-28 | **Spec**: [spec.md](./spec.md)  
**Input**: Feature specification from `specs/002-cardok-makefile/spec.md`

## Summary

Implementação de um `Makefile` na raiz do repositório Cardok para fornecer uma camada de comandos simples, intuitivos e autoexplicativos para avaliadores técnicos e desenvolvedores. O Makefile padroniza o ciclo de vida dos contêineres (`up`, `down`, `restart`, `status`, `logs`), diagnósticos e testes (`health`, `test`, `check`), operações interativas (`shell`, `artisan`) e demonstrações práticas da API (`demo`, `demo-success`, `demo-fallback`, `demo-invalid-plate`), seguido da atualização da documentação no `README.md`.

## Technical Context

**Language/Version**: GNU Make / BSD Make (POSIX-compliant syntax), Shell (`/bin/sh`)  
**Primary Dependencies**: Docker Engine 24+, Docker Compose v2+  
**Storage**: N/A (o Makefile gerencia contêineres sem alterar esquemas de armazenamento)  
**Testing**: PHPUnit 12.5 (executado via `docker compose exec monolith php artisan test`)  
**Target Platform**: macOS (Darwin), Linux (todas as distribuições compatíveis com POSIX Make)  
**Project Type**: Automação operacional de infraestrutura e Developer Experience (DevEx)  
**Performance Goals**: Execução imediata dos comandos de automação; `make health` < 5s; `make test` sem overhead além do PHPUnit  
**Constraints**: Zero dependências de ferramentas externas além de `make`, `docker` e `docker compose`; compatibilidade multiplataforma; não mascarar erros (`|| true`)  
**Scale/Scope**: 1 arquivo raiz (`Makefile`) e atualização da seção de comandos no `README.md`  

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

* **I. Arquitetura e Isolamento do Domínio**: PASS. Nenhuma linha de código em `app/Domain` é adicionada ou alterada.
* **II. Precisão Financeira**: PASS. Nenhuma regra de cálculo ou arredondamento é tocada.
* **III. Integrações e Resiliência**: PASS. O comando `make health` apenas dispara a rotina real de diagnóstico já validada.
* **IV. Simulação de Pagamentos**: PASS. Preservada integralmente sem alterações.
* **V. Determinismo Temporal**: PASS. Sem impacto.
* **VI. Observabilidade e Privacidade**: PASS. Logs e comandos preservam integridade e mascaramento.
* **VII. Testes**: PASS. Os 110 testes continuam sendo executados e validados pelo `make test`.
* **VIII. Artefatos de Engenharia**: PASS. Toda a documentação e descrições do Makefile estão em pt-BR.
* **IX. Desenvolvimento Assistido por IA**: PASS. Fluxo rigoroso Spec-Kit seguido.
* **X. Gates de Qualidade**: PASS. `docker compose config` válido, serviços saudáveis e testes aprovados.

## Project Structure

### Documentation (this feature)

```text
specs/002-cardok-makefile/
├── spec.md                  # Especificação funcional e histórias de usuário
├── plan.md                  # Este plano de implementação
├── research.md              # Pesquisa técnica e decisões de compatibilidade
├── data-model.md            # Modelo conceitual de alvos e cenários
├── quickstart.md            # Guia rápido de avaliação técnica
├── contracts/
│   └── makefile-targets.md  # Contrato de comandos, argumentos e exit codes
└── checklists/
    └── requirements.md      # Validação de qualidade de requisitos
```

### Source Code Impacted

```text
Makefile                     # Novo arquivo na raiz com todos os alvos implementados
README.md                    # Atualização com tabela 'Comandos úteis' em pt-BR
```

**Structure Decision**: A adição limita-se a um arquivo `Makefile` na raiz e à documentação no `README.md`, garantindo que todo o ecossistema existente permaneça intacto.

## Complexity Tracking

Nenhuma violação ou desvio da Constituição do projeto. A solução utiliza Make puro e Docker Compose nativo sem introduzir dependências ou camadas desnecessárias.
