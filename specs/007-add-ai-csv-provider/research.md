# Pesquisa Técnica: Provedor de Débitos com IA em Plaintext CSV

**Feature**: `007-add-ai-csv-provider`  
**Data**: 2026-10-08  
**Status**: Concluído  

---

## 1. Decisão: Mecanismo de Parsing e Sanitização de CSV em Texto Puro

### Decisão
Utilizar um parser linha a linha nativo em PHP (`str_getcsv`) com higienização prévia de blocos de código Markdown (````csv ... ````), comentários explicativos e linhas em branco. Detectar automaticamente o delimitador entre vírgula (`,`) e ponto e vírgula (`;`).

### Justificativa
- Respostas geradas por modelos de linguagem (LLMs) frequentemente envelopam a saída em blocos Markdown ou adicionam preâmbulos informativos.
- O PHP provê suporte nativo e performático a parsing CSV via `str_getcsv()`, dispensando dependências externas pesadas no monólito.
- A tolerância mútua para `,` e `;` previne falhas quando modelos formatam números decimais no padrão brasileiro (`1200,50` com delimitador `;`) ou internacional (`1200.50` com delimitador `,`).

### Alternativas Consideradas
- **Biblioteca League/Csv**: Excelente biblioteca, porém desnecessária para payloads pequenos de débitos veiculares (< 50 linhas), gerando dependência externa adicional desnecessária.
- **Parsing Estrito por Expressão Regular**: Rejeitado pois regras de escape e valores entre aspas são melhor manipulados pelas funções nativas de CSV do PHP.

---

## 2. Decisão: Arquitetura do Adaptador no Monólito (`AiVehicleDebtProvider`)

### Decisão
Implementar o adaptador `App\Infrastructure\Providers\Ai\AiVehicleDebtProvider` implementando a porta de domínio `App\Domain\Debt\Contracts\VehicleDebtProvider`. O adaptador será responsável por:
1. Realizar a chamada HTTP `GET /api/v1/debts/{plate}` com header `Accept: text/plain, text/csv` e propagation do `X-Request-ID`.
2. Processar a resposta em texto puro.
3. Filtrar e normalizar cada débito para o modelo canônico `App\Domain\Debt\Debt` com tipo normalizado (`IPVA`, `MULTA`, `LICENCIAMENTO`), valor encapsulado em `App\Domain\Debt\Money` e vencimento em `CarbonImmutable`.
4. Ignorar linhas com categorias não suportadas registrando log de aviso (`warning`).
5. Retornar `App\Domain\Debt\ProviderDebtResponse(plate, debts, provider: 'ai')`.

### Justificativa
- Atende 100% ao Princípio I da Constituição (Isolamento do Domínio) e Princípio III (Modelo Canônico na borda).
- Garante total compatibilidade com o serviço de cálculo (`DebtCalculationService`) e simulador de pagamentos (`PaymentSimulator`).

### Alternativas Consideradas
- **Estender o `RestVehicleDebtProvider`**: Rejeitado porque, embora ambos usem HTTP, a semântica, o formato do corpo (JSON vs plaintext CSV) e as regras defensivas de parsing para saídas de IA são fundamentalmente distintas.

---

## 3. Decisão: Integração na Cadeia de Resiliência (`ProviderResolver` e `ProviderExecutor`)

### Decisão
Adicionar a chave `ai` na configuração de provedores (`config/services.php`), permitindo configurar `PROVIDER_AI_URL` e incluir `ai` na variável de ambiente `PROVIDER_ORDER` (ex.: `PROVIDER_ORDER=ai,rest,soap`). O `AppServiceProvider` registrará a instância singleton de `AiVehicleDebtProvider` no `ProviderResolver`.

### Justificativa
- A infraestrutura já possui o mecanismo de *First-Success-Wins* e retry linear com backoff em `ProviderExecutor`.
- Respostas vazias válidas retornam lista de débitos vazia sem acionar fallback.
- Falhas de rede, timeouts ou payloads malformados disparam `ProviderUnavailableException` ou `InvalidProviderResponseException`, acionando o fallback transparente para o próximo provedor.

### Alternativas Consideradas
- **Execução paralela de provedores**: Proibido explicitamente pelo Princípio III da Constituição ("Providers NÃO DEVEM ser consultados concorrentemente no fluxo padrão").

---

## 4. Decisão: Provisionamento do Serviço Satélite Mock (`provider-ai`) no Docker

### Decisão
Criar o diretório `provider-ai/` com aplicação Laravel/PHP leve (ou endpoint HTTP enxuto espelhando `provider-rest`) rodando em container Docker dedicado `cardok-provider-ai` na porta 8003, integrado à rede `cardok-network` e com suporte a:
- `GET /health` para o healthcheck do Docker Compose.
- `GET /api/v1/debts/{plate}` retornando plaintext CSV com cabeçalho `Content-Type: text/plain`.
- Gestão de modo de simulação (`PROVIDER_MODE=success|error|timeout|invalid_response|empty`) e endpoints `/api/simulation/mode`.

### Justificativa
- Garante total paridade com os mocks existentes `provider-rest` (8001) e `provider-soap` (8002).
- Permite testes ponta a ponta reais via `docker compose` sem custos de API ou dependência de serviços externos de IA.

### Alternativas Consideradas
- **Reutilizar a rota em `provider-rest`**: Descartado na clarificação (Questão 4, Opção A escolhida) para manter isolamento de responsabilidades e independência de modos de simulação.

---

## 5. Decisão: Precisão Financeira e Determinismo

### Decisão
- Valores monetários do CSV serão convertidos diretamente para string com duas casas decimais e instanciados via `Money::fromDecimal($string)`.
- Nenhuma operação monetária usará `float`.
- Relógio injetável `ClockInterface` continua sendo utilizado nas políticas de juros.

### Justificativa
- Cumpre rigorosamente os Princípios II (Precisão Financeira) e V (Determinismo Temporal) da Constituição.
