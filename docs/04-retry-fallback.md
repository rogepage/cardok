Você é meu pair programmer no projeto `cardok`.

As fases anteriores já foram implementadas:

* infraestrutura Docker;
* provider REST;
* provider SOAP;
* mocks de sucesso, erro, timeout e resposta inválida;
* interface `VehicleDebtProvider`;
* adapters REST e SOAP;
* modelo canônico de débitos.

Agora vamos implementar a **Fase 4 — Resiliência: Retry + Fallback entre providers**.

## Objetivo

O monólito deve conseguir consultar os providers configurados em ordem e lidar com falhas de forma resiliente.

A estratégia desejada é:

```text
Request
   ↓
Provider REST
   ↓
falha
   ↓
Retry
   ↓
falha
   ↓
Provider SOAP
   ↓
sucesso
   ↓
Resposta
```

Se todos os providers falharem:

```text
REST
 ↓
retry
 ↓
falha
 ↓
SOAP
 ↓
retry
 ↓
falha
 ↓
HTTP 503
```

---

# 1. Responsabilidade

O retry/fallback deve ficar na camada de aplicação/orquestração.

Os adapters individuais:

```text
RestVehicleDebtProvider
SoapVehicleDebtProvider
```

continuam responsáveis apenas por conversar com seus respectivos providers.

Eles NÃO devem decidir qual será o próximo provider.

A decisão deve ficar em um componente separado.

Uma estrutura possível:

```text
Application/
└── VehicleDebt/
    ├── VehicleDebtService.php
    └── ProviderResolver.php
```

ou outra estrutura equivalente que mantenha as responsabilidades separadas.

Não siga o nome cegamente; escolha uma nomenclatura clara.

---

# 2. Ordem dos providers

A ordem deve vir da configuração:

```env
PROVIDER_ORDER=rest,soap
```

Isso significa:

```text
1. REST
2. SOAP
```

Se estiver:

```env
PROVIDER_ORDER=soap,rest
```

a ordem deverá ser:

```text
1. SOAP
2. REST
```

Não codifique a ordem diretamente na classe.

---

# 3. Retry

Utilize a configuração existente:

```env
PROVIDER_RETRIES=2
```

Interprete isso como:

```text
2 tentativas adicionais após a primeira tentativa
```

Portanto:

```text
tentativa inicial
retry 1
retry 2
```

Total:

```text
3 chamadas
```

Documente claramente essa decisão.

---

# 4. Timeout

Utilize:

```env
PROVIDER_TIMEOUT=2
```

O timeout deve ser aplicado individualmente a cada chamada do provider.

Exemplo:

```text
REST
timeout = 2s
 ↓
retry
timeout = 2s
 ↓
retry
timeout = 2s
 ↓
SOAP
```

Não deixe uma chamada bloqueada indefinidamente.

---

# 5. Backoff

Implemente um backoff simples entre retries.

Pode utilizar, por exemplo:

```text
retry 1 → 100ms
retry 2 → 200ms
```

Ou uma estratégia equivalente.

Não precisamos de um algoritmo extremamente sofisticado nesta etapa.

O objetivo é demonstrar que não estamos fazendo chamadas imediatamente em sequência.

Documente a estratégia escolhida.

---

# 6. Quais erros devem gerar retry?

Considere falhas de infraestrutura como elegíveis para retry:

* timeout;
* conexão recusada;
* HTTP 500;
* HTTP 502;
* HTTP 503;
* HTTP 504.

Não faça retry indiscriminadamente em qualquer erro.

Por exemplo, uma resposta estruturalmente inválida pode ser tratada como erro do provider, mas não deve necessariamente gerar múltiplas tentativas se não houver benefício claro.

Escolha uma política coerente e documente.

---

# 7. Fallback

Depois que um provider esgotar suas tentativas, o sistema deve tentar o próximo provider.

Exemplo:

```text
PROVIDER_ORDER=rest,soap

REST
 ├── tentativa 1 → timeout
 ├── retry 1     → timeout
 └── retry 2     → timeout

SOAP
 └── tentativa 1 → sucesso
```

Resultado:

```text
HTTP 200
```

com os débitos retornados pelo SOAP.

---

# 8. Todos os providers indisponíveis

Se todos os providers falharem, retornar:

HTTP:

```text
503 Service Unavailable
```

Payload:

```json
{
    "error": "all_providers_unavailable"
}
```

Não exponha stack trace ou detalhes internos na resposta HTTP.

Os detalhes devem aparecer apenas nos logs.

---

# 9. Erros de negócio

Nesta etapa ainda NÃO implemente:

```text
unknown_debt_type
```

Isso será tratado posteriormente pelo domínio.

O objetivo desta fase é apenas resiliência de integração.

