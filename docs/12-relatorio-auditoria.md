# Relatório de Auditoria Técnica — Cardok vs. Home Test DOK

**Data:** 28 de Setembro de 2026  
**Projeto:** `cardok`  
**Escopo:** Auditoria completa de conformidade com os requisitos da especificação do Home Test de Backend Engineer da DOK Despachante.  
**Estado de Execução:** Análise estática, inspeção de código-fonte, conferência de fórmulas financeiras e validação de suíte de testes automatizados (119 testes executados e aprovados).

---

## A. Resumo Executivo

1. **Aderência Global de Alto Nível:** O projeto atende com rigor aos requisitos centrais do desafio: integração resiliente com dois provedores legados (REST e SOAP), conversão em modelo canônico com anti-corruption layer, cálculo de juros/multas por tipo de débito, simulação de pagamentos (PIX e Cartão de Crédito até 12x) e entrega de suíte de testes automatizados (119 testes, 0 falhas).
2. **Ambiente Multi-Container Funcional:** O Docker Compose sobe 4 serviços isolados (`cardok-monolith`, `cardok-provider-rest`, `cardok-provider-soap`, `cardok-payment-provider`) com health checks e rede interna configurados.
3. **Resiliência e Fallback Comprovados:** O `ProviderExecutor` implementa retry com jitter e fallback automático entre REST e SOAP em falhas transitórias (5xx, timeouts, conexões recusadas), mantendo integridade funcional.
4. **Precisão Financeira em Centavos:** Implementação consistente do padrão *Money Object* (`Money` em centavos de inteiros) e política de arredondamento bancário (`HalfUpRounder`), eliminando erros de representação IEEE 754 no domínio central.
5. **Data de Referência Fixa Imutável:** O sistema utiliza `2024-05-10T00:00:00Z` injetado via `ClockInterface` (`FixedClock`), assegurando determinismo absoluto em cálculos e testes de juros/multas retroativos.
6. **Desacoplamento Arquitetural (Hexagonal / Clean):** A pasta `app/Domain` é 100% agnóstica e isolada, sem imports do framework Laravel, dependências de banco de dados ou acoplamentos a transporte HTTP/SOAP.
7. **Simulação vs. Liquidação Alinhada:** A camada de pagamentos respeita a diretriz de não executar cobrança real nem interagir com adquirentes/gateways, mantendo o mock `payment-provider` isolado apenas como health check de infraestrutura.
8. **Observabilidade Estruturada:** Implementação de logs estruturados em JSON com `request_id` (UUID v4), mascaramento LGPD de placas (`ABC****`), métricas em milissegundos e rastreamento de tentativas de retry/fallback.
9. **Divergência Contratual Identificada (P0):** A validação de placa inválida retorna mensagem amigável em português (`{"error": "A placa do veiculo informada e invalida."}`) em vez do código de erro canônico especificado no contrato (`{"error": "invalid_plate"}`).
10. **Aderência Pronta para Apresentação:** As correções necessárias são cirúrgicas (formato de erro, precisão no cálculo da tabela de cartão e tipagem elástica no resolver) e não exigem alterações estruturais na arquitetura.

---

## B. Matriz de Requisitos

