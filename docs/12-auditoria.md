Você é meu principal revisor técnico no projeto `cardok`.

Nesta fase NÃO quero que você implemente novas funcionalidades imediatamente.

Quero realizar uma **AUDITORIA COMPLETA** do projeto atual contra o Home Test original de Backend Engineer.

O objetivo é descobrir:

* o que está correto;
* o que está parcialmente correto;
* o que está incorreto;
* o que está faltando;
* o que foi implementado além do necessário;
* inconsistências arquiteturais;
* riscos técnicos;
* pontos que podem ser questionados pelo avaliador;
* melhorias que realmente valem a pena.

## REGRA PRINCIPAL

### NÃO ALTERE O CÓDIGO NESTA FASE.

Primeiro faça somente a auditoria e apresente o relatório.

Depois de apresentar o relatório, aguardaremos minha decisão sobre quais correções implementar.

Não faça refatorações automáticas.

Não crie novos arquivos.

Não altere testes.

Não altere Docker.

Não altere documentação.

Não "melhore" código por iniciativa própria.

---

# 1. Contexto do Home Test

O projeto deve implementar um serviço de consulta e simulação de pagamento de débitos veiculares.

Entrada:

```json
{
  "placa": "ABC1234"
}
```

O sistema deve:

* consultar múltiplos provedores;
* normalizar os dados;
* calcular juros;
* simular PIX;
* simular cartão;
* permitir pagamento total ou parcial por tipo;
* suportar novos providers;
* suportar novos tipos de débito;
* possuir fallback;
* ser resiliente a falhas.

---

# 2. Providers

Existem dois providers simulados.

## Provider A

REST/JSON:

```json
{
  "vehicle": "ABC1234",
  "debts": [
    {
      "type": "IPVA",
      "amount": 1500.00,
      "due_date": "2024-01-10"
    },
    {
      "type": "MULTA",
      "amount": 300.50,
      "due_date": "2024-02-15"
    }
  ]
}
```

## Provider B

SOAP/XML:

```xml
<response>
  <plate>ABC1234</plate>
  <debts>
    <debt>
      <category>IPVA</category>
      <value>1500.00</value>
      <expiration>2024-01-10</expiration>
    </debt>
    <debt>
      <category>MULTA</category>
      <value>300.50</value>
      <expiration>2024-02-15</expiration>
    </debt>
  </debts>
</response>
```

Quando não houver débitos:

```xml
<debts/>
```

deve ser interpretado como lista vazia.

---

# 3. Data de referência

A data atual para o teste é FIXA:

```text
2024-05-10T00:00:00Z
```

Todas as comparações devem usar UTC.

Verificar se existe algum lugar no código usando:

```text
now()
Carbon::now()
```

ou equivalente sem respeitar a política de data fixa.

---

# 4. IPVA

Taxa:

```text
0,33% ao dia
```

Teto:

```text
20% do valor original
```

Fórmula:

```text
juros =
min(
    valor × 0,0033 × dias_atraso,
    valor × 0,20
)
```

Depois:

```text
valor_atualizado =
valor_original + juros
```

Arredondamento:

```text
HALF_UP
2 casas
```

Exemplo:

```text
R$ 1.500,00
121 dias

juros calculado = 598,95
teto = 300,00

juros = 300,00
total = 1.800,00
```

---

# 5. MULTA

Taxa:

```text
1% ao dia
```

Sem teto.

Exemplo:

```text
300,50 × 0,01 × 85
=
255,425
```

HALF_UP:

```text
255,43
```

Total:

```text
555,93
```

Verifique cuidadosamente se o sistema produz esses valores.

---

# 6. Débitos não vencidos

Quando:

```text
dias_atraso <= 0
```

deve resultar em:

```text
juros = 0
valor_atualizado = valor_original
```

Testar também débito vencendo exatamente na data de referência.

---

# 7. Tipos desconhecidos

Qualquer tipo diferente de:

```text
IPVA
MULTA
```

deve gerar:

```http
422
```

com:

```json
{
  "error": "unknown_debt_type",
  "type": "<TIPO>"
}
```

Não converter para OUTROS.

Não ignorar.

Verificar também o comportamento quando:

* existe um débito conhecido e um desconhecido;
* todos os débitos são desconhecidos;
* existem vários tipos desconhecidos.

