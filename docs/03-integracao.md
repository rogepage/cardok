Você é meu pair programmer no projeto `cardok`.

As fases anteriores já foram implementadas:

* infraestrutura Docker;
* `provider-rest`;
* `provider-soap`;
* mocks de sucesso, erro, timeout e resposta inválida.

Agora vamos implementar a **Fase 3 — Integração dos providers no monólito e normalização para um modelo canônico**.

## Objetivo

O monólito Laravel deve conseguir consultar os dois providers externos:

```text
provider-rest
provider-soap
```

e transformar suas respostas diferentes em um único modelo interno.

A arquitetura desejada é:

```text
                    Monolith
                       │
              VehicleDebtProvider
                       │
             ┌─────────┴─────────┐
             │                   │
     RestDebtProvider      SoapDebtProvider
             │                   │
             ▼                   ▼
      REST / JSON           SOAP / XML
             │                   │
             └─────────┬─────────┘
                       ▼
               Canonical Model
```

O domínio/aplicação do monólito não deve conhecer detalhes de JSON ou XML.

---

# 1. Interface do provider

Crie uma abstração para os providers.

Sugestão:

```php
interface VehicleDebtProvider
{
    public function getDebts(string $plate): ProviderDebtResponse;
}
```

O nome pode ser ajustado se houver uma nomenclatura melhor, mas mantenha a responsabilidade clara.

Essa interface deve representar uma **porta de entrada de dados de débitos veiculares**, e não uma implementação HTTP específica.

---

# 2. Modelo canônico

Crie DTOs/Value Objects apropriados para representar os dados normalizados.

Sugestão conceitual:

```text
ProviderDebtResponse
    plate
    debts[]

Debt
    type
    amount
    dueDate
```

O modelo canônico deve ser independente do formato externo.

Por exemplo, tanto:

```json
{
    "type": "IPVA",
    "amount": 1500.00,
    "due_date": "2024-01-10"
}
```

quanto:

```xml
<debt>
    <category>IPVA</category>
    <value>1500.00</value>
    <expiration>2024-01-10</expiration>
</debt>
```

devem resultar no mesmo objeto interno:

```text
Debt
├── type = IPVA
├── amount = 1500.00
└── dueDate = 2024-01-10
```

Não mantenha campos como:

```text
category
value
expiration
```

no modelo canônico.

Esses nomes pertencem ao contrato externo do SOAP.

---

# 3. Tipagem

Utilize tipos fortes onde fizer sentido.

Por exemplo, considere:

```php
enum DebtType: string
{
    case IPVA = 'IPVA';
    case MULTA = 'MULTA';
}
```

Porém, atenção:

Nesta etapa NÃO queremos aplicar ainda a regra de negócio que diz que tipos desconhecidos devem gerar HTTP 422.

A responsabilidade desta etapa é apenas normalizar os dados.

A validação de tipos permitidos será tratada posteriormente na camada de domínio.

Se considerar melhor manter `DebtType` fora desta etapa para não antecipar regra de negócio, explique a decisão.

---

# 4. Valores monetários

Não utilize `float` internamente para representar dinheiro.

Escolha uma abordagem segura.

Pode utilizar:

* string decimal;
* Value Object `Money`;
* ou outra abordagem tecnicamente justificável.

Como teremos cálculos monetários posteriormente, a solução deve evitar erros de precisão de ponto flutuante.

Se criar um `Money` agora, mantenha-o simples.

Não implemente ainda juros ou pagamento.

---

# 5. Datas

Utilize objetos de data apropriados, preferencialmente:

```php
CarbonImmutable
```

ou uma abstração equivalente.

As datas devem representar apenas a data do vencimento neste momento.

Não precisamos implementar ainda cálculo de dias de atraso.

---

# 6. REST Adapter

Implemente:

```text
RestVehicleDebtProvider
```

Ele deverá:

1. receber uma placa;
2. fazer HTTP para:

```text
GET {PROVIDER_REST_URL}/api/v1/vehicles/{plate}/debts
```

3. validar minimamente a resposta;
4. converter JSON para o modelo canônico;
5. retornar `ProviderDebtResponse`.

Não deixe JSON atravessar a camada de aplicação.

---

