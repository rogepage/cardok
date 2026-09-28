Você é meu pair programmer no projeto `cardok`.

As fases anteriores já foram implementadas:

* infraestrutura Docker;
* provider REST;
* provider SOAP;
* adapters REST e SOAP;
* modelo canônico;
* retry;
* fallback;
* tratamento de indisponibilidade dos providers.

Agora vamos implementar a **Fase 5 — Domínio e regras de juros**.

## Objetivo

Implementar as regras de negócio relacionadas aos débitos veiculares:

* tipos de débito;
* cálculo de dias de atraso;
* juros simples;
* teto de juros do IPVA;
* cálculo do valor atualizado;
* arredondamento monetário;
* tratamento de tipos de débito desconhecidos.

Nesta etapa NÃO implementar pagamentos.

---

# 1. Princípio arquitetural

As regras de negócio devem ficar no `Domain`.

O domínio NÃO deve conhecer:

* Laravel HTTP;
* Controllers;
* REST;
* SOAP;
* Docker;
* Providers;
* Request;
* Response;
* banco de dados.

A ideia é:

```text
Provider
   ↓
Canonical Model
   ↓
Application
   ↓
Domain
   ↓
Débito atualizado
```

---

# 2. Data atual fixa

Para o teste, a data atual é obrigatoriamente:

```text
2024-05-10T00:00:00Z
```

Todas as comparações de data devem utilizar UTC.

Não utilize diretamente:

```php
now()
```

ou:

```php
Carbon::now()
```

dentro das regras de negócio.

Crie uma abstração para a data atual ou injete um `Clock`.

Por exemplo:

```text
Clock
FixedClock
```

A aplicação poderá fornecer:

```text
2024-05-10
```

como data de referência.

Isso é importante para que os testes sejam determinísticos.

---

# 3. Dias de atraso

Para cada débito:

```text
dias_atraso =
    data_atual - data_vencimento
```

Se o débito ainda não venceu:

```text
dias_atraso = 0
```

Ou seja:

```text
dias_atraso <= 0
```

deve resultar em:

```text
juros = 0
valor_atualizado = valor_original
```

---

# 4. IPVA

Regra:

```text
taxa = 0,33% ao dia
```

Em decimal:

```text
0.0033
```

Teto:

```text
20% do valor original
```

Importante:

O teto é aplicado **somente aos juros**, não ao valor total.

Fórmula:

```text
juros = min(
    valor_original × 0.0033 × dias_atraso,
    valor_original × 0.20
)
```

Depois:

```text
valor_atualizado =
    valor_original + juros
```

O valor atualizado deve ser arredondado HALF_UP para 2 casas.

Exemplo:

```text
valor original = 1500.00
dias atraso = 121

juros calculado = 1500 × 0.0033 × 121
                = 598.95

teto = 1500 × 0.20
     = 300.00

juros aplicado = 300.00

valor atualizado = 1800.00
```

---

# 5. MULTA

Taxa:

```text
1,00% ao dia
```

Decimal:

```text
0.01
```

Não existe teto.

Fórmula:

```text
juros =
    valor_original × 0.01 × dias_atraso
```

Depois:

```text
valor_atualizado =
    valor_original + juros
```

Utilize HALF_UP com 2 casas.

Exemplo:

```text
300.50
85 dias

juros =
300.50 × 0.01 × 85

= 255.425

juros arredondado =
255.43

valor atualizado =
555.93
```

---

# 6. Arredondamento

Política:

```text
HALF_UP
```

Sempre:

```text
2 casas decimais
```

Não utilize arredondamento bancário.

Não utilize `float` para cálculo monetário.

Preferencialmente utilize:

* `Money` Value Object;
* strings decimais;
* ou outra abordagem segura.

Se já existir um `Money` implementado na fase anterior, reutilize-o.

Não crie uma segunda representação monetária.

---

# 7. Tipos de débito

Inicialmente existem:

```text
IPVA
MULTA
```

Utilize uma representação explícita.

Por exemplo:

```php
enum DebtType: string
{
    case IPVA = 'IPVA';
    case MULTA = 'MULTA';
}
```

Se já existir esse enum, reutilize-o.

---

# 8. Tipo desconhecido

Se o provider retornar:

```text
LICENCIAMENTO
```

ou qualquer outro tipo não suportado:

```text
HTTP 422
```

Payload:

```json
{
    "error": "unknown_debt_type",
    "type": "LICENCIAMENTO"
}
```

Importante:

Se houver vários débitos e apenas um deles for desconhecido, NÃO silencie o débito.

