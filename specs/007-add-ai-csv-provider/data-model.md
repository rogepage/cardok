# Data Model: Provedor de Débitos com IA em Plaintext CSV

**Feature**: `007-add-ai-csv-provider`  
**Data**: 2026-10-08  
**Status**: Concluído  

---

## 1. Estrutura do Payload Bruto do Provedor AI (Plaintext CSV)

O serviço de IA retorna dados em texto plano com cabeçalho opcional ou linhas estruturadas por registros tabulares:

### Representação da Linha de Dados (CSV)

```text
tipo,valor,vencimento
```
ou utilizando ponto e vírgula como delimitador:
```text
tipo;valor;vencimento
```

### Atributos por Linha

| Campo | Posição Padrão | Tipo Bruto | Regras de Validação | Exemplos |
|---|---|---|---|---|
| `tipo` | Coluna 0 | `string` | Obrigatório; convertido para maiúsculas e higienizado de espaços e aspas. | `IPVA`, `MULTA`, `LICENCIAMENTO` |
| `valor` | Coluna 1 | `string` / numérico | Obrigatório; aceita ponto (`150.00`) ou vírgula (`150,00`) como separador decimal. Não pode ser negativo. | `1500.00`, `150,50` |
| `vencimento` | Coluna 2 | `string` | Obrigatório; formato de data ISO-8601 `YYYY-MM-DD`. | `2024-01-15` |

---

## 2. Regras de Normalização para o Domínio Canônico

O adaptador `AiVehicleDebtProvider` aplica o seguinte fluxo de transformação:

1. **Higienização de Linhas**:
   - Remoção de delimitadores de código markdown (````csv`, ````).
   - Descarte de linhas vazias ou contendo apenas espaços.
   - Identificação e descarte da linha de cabeçalho (`tipo,valor,vencimento`).
   - Descarte de linhas de texto corrido ou preâmbulo não tabulares.

2. **Mapeamento de Categoria (`DebtType`)**:
   - `IPVA` → `DebtType::IPVA` (ou string `'IPVA'`)
   - `MULTA` → `DebtType::MULTA` (ou string `'MULTA'`)
   - `LICENCIAMENTO` → `DebtType::LICENCIAMENTO` (ou string `'LICENCIAMENTO'`)
   - *Categoria não reconhecida* (ex.: `DPVAT`, `PEDAGIO`): Linha descartada e registrado log estruturado de aviso (`warning`).

3. **Mapeamento Monetário (`Money`)**:
   - Substituição de vírgula decimal por ponto: `str_replace(',', '.', $rawAmount)`.
   - Conversão estrita para centavos inteiros via `Money::fromDecimal($amountString)`.
   - Proibido qualquer casting intermediário para `float`.

4. **Mapeamento de Data de Vencimento**:
   - Conversão da data ISO-8601 para `CarbonImmutable` em UTC.

---

## 3. Modelo Canônico Produzido (`ProviderDebtResponse`)

```text
ProviderDebtResponse
├── plate: string (ex.: "ABC1234")
├── provider: "ai"
└── debts: array<App\Domain\Debt\Debt>
     ├── Debt[0]: { type: "IPVA", amount: Money(150000), dueDate: 2024-01-15 }
     └── Debt[1]: { type: "LICENCIAMENTO", amount: Money(10000), dueDate: 2024-02-20 }
```

Se a resposta da IA contiver apenas o cabeçalho ou texto indicando ausência de débitos:
```text
ProviderDebtResponse
├── plate: string (ex.: "ABC1234")
├── provider: "ai"
└── debts: []
```
*Observação: Uma resposta válida de 0 débitos não dispara fallback para os provedores seguintes.*
