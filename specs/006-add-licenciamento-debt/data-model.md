# Data Model: Adição do Débito de Licenciamento

**Feature**: `006-add-licenciamento-debt`  
**Status**: Concluído  

---

## 1. Entidades de Domínio e Enums

### Enum `App\Domain\Debt\DebtType`
Enum nativo tipado em string para classificação canônica do débito:

| Caso | Valor String | Descrição |
|---|---|---|
| `IPVA` | `'IPVA'` | Imposto sobre a Propriedade de Veículos Automotores |
| `MULTA` | `'MULTA'` | Penalidade pecuniária por infração de trânsito |
| `LICENCIAMENTO` *(novo)* | `'LICENCIAMENTO'` | Taxa anual de licenciamento e regularização do veículo |

### Entidade `App\Domain\Debt\Debt` (Inalterada, suporta o novo tipo)
* `type`: `DebtType::LICENCIAMENTO`
* `amount`: `Money` (valor original em centavos)
* `dueDate`: `DateTimeImmutable` (data de vencimento em UTC)

### Entidade `App\Domain\Debt\CalculatedDebt`
* `debt`: Instância de `Debt`
* `daysOverdue`: `int` (quantidade de dias corridos entre vencimento e data de referência)
* `interest`: `Money` (valor dos encargos/juros em centavos)
* `updatedAmount`: `Money` (`amount + interest`)

---

## 2. Estrutura de Pagamentos Agrupados (`PaymentOption`)

Quando houver débitos de licenciamento no veículo, o `PaymentSimulator` gera:

### Opção Agrupada: `SOMENTE_LICENCIAMENTO`
* `type`: `'SOMENTE_LICENCIAMENTO'`
* `baseAmount`: Soma de todos os `updatedAmount` dos débitos com `type === DebtType::LICENCIAMENTO`.
* `pix`: `totalComDesconto` com $5\%$ de abatimento sobre `baseAmount`.
* `creditCard`: Parcelamento com taxa de $2{,}5\%$ a.m. (Tabela Price) em:
  * `1x`: Parcela integral à vista (sem juros).
  * `6x`: 6 parcelas calculadas com amortização Price.
  * `12x`: 12 parcelas calculadas com amortização Price.

---

## 3. Mapeamento de Entrada dos Provedores (Normalização)

| Provedor | Formato Bruto de Origem | Mapeamento Canônico |
|---|---|---|
| **REST (JSON)** | `{"type": "LICENCIAMENTO", "amount": 100.00, "due_date": "2024-01-10"}` | `Debt(DebtType::LICENCIAMENTO, Money(10000), 2024-01-10)` |
| **SOAP (XML)** | `<debt><category>LICENCIAMENTO</category><value>100.00</value><expiration>2024-01-10</expiration></debt>` | `Debt(DebtType::LICENCIAMENTO, Money(10000), 2024-01-10)` |

A normalização ocorre via `DebtType::tryFromNormalized($rawType)`, garantindo tolerância a maiúsculas/minúsculas e corte de espaços sem permitir termos não previstos.
