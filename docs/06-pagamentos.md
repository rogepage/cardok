Você é meu pair programmer no projeto `cardok`.

As fases anteriores já foram implementadas:

* infraestrutura Docker;
* provider REST;
* provider SOAP;
* adapters REST e SOAP;
* modelo canônico;
* retry;
* fallback;
* tratamento de indisponibilidade;
* domínio de débitos;
* cálculo de juros;
* cálculo de dias de atraso;
* resumo dos débitos;
* tratamento de tipos desconhecidos.

Agora vamos implementar a **Fase 6 — Pagamentos**.

## Objetivo

Implementar as opções de pagamento:

* pagamento TOTAL;
* pagamento parcial por tipo de débito;
* PIX com desconto de 5%;
* cartão de crédito;
* pagamento em 1x;
* pagamento em 6x;
* pagamento em 12x;
* cálculo das parcelas utilizando Price/PMT.

Nesta fase NÃO implementar:

* autenticação;
* banco de dados;
* processamento real de pagamentos;
* integração com gateway;
* cobrança real;
* circuit breaker;
* validação de placa.

O sistema apenas deve **simular as opções de pagamento**.

---

# 1. Modelo conceitual

Temos os débitos calculados:

```text
IPVA
valor_atualizado = 1800.00

MULTA
valor_atualizado = 555.93
```

Então devemos gerar:

```text
TOTAL
SOMENTE_IPVA
SOMENTE_MULTA
```

Cada opção possui:

```text
valor_base
PIX
cartão
```

---

# 2. Pagamento TOTAL

O pagamento TOTAL utiliza:

```text
total_atualizado
```

Exemplo:

```text
1800.00 + 555.93
= 2355.93
```

Portanto:

```json
{
    "tipo": "TOTAL",
    "valor_base": "2355.93"
}
```

---

# 3. Pagamentos parciais

Deve existir uma opção para cada tipo de débito presente.

Para:

```text
IPVA
MULTA
```

teremos:

```text
SOMENTE_IPVA
SOMENTE_MULTA
```

IMPORTANTE:

O agrupamento deve ser por tipo.

Se existirem três débitos IPVA:

```text
IPVA 100
IPVA 200
IPVA 300
```

deve existir apenas:

```text
SOMENTE_IPVA
```

com:

```text
600.00
```

Não criar:

```text
SOMENTE_IPVA_1
SOMENTE_IPVA_2
SOMENTE_IPVA_3
```

---

# 4. PIX

O PIX possui desconto de:

```text
5%
```

O desconto deve ser aplicado ao:

```text
valor_base
```

de CADA opção.

Fórmula:

```text
total_com_desconto =
valor_base × 0.95
```

Arredondamento:

```text
HALF_UP
2 casas
```

Exemplo:

```text
TOTAL

valor_base = 2355.93

2355.93 × 0.95
= 2238.1335

resultado:
2238.13
```

---

# 5. IMPORTANTE — PIX parcial

O desconto também deve ser aplicado aos pagamentos parciais.

Exemplo:

```text
SOMENTE_IPVA

valor_base = 1800.00

1800 × 0.95
= 1710.00
```

E:

```text
SOMENTE_MULTA

555.93 × 0.95
= 528.1335

resultado:
528.13
```

Não aplicar o desconto somente ao TOTAL.

---

# 6. Cartão de crédito

O cartão deve possuir EXATAMENTE estas opções:

```text
1x
6x
12x
```

Não adicionar:

```text
2x
3x
4x
5x
10x
18x
24x
```

nesta fase.

---

# 7. Cartão 1x

1x significa pagamento à vista.

Não existe juros.

Fórmula:

```text
valor_parcela = valor_base
```

Exemplo:

```text
TOTAL
valor_base = 2355.93

1x = 2355.93
```

---

# 8. Cartão 6x e 12x

Utilizar sistema de amortização Price.

Taxa:

```text
2,5% ao mês
```

Decimal:

```text
0.025
```

Fórmula:

```text
PMT =
base × i × (1+i)^n
-------------------
((1+i)^n - 1)
```

Onde:

```text
i = 0.025
n = quantidade de parcelas
base = valor_base
```

---

# 9. Arredondamento do Price

IMPORTANTE:

O cálculo matemático do PMT deve ser realizado com a maior precisão possível.

Não arredondar valores intermediários.

Somente o resultado final da parcela deve ser arredondado:

```text
HALF_UP
2 casas
```

Não utilize `float` se isso causar perda relevante de precisão.

Se necessário, utilize:

* decimal;
* BCMath;
* Money;
* ou outra solução segura já existente no projeto.

Não crie uma segunda solução monetária se o projeto já possui uma.

---

# 10. Exemplo oficial

Para:

```text
valor_base = 2355.93
```

devemos obter aproximadamente:

```text
1x = 2355.93
6x = 427.72
12x = 229.67
```

