# Observabilidade no Cardok — Guia e Arquitetura

Este documento descreve a camada de observabilidade implementada no projeto **Cardok**, desenvolvida sob os padrões esperados para um teste técnico de **Staff Engineer**.

---

## 1. Visão Geral e Princípios

O objetivo principal desta implementação **não** é subir uma plataforma pesada de telemetria (Grafana, Prometheus, ELK ou OpenTelemetry collector), mas sim responder de forma instantânea e estruturada às seguintes perguntas operacionais:

1. **Qual requisição aconteceu?** (`request_id`, método HTTP, rota).
2. **Qual placa foi consultada?** (com mascaramento seguro para conformidade LGPD: `ABC****`).
3. **Qual provedor foi utilizado?** (`rest` ou `soap`).
4. **Quantas tentativas foram feitas?** (`attempt` de 1 a 3).
5. **Houve fallback?** (`from`, `to`, `reason`).
6. **Quanto tempo cada provedor demorou?** (`duration_ms` por chamada e total da consulta).
7. **Por que uma consulta falhou?** (`error_type`, `status_code`, motivo detalhado sem stack trace em INFO).
8. **Qual foi o resultado final?** (quantidade de débitos retornados, valores calculados e opções de pagamento).

---

## 2. Componentes Arquiteturais

```text
Cliente (Browser / API)
       │
       │ HTTP Request [Header opcional: X-Request-ID]
       ▼
RequestIdMiddleware
       │
       ├── 1. Valida/Gera Request ID (UUID v4)
       ├── 2. Injeta em Log::withContext(['request_id' => ...])
       ├── 3. Emite log 'request.received'
       └── 4. Adiciona 'X-Request-ID' no Response Header
       ▼
VehicleDebtIntegrationController / UseCase
       │
       ├── Inicia cronômetro (microtime)
       ├── Executa consulta com ProviderExecutor
       │      ├── provider.request (tentativa, placa mascarada)
       │      ├── provider.retry (caso ocorra erro transitório)
       │      └── provider.response (sucesso, duração ms)
       │
       ├── Alterna provedores via VehicleDebtService
       │      └── provider.fallback (de rest para soap com motivo)
       │
       ├── Emite vehicle_debt.completed (sucesso + duração total)
       └── Emite vehicle_debt.failed (em caso de erro de negócio ou indisponibilidade)
```

---

## 3. Catálogo de Eventos Estruturados (JSON)

Todos os logs são emitidos em **JSON de linha única (NDJSON)** pelo `StructuredJsonFormatter`, facilitando parsing, grep e ingestão direta por coletores de logs.

### 3.1. `request.received`
Emitido no momento em que uma requisição de negócio chega à aplicação:
```json
{
  "timestamp": "2026-09-28T13:34:47.042Z",
  "level": "INFO",
  "message": "request.received",
  "event": "request.received",
  "request_id": "req-audit-staff-001",
  "method": "POST",
  "path": "api/v1/vehicles/debts"
}
```

### 3.2. `provider.request`
Emitido antes de cada tentativa de chamada a um provedor externo:
```json
{
  "timestamp": "2026-09-28T13:34:47.385Z",
  "level": "INFO",
  "message": "provider.request",
  "event": "provider.request",
  "request_id": "req-audit-staff-001",
  "provider": "rest",
  "operation": "get_debts",
  "plate": "ABC****",
  "attempt": 1
}
```

### 3.3. `provider.retry`
Emitido quando uma chamada falha por indisponibilidade transitória ou timeout e uma nova tentativa é agendada:
```json
{
  "timestamp": "2026-09-28T13:36:03.150Z",
  "level": "WARNING",
  "message": "provider.retry",
  "event": "provider.retry",
  "request_id": "scenario-2-fallback-verified",
  "provider": "rest",
  "attempt": 2,
  "max_attempts": 3,
  "reason": "Connection to REST provider failed or timed out",
  "backoff_ms": 100
}
```

### 3.4. `provider.fallback`
Emitido quando todas as tentativas do provedor atual se esgotaram e o fluxo transiciona para o provedor secundário:
```json
{
  "timestamp": "2026-09-28T13:36:03.472Z",
  "level": "WARNING",
  "message": "provider.fallback",
  "event": "provider.fallback",
  "request_id": "scenario-2-fallback-verified",
  "from": "rest",
  "to": "soap",
  "reason": "Connection to REST provider failed or timed out",
  "plate": "ABC****"
}
```

