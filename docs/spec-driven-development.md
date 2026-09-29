# Spec-Driven Development no Cardok

Este documento registra a adoção do **GitHub Spec Kit** no projeto **Cardok** a partir da Fase 13.

---

## 1. Objetivo

Estruturar o desenvolvimento de novas funcionalidades e evoluções arquiteturais através da metodologia **Spec-Driven Development (SDD)**, garantindo que requisitos, planos, critérios de aceite e tarefas sejam formalizados e validados antes da implementação no código.

---

## 2. Ferramental e Integração

* **Ferramenta:** GitHub Spec Kit (`specify-cli` v0.7.4.dev0)
* **Agente IA:** Antigravity (`agy`)
* **Estrutura de Suporte:**
  * `.specify/`: Modelos de especificação, constitution, planos, checklists e scripts utilitários.
  * `.agents/skills/`: Skills de comando integrados ao Antigravity IDE para automação do ciclo SDD.
  * `AGENTS.md`: Diretrizes e contexto operacional no nível raiz do repositório.

---

## 3. Ciclo de Vida de uma Feature (SDD)

```text
Constitution (/speckit-constitution)
    │
    ▼
Specification (/speckit-specify)
    │
    ▼
Clarification (/speckit-clarify) [opcional]
    │
    ▼
Implementation Plan (/speckit-plan)
    │
    ▼
Checklist (/speckit-checklist) [opcional]
    │
    ▼
Task Breakdown (/speckit-tasks)
    │
    ▼
Consistency Analysis (/speckit-analyze) [opcional]
    │
    ▼
Implementation (/speckit-implement)
```

---

## 4. Skills Disponíveis no Projeto

| Comando / Skill | Função Principal |
| :--- | :--- |
| `/speckit-constitution` | Estabelece os princípios de engenharia, restrições e padrões do projeto. |
| `/speckit-specify` | Cria a especificação inicial de uma feature com escopo e cenários de teste. |
| `/speckit-clarify` | Identifica e resolve ambiguidades na especificação antes do planejamento. |
| `/speckit-plan` | Gera o plano arquitetural e técnico de implementação da feature. |
| `/speckit-checklist` | Gera checklists de qualidade e completude para validação de requisitos. |
| `/speckit-tasks` | Divide o plano em tarefas atômicas e ordenadas de execução. |
| `/speckit-analyze` | Audita o alinhamento e consistência entre spec, plano e tarefas. |
| `/speckit-implement` | Executa o código e testes seguindo estritamente as tarefas planejadas. |

---

## 5. Princípio de Isolamento

A adoção do Spec Kit não altera a arquitetura existente do Cardok. As camadas de domínio, aplicação, infraestrutura, mock providers e containers Docker permanecem independentes do ferramental de especificação.

---

## 6. Features Desenvolvidas com Spec Kit

| Feature | Branch | Especificação | Status |
| :--- | :--- | :--- | :--- |
| `001-health-check-cache` | `001-health-check-cache` | [`specs/001-health-check-cache/spec.md`](../specs/001-health-check-cache/spec.md) | ✅ Implementada |
| `002-cardok-makefile` | `002-cardok-makefile` | [`specs/002-cardok-makefile/spec.md`](../specs/002-cardok-makefile/spec.md) | ✅ Implementada |
| `003-pre-delivery-fixes` | `003-pre-delivery-fixes` | [`specs/003-pre-delivery-fixes/spec.md`](../specs/003-pre-delivery-fixes/spec.md) | ✅ Implementada |