# 7. SOAP Adapter

Implemente:

```text
SoapVehicleDebtProvider
```

Ele deverá:

1. receber uma placa;
2. enviar uma requisição para:

```text
POST {PROVIDER_SOAP_URL}/soap
```

3. enviar a placa no formato esperado pelo mock;
4. interpretar XML;
5. converter XML para o modelo canônico;
6. retornar `ProviderDebtResponse`.

O restante da aplicação não deve saber que esse provider utiliza XML.

---

# 8. Cliente HTTP

Utilize o HTTP Client nativo do Laravel:

```php
Illuminate\Support\Facades\Http
```

para REST.

Para SOAP, pode utilizar uma solução apropriada para o protocolo SOAP/XML.

Não adicione uma biblioteca pesada sem necessidade.

Se utilizar uma biblioteca externa para SOAP, explique:

* por que foi necessária;
* qual problema resolve;
* qual o trade-off.

---

# 9. Tratamento de erros

Nesta etapa, não implemente retry nem fallback.

Porém, os adapters precisam diferenciar pelo menos:

```text
Provider unavailable
Invalid provider response
Provider returned unexpected status
```

Crie exceções próprias se isso ajudar.

Por exemplo:

```text
ProviderException
ProviderUnavailableException
InvalidProviderResponseException
```

Não transforme esses erros diretamente em HTTP 503 ainda.

A camada HTTP será responsável por isso posteriormente.

---

# 10. Configuração

Utilize as configurações existentes:

```env
PROVIDER_REST_URL=http://provider-rest:8000
PROVIDER_SOAP_URL=http://provider-soap:8000
```

Não coloque URLs diretamente no código.

O monólito deve acessar:

```text
provider-rest
provider-soap
```

através da configuração.

---

# 11. Endpoint temporário de integração

Para conseguirmos testar a integração antes de implementar o domínio completo, crie temporariamente:

```http
POST /api/v1/vehicles/debts
```

Request:

```json
{
    "placa": "ABC1234"
}
```

Nesta etapa, o endpoint pode receber um provider explicitamente:

```json
{
    "placa": "ABC1234",
    "provider": "rest"
}
```

ou:

```json
{
    "placa": "ABC1234",
    "provider": "soap"
}
```

Isso é apenas para testar os dois adapters individualmente.

Não implemente ainda fallback.

Se preferir não expor o provider na API, crie uma rota interna/temporária para testes e explique a decisão.

---

# 12. Resposta temporária

Para `ABC1234`, a resposta deve permitir verificar que os dois providers foram normalizados para a mesma estrutura.

Por exemplo:

```json
{
    "placa": "ABC1234",
    "debitos": [
        {
            "tipo": "IPVA",
            "valor": "1500.00",
            "vencimento": "2024-01-10"
        },
        {
            "tipo": "MULTA",
            "valor": "300.50",
            "vencimento": "2024-02-15"
        }
    ]
}
```

A estrutura pode ser ajustada se houver uma decisão arquitetural melhor, mas REST e SOAP devem produzir a mesma resposta.

---

# 13. Testes unitários

Crie testes para:

### REST Adapter

* converter resposta JSON corretamente;
* converter múltiplos débitos;
* converter zero débitos;
* tratar HTTP 500;
* tratar resposta inválida.

### SOAP Adapter

* converter XML corretamente;
* converter múltiplos débitos;
* converter `<debts/>` para lista vazia;
* tratar HTTP 500;
* tratar XML inválido.

### Canonical Model

Teste que:

```text
REST response
```

e:

```text
SOAP response
```

produzem objetos canônicos equivalentes.

Esse teste é importante.

---

# 14. Teste de integração

Crie pelo menos um teste de integração que valide:

```text
Monolith
    ↓
REST Provider
    ↓
JSON
    ↓
REST Adapter
    ↓
Canonical Model
```

Se for viável no ambiente atual, faça também para SOAP.

Caso testes de integração entre containers sejam excessivamente complexos nesta etapa, não crie uma solução artificial. Explique a limitação e mantenha testes de unidade fortes.

---

# 15. Arquitetura

Organize o código de maneira que fique clara a separação entre:

```text
HTTP
Application
Domain
Infrastructure
```

Uma estrutura possível:

