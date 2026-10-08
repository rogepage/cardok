# Implementation Plan: Provedor de Débitos com IA em Plaintext CSV

**Branch**: `007-add-ai-csv-provider` | **Data**: 2026-10-08 | **Spec**: [spec.md](spec.md)  
**Input**: Especificação da feature em `specs/007-add-ai-csv-provider/spec.md`

---

## Summary

Esta feature integra um novo provedor de débitos veiculares parceiro baseado em inteligência artificial que responde via HTTP entregando dados em texto puro (`text/plain` / `text/csv`) com formatação tabular separada por vírgula ou ponto e vírgula (`tipo,valor,vencimento`). A solução implementa um adaptador de infraestrutura defensivo (`AiVehicleDebtProvider`) capaz de ignorar marcações de código markdown (````csv ... ````), comentários explicativos e linhas vazias, normalizando os débitos para o modelo canônico da aplicação. Para viabilizar testes ponta a ponta determinísticos e simulação de falhas sem custos de APIs pagas, é provisionado um container Docker satélite dedicado `provider-ai` na porta 8003.

---

## Technical Context

**Language/Version**: PHP 8.4 (Monólito Laravel 10)  
**Primary Dependencies**: Laravel 10 Framework, Illuminate HTTP Client (Guzzle), CarbonImmutable  
**Storage**: N/A (dados residem nos provedores externos e no modelo canônico em memória)  
**Testing**: PHPUnit 10 (Testes Unitários de Adaptador, Testes de Resolução e Integração)  
**Target Platform**: Docker Compose / Linux Container (arquitetura multi-serviço)  
**Project Type**: Web Service com Arquitetura Hexagonal (Ports & Adapters)  
**Performance Goals**: Tempo de resposta ponta a ponta < 1s; timeout por tentativa configurado em 2s  
**Constraints**:
- Princípio I: Domínio puro sem dependência de frameworks.
- Princípio II: Valores monetários manipulados estritamente via `Money` em centavos inteiros (`int`), sem representação em `float`.
- Princípio III: Resiliência *First-Success-Wins* com timeout, retry com backoff linear e fallback transparente.
- Princípio VIII: Documentação e artefatos de engenharia redigidos em pt-BR.  
**Scale/Scope**: 1 novo adapter de infraestrutura (`AiVehicleDebtProvider`), 1 novo container satélite (`provider-ai`), atualização de `services.php`, `ProviderResolver`, `AppServiceProvider` e suíte de testes completa.

---

## Constitution Check

*GATE: Avaliação pré-design e pós-design aprovada.*

| Princípio | Status | Justificativa |
|---|---|---|
| **I. Arquitetura e Isolamento do Domínio** | ✅ PASS | O adaptador reside estritamente em `App\Infrastructure\Providers\Ai\AiVehicleDebtProvider`, implementando a porta de domínio `App\Domain\Debt\Contracts\VehicleDebtProvider`. Nenhuma classe de domínio é contaminada. |
| **II. Precisão Financeira** | ✅ PASS | O parser higieniza strings monetárias e as converte diretamente via `Money::fromDecimal()`. Proibido uso de `float` para montantes. Arredondamento `HALF_UP` mantido nos cálculos subsequentes. |
| **III. Integrações e Resiliência** | ✅ PASS | O provedor de IA se integra ao `ProviderResolver` e `ProviderExecutor`, respeitando a ordem configurada, retries com backoff, fallback *First-Success-Wins* e sem chamadas concorrentes. Resposta válida sem débitos não dispara fallback. |
| **IV. Simulação de Pagamentos** | ✅ PASS | O fluxo de simulação de pagamentos (PIX com 5% de desconto, Cartão 1x, 6x, 12x via Price) permanece intacto e reutilizado automaticamente para os débitos retornados pela IA. |
| **V. Determinismo Temporal** | ✅ PASS | Datas de vencimento são manipuladas como `CarbonImmutable` e o cálculo de encargos consome o `ClockInterface` injetável (`FixedClock`). |
| **VI. Observabilidade e Privacidade** | ✅ PASS | Propagação de `X-Request-ID` nas chamadas ao serviço de IA, logging estruturado de warnings para categorias não homologadas e mascaramento de placas preservados. |
| **VII. Testes** | ✅ PASS | Cobertura integral com testes unitários do parser/adaptador, testes de resolução e testes de regressão de fallback. |
| **VIII. Artefatos de Engenharia** | ✅ PASS | Todos os documentos gerados em pt-BR. |

---

## Project Structure

### Documentation (this feature)

```text
specs/007-add-ai-csv-provider/
├── spec.md              # Especificação de requisitos e clarificações
├── plan.md              # Este plano de implementação
├── research.md          # Decisões de pesquisa técnica (Phase 0)
├── data-model.md        # Modelo de dados e normalização (Phase 1)
├── quickstart.md        # Guia de validação e testes (Phase 1)
├── contracts/           # Contratos de API (Phase 1)
│   └── ai-provider-api.md
├── checklists/
│   └── requirements.md  # Checklist de qualidade
└── tasks.md             # Tarefas de implementação (Phase 2 - speckit.tasks)
```

### Source Code Impact

```text
monolith/
├── app/
│   ├── Infrastructure/
│   │   └── Providers/
│   │       └── Ai/
│   │           └── AiVehicleDebtProvider.php     # Novo adaptador HTTP + CSV Parser
│   ├── Application/
│   │   └── VehicleDebt/
│   │       └── ProviderResolver.php             # Suporte e validação da chave 'ai'
│   └── Providers/
│       └── AppServiceProvider.php               # Registro de AiVehicleDebtProvider no container
├── config/
│   └── services.php                             # Adição de PROVIDER_AI_URL
└── tests/
    ├── Unit/
    │   └── Infrastructure/
    │       └── Providers/
    │           └── AiVehicleDebtProviderTest.php # Testes do parser, markdown stripping e erros
    └── Feature/
        └── AiProviderIntegrationTest.php        # Teste de consulta e fallback na API Cardok

provider-ai/                                     # Novo container satélite Docker
├── Dockerfile
├── composer.json
├── routes/
│   └── api.php                                  # GET /api/v1/debts/{plate}, /health, /simulation/mode
└── app/
    └── Services/
        └── AiDebtMockService.php                # Gerador de CSV e gerenciador de modo de simulação

docker-compose.yml                               # Adição do serviço provider-ai (porta 8003)
```

---

## Complexity Tracking

*Nenhuma violação constitucional identificada. A arquitetura segue exatamente o padrão estabelecido para os provedores REST e SOAP.*