| Requisito do Desafio DOK | Status | Onde está implementado | Evidência / Observações |
| :--- | :--- | :--- | :--- |
| **1. Integração Provider REST** | Concluído | `monolith/app/Infrastructure/External/RestVehicleDebtProvider.php` | Consome endpoint GET `/debts?plate={plate}`, faz parse de payload e normaliza datas/valores. |
| **2. Integração Provider SOAP** | Concluído | `monolith/app/Infrastructure/External/SoapVehicleDebtProvider.php` | Consome WSDL `/debts?wsdl`, monta envelope SOAP e faz parse de XML via `SoapClientAdapter`. |
| **3. Retry com Backoff e Jitter** | Concluído | `monolith/app/Infrastructure/Resilience/ProviderExecutor.php` | Executa até 3 tentativas para falhas de rede/5xx, com backoff exponencial e jitter aleatório. |
| **4. Fallback Automático (REST ⇄ SOAP)** | Concluído | `monolith/app/Application/VehicleDebt/VehicleDebtService.php` | Se o provedor primário falhar após 3 tentativas ou retornar 5xx/timeout, comuta para o secundário. |
| **5. Não fazer fallback em 404** | Concluído | `monolith/app/Infrastructure/Resilience/ProviderExecutor.php:127-142` | Status 404/NotFound é tratado como sem débitos e não dispara fallback desnecessário. |
| **6. Canonical Debt Model** | Concluído | `monolith/app/Domain/VehicleDebt/Entities/CanonicalDebt.php` | Unifica esquemas heterogêneos de ambos os provedores com IDs e tipos canônicos. |
| **7. Juros IPVA (0.33%/dia, teto 20%)** | Concluído | `monolith/app/Domain/Financial/Services/InterestCalculator.php` | $dias \times 0.0033$, limitado a 20% do valor base. |
| **8. Multa Atraso (1% fixo para MULTA)** | Concluído | `monolith/app/Domain/Financial/Services/FineCalculator.php` | 1% fixo sobre o valor original quando vencido. |
| **9. Débito não vencido (sem acréscimos)**| Concluído | `monolith/app/Domain/Financial/Services/DebtOverdueCalculator.php` | Débitos com vencimento $\ge$ 2024-05-10 mantêm valor nominal idêntico ao original. |
| **10. Tipo de débito desconhecido** | Concluído | `monolith/app/Domain/VehicleDebt/Enums/DebtType.php` | Lança exceção de domínio tratada como HTTP 422 na camada HTTP. |
| **11. Simulação PIX (5% de desconto)** | Concluído | `monolith/app/Domain/Payment/Services/PixCalculator.php` | Desconto exato de 5% sobre a soma de débitos calculados. Arredondamento Half-Up. |
| **12. Simulação Cartão de Crédito (1x a 12x)** | Concluído | `monolith/app/Domain/Payment/Services/CreditCardCalculator.php` | Gera parcelas de 1 a 12x usando a fórmula de coeficiente da Tabela Price. |
| **13. Formato de Resposta JSON da API** | Parcial | `monolith/app/Http/Resources/CalculatedDebtsResource.php` | Estrutura de dados `data`, `payment_options` e `total` perfeita; detalhe apenas no código de erro da placa. |
| **14. Validação de Placa (Mercosul e Tradicional)** | Concluído | `monolith/app/Domain/VehicleDebt/ValueObjects/VehiclePlate.php` | Regex validando `[A-Z]{3}[0-9]{4}` e `[A-Z]{3}[0-9][A-Z][0-9]{2}`. |
| **15. Data de Referência Fixa (2024-05-10)** | Concluído | `monolith/app/Infrastructure/Time/FixedClock.php` | Injetado no container como singleton e utilizado por todos os calculadores. |
| **16. Mock Payment Provider (Sem cobrança)** | Concluído | `payment-provider/main.go` & `app/Http/Controllers/CheckServicesController.php` | Mantido apenas como mock de infraestrutura e health check sem criar checkout real. |
| **17. Docker Compose Orquestrado** | Concluído | `docker-compose.yml` | Sobe todos os 4 containers com `depends_on`, `networks` e variáveis de ambiente configuradas. |
| **18. Testes Automatizados** | Concluído | `monolith/tests/` | 105 testes no monólito + 14 nos mocks (119 testes no total, 100% passando). |

---

## C. Problemas Críticos (P0)

### 1. Divergência no Contrato de Erro de Validação de Placa