```text
app/
├── Application/
│   └── VehicleDebt/
│
├── Domain/
│   └── Debt/
│
├── Infrastructure/
│   └── Providers/
│       ├── Rest/
│       └── Soap/
│
└── Http/
    └── Controllers/
```

Não siga essa estrutura cegamente.

Se a estrutura Laravel padrão for suficiente ou houver uma alternativa melhor, escolha uma abordagem simples e explique.

Evite criar abstrações apenas para "parecer arquitetura".

---

# 16. Princípio arquitetural

Quero que seja possível substituir:

```text
REST
```

por:

```text
SOAP
```

sem alterar a lógica de negócio.

Ou adicionar posteriormente:

```text
Provider C
```

sem modificar o modelo canônico.

A dependência deve apontar para abstrações:

```text
Application
     ↓
VehicleDebtProvider
     ↑
     │
 ┌───┴────┐
 │        │
REST     SOAP
```

---

# 17. O que NÃO implementar

Ainda não implemente:

* retry;
* fallback;
* circuit breaker;
* juros;
* cálculo de atraso;
* pagamento;
* PIX;
* cartão;
* validação Mercosul/placa antiga;
* HTTP 422;
* HTTP 503;
* regras de divergência entre providers.

Tudo isso será implementado posteriormente.

---

# 18. Critério de conclusão

A fase estará concluída quando:

1. O monólito conseguir consultar o provider REST.
2. O monólito conseguir consultar o provider SOAP.
3. JSON e XML forem convertidos para o mesmo modelo canônico.
4. O código da aplicação não depender diretamente do formato REST ou SOAP.
5. Zero débitos funcionar corretamente.
6. Erros de provider forem representados por exceções/erros apropriados.
7. Existirem testes unitários dos adapters.
8. Existir pelo menos um teste de integração ou uma justificativa técnica caso não seja viável.
9. Não existir retry/fallback ainda.

---

# 19. Processo de implementação

Antes de modificar arquivos:

1. Analise o código atual.
2. Identifique como a infraestrutura existente está organizada.
3. Liste os arquivos que pretende criar/alterar.
4. Explique as principais decisões arquiteturais.
5. Implemente incrementalmente.
6. Execute os testes.
7. Corrija eventuais problemas.
8. Ao final, apresente um resumo da arquitetura implementada.

Não avance para a próxima fase.

A próxima fase será dedicada exclusivamente a **resiliência: retry + fallback entre providers**.

---

## 20. Implementação Realizada e Decisões Técnicas

Esta seção registra as decisões arquiteturais tomadas e a validação da Fase 3.

### 20.1. Decisões Técnicas e Racional de Engenharia

1. **Modelo Canônico Puro e Imutável (`App\Domain\Debt`)**:
   - `Debt`, `Money` e `ProviderDebtResponse` foram implementados como classes `readonly`.
   - Nomes de campos específicos de fornecedores (como `category`, `value`, `expiration` do SOAP) foram estritamente isolados na camada de infraestrutura dos providers. O domínio enxerga unicamente `type`, `amount` e `dueDate`.

2. **Segurança Monetária com Value Object `Money`**:
   - **Decisão**: Criação do Value Object `Money`, armazenando o valor monetário internamente em centavos inteiros (`int $amountInCents`).
   - **Racional**: Elimina imprecisões inerentes a números de ponto flutuante (`float`) em PHP, garantindo precisão absoluta para futuras regras de cálculo de juros, multas e pagamentos. Expõe `toDecimal()` formatado para 2 casas decimais.

3. **Imutabilidade Temporal com `CarbonImmutable`**:
   - A data de vencimento (`dueDate`) é convertida e armazenada como instância de `CarbonImmutable`, assegurando que instâncias de `Debt` não sofram efeitos colaterais de mutação acidental.

4. **Decisão sobre `DebtType`**:
   - **Decisão**: O tipo de débito foi mantido como `string` nesta fase.
   - **Racional**: A especificação explicitamente orientou a não antecipar regras de negócio ou rejeições prematuras com HTTP 422 para tipos desconhecidos. A normalização apenas preserva o valor textual fornecido pelo provedor para que a camada de domínio posterior avalie a conformidade.