A tolerância do enunciado é:

```text
± R$ 0,02
```

Crie testes para esses valores.

---

# 11. Exemplo IPVA

Para:

```text
valor_base = 1800.00
```

esperamos aproximadamente:

```text
1x = 1800.00
6x = 326.79
12x = 175.48
```

PIX:

```text
1710.00
```

---

# 12. Exemplo MULTA

Para:

```text
valor_base = 555.93
```

esperamos aproximadamente:

```text
1x = 555.93
6x = 100.93
12x = 54.20
```

PIX:

```text
528.13
```

---

# 13. Arquitetura

Não coloque cálculo de PIX ou cartão no Controller.

Também não coloque essas regras dentro do domínio de `Debt`.

Crie uma separação clara.

Uma possibilidade:

```text
Domain/
├── Debt/
└── Payment/
    ├── PaymentOption.php
    ├── PixCalculator.php
    ├── CreditCardCalculator.php
    └── PaymentSimulator.php
```

A estrutura pode ser diferente se você encontrar uma solução melhor.

O importante é:

```text
Debt
```

e:

```text
Payment
```

serem responsabilidades separadas.

---

# 14. Strategy

Considere utilizar Strategy para os meios de pagamento.

Por exemplo:

```text
PaymentMethod
      ↑
 ┌────┴─────┐
 │          │
 PIX      CREDIT_CARD
```

Ou uma solução equivalente.

Não crie abstrações excessivas.

A arquitetura deve ser simples o suficiente para um Home Test, mas demonstrar extensibilidade.

---

# 15. Modelo de saída

O resultado final deve ter:

```json
{
    "pagamentos": {
        "opcoes": []
    }
}
```

Cada opção:

```json
{
    "tipo": "TOTAL",
    "valor_base": "2355.93",
    "pix": {
        "total_com_desconto": "2238.13"
    },
    "cartao_credito": {
        "parcelas": [
            {
                "quantidade": 1,
                "valor_parcela": "2355.93"
            },
            {
                "quantidade": 6,
                "valor_parcela": "427.72"
            },
            {
                "quantidade": 12,
                "valor_parcela": "229.67"
            }
        ]
    }
}
```

---

# 16. Ordem das opções

Para o exemplo:

```text
TOTAL
SOMENTE_IPVA
SOMENTE_MULTA
```

Mantenha uma ordem determinística.

Sugestão:

1. TOTAL;
2. tipos de débito na ordem em que aparecem pela primeira vez.

Não ordene aleatoriamente.

Documente essa decisão.

---

# 17. Integração com o fluxo atual

O fluxo deverá ficar:

```text
HTTP
 ↓
Application
 ↓
Provider Resolver
 ↓
REST/SOAP
 ↓
Canonical Model
 ↓
Domain - Debt
 ↓
Interest Calculator
 ↓
Calculated Debts
 ↓
Payment Simulator
 ↓
Response
```

O Payment Simulator recebe os débitos já calculados.

Ele NÃO deve recalcular juros.

---

# 18. Regra importante

O `valor_base` do pagamento deve ser sempre:

```text
valor_atualizado
```

e nunca:

```text
valor_original
```

Exemplo:

```text
IPVA

original = 1500.00
atualizado = 1800.00
```

O pagamento deve utilizar:

```text
1800.00
```

---

# 19. Zero débitos

Se não houver débitos:

```json
{
    "debitos": [],
    "resumo": {
        "total_original": "0.00",
        "total_atualizado": "0.00"
    }
}
```

Não criar:

```text
SOMENTE_IPVA
SOMENTE_MULTA
```

e não inventar opções de pagamento.

Defina uma resposta coerente e documente.

---

# 20. Testes unitários

Crie testes para:

### PIX

* TOTAL;
* IPVA;
* MULTA;
* arredondamento HALF_UP;
* desconto de exatamente 5%.

### Cartão

* 1x;
* 6x;
* 12x;
* cálculo Price;
* arredondamento.

### Agrupamento

Testar:

```text
IPVA
IPVA
MULTA
```

resultado:

```text
TOTAL
SOMENTE_IPVA
SOMENTE_MULTA
```

E:

```text
SOMENTE_IPVA
```

deve somar os dois IPVA.

---

# 21. Teste de integração

Utilize o cenário oficial completo:

```text
IPVA
1500.00
2024-01-10

MULTA
300.50
2024-02-15

data:
2024-05-10
```

O resultado deve conter:

```text
TOTAL
SOMENTE_IPVA
SOMENTE_MULTA
```

com os valores apresentados no enunciado.

Valide:

```text
PIX
1x
6x
12x
```

para cada opção.

---

# 22. Valores monetários

Todos os valores monetários na resposta JSON devem ser strings:

CORRETO:

```json
"2355.93"
```

INCORRETO:

```json
2355.93
```