Exemplo:

```text
IPVA
MULTA
LICENCIAMENTO
```

O sistema deve identificar o tipo desconhecido.

Não transforme em:

```text
OUTROS
```

Não descarte silenciosamente.

Defina uma política consistente e documente.

---

# 9. Caso todos os débitos sejam desconhecidos

Se o provider retornar somente:

```text
LICENCIAMENTO
```

o resultado deverá ser:

```http
422
```

com:

```json
{
    "error": "unknown_debt_type",
    "type": "LICENCIAMENTO"
}
```

---

# 10. Caso zero débitos

Se o provider retornar:

```text
[]
```

o domínio deve aceitar normalmente.

Resultado:

```text
debitos = []
```

Não é erro.

Posteriormente a camada de pagamento deverá decidir como representar essa situação.

Nesta etapa não implemente pagamento.

---

# 11. Estratégia para juros

Quero evitar um `if` gigante como:

```php
if ($type === 'IPVA') {
    ...
}

if ($type === 'MULTA') {
    ...
}
```

Utilize uma estrutura extensível.

Uma possibilidade:

```text
InterestCalculator
       ↑
       │
 ┌─────┴──────────┐
 │                │
IpvaInterest   MultaInterest
```

ou:

```text
InterestPolicy
       ↑
       │
 ┌─────┴──────────┐
 │                │
IPVA Policy    Multa Policy
```

Escolha a abordagem que considerar mais simples e justificável.

A ideia é permitir futuramente:

```text
LICENCIAMENTO
SEGURO
TAXA
```

sem alterar uma classe gigante.

---

# 12. Responsabilidades

O cálculo deve ser aproximadamente:

```text
Debt
 ↓
InterestPolicy
 ↓
Interest
 ↓
UpdatedDebt
```

Não coloque cálculo de juros:

* no Controller;
* no Provider;
* no Adapter;
* no DTO de integração.

---

# 13. Modelo de débito atualizado

Precisamos conseguir representar:

```text
tipo
valor_original
valor_atualizado
vencimento
dias_atraso
```

Pode criar um DTO/Value Object específico para o resultado de domínio.

Por exemplo:

```text
CalculatedDebt
```

ou outra nomenclatura adequada.

---

# 14. Serviço de domínio

Crie um serviço responsável por transformar:

```text
Debt
```

em:

```text
CalculatedDebt
```

Algo conceitualmente parecido com:

```php
$calculatedDebt = $debtCalculator->calculate($debt, $referenceDate);
```

Não precisa utilizar exatamente esse nome.

---

# 15. Testes obrigatórios

Crie testes unitários para:

### IPVA

1. débito vencido;
2. débito não vencido;
3. juros abaixo do teto;
4. juros exatamente no teto;
5. juros acima do teto;
6. arredondamento HALF_UP.

### MULTA

1. débito vencido;
2. débito não vencido;
3. cálculo normal;
4. arredondamento HALF_UP.

### Datas

Testar:

```text
vencimento = 2024-05-10
```

Resultado:

```text
dias_atraso = 0
```

Testar:

```text
vencimento = 2024-05-11
```

Resultado:

```text
dias_atraso = 0
```

Testar:

```text
vencimento = 2024-05-09
```

Resultado:

```text
dias_atraso = 1
```

---

# 16. Teste do exemplo oficial

Crie um teste usando exatamente:

```text
IPVA
1500.00
2024-01-10
```

e:

```text
MULTA
300.50
2024-02-15
```

com data:

```text
2024-05-10
```

O resultado esperado deve ser:

```text
IPVA
dias_atraso = 121
valor_atualizado = 1800.00
```

e:

```text
MULTA
dias_atraso = 85
valor_atualizado = 555.93
```

---

# 17. Resumo

Depois de calcular os débitos, implemente também a soma:

```text
total_original
total_atualizado
```

Para o exemplo:

```text
total_original = 1800.50
total_atualizado = 2355.93
```

O resumo deve utilizar o mesmo tratamento monetário.

---

# 18. Endpoint

Mantenha:

```http
POST /api/v1/vehicles/debts
```

Request:

```json
{
    "placa": "ABC1234"
}
```

Agora o fluxo deve ser:

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
Domain
 ↓
Interest Calculator
 ↓
Calculated Debts
 ↓