### 3.5. `provider.response`
Emitido quando a resposta do provedor é recebida e convertida para o modelo canônico:
```json
{
  "timestamp": "2026-09-28T13:34:47.715Z",
  "level": "INFO",
  "message": "provider.response",
  "event": "provider.response",
  "request_id": "req-audit-staff-001",
  "provider": "rest",
  "status": "success",
  "duration_ms": 331,
  "debts_count": 2
}
```

### 3.6. `vehicle_debt.completed`
Emitido ao final do processamento da consulta de débitos (incluindo cálculo de juros/multas e simulação de parcelamento):
```json
{
  "timestamp": "2026-09-28T13:34:47.729Z",
  "level": "INFO",
  "message": "vehicle_debt.completed",
  "event": "vehicle_debt.completed",
  "request_id": "req-audit-staff-001",
  "plate": "ABC****",
  "provider": "rest",
  "debts_count": 2,
  "duration_ms": 344
}
```

### 3.7. `vehicle_debt.failed`
Emitido quando a consulta não pôde ser completada (falha de validação ou indisponibilidade de todos os provedores):
```json
{
  "timestamp": "2026-09-28T13:37:19.529Z",
  "level": "ERROR",
  "message": "vehicle_debt.failed",
  "event": "vehicle_debt.failed",
  "request_id": "scenario-4-both-down-req",
  "plate": "ABC****",
  "error_type": "all_providers_unavailable",
  "status_code": 503,
  "reason": "All vehicle debt providers failed",
  "duration_ms": 686
}
```

---

## 4. LGPD — Mascaramento Centralizado da Placa

Para cumprir as diretrizes da LGPD (Lei Geral de Proteção de Dados), identificadores veiculares jamais devem ser persistidos em formato aberto nos logs.

A classe `App\Application\Support\PlateMasker` encapsula essa responsabilidade:
- Entrada: `ABC1234` ou `BRA2E19`
- Saída: `ABC****` e `BRA****`
- Proteção contra entradas nulas ou vazias (`***`).
- Nenhuma chamada a `substr()` ou lógica ad-hoc é dispersa nos controllers ou provedores.

---

## 5. Métricas Leves em Tempo Real

A classe `SimpleMetricsRegistry` implementa contadores atômicos com persistência resiliente em cache (Driver Database/SQLite) e buffer em memória para fallbacks rápidos.

### Endpoint: `GET /api/metrics`

```bash
curl -s http://localhost:8000/api/metrics | jq .
```

Resposta:
```json
{
  "status": "ok",
  "metrics": {
    "vehicle_debt_requests_total": 42,
    "vehicle_debt_success_total": 38,
    "vehicle_debt_error_total": 4,
    "provider_requests_total": 55,
    "provider_failures_total": 17,
    "provider_retries_total": 12,
    "provider_fallbacks_total": 5
  }
}
```

---

## 6. Distinção entre Health do Sistema e Dependências Externas

Para evitar alarmes falsos de infraestrutura:

1. **`GET /api/health`**:
   - Mede se o Cardok está executando normalmente e apto a processar requisições.
   - Retorna HTTP 200 independentemente do status de provedores externos.
   - Configurado no `healthcheck` do Docker Compose.

2. **`GET /api/health/integrations`**:
   - Diagnostica o estado das conexões com `provider-rest`, `provider-soap` e `payment-provider`.
   - Se um provedor estiver fora, retorna `status: degraded` e HTTP 503, permitindo intervenção cirúrgica sem derrubar o container do monólito.

3. **`php artisan cardok:check-services`**:
   - Utilitário de linha de comando para validação rápida no terminal do operador.

---

## 7. Trade-offs

> **Para este Home Test optamos por observabilidade baseada em logs estruturados e correlation ID, sem introduzir uma stack completa como Prometheus, Grafana ou OpenTelemetry. Isso mantém a infraestrutura simples e suficiente para demonstrar diagnóstico de falhas, retry, fallback e latência. Uma evolução futura poderia exportar esses mesmos eventos para uma plataforma centralizada de observabilidade.**

### Vantagens:
- **Zero Overhead**: Não exige containers pesados de Elasticsearch, Logstash, Prometheus ou OpenTelemetry Collector.
- **Transparência**: Permite inspecionar logs com `docker compose logs -f monolith` e filtrar eventos com comandos shell comuns (`grep`, `jq`).
- **Compatibilidade Cloud Native**: Os logs em NDJSON já estão 100% prontos para ingestão pelo AWS CloudWatch, Google Cloud Logging, Datadog ou Grafana Loki caso o projeto seja implantado em produção.