---

# 10. Exceções

Utilize exceções específicas quando fizer sentido.

Por exemplo:

```text
ProviderException
ProviderUnavailableException
InvalidProviderResponseException
```

Não crie dezenas de exceções sem necessidade.

O importante é conseguir distinguir:

```text
Provider falhou
```

de:

```text
Regra de negócio falhou
```

---

# 11. Logs estruturados

Adicione logs estruturados para permitir observar o comportamento do fallback.

Quando um provider falhar, registre informações como:

```text
provider
attempt
error
duration
```

Exemplo conceitual:

```json
{
    "event": "vehicle_provider_failed",
    "provider": "rest",
    "attempt": 2,
    "error": "timeout",
    "duration_ms": 2003
}
```

Não registre a placa completa.

Utilize uma versão mascarada.

Exemplo:

```text
ABC**** 
```

ou outra estratégia consistente.

A ideia é evitar exposição desnecessária de dados potencialmente identificáveis.

---

# 12. Teste do fallback

Crie testes automatizados para os cenários abaixo.

## Cenário 1 — REST funciona

```text
REST → sucesso
```

Resultado:

```text
HTTP 200
```

SOAP não deve ser chamado.

---

## Cenário 2 — REST falha e SOAP funciona

```text
REST → erro
REST → retry
REST → retry
SOAP → sucesso
```

Resultado:

```text
HTTP 200
```

Verifique através dos mocks/spies que o SOAP foi chamado somente depois de esgotar as tentativas do REST.

---

## Cenário 3 — REST timeout e SOAP funciona

```text
REST → timeout
REST → retry
REST → retry
SOAP → sucesso
```

Resultado:

```text
HTTP 200
```

---

## Cenário 4 — REST e SOAP falham

```text
REST → falha
REST → retry
REST → retry

SOAP → falha
SOAP → retry
SOAP → retry
```

Resultado:

```text
HTTP 503
```

Payload:

```json
{
    "error": "all_providers_unavailable"
}
```

---

# 13. Não fazer retry desnecessário

Teste também um cenário de resposta que não deve ser repetida desnecessariamente.

Por exemplo, uma resposta estruturalmente inválida.

Defina e documente claramente se:

```text
invalid_response
```

será:

* imediatamente considerada falha e seguirá para o próximo provider;
* ou será submetida a retry.

Minha preferência inicial é:

```text
timeout / 5xx → retry
resposta inválida → não retry, fallback imediato
```

Porque repetir uma resposta inválida provavelmente não corrigirá o problema.

Se escolher outra estratégia, justifique.

---

# 14. Separar Retry de Fallback

Não implemente tudo em uma única classe gigante.

Quero conseguir identificar conceitualmente:

```text
Retry Policy
```

e:

```text
Provider Fallback
```

Por exemplo:

```text
ProviderExecutor
    ↓
executa provider com retry

ProviderResolver
    ↓
decide qual provider tentar em seguida
```

A nomenclatura pode ser diferente.

O importante é manter as responsabilidades separadas.

---

# 15. Configuração

Mantenha:

```env
PROVIDER_ORDER=rest,soap
PROVIDER_TIMEOUT=2
PROVIDER_RETRIES=2
```

Se precisar adicionar:

```env
PROVIDER_BACKOFF_MS=100
```

pode fazer.

Documente todas as configurações no README.

---

# 16. Não utilizar bibliotecas desnecessárias

Antes de adicionar qualquer biblioteca de retry/circuit breaker:

avalie se o Laravel/PHP já fornece recursos suficientes para implementar a solução.

Para este Home Test, prefiro uma implementação simples e explícita.

Não adicione circuit breaker ainda.

Circuit breaker será uma possível melhoria futura.

---

# 17. Testes

Utilize os recursos de teste do Laravel para simular:

* timeout;
* HTTP 500;
* HTTP 503;
* sucesso;
* resposta inválida.

Não dependa de serviços externos reais para os testes unitários.

Para testes de integração, utilize os mocks existentes quando apropriado.

---

# 18. Métricas que queremos conseguir demonstrar

Durante a apresentação, quero conseguir mostrar algo semelhante a:

```text
Request
  ↓
REST
  ↓
500
  ↓
Retry #1
  ↓
500
  ↓
Retry #2
  ↓
500
  ↓
SOAP
  ↓
200
  ↓
Resposta final
```

Portanto, deixe logs suficientemente claros para tornar esse fluxo observável.

---

# 19. Endpoint

Mantenha o endpoint principal:

```http
POST /api/v1/vehicles/debts
```

Request:

```json
{
    "placa": "ABC1234"
}
```

A partir desta fase, não deve mais ser necessário informar manualmente qual provider usar.

O sistema deve usar:

```env
PROVIDER_ORDER
```

para decidir.

---

# 20. Resposta de sucesso

Continue retornando o modelo canônico criado na fase anterior.

Não implemente ainda:

* juros;
* valor atualizado;
* resumo;
* PIX;
* cartão.

---

# 21. Critério de conclusão

A fase estará concluída quando:

1. O provider configurado em primeiro lugar seja utilizado primeiro.
2. Retry funcione conforme `PROVIDER_RETRIES`.
3. Timeout seja respeitado.
4. Backoff seja aplicado.
5. Após esgotar retries, o próximo provider seja acionado.
6. Se qualquer provider funcionar, a requisição possa ser concluída com sucesso.
7. Se todos falharem, seja retornado HTTP 503.
8. O payload de indisponibilidade seja exatamente:

```json
{
    "error": "all_providers_unavailable"
}
```

9. Os logs permitam acompanhar retry/fallback.
10. A placa não seja registrada em texto completo nos logs.
11. Existam testes automatizados cobrindo os cenários principais.
12. O código mantenha separadas as responsabilidades de retry e fallback.

---

# 22. Processo de implementação

Antes de modificar qualquer arquivo:

1. Analise o estado atual do `cardok`.
2. Identifique como os adapters e a interface `VehicleDebtProvider` foram implementados.
3. Liste os arquivos que serão alterados/criados.
4. Explique a arquitetura proposta.
5. Implemente.
6. Execute os testes.
7. Corrija eventuais problemas.
8. Mostre os resultados.

Não implemente ainda:

* juros;
* pagamento;
* validação de placa;
* `unknown_debt_type`;
* circuit breaker;
* RabbitMQ.

A próxima etapa será a implementação das regras de domínio e cálculo de juros.


IMPORTANTE SOBRE CONSISTÊNCIA

O fallback existe exclusivamente para disponibilidade.

Se o REST responder com sucesso, o SOAP NÃO deve ser consultado
apenas para comparar os dados.

Se o REST falhar e o SOAP assumir o atendimento, os dados retornados
pelo SOAP serão considerados a resposta da operação.

Não implemente nesta fase:
- consulta paralela aos providers;
- comparação de respostas;
- conciliação;
- escolha do resultado com base em quantidade de débitos;
- merge de débitos de diferentes providers.

A divergência entre providers deve ser documentada como uma
decisão arquitetural e como possível evolução futura.

---

## 23. Implementação Realizada e Decisões Técnicas

Esta seção registra as decisões arquiteturais tomadas e a validação da Fase 4.

### 23.1. Decisões Técnicas e Racional de Engenharia

1. **Separação Rígida entre Retry e Fallback (`App\Application\VehicleDebt`)**:
   - `ProviderResolver`: responsável por mapear identificadores (`'rest'`, `'soap'`) para instâncias de `VehicleDebtProvider` e obter a ordem configurada via `PROVIDER_ORDER`.
   - `ProviderExecutor`: responsável pela política de execução resiliente de um único provedor: contagem de tentativas (`PROVIDER_RETRIES`), cálculo de backoff linear (`PROVIDER_BACKOFF_MS`), logging estruturado e filtro de exceções retentáveis.
   - `VehicleDebtService`: responsável por orquestrar a cadeia sequencial de fallback percorrendo os provedores configurados.
   - **Racional**: Os adapters (`RestVehicleDebtProvider`, `SoapVehicleDebtProvider`) continuam agnósticos a decisões de orquestração externa, preservando o Single Responsibility Principle (SRP).

2. **Interpretação da Política de Retry (`PROVIDER_RETRIES=2`)**:
   - Configurado como 2 retentativas após a tentativa inicial, totalizando no máximo 3 chamadas por provedor antes de declarar falha e acionar o fallback.

3. **Política de Seleção de Erros Elegíveis para Retry**:
   - **Com Retry**: falhas de infraestrutura (`ProviderUnavailableException`), incluindo timeouts de conexão/leitura e respostas HTTP 5xx (`500`, `502`, `503`, `504`).
   - **Sem Retry (Fallback Imediato)**: respostas com payload quebrado ou fora do contrato (`InvalidProviderResponseException`). Repetir imediatamente uma resposta sintática ou estruturalmente inválida não resolveria a falha e apenas consumiria tempo e quota da aplicação.

4. **Backoff Linear**:
   - Entre as tentativas, o sistema aplica um intervalo progressivo:
     - Retry 1: 1 * 100ms = 100ms
     - Retry 2: 2 * 100ms = 200ms
   - Na implementação, o executor aceita uma função de sono injetável (`?callable $sleeper`), permitindo que testes automatizados executem instantaneamente sem esperas reais de clock.