Response
```

---

# 19. Resposta

Agora o endpoint pode retornar:

```json
{
    "placa": "ABC1234",
    "debitos": [
        {
            "tipo": "IPVA",
            "valor_original": "1500.00",
            "valor_atualizado": "1800.00",
            "vencimento": "2024-01-10",
            "dias_atraso": 121
        },
        {
            "tipo": "MULTA",
            "valor_original": "300.50",
            "valor_atualizado": "555.93",
            "vencimento": "2024-02-15",
            "dias_atraso": 85
        }
    ],
    "resumo": {
        "total_original": "1800.50",
        "total_atualizado": "2355.93"
    }
}
```

Ainda NÃO adicione:

```text
pagamentos
pix
cartao_credito
```

Isso ficará para a próxima fase.

---

# 20. HTTP 422

A transformação de uma exceção de domínio em:

```text
HTTP 422
```

deve acontecer na camada HTTP/Application, e não dentro do domínio.

O domínio deve expressar o problema.

Por exemplo:

```text
UnknownDebtTypeException
```

e uma camada superior transforma isso em:

```json
{
    "error": "unknown_debt_type",
    "type": "LICENCIAMENTO"
}
```

---

# 21. Importante sobre fallback e dados

Não altere a lógica de fallback implementada anteriormente.

O fallback continua significando:

> "O provider atual está indisponível; tente o próximo."

Não implemente nesta fase:

* comparação entre REST e SOAP;
* merge de débitos;
* conciliação;
* consulta simultânea dos providers.

Se REST responder com sucesso, seus dados são utilizados.

Se REST estiver indisponível e SOAP assumir, os dados do SOAP são utilizados.

A divergência entre providers continua documentada como uma evolução futura.

---

# 22. README

Atualize o README documentando:

* data de referência fixa;
* política de juros;
* juros do IPVA;
* teto do IPVA;
* juros da MULTA;
* política de arredondamento;
* tipos suportados;
* comportamento para tipos desconhecidos.

Explique também a decisão arquitetural de separar:

```text
Integration
Domain
HTTP
```

---

# 23. Critério de conclusão

A fase estará concluída quando:

1. IPVA calcular corretamente.
2. MULTA calcular corretamente.
3. Dias de atraso forem calculados corretamente.
4. Débitos não vencidos não tenham juros.
5. Teto do IPVA seja respeitado.
6. HALF_UP seja utilizado.
7. Não exista cálculo monetário baseado em `float`.
8. Tipos desconhecidos gerem `UnknownDebtTypeException`.
9. A camada HTTP transforme isso em HTTP 422.
10. Zero débitos continue funcionando.
11. O total original seja calculado.
12. O total atualizado seja calculado.
13. Todos os testes estejam passando.
14. O exemplo oficial do enunciado produza exatamente os valores esperados.

---

# 24. Processo de implementação

Antes de modificar qualquer arquivo:

1. Analise o estado atual do projeto.
2. Identifique os DTOs/Value Objects já existentes.
3. NÃO crie duplicação de `Money`, `DebtType` ou outros modelos.
4. Liste os arquivos que serão alterados/criados.
5. Explique a arquitetura.
6. Implemente incrementalmente.
7. Execute os testes.
8. Corrija eventuais problemas.
9. Apresente os resultados.

Não implemente ainda:

* PIX;
* cartão;
* parcelamento;
* Price/PMT;
* validação de placa;
* autenticação;
* banco de dados;
* circuit breaker.

A próxima fase será exclusivamente a implementação das opções de pagamento.

---

## 25. Implementação Realizada e Decisões Técnicas

Esta seção registra as decisões arquiteturais tomadas e a validação da Fase 5.

### 25.1. Decisões Técnicas e Racional de Engenharia

1. **Domínio Puro e Desacoplado (`App\Domain\Debt`)**:
   - Todo o cálculo financeiro, regras de juros, políticas de teto e validação de tipos residem exclusivamente no domínio, sem nenhuma referência a controllers, HTTP, banco de dados ou formato de fornecedores externos.
   - O domínio recebe o modelo canônico da Fase 3 (`ProviderDebtResponse` e `Debt`) e produz `CalculatedVehicleDebts` e `CalculatedDebt`.

2. **Aritmética Monetária Segura Sem `float`**:
   - **Decisão**: Todos os cálculos de juros e totais são executados em centavos inteiros (`int $amountInCents`) no Value Object `Money`.
   - **Arredondamento HALF_UP**: Implementado através de aritmética inteira: `intdiv($numerador + ($denominador / 2), $denominador)`. Isso elimina qualquer erro de representação de ponto flutuante binário e não requer dependências externas (como `bcmath`).

3. **Determinismo Temporal com `ClockInterface` e `FixedClock`**:
   - **Decisão**: Foi criada a abstração `ClockInterface` com a implementação `FixedClock`, fixando a data de referência em `2024-05-10T00:00:00Z` (UTC).
   - **Racional**: Assegura que cálculos de dias de atraso e juros sejam 100% determinísticos e independentes do horário do servidor host ou da execução dos testes.

4. **Padrão Strategy para Políticas de Juros (`DebtInterestPolicyRegistry`)**:
   - **Decisão**: Criada a interface `DebtInterestPolicyInterface` com implementações específicas:
     - `IpvaInterestPolicy`: taxa de 0,33% ao dia (`33 / 10000`), com teto estrito de 20% sobre o valor original.
     - `MultaInterestPolicy`: taxa de 1,00% ao dia (`1 / 100`), sem teto.
   - **Racional**: Novos tipos de débitos (ex: `LICENCIAMENTO`, `SEGURO`) poderão ser adicionados no futuro bastando criar uma nova policy e registrá-la no `DebtInterestPolicyRegistry`, sem modificar código existente (Open/Closed Principle).

5. **Tratamento Rigoroso de Tipos Desconhecidos (HTTP 422)**:
   - Se o provedor retornar qualquer tipo diferente de `IPVA` e `MULTA`, o domínio lança imediatamente `UnknownDebtTypeException`, carregando o tipo rejeitado.
   - A camada HTTP (`VehicleDebtIntegrationController`) intercepta essa exceção de domínio e responde com **HTTP 422**:
     ```json
     {
         "error": "unknown_debt_type",
         "type": "LICENCIAMENTO"
     }
     ```
   - Nenhum débito inválido é silenciado, descartado ou agrupado como "OUTROS".

6. **Resumo Financeiro com Modelo Canônico**:
   - A resposta agrega o `resumo` com `total_original` e `total_atualizado` calculados pelo `DebtCalculationService` somando instâncias de `Money`.

### 25.2. Validação dos Testes Automatizados

Execução da suíte completa de testes no monólito via Docker:
```bash
docker compose exec monolith php artisan test
```
```text
   PASS  Tests\Unit\Domain\Debt\DebtCalculationServiceTest
  ✓ official example scenario                                            0.08s  
  ✓ ipva overdue below cap                                               0.05s  
  ✓ ipva overdue exactly at cap                                          0.04s  
  ✓ ipva not overdue has zero interest                                   0.03s  
  ✓ ipva half up rounding                                                0.03s  
  ✓ multa overdue and half up rounding                                   0.04s  
  ✓ multa not overdue has zero interest                                  0.04s  
  ✓ date boundary cases                                                  0.05s  
  ✓ throws unknown debt type exception                                   0.03s  
  ✓ handles zero debts correctly                                         0.04s  

   PASS  Tests\Feature\VehicleDebtIntegrationTest
  ✓ endpoint returns debts from rest provider                            0.06s  
  ✓ endpoint returns debts from soap provider                            0.04s  
  ✓ endpoint automatically uses configured order and falls back to soap… 0.04s  
  ✓ endpoint returns 503 when all providers fail                         0.04s  
  ✓ endpoint returns empty debts for vehicle without debts               0.03s  
  ✓ endpoint returns 422 when provider returns unknown debt type         0.05s  
  ✓ endpoint returns bad request when plate is missing                   0.04s  

  Tests:    48 passed (163 assertions)
  Duration: 3.99s
```

### 25.3. Evidências de Validação Manual (Curls)

```bash
# 1. Consulta com débitos (ABC1234)
curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa":"ABC1234"}'
```
**Resposta (HTTP 200):**
```json
{
  "placa": "ABC1234",
  "debitos": [
    {
      "tipo": "IPVA",
      "valor_original": "1500.00",
      "valor_atualizado": "1800.00",
      "vencimento": "2024-01-10",
      "dias_atraso": 121
    },
    {
      "tipo": "MULTA",
      "valor_original": "300.50",
      "valor_atualizado": "555.93",
      "vencimento": "2024-02-15",
      "dias_atraso": 85
    }
  ],
  "resumo": {
    "total_original": "1800.50",
    "total_atualizado": "2355.93"
  }
}
```

```bash
# 2. Consulta sem débitos (DEF5678)
curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa":"DEF5678"}'
```
**Resposta (HTTP 200):**
```json
{
  "placa": "DEF5678",
  "debitos": [],
  "resumo": {
    "total_original": "0.00",
    "total_atualizado": "0.00"
  }
}
```