Comparar o comportamento atual com o contrato esperado.

---

# 8. Política de arredondamento

A política global é:

```text
HALF_UP
2 casas decimais
```

Valores monetários na API devem ser strings:

```json
"1500.00"
```

e nunca:

```json
1500.00
```

Auditar TODOS os pontos onde dinheiro é calculado.

Especialmente:

* juros;
* valor atualizado;
* total;
* PIX;
* Price;
* parcelas;
* somatórios.

Verificar se existe uso de `float` em alguma parte crítica.

Se existir, NÃO corrija agora.

Apenas reporte:

* onde;
* por que é um risco;
* impacto;
* possível solução.

---

# 9. PIX

Desconto:

```text
5%
```

Aplicado individualmente ao:

```text
TOTAL
SOMENTE_<TIPO>
```

Fórmula:

```text
valor_base × 0,95
```

HALF_UP:

```text
2 casas
```

Verificar se o desconto está sendo aplicado corretamente a todas as opções.

---

# 10. Cartão

Somente:

```text
1x
6x
12x
```

1x:

```text
sem juros
```

6x e 12x:

```text
Price
2,5% ao mês
```

Fórmula:

```text
PMT =
base × i × (1+i)^n
/
((1+i)^n - 1)
```

onde:

```text
i = 0,025
```

Verificar:

* valores;
* arredondamento;
* tipos numéricos;
* ausência de parcelas extras;
* comportamento com valores pequenos;
* comportamento com zero.

---

# 11. Pagamento parcial

Devem existir:

```text
TOTAL
SOMENTE_IPVA
SOMENTE_MULTA
```

Se houver:

```text
LICENCIAMENTO
```

no futuro, deve ser:

```text
SOMENTE_LICENCIAMENTO
```

Nunca:

```text
SOMENTE_IPVAS
SOMENTE_MULTAS
```

Mesmo que existam múltiplos débitos do mesmo tipo.

Verificar isso na implementação.

---

# 12. Provedores

Auditar a arquitetura.

Queremos:

```text
Application
    ↓
Domain
    ↓
Ports
    ↓
Infrastructure
    ↓
REST / SOAP
```

Verificar se:

* Domain conhece HTTP;
* Domain conhece Laravel;
* Domain conhece XML;
* Domain conhece JSON;
* Application conhece detalhes de infraestrutura;
* Controller chama diretamente provider;
* Livewire chama diretamente provider.

Qualquer violação deve ser reportada.

---

# 13. Adapter Pattern

Verificar se REST e SOAP estão realmente isolados por adapters.

Queremos algo conceitualmente semelhante a:

```text
VehicleDebtProviderInterface
            │
      ┌─────┴─────┐
      ▼           ▼
 RestAdapter   SoapAdapter
```

Verificar se adicionar um terceiro provider exigiria alterar regras de negócio.

Se exigir, reportar.

---

# 14. Retry

Auditar a implementação de retry.

Verificar:

* número máximo de tentativas;
* quais erros permitem retry;
* timeout;
* backoff;
* possibilidade de retry infinito;
* impacto na latência;
* logs;
* testes.

Diferenciar:

```text
timeout
connection refused
5xx
```

de:

```text
400
422
resposta inválida
```

Avaliar se a política atual faz sentido.

---

# 15. Fallback

Verificar:

```text
REST
 ↓
falha
 ↓
retry
 ↓
falha
 ↓
SOAP
```

e também:

```text
SOAP
 ↓
falha
 ↓
REST
```

caso a ordem configurada permita.

Verificar se:

```text
todos falharam
```

resulta em:

```http
503
```

com:

```json
{
  "error": "all_providers_unavailable"
}
```

---

# 16. IMPORTANTE — divergência entre providers

Auditar especificamente este requisito:

> Provedores podem retornar dados divergentes para a mesma placa.

Verificar se o sistema:

* detecta divergência;
* ignora divergência;
* usa primeiro provider;
* usa provider prioritário;
* reconcilia dados;
* consulta todos simultaneamente.

Não implementar nada.

Apenas documentar exatamente o comportamento atual.

Depois apresentar:

### Estratégia atual

e:

### Estratégias possíveis para evolução

Por exemplo:

```text
prioridade por provider
quorum
reconciliação
fonte oficial
última atualização
```

Não escolher uma estratégia por conta própria.

---

# 17. Validação da placa

Verificar suporte para:

### Padrão antigo

```text
ABC1234
```

### Mercosul

```text
ABC1D23
```

Rejeitar formatos inválidos.

Deve retornar:

```http
400
```

com:

```json
{
  "error": "invalid_plate"
}
```

Testar casos:

```text
ABC1234
ABC1D23
abc1234
ABC 1234
1234567
ABC123
```

e outros casos relevantes.

---

# 18. REST Provider

Verificar:

* contrato JSON;
* status HTTP;
* timeout;
* respostas inválidas;
* lista vazia;
* placa inexistente;
* erro 500;
* conexão recusada.

Verificar se o mock permite simular falhas.

---

# 19. SOAP Provider

Verificar:

* XML;
* `<debts/>`;
* lista vazia;
* XML inválido;
* SOAP fault;
* timeout;
* erro HTTP.

Verificar se o parser realmente trata:

```xml
<debts/>
```

como:

```text
[]
```

---

# 20. Payment Provider

Auditar o `payment-provider`.

Verificar que ele:

* existe no Docker;
* possui health check;
* não é necessário para a consulta;
* não é necessário para a simulação;
* não foi acoplado desnecessariamente ao fluxo atual.

O pagamento real está fora do escopo.

Verificar se isso está documentado.

---

# 21. Livewire

Verificar se Livewire:

* não possui regra de juros;
* não possui cálculo de PIX;
* não possui cálculo de cartão;
* não conhece REST;
* não conhece SOAP;
* utiliza Application Layer;
* trata erros;
* possui loading;
* possui testes.

---

# 22. Observabilidade

Auditar:

### Request ID

Verificar:

```text
X-Request-ID
```

entrada e saída.

### Logs

Verificar eventos:

```text
request.received
provider.request
provider.response
provider.retry
provider.fallback
vehicle_debt.completed
vehicle_debt.failed
```

### Segurança

Verificar se a placa é mascarada.

Verificar se NÃO aparecem:

* tokens;
* passwords;
* Authorization;
* secrets;
* dados financeiros desnecessários.

### Latência

Verificar se existe:

```text
duration_ms
```

para providers e requisição total.

---

# 23. Health Checks

Verificar diferença entre:

```text
/api/health
```

e:

```text
/api/health/integrations
```

Verificar se o health check de uma dependência indisponível está sendo confundido com a própria aplicação indisponível.

---

# 24. Docker

Auditar:

```text
monolith
provider-rest
provider-soap
payment-provider
```

Verificar:

* healthcheck;
* depends_on;
* network;
* DNS;
* variáveis de ambiente;
* portas;
* volumes;
* isolamento;
* restart policy;
* containers desnecessários.

Verificar se:

```bash
docker compose up -d
```

é suficiente para subir todo o projeto.

---

# 25. Segurança

Auditar:

* validação de entrada;
* tamanho máximo do body;
* campos desconhecidos;
* headers;
* exposição de erros;
* secrets;
* `.env`;
* logs;
* XML parsing;
* XXE;
* SSRF;
* URLs configuráveis;
* timeouts.

O enunciado sugere:

> limitar corpo da requisição a aproximadamente 1 MiB;

e:

> rejeitar JSON com campos desconhecidos.

Verificar se isso está implementado.

Se não estiver, reportar.

---

# 26. Testes

Executar todos os testes existentes.

Não modificar os testes.

Classificar:

```text
PASS
FAIL
SKIPPED
NOT COVERED
```

Depois comparar a cobertura real com os casos exigidos pelo enunciado.

Criar uma matriz:

| Requisito             | Implementado | Testado | Observação |
| --------------------- | ------------ | ------- | ---------- |
| REST                  |              |         |            |
| SOAP                  |              |         |            |
| fallback              |              |         |            |
| retry                 |              |         |            |
| IPVA                  |              |         |            |
| MULTA                 |              |         |            |
| PIX                   |              |         |            |
| cartão                |              |         |            |
| placa inválida        |              |         |            |
| zero débitos          |              |         |            |
| provider indisponível |              |         |            |
| tipo desconhecido     |              |         |            |
| divergência           |              |         |            |
| observabilidade       |              |         |            |