5. **Tratamento de Indisponibilidade Total (HTTP 503)**:
   - Se todos os provedores da ordem configurada falharem, o `VehicleDebtService` lança `AllProvidersUnavailableException`. O `VehicleDebtIntegrationController` captura a exceção e retorna HTTP 503 com o payload exato:
     ```json
     {
         "error": "all_providers_unavailable"
     }
     ```
   - Nenhum stack trace ou mensagem interna é vazado na resposta HTTP.

6. **Observabilidade e Mascaramento de Placa**:
   - Cada falha, retentativa e evento de fallback é registrado com contexto em JSON estruturado nos logs da aplicação (`laravel.log`).
   - A placa veicular é mascarada por segurança e privacidade (ex: `ABC****`).

7. **Consistência e Fallback Baseado em Disponibilidade**:
   - O fallback existe estritamente para manter o serviço disponível. Se o provedor primário responder com sucesso, o secundário não é chamado. Se o primário falhar e o secundário responder, os dados do secundário são aceitos integralmente. Não há conciliação paralela ou mesclagem nesta etapa.

### 23.2. Validação dos Testes Automatizados

Execução da suíte completa de testes no monólito:
```bash
docker compose exec monolith php artisan test
```
```text
   PASS  Tests\Unit\Application\VehicleDebt\ProviderExecutorTest
  ✓ succeeds on first attempt without retries                            0.41s  
  ✓ retries on provider unavailable and succeeds                         0.08s  
  ✓ exhausts retries and throws provider unavailable exception           0.05s  
  ✓ does not retry on invalid provider response                          0.04s  
  ✓ mask plate masks trailing characters                                 0.03s  

   PASS  Tests\Unit\Application\VehicleDebt\ProviderResolverTest
  ✓ resolves rest and soap providers                                     0.15s  
  ✓ throws exception on unknown provider                                 0.06s  
  ✓ returns configured order                                             0.06s  

   PASS  Tests\Unit\Application\VehicleDebt\VehicleDebtServiceTest
  ✓ scenario 1 rest succeeds soap is never called                        0.12s  
  ✓ scenario 2 rest fails with 500 retries and falls back to soap        0.04s  
  ✓ scenario 3 rest times out and falls back to soap                     0.03s  
  ✓ scenario 4 both rest and soap fail throws all providers unavailable  0.04s  
  ✓ respects custom configured order soap first                          0.03s  
  ✓ rest invalid response triggers immediate fallback without retry      0.03s  

   PASS  Tests\Unit\Domain\Debt\CanonicalModelEquivalenceTest
  ✓ rest and soap providers produce equivalent canonical models          0.13s  

   PASS  Tests\Unit\Domain\Debt\MoneyTest
  ✓ creates money from decimal string and formats properly
  ✓ creates money from float and int
  ✓ rejects invalid decimal strings
  ✓ money equality

   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Unit\Infrastructure\Providers\RestVehicleDebtProviderTest
  ✓ converts valid json response with multiple debts                     0.03s  
  ✓ converts valid json response with zero debts                         0.03s  
  ✓ throws provider unavailable exception on http 500                    0.03s  
  ✓ throws provider unavailable exception on connection timeout          0.07s  
  ✓ throws invalid provider response exception on malformed payload      0.03s  

   PASS  Tests\Unit\Infrastructure\Providers\SoapVehicleDebtProviderTest
  ✓ converts valid xml response with multiple debts                      0.05s  
  ✓ converts self closing debts tag to empty collection                  0.04s  
  ✓ throws provider unavailable exception on http 500                    0.03s  
  ✓ throws provider unavailable exception on connection timeout          0.03s  
  ✓ throws invalid provider response exception on malformed xml          0.07s  

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                        0.21s  

   PASS  Tests\Feature\VehicleDebtIntegrationTest
  ✓ endpoint returns debts from rest provider                            0.05s  
  ✓ endpoint returns debts from soap provider                            0.03s  
  ✓ endpoint automatically uses configured order and falls back to soap… 0.04s  
  ✓ endpoint returns 503 when all providers fail                         0.04s  
  ✓ endpoint returns empty debts for vehicle without debts               0.03s  
  ✓ endpoint returns bad request when plate is missing                   0.03s  

  Tests:    37 passed (120 assertions)
  Duration: 3.34s
```

### 23.3. Evidências de Validação Manual

```bash
# Requisição utilizando a ordem padrão configurada (PROVIDER_ORDER=rest,soap)
curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa":"ABC1234"}'

# Resposta HTTP 200:
# {"placa":"ABC1234","debitos":[{"tipo":"IPVA","valor":"1500.00","vencimento":"2024-01-10"},{"tipo":"MULTA","valor":"300.50","vencimento":"2024-02-15"}]}
```