5. **Simplicidade do Adapter SOAP com `SimpleXMLElement`**:
   - **Decisão**: Em vez de instalar pacotes pesados de SOAP (como extensões PHP SOAP complexas ou bibliotecas WSDL de terceiros), o `SoapVehicleDebtProvider` utiliza o cliente HTTP nativo do Laravel (`Illuminate\Support\Facades\Http`) para enviar o XML e `simplexml_load_string` para interpretar o retorno.
   - **Racional**: O mock do provedor SOAP opera com envelopes XML simplificados. A abordagem nativa tem custo zero de dependências adicionais, alto desempenho e atende perfeitamente ao contrato, incluindo o suporte a coleções vazias `<debts/>`.

6. **Hierarquia Semântica de Exceções**:
   - `ProviderException`: base para erros de provedor.
   - `ProviderUnavailableException`: lançada em timeouts, falhas de conexão de rede ou HTTP 5xx.
   - `InvalidProviderResponseException`: lançada quando o payload (JSON ou XML) está quebrado, vazio ou não contém os campos mandatórios.

7. **Endpoint Temporário de Integração (`POST /api/v1/vehicles/debts`)**:
   - Permite consultar débitos de uma placa informando opcionalmente `"provider": "rest"` ou `"provider": "soap"`.
   - Facilita testar e comprovar que ambos os provedores produzem exatamente a mesma resposta canônica formatada.

### 20.2. Validação dos Testes Automatizados

Execução da suíte completa no monólito via Docker:
```bash
docker compose exec monolith php artisan test
```
```text
   PASS  Tests\Unit\Domain\Debt\CanonicalModelEquivalenceTest
  ✓ rest and soap providers produce equivalent canonical models

   PASS  Tests\Unit\Domain\Debt\MoneyTest
  ✓ creates money from decimal string and formats properly
  ✓ creates money from float and int
  ✓ rejects invalid decimal strings
  ✓ money equality

   PASS  Tests\Unit\Infrastructure\Providers\RestVehicleDebtProviderTest
  ✓ converts valid json response with multiple debts
  ✓ converts valid json response with zero debts
  ✓ throws provider unavailable exception on http 500
  ✓ throws provider unavailable exception on connection timeout
  ✓ throws invalid provider response exception on malformed payload

   PASS  Tests\Unit\Infrastructure\Providers\SoapVehicleDebtProviderTest
  ✓ converts valid xml response with multiple debts
  ✓ converts self closing debts tag to empty collection
  ✓ throws provider unavailable exception on http 500
  ✓ throws provider unavailable exception on connection timeout
  ✓ throws invalid provider response exception on malformed xml

   PASS  Tests\Feature\VehicleDebtIntegrationTest
  ✓ endpoint returns debts from rest provider
  ✓ endpoint returns debts from soap provider
  ✓ endpoint returns empty debts for vehicle without debts
  ✓ endpoint returns bad request when plate is missing

  Tests:    21 passed (50 assertions)
  Duration: 2.30s
```

### 20.3. Evidências de Validação Manual (Comunicação Real entre Containers)

```bash
# 1. Monolith -> Provider REST (ABC1234)
curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa":"ABC1234","provider":"rest"}'
# Resposta:
# {"placa":"ABC1234","debitos":[{"tipo":"IPVA","valor":"1500.00","vencimento":"2024-01-10"},{"tipo":"MULTA","valor":"300.50","vencimento":"2024-02-15"}]}

# 2. Monolith -> Provider SOAP (ABC1234)
curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa":"ABC1234","provider":"soap"}'
# Resposta:
# {"placa":"ABC1234","debitos":[{"tipo":"IPVA","valor":"1500.00","vencimento":"2024-01-10"},{"tipo":"MULTA","valor":"300.50","vencimento":"2024-02-15"}]}

# 3. Monolith -> Provider REST (DEF5678 - Sem Débitos)
curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa":"DEF5678","provider":"rest"}'
# Resposta:
# {"placa":"DEF5678","debitos":[]}

# 4. Monolith -> Provider SOAP (DEF5678 - Sem Débitos)
curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa":"DEF5678","provider":"soap"}'
# Resposta:
# {"placa":"DEF5678","debitos":[]}
```