---

# 27. Requisitos extras do enunciado

Verificar também:

* simulação de timeout;
* circuit breaker;
* logs estruturados;
* mascaramento LGPD;
* testes unitários;
* testes de integração;
* Strategy;
* Adapter;
* Ports & Adapters.

Classificar cada item como:

```text
IMPLEMENTADO
PARCIAL
NÃO IMPLEMENTADO
FORA DO ESCOPO
```

Não considerar automaticamente algo "fora do escopo" apenas porque não foi implementado.

Explicar a justificativa.

---

# 28. Performance

Fazer uma análise conceitual de:

* quantidade de chamadas externas;
* retries;
* fallback;
* timeout;
* processamento;
* memória.

Não realizar benchmark complexo.

Identificar possíveis gargalos.

---

# 29. Código morto e overengineering

Procurar:

* classes não utilizadas;
* interfaces sem implementação;
* abstrações desnecessárias;
* duplicação;
* código de infraestrutura sem uso;
* dependências desnecessárias;
* comentários que contradizem o código.

Especialmente procurar código criado durante as fases anteriores que não tenha função real.

---

# 30. Documentação

Verificar:

```text
README.md
docs/
```

e comparar documentação com o comportamento real.

Procurar contradições.

Exemplo:

Documentação diz:

```text
retry = 3
```

mas código faz:

```text
retry = 2
```

Isso deve ser reportado.

---

# 31. Resultado final da auditoria

Produza um relatório organizado nestas seções:

## A. Resumo executivo

No máximo 10 pontos.

## B. Matriz de requisitos

Tabela completa do Home Test.

## C. Problemas críticos

Somente problemas que realmente podem comprometer a avaliação.

## D. Problemas importantes

Problemas que valem corrigir antes da apresentação.

## E. Melhorias opcionais

Melhorias que não são necessárias.

## F. Pontos fortes

O que está particularmente bem feito.

## G. Riscos arquiteturais

Possíveis questionamentos do avaliador.

## H. Trade-offs

Decisões arquiteturais que devemos conseguir explicar.

## I. Perguntas que o avaliador provavelmente fará

Liste perguntas técnicas prováveis.

Para cada pergunta, explique qual parte do código/documentação permite respondê-la.

## J. Matriz de testes

Mostrar o que está coberto e o que não está.

## K. Plano de correção

Ordenar por prioridade:

```text
P0 — obrigatório corrigir
P1 — recomendado
P2 — melhoria futura
```

---

# 32. Regra de prioridade

NÃO quero uma lista enorme de "melhorias".

Priorize.

Uma pequena quantidade de problemas realmente importantes é melhor do que 50 sugestões irrelevantes.

Use esta classificação:

### P0

Pode gerar:

* falha funcional;
* resultado financeiro incorreto;
* quebra do contrato;
* falha de segurança;
* falha no requisito principal.

### P1

Pode gerar:

* questionamento arquitetural;
* dificuldade na apresentação;
* problema de manutenção;
* observabilidade insuficiente.

### P2

Melhoria futura.

---

# 33. Muito importante

Ao encontrar um problema:

NÃO corrija.

Mostre:

```text
Problema
Onde está
Por que é problema
Impacto
Como eu corrigiria
Prioridade
```

Exemplo:

```text
Problema:
CreditCardCalculator utiliza float.

Arquivo:
...

Impacto:
Possível perda de precisão em determinados valores.

Correção sugerida:
...

Prioridade:
P1
```

---

# 34. Critério final

Quero terminar esta fase sabendo exatamente:

> "Se eu tivesse que apresentar o Cardok amanhã para a DOK, quais são os 5 pontos que eu deveria corrigir antes?"

Portanto, ao final do relatório, apresente obrigatoriamente:

# TOP 5 — CORREÇÕES ANTES DA APRESENTAÇÃO

Ordenadas por impacto.

Não implemente essas correções ainda.

---

## Regra final

Esta é uma AUDITORIA.

Não altere nenhum arquivo.

Não faça commits.

Não crie arquivos.

Não corrija testes.

Não refatore.

Não instale dependências.

Somente analise o estado atual do projeto e produza o relatório técnico.

Depois que eu revisar o relatório, vamos implementar as correções uma fase por vez.
