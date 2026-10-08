# Implementation Plan: Adição do Débito de Licenciamento

**Branch**: `006-add-licenciamento-debt` | **Date**: 2026-10-08 | **Spec**: [specs/006-add-licenciamento-debt/spec.md](file:///Users/roger/Desktop/docker/cardok/specs/006-add-licenciamento-debt/spec.md)  
**Input**: Especificação da feature em `specs/006-add-licenciamento-debt/spec.md` com esclarecimentos da Sessão 2026-10-08.

---

## Summary

Adicionar o suporte canônico ao débito do tipo `LICENCIAMENTO` no ecossistema Cardok. A regra de cálculo de juros diários por mora será idêntica à do IPVA (taxa de 0,33% ao dia limitada a um teto máximo de 20%, calculada de forma determinística sobre centavos inteiros via Value Object `Money` com arredondamento `HALF_UP`). O débito participará do totalizador geral (`TOTAL`) e gerará automaticamente uma opção de quitação exclusiva (`SOMENTE_LICENCIAMENTO`) com desconto de 5% no PIX e parcelamento no cartão de crédito em 1x, 6x e 12x via Tabela Price (2,5% a.m.). A implementação segue estritamente o padrão *Strategy & Registry*, preservando isolamento total do domínio e estendendo os adaptadores de infraestrutura e mocks de teste.

---

## Technical Context

**Language/Version**: PHP 8.2+ / PHP 8.4 (Container CLI) / Laravel 10.x  
**Primary Dependencies**: `Illuminate\Support\ServiceProvider`, `App\Domain\Debt\Money`, `App\Domain\Debt\DebtType`, `App\Domain\Debt\Policies\DebtInterestPolicyInterface`  
**Storage**: N/A (memória e modelo canônico sem persistência relacional)  
**Testing**: PHPUnit / Pest via `docker compose exec monolith php artisan test`  
**Target Platform**: Linux Docker Containers (`cardok-monolith`, `cardok-provider-rest`, `cardok-provider-soap`)  
**Project Type**: REST API Service / Monólito Modular com Hexagonal Architecture  
**Performance Goals**: <50ms para cálculo financeiro completo e simulação de opções  
**Constraints**: Zero acoplamento de frameworks em `app/Domain/`; precisão financeira em centavos inteiros (`int`); arredondamento `HALF_UP`; determinismo temporal via `ClockInterface`; backward compatibility com débitos existentes (IPVA e MULTA).  
**Scale/Scope**: Adição de 1 enum case, 1 strategy policy, registro no container, testes unitários e de integração.  

---

## Constitution Check

*GATE: Avaliação pré e pós-design com base na Constituição do Projeto Cardok (v1.0.1).*

| Princípio | Requisito da Constituição | Status no Plano |
|---|---|---|
| **I. Arquitetura e Isolamento do Domínio** | `app/Domain` DEVE ser independente de Laravel, banco de dados e HTTP. Implementações em `Infrastructure`. | **PASS** — Nova política `LicenciamentoInterestPolicy` é PHP puro; `DebtType` é enum nativo puro. |
| **II. Precisão Financeira** | Valores monetários DEVEM utilizar `Money` internamente em centavos inteiros (`int`) com `HALF_UP`. | **PASS** — Utiliza `Money` e constantes de basis points para 0,33% e teto de 20%. |
| **III. Integrações e Resiliência** | Providers externos DEVEM ser convertidos para modelo canônico antes de entrar no domínio. | **PASS** — Normalização via `DebtType::tryFromNormalized()` em `RestVehicleDebtProvider` e `SoapVehicleDebtProvider`. |
| **IV. Simulação de Pagamentos** | Somente simulação: PIX 5% desc; Cartão 1x, 6x, 12x Price 2,5% a.m.; opções `TOTAL` e `SOMENTE_<TIPO>`. | **PASS** — `PaymentSimulator` gera `SOMENTE_LICENCIAMENTO` dinamicamente conforme enum canônico. |
| **V. Determinismo Temporal** | Regras de tempo DEVEM usar `ClockInterface` injetável (data de ref Home Test: 2024-05-10T00:00:00Z). | **PASS** — O cálculo de dias de atraso consome o `ClockInterface` já existente. |
| **VI. Observabilidade e Privacidade** | `X-Request-ID` atribuído; mascaramento de placas (`ABC****`) mantido. | **PASS** — Preservado em todas as camadas. |
| **VII. Testes** | Suíte de testes passando integralmente; testes unitários e regressão obrigatórios. | **PASS** — Testes unitários para a política e integração cobrindo novos cenários. |

---

## Project Structure

### Documentação da Feature
```text
specs/006-add-licenciamento-debt/
├── spec.md              # Especificação refinada com esclarecimentos
├── plan.md              # Este plano de implementação
├── research.md          # Pesquisa técnica e decisões de design (Fase 0)
├── data-model.md        # Modelo canônico e contratos de dados (Fase 1)
├── quickstart.md        # Guia de validação e comandos de teste (Fase 1)
├── checklists/
│   └── requirements.md  # Checklist de conformidade da especificação
└── contracts/
    └── vehicle-debts-contract.json # Esquema JSON da resposta com Licenciamento
```

### Arquivos de Código Afetados
```text
monolith/app/
├── Domain/Debt/
│   ├── DebtType.php                             # Adição do case LICENCIAMENTO = 'LICENCIAMENTO'
│   └── Policies/
│       └── LicenciamentoInterestPolicy.php      # [NOVO] Estratégia de cálculo (0,33%/dia, teto 20%)
├── Providers/
│   └── AppServiceProvider.php                   # Registro da nova política no DebtInterestPolicyRegistry
└── (Payment/Services/PaymentSimulator.php)      # [JÁ COMPATÍVEL] Dinamicamente agrupa SOMENTE_LICENCIAMENTO

monolith/tests/
├── Unit/Domain/Debt/Policies/
│   └── LicenciamentoInterestPolicyTest.php      # [NOVO] Testes unitários exaustivos da política
└── Feature/
    └── VehicleDebtLicenciamentoIntegrationTest.php # [NOVO] Teste end-to-end com débito de Licenciamento

provider-rest/app/Http/Controllers/
└── VehicleDebtController.php                    # Mock de dados contendo LICENCIAMENTO

provider-soap/app/Http/Controllers/
└── SoapVehicleDebtController.php                # Mock SOAP contendo <category>LICENCIAMENTO</category>
```

---

## Complexity Tracking

Nenhuma violação constitucional detectada. Complexidade mínima graças à adesão prévia aos padrões *Strategy & Registry* e *Hexagonal Architecture*.