Isso vale para:

* valor original;
* valor atualizado;
* total;
* PIX;
* valor da parcela.

---

# 23. Não implementar

Ainda não implementar:

* gateway de pagamento;
* API externa de pagamento;
* autenticação;
* banco;
* cartão real;
* PIX real;
* geração de QR Code;
* webhook;
* cobrança;
* transação financeira.

É apenas uma simulação matemática das opções de pagamento.

---

# 24. README

Atualize o README explicando:

* como funciona o PIX;
* desconto de 5%;
* como funciona o Price;
* taxa de 2,5% ao mês;
* opções permitidas;
* política de arredondamento;
* agrupamento por tipo;
* diferença entre valor original e valor atualizado.

Inclua exemplos.

---

# 25. Critério de conclusão

A fase estará concluída quando:

1. TOTAL for calculado corretamente.
2. Pagamentos parciais forem gerados por tipo.
3. Débitos do mesmo tipo forem agrupados.
4. PIX aplicar 5% de desconto em cada opção.
5. Cartão gerar somente 1x, 6x e 12x.
6. 1x não aplicar juros.
7. 6x utilizar Price a 2,5% a.m.
8. 12x utilizar Price a 2,5% a.m.
9. HALF_UP seja utilizado.
10. Não haja cálculo monetário baseado em `float`.
11. Valores JSON sejam strings.
12. Testes automatizados cubram os principais cenários.
13. O exemplo oficial produza os valores esperados.

---

# 26. Processo de implementação

Antes de modificar arquivos:

1. Analise o código atual.
2. Identifique o `Money` existente.
3. Identifique os modelos de domínio existentes.
4. Evite duplicação.
5. Liste os arquivos que pretende criar/alterar.
6. Explique as decisões arquiteturais.
7. Implemente incrementalmente.
8. Execute os testes.
9. Corrija os problemas.
10. Apresente o resultado final.

Não implemente ainda:

* validação de placa;
* autenticação;
* banco;
* gateway;
* circuit breaker;
* observabilidade avançada.

A próxima fase será dedicada à **validação da placa, tratamento de entrada e fechamento do contrato HTTP da API**.

---

# 27. Implementação Realizada

### 27.1. Componentes Criados

1. **DTOs de Pagamento (`App\Domain\Payment\DTO`)**:
   - `PixOption`: encapsula `Money $totalWithDiscount` e serialização `['total_com_desconto' => string]`.
   - `CreditCardInstallment`: encapsula `int $quantity` e `Money $installmentAmount` (`['quantidade' => int, 'valor_parcela' => string]`).
   - `CreditCardOption`: encapsula `CreditCardInstallment[]` e serialização `['parcelas' => ...]`.
   - `PaymentOption`: encapsula `string $type`, `Money $baseAmount`, `PixOption $pix`, `CreditCardOption $creditCard`.
   - `PaymentSimulationResult`: encapsula `PaymentOption[]` e serialização `['opcoes' => ...]`.

2. **Contrato Strategy (`App\Domain\Payment\Contracts`)**:
   - `PaymentMethodCalculatorInterface`: define `getMethodKey(): string` e `calculate(Money $baseAmount): mixed`.

3. **Calculadoras de Domínio (`App\Domain\Payment\Services`)**:
   - `PixCalculator`: aplica desconto de 5% sobre o `valor_base` com arredondamento `HALF_UP` a 2 casas decimais sem float via `HalfUpRounder`.
   - `CreditCardCalculator`: simula parcelamento em 1x (à vista sem juros), 6x e 12x via fórmula Price/PMT ($i = 0.025$ a.m.), com precisão matemática total e arredondamento `HALF_UP` na parcela final.
   - `PaymentSimulator`: orquestra a geração de opções para `TOTAL` e opções parciais `SOMENTE_<TIPO>`, preservando a ordem de primeira aparição e agrupando múltiplos débitos do mesmo tipo. Retorna `opcoes: []` quando não há débitos.

### 27.2. Integração e Camada HTTP

- `AppServiceProvider`: registro dos serviços de domínio como singletons.
- `VehicleDebtIntegrationController`: recebe `CalculatedVehicleDebts`, delega a simulação para `PaymentSimulator->simulate()`, e anexa o bloco `pagamentos` no JSON de resposta.

### 27.3. Evidência dos Testes

- **Total Monolith**: 78 testes passando (266 asserções).
  - `CreditCardCalculatorTest`: 6 testes (18 asserções).
  - `PaymentSimulatorTest`: 4 testes (31 asserções).
  - `PixCalculatorTest`: 6 testes (12 asserções).
  - `VehicleDebtIntegrationTest`: 7 testes atualizados validando payload completo com `pagamentos`.
- **Provedores Mock**: 14 testes passando (40 asserções).
- **Total do Projeto**: 92 testes, 306 asserções, 100% green.