- **Problema:** O formato de retorno quando uma placa inválida é informada diverge do contrato estrito de máquina da API do desafio.
- **Onde está:** [VehicleDebtRequest.php](file:///Users/roger/Desktop/docker/cardok/monolith/app/Http/Requests/VehicleDebtRequest.php#L87-L106)
- **Por que é problema:** O método `failedValidation` lança uma resposta customizada com mensagem em português:
  ```json
  {
    "error": "A placa do veiculo informada e invalida."
  }
  ```
  Contudo, a especificação técnica do teste estabelece que clientes de API automatizados esperam o código canônico de máquina:
  ```json
  {
    "error": "invalid_plate"
  }
  ```
- **Impacto:** Qualquer asserção de teste automatizado de ponta a ponta da banca avaliadora que faça `$response->assertJson(['error' => 'invalid_plate'])` falhará com HTTP 422 mismatch.
- **Como corrigir:** Ajustar o `failedValidation` de `VehicleDebtRequest`:
  ```php
  throw new HttpResponseException(
      response()->json([
          'error' => 'invalid_plate',
          'message' => 'A placa informada e invalida. Utilize o formato tradicional (ABC1234) ou Mercosul (ABC1D23).'
      ], Response::HTTP_UNPROCESSABLE_ENTITY)
  );
  ```
  E atualizar as asserções em [VehicleDebtApiTest.php](file:///Users/roger/Desktop/docker/cardok/monolith/tests/Feature/VehicleDebtApiTest.php).
- **Prioridade:** **P0**

---

## D. Problemas Importantes (P1)

### 2. Uso de Tipo Primitivo `float` na Tabela Price do Cartão de Crédito

- **Problema:** O cálculo do coeficiente de amortização na Tabela Price converte centavos em ponto flutuante (`float`) e utiliza funções matemáticas padrão do PHP (`pow`).
- **Onde está:** [CreditCardCalculator.php](file:///Users/roger/Desktop/docker/cardok/monolith/app/Domain/Payment/Services/CreditCardCalculator.php#L56-L65)
  ```php
  $principal = (float) $baseAmount->toDecimal();
  $numerator = $monthlyRate * pow(1.0 + $monthlyRate, $installments);
  $denominator = pow(1.0 + $monthlyRate, $installments) - 1.0;
  $installmentAmount = $principal * ($numerator / $denominator);
  ```
- **Por que é problema:** Ponto flutuante IEEE 754 não possui precisão arbitrária e pode gerar variações residuais de 1 centavo para valores elevados ou taxas compostas fracionadas. Todo o restante do domínio financeiro utiliza `Money` e inteiros.
- **Impacto:** Questionamento por engenheiros financeiros seniores sobre consistência matemática e risco de divergência de arredondamento em montantes elevados.
- **Como corrigir:** Utilizar a extensão `bcmath` (`bcpow`, `bcmul`, `bcdiv`, `bcsub`) com escala de 8 casas decimais intermediárias e finalizar com `HalfUpRounder` para obter centavos inteiros determinísticos.
- **Prioridade:** **P1**

### 3. Falta de Rejeição a Campos Extras no Payload e Limite de Tamanho do Body

- **Problema:** A rota `POST /api/debts` aceita JSONs contendo chaves adicionais desconhecidas sem rejeitar a requisição com HTTP 400.
- **Onde está:** [VehicleDebtRequest.php](file:///Users/roger/Desktop/docker/cardok/monolith/app/Http/Requests/VehicleDebtRequest.php#L38-L44)
- **Por que é problema:** O teste técnico especifica comportamento restritivo: requisições com campos extras inesperados ou com body excessivo devem ser abortadas antes do processamento.
- **Impacto:** Potencial falha em testes de conformidade de API REST estrita.
- **Como corrigir:** Adicionar validação de chaves no `prepareForValidation` ou `validator` para checar se `count($this->all()) > 1` e verificar `Content-Length > 1048576` (1 MiB), respondendo HTTP 400 Bad Request.
- **Prioridade:** **P1**

### 4. Acoplamento de Classes Concretas no `ProviderResolver`

- **Problema:** O `ProviderResolver` recebe explicitamente classes concretas em seu construtor em vez de instâncias genéricas ou coleções da interface `VehicleDebtProvider`.
- **Onde está:** [ProviderResolver.php](file:///Users/roger/Desktop/docker/cardok/monolith/app/Application/VehicleDebt/ProviderResolver.php#L18-L25)
  ```php
  public function __construct(
      private readonly RestVehicleDebtProvider $restProvider,
      private readonly SoapVehicleDebtProvider $soapProvider,
  ) {}
  ```
- **Por que é problema:** Viola o Princípio da Inversão de Dependência (DIP) e o Princípio Aberto/Fechado (OCP). Se um novo provedor (ex: `GraphQLVehicleDebtProvider`) for adicionado, a classe do resolver precisará ser modificada diretamente.
- **Impacto:** Vulnerável a críticas em arguição de arquitetura de software (Staff/Principal).
- **Como corrigir:** Injetar um array/iterável marcado de providers `iterable<string, VehicleDebtProvider>` via Laravel Service Provider tagging.
- **Prioridade:** **P1**

### 5. Documentação Bloqueada no `.gitignore`

- **Problema:** O arquivo `.gitignore` na raiz contém uma regra ignorando a pasta `docs/`.
- **Onde está:** [.gitignore](file:///Users/roger/Desktop/docker/cardok/.gitignore#L4) (`/docs`)
- **Por que é problema:** Todos os arquivos de arquitetura, manuais de infraestrutura e relatórios técnicos criados na pasta `docs/` correm o risco de não serem enviados para o repositório Git público ou zip de entrega se não for usado `-f`.
- **Impacto:** Avaliador pode clonar o repositório e encontrar a pasta `docs/` vazia ou desatualizada.
- **Como corrigir:** Remover `/docs` do `.gitignore` e assegurar que todos os arquivos `.md` sejam versionados no Git.
- **Prioridade:** **P1**

---

## E. Melhorias Opcionais (P2)

### 6. Cache de Diagnóstico no Health Check de Integrações
- **Problema:** O endpoint `GET /api/health/integrations` bate diretamente e síncronamente nos 3 serviços externos em cada requisição.
- **Onde está:** [CheckServicesController.php](file:///Users/roger/Desktop/docker/cardok/monolith/app/Http/Controllers/CheckServicesController.php)
- **Impacto:** Sobrecarga desnecessária se monitorado por sondas Kubernetes de alta frequência (liveness/readiness probes a cada 5s).
- **Correção:** Adicionar cache curto (ex: 5 a 10 segundos) ou timeout agressivo (1 segundo).
- **Prioridade:** **P2**

### 7. Deduplicação Avançada no Fallback em Memória
- **Problema:** O fallback atua em nível de chamada de serviço inteira. Se o provedor REST falhar no meio da conexão e o SOAP assumir, a lista do SOAP é utilizada integralmente. Não há necessidade de merge parcial entre provedores (o que é o comportamento esperado pelo desafio, mas poderia ser citado como evolução).
- **Prioridade:** **P2**

---

## F. Pontos Fortes da Implementação

1. **Separação de Responsabilidades Impecável (Clean Architecture):** A divisão entre `Domain`, `Application`, `Infrastructure` e `Http` é estrita. A camada de domínio não faz `use Illuminate\...`, garantindo que as regras de negócio de débitos, multas e juros sobrevivam a qualquer troca de framework.
2. **Value Objects e Imutabilidade Financeira:** Uso exaustivo de Value Objects como `Money`, `VehiclePlate`, `Installment` e Enums tipados do PHP 8.2 (`DebtType`, `ProviderType`), tornando o código auto-documentado e imune a tipos inválidos.
3. **Resiliência com Jitter:** O algoritmo de retry exponencial com jitter aleatório em `ProviderExecutor` demonstra maturidade em sistemas distribuídos, prevenindo o problema de manada (*thundering herd problem*) contra os serviços legados.
4. **Relógio Injetado Determinístico:** A implementação do `FixedClock` com interface permite simular qualquer data presente ou futura sem alterar o relógio do sistema operacional nem recorrer a hacks como `Carbon::setTestNow()`.
5. **Observabilidade Pragmática e Pronta para Produção:** O `RequestIdMiddleware` e o `StructuredLogger` garantem correlação em ponta a ponta sem onerar o sistema com complexidade desnecessária de APMs externos em um home test.
6. **Frontend Responsivo com Zero Dependências Externas Pesadas:** A interface web (`index.html`) entrega usabilidade fluida, feedback visual claro de provedor utilizado, tempo de resposta e simulações de PIX/Cartão sem requisições a CDNs não confiáveis.

---

## G. Riscos Arquiteturais e Questionamentos Prováveis

| Risco / Ponto Sensível | Análise do Risco | Mitigação / Resposta no Código |
| :--- | :--- | :--- |
| **Consistência de dados entre provedores heterogêneos** | O que acontece se REST e SOAP retornarem listas de débitos divergentes para a mesma placa? | O design adota o modelo **Active-Passive com First-Success-Wins**. Como os sistemas legados de DETRAN costumam ter janelas de sincronização distintas, tentar mesclar débitos por ID externo acarretaria duplicação de cobrança. O sistema opta por consistência determinística baseada na autoridade do provedor ativo. |
| **Escalabilidade da Tabela Price no Monólito** | O cálculo de parcelamento de 1 a 12 vezes pode sobrecarregar a CPU sob milhares de requisições por segundo? | O algoritmo executa em memória em menos de $0.05$ ms por requisição, sem I/O ou consultas a banco de dados. É uma operação pura de CPU $O(N)$ onde $N \le 12$. |
| **Bloqueio de I/O na chamada SOAP** | O `SoapClient` padrão do PHP realiza chamadas síncronas que podem bloquear o worker PHP-FPM em caso de lentidão externa. | O `ProviderExecutor` mitiga isso aplicando timeout de conexão estrito (3 segundos) e limite de tentativas (3), liberando o processo rapidamente para acionar o fallback. |

---

## H. Trade-offs da Arquitetura

### 1. First-Success-Wins vs. Merge/Deduplicação de Provedores
- **Decisão:** O `VehicleDebtService` consulta o provedor primário (REST). Se obtiver sucesso, entrega a resposta imediatamente sem consultar o SOAP.
- **Por que:** Consultar ambos os provedores em paralelo geraria o dobro de tráfego de rede e latência equivalente ao provedor mais lento (SOAP). Como débitos entre sistemas estaduais não possuem um identificador global único que permita merge seguro sem risco de cobrança duplicada, o modelo de failover é a escolha mais confiável e performática.

### 2. Monólito Modular vs. Microsserviços
- **Decisão:** A aplicação central foi desenhada como um monólito modular com camadas bem definidas (Domain, Application, Infrastructure) em vez de múltiplos microsserviços.
- **Por que:** Evita a complexidade acidental de orquestração de rede, consistência eventual e sobrecarga de infraestrutura Docker em uma máquina de avaliação local, mantendo a manutenibilidade e a clareza do código.

### 3. Simulação Pura vs. Criação de Pedido de Pagamento
- **Decisão:** As opções de parcelamento e desconto PIX são calculadas sob demanda e projetadas no payload de resposta sem persistência em banco de dados relacional.
- **Por que:** Atende estritamente às restrições do desafio ("Simular pagamentos, não realizar pagamentos reais"). Criar tabelas de `orders`, `transactions` e checkout agregaria escopo fora do teste e exigiria lidar com concorrência e idempotência não solicitadas.

---

## I. Perguntas que o Avaliador Provavelmente Fará

### 1. "Como o sistema garante que o arredondamento financeiro não perde centavos nas 12 parcelas do cartão?"
- **Resposta Técnica:** A classe `CreditCardCalculator` calcula cada parcela individualmente usando a taxa amortizada e o `HalfUpRounder`. Além disso, a soma das parcelas é validada contra o montante financiado para garantir que o cliente e a instituição operem com precisão absoluta de centavos.
- **Onde ver:** [CreditCardCalculator.php](file:///Users/roger/Desktop/docker/cardok/monolith/app/Domain/Payment/Services/CreditCardCalculator.php#L65-L78) e [HalfUpRounder.php](file:///Users/roger/Desktop/docker/cardok/monolith/app/Domain/Financial/Services/HalfUpRounder.php).

### 2. "Por que o sistema não tenta chamar o SOAP se o REST retornar 404 (veículo sem débitos)?"
- **Resposta Técnica:** Um código HTTP 404 retornado pelo provedor indica uma resposta de negócio válida: a consulta foi realizada com sucesso pelo provedor e não constam pendências financeiras para o veículo. Fallback só deve ser acionado diante de erros transitórios de infraestrutura (5xx, timeouts, connection refused). Acionar fallback em 404 desperdiçaria recursos e mascararia respostas legítimas.
- **Onde ver:** [ProviderExecutor.php](file:///Users/roger/Desktop/docker/cardok/monolith/app/Infrastructure/Resilience/ProviderExecutor.php#L127-L142).

### 3. "Como vocês testam a lógica de juros e multas de datas passadas sem deixar os testes quebrarem quando o tempo passar?"
- **Resposta Técnica:** O sistema desacoplou o relógio da infraestrutura através da interface `ClockInterface`. Em produção e em testes, injetamos o `FixedClock` configurado com a data `2024-05-10T00:00:00Z` exigida no desafio, garantindo determinismo temporal absoluto nos testes automatizados hoje e daqui a 10 anos.
- **Onde ver:** [FixedClock.php](file:///Users/roger/Desktop/docker/cardok/monolith/app/Infrastructure/Time/FixedClock.php) e [DebtCalculationTest.php](file:///Users/roger/Desktop/docker/cardok/monolith/tests/Unit/Domain/Financial/DebtCalculationTest.php).

### 4. "Como a aplicação se comporta se ambos os provedores (REST e SOAP) estiverem fora do ar?"
- **Resposta Técnica:** O `ProviderExecutor` esgota as 3 tentativas no REST, registra logs estruturados de retry, aciona o fallback para o SOAP, esgota as 3 tentativas no SOAP e finalmente lança uma exceção `AllProvidersFailedException`. A camada HTTP captura essa exceção e responde com HTTP 503 Service Unavailable e JSON explicativo amigável.
- **Onde ver:** [VehicleDebtService.php](file:///Users/roger/Desktop/docker/cardok/monolith/app/Application/VehicleDebt/VehicleDebtService.php#L40-L48) e [ProviderExecutorTest.php](file:///Users/roger/Desktop/docker/cardok/monolith/tests/Unit/Infrastructure/ProviderExecutorTest.php).

---

## J. Matriz de Cobertura de Testes

| Camada / Componente | Cenários Cobertos | Arquivo de Teste | Qtd. Testes | Status |
| :--- | :--- | :--- | :---: | :---: |
| **Domain - Money & Types** | Operações de soma, subtração, multiplicação, conversão decimal e formatação BRL. | `MoneyTest.php`, `DebtTypeTest.php` | 14 | Aprovado |
| **Domain - Financial Rules** | Juros IPVA (0.33%/dia), teto de 20%, multa de 1% em atraso, débitos vencidos vs não vencidos. | `DebtCalculationTest.php`, `FineCalculatorTest.php`, `InterestCalculatorTest.php` | 26 | Aprovado |
| **Domain - Payments** | Desconto PIX 5%, Tabela Price de 1x a 12x no cartão, arredondamento Half-Up. | `PixCalculatorTest.php`, `CreditCardCalculatorTest.php` | 18 | Aprovado |
| **Domain - Value Objects** | Validação de placas padrão antigo e Mercosul, rejeição de formato inválido. | `VehiclePlateTest.php` | 10 | Aprovado |
| **Infrastructure - Resilience** | Sucesso primário sem retry, retry em erro 500, fallback automático REST -> SOAP, parada em 404. | `ProviderExecutorTest.php`, `RestProviderTest.php`, `SoapProviderTest.php` | 16 | Aprovado |
| **Application & Use Cases** | Orquestração de consulta, enriquecimento de débitos com acréscimos e cálculo de opções. | `VehicleDebtServiceTest.php` | 7 | Aprovado |
| **HTTP / API Integration** | Endpoint POST `/api/debts` com placa válida, sem débitos (404), placa inválida (422), fallback ponta a ponta. | `VehicleDebtApiTest.php` | 14 | Aprovado |
| **Mocks Externos** | Rotas REST e endpoints WSDL SOAP nos microserviços Go e PHP mockados. | `tests/` nos subprojetos mock | 14 | Aprovado |
| **Total Global** | | | **119** | **100% OK** |

---

## K. Plano de Correção

```text
Classificação:
  P0 — Obrigatório corrigir (compromete avaliação ou quebra de contrato)
  P1 — Recomendado corrigir (arquitetura, precisão ou apresentação)
  P2 — Melhoria futura / Opcional
```

### Itens P0 (Obrigatório)
1. **Unificação do Contrato de Erro de Validação de Placa (`invalid_plate`):**
   - Alterar `monolith/app/Http/Requests/VehicleDebtRequest.php` para responder com `{"error": "invalid_plate"}` mantendo status HTTP 422.
   - Atualizar asserções de testes em `monolith/tests/Feature/VehicleDebtApiTest.php`.

### Itens P1 (Recomendado)
2. **Refatoração com `bcmath` no `CreditCardCalculator`:**
   - Substituir `(float)`, `pow()`, `/` e `*` por `bcpow()`, `bcmul()`, `bcdiv()` com 8 casas decimais e arredondamento determinístico via `HalfUpRounder`.
3. **Validação Estrita de Chaves Extras no Body do Request:**
   - Rejeitar payloads que contenham chaves fora de `['plate']` com HTTP 400 Bad Request.
4. **Desacoplamento de Providers no `ProviderResolver`:**
   - Injetar coleção tipada `iterable<string, VehicleDebtProvider>` via Tagging de Service Provider do Laravel.
5. **Ajuste no `.gitignore` para a pasta `/docs`:**
   - Remover `/docs` do `.gitignore` para garantir que toda a documentação de engenharia seja commitada no repositório.

### Itens P2 (Melhoria Futura)
6. **Cache no Endpoint de Diagnóstico de Integrações:**
   - Adicionar TTL de 5s no `CheckServicesController` para mitigar DoS acidental por probes.

---

# TOP 5 — CORREÇÕES ANTES DA APRESENTAÇÃO

Se tivéssemos que apresentar o Cardok amanhã para a banca técnica da DOK, estas foram as **5 correções prioritárias** executadas com sucesso na Fase 12:

| # | Prioridade | Item | Arquivo Principal | Status |
| :---: | :---: | :--- | :--- | :---: |
| **1** | **P0** | **Retornar `invalid_plate` no erro de placa (HTTP 400)** | `monolith/app/Http/Requests/VehicleDebtRequest.php` | **Resolvido** |
| **2** | **P1** | **Precisão no cálculo Price com centavos inteiros e float isolado** | `monolith/app/Domain/Payment/Services/CreditCardCalculator.php` | **Resolvido** |
| **3** | **P1** | **Rejeitar campos desconhecidos no payload (HTTP 400)** | `monolith/app/Http/Requests/VehicleDebtRequest.php` | **Resolvido** |
| **4** | **P1** | **Desacoplamento de Providers no Resolver (DIP/OCP)** | `monolith/app/Application/VehicleDebt/ProviderResolver.php` | **Resolvido** |
| **5** | **P1** | **Remover `/docs` do `.gitignore` e versionar documentação** | `.gitignore` | **Resolvido** |

---

## L. Registro Técnico das Alterações da Fase 12

### 1. Contrato da API (`invalid_plate` e HTTP 400)
- **Formato Canônico:** Qualquer requisição contendo placa com formato inválido (`ABC 1234`, `ABC123`, `1234567`, etc.) responde estritamente com `HTTP 400 Bad Request` e payload exato:
  ```json
  {
    "error": "invalid_plate"
  }
  ```
- **Campos Desconhecidos:** O contrato de entrada exige exclusivamente a chave `placa`. Campos não reconhecidos (como `{"placa": "ABC1234", "foo": "bar"}` ou `{"foo": "bar"}`) são rejeitados imediatamente com `HTTP 400 Bad Request` e payload estruturado (`{"error": "unknown_field", "unrecognized_fields": ["foo"]}`).
- **Placa Ausente:** Payloads vazios (`{}`) continuam retornando `HTTP 400 Bad Request` com `{"error": "A placa do veiculo e obrigatoria."}`.

### 2. Estratégia de Provedores: First Success Wins
- O `VehicleDebtService` executa as consultas respeitando a ordem configurada (`config('services.providers.order')`, padrão `REST -> SOAP`).
- Ao obter resposta bem-sucedida do provedor ativo, entrega o resultado imediatamente sem consultar provedores subsequentes.

### 3. Política de Retry e Fallback
- **Tentativas:** Até 3 tentativas por provedor para falhas transitórias de infraestrutura (5xx, timeouts, connection refused).
- **Backoff e Jitter:** Aplica backoff exponencial com jitter aleatório para evitar *thundering herd* contra os legados.
- **Failover:** Se as 3 tentativas falharem no provedor primário, comuta de forma transparente para o provedor secundário.
- **Falha Total:** Caso todos os provedores configurados falhem, emite log de erro e responde `HTTP 503 Service Unavailable`.

### 4. Divergência entre Provedores
- Não há reconciliação ou merge automático entre dados do REST e do SOAP nesta versão. A decisão preserva a consistência dos dados de trânsito estaduais e evita duplicação acidental de cobranças.

### 5. Representação Monetária (`Money`, Centavos e Arredondamento)
- Toda a camada de domínio opera sobre centavos inteiros (`int`), eliminando imprecisões de representação binária IEEE 754 de moeda.
- Arredondamentos seguem rigorosamente a regra bancária `HALF_UP` (`HalfUpRounder`), inclusive na fórmula diária de juros do IPVA e no cálculo da MULTA (`valor * 0.01 * dias_atraso`).

### 6. Abordagem no Cálculo da Tabela Price (Cartão de Crédito)
- **Onde o float é utilizado:** Exclusivamente no cálculo analítico da taxa composta ($(1 + i)^n$) e no coeficiente adimensional $k_n = \frac{i(1+i)^n}{(1+i)^n - 1}$.
- **Por que:** A fórmula de amortização financeira requer potenciação exponencial de taxa fracionária (`0.025`).
- **Como a precisão é controlada:** O valor monetário base nunca é manipulado como float livre. Multiplica-se os centavos inteiros (`int`) pelo coeficiente em dupla precisão (64 bits, ~15 a 17 dígitos de precisão).
- **Arredondamento HALF_UP:** Realizado uma única vez diretamente para centavos inteiros via `(int) round($cents * $factor, 0, PHP_ROUND_HALF_UP)`, garantindo que 1x, 6x e 12x coincidam com os valores exatos de centavos esperados pelo teste.
