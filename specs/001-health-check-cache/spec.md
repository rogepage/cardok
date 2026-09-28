# Feature Specification: Health Check Integration Cache

**Feature Branch**: `001-health-check-cache`  
**Created**: 2026-09-28  
**Status**: Draft  
**Input**: User description: "vamos criar a spec para o cache do health check"

---

## Clarifications

### Session 2026-09-28
- Q: Como o endpoint GET /api/health/integrations deve lidar com tentativas de bypass de cache? → A: Permitir bypass se o header `Cache-Control: no-cache` for enviado.
- Q: A resposta de GET /api/health/integrations deve incluir o cabeçalho X-Cache (HIT/MISS)? → A: Sim, retornar o header HTTP `X-Cache` com valor `HIT` ou `MISS`.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Fast Repeated Health Checks with Cache Hit (Priority: P1)

Como um agente de monitoramento ou probe de orquestrador (Kubernetes/ECS), quero consultar `GET /api/health/integrations` repetidas vezes e receber uma resposta instantânea em menos de 15ms quando houver uma medição recente em cache, evitando sobrecarga desnecessária na rede e nos serviços integrados.

**Why this priority**: É o valor central da funcionalidade: proteger a infraestrutura e reduzir latência de 100ms+ para <15ms em chamadas consecutivas de alta frequência.

**Independent Test**:
- Disparar uma requisição inicial ao endpoint (status 200).
- Disparar uma segunda requisição imediatamente (100ms depois) simulando falha ou indisponibilidade nos provedores externos.
- A segunda requisição deve responder 200 com os dados em cache, sem realizar novas chamadas HTTP externas.

**Acceptance Scenarios**:
1. **Given** que o endpoint foi consultado com sucesso há 2 segundos, **When** um novo GET for recebido, **Then** o sistema retorna a resposta anterior em cache sem fazer requisições de rede aos serviços externos.
2. **Given** uma requisição atendida via cache, **When** inspecionado o payload de resposta, **Then** a resposta deve ser idêntica à do payload original.
3. **Given** uma resposta gerada a partir do cache, **When** inspecionados os cabeçalhos HTTP, **Then** a resposta deve conter o cabeçalho `X-Cache: HIT`.
4. **Given** uma resposta originada de sondagem ativa aos serviços externos (cache miss, expiração ou bypass), **When** inspecionados os cabeçalhos HTTP, **Then** a resposta deve conter o cabeçalho `X-Cache: MISS`.

---

### User Story 2 - Cache Expiry and Fresh Diagnostic Re-evaluation (Priority: P2)

Como engenheiro de confiabilidade, quero que o status em cache expire automaticamente após 5 segundos, garantindo que mudanças no estado de saúde dos serviços externos sejam refletidas tempestivamente.

**Why this priority**: Garante que o cache não mascare falhas reais dos serviços por períodos prolongados, preservando a utilidade diagnóstica do endpoint.

**Independent Test**:
- Consultar o endpoint no instante $T_0$.
- Avançar o tempo em mais de 5 segundos ($T_0 + 6s$).
- Confirmar que a nova consulta dispara chamadas reais aos serviços externos e renova o cache.

**Acceptance Scenarios**:
1. **Given** que o cache expirou há mais de 5 segundos, **When** uma nova requisição for recebida, **Then** o sistema consulta novamente os 3 provedores externos e renova o cache por mais 5 segundos.
2. **Given** que há um diagnóstico válido em cache, **When** uma requisição for enviada com o cabeçalho `Cache-Control: no-cache`, **Then** o sistema ignora o cache, realiza sondagens ativas aos serviços externos e atualiza o cache com o novo estado.

---

### User Story 3 - Degraded State Reporting Cached Consistently (Priority: P2)

Como administrador do sistema, quero que falhas detectadas em qualquer um dos serviços externos retornem status `503 Service Unavailable` com `"status": "degraded"`, e que esse status também seja armazenado no cache pelo mesmo TTL para evitar tempestades de retry (*thundering herd*) durante incidentes externos.

**Why this priority**: Evita amplificação de incidentes contra serviços parceiros já sobrecarregados ou indisponíveis.

**Acceptance Scenarios**:
1. **Given** que um dos serviços externos responde com erro, **When** o endpoint for consultado, **Then** responde HTTP 503 com status `degraded` e armazena o resultado no cache por 5 segundos.
2. **Given** uma falha armazenada em cache, **When** novas requisições chegarem dentro de 5 segundos, **Then** o endpoint responde 503 diretamente do cache sem re-executar sondagens externas.

---

### Edge Cases

- **Provedor Externo Lento durante Cache Miss:** Se um provedor demorar mais do que o timeout configurado (2s), a chamada aborta com erro e o status `degraded` é retornado e mantido no cache por 5 segundos.
- **Ambientes de Teste Automatizado:** Testes automatizados devem conseguir desabilitar ou limpar o cache via `Cache::flush()` para manter testes determinísticos e isolados.
- **Falha no Driver de Cache:** Se o driver de cache falhar ou lançar exceção, o endpoint deve executar o diagnóstico diretamente em modo fallback transparente sem quebrar a requisição.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O endpoint `GET /api/health/integrations` DEVE armazenar o resultado do diagnóstico de integrações em cache com tempo de expiração (TTL) de exatamente **5 segundos**.
- **FR-002**: Durante o período de validade do cache (5s), qualquer requisição subsequente DEVE retornar o payload e status HTTP armazenados sem efetuar chamadas HTTP aos endpoints externos (`provider-rest`, `provider-soap`, `payment-provider`).
- **FR-003**: Após a expiração dos 5 segundos, a próxima requisição DEVE executar uma nova checagem completa e atualizar o cache com o novo estado.
- **FR-004**: O cache DEVE armazenar tanto estados saudáveis (`200 OK`, `status: ok`) quanto estados degradados (`503 Service Unavailable`, `status: degraded`).
- **FR-005**: O mecanismo de cache DEVE utilizar o driver de cache do Laravel com chave canônica `health:integrations:status`.
- **FR-006**: O endpoint DEVE permitir o bypass do cache quando a requisição contiver o cabeçalho HTTP `Cache-Control: no-cache`, disparando imediatamente uma verificação ativa de rede e renovando o registro em cache.
- **FR-007**: O endpoint DEVE retornar o cabeçalho HTTP `X-Cache: HIT` quando a resposta for servida a partir do cache e `X-Cache: MISS` quando a resposta envolver a consulta ativa aos serviços externos.

### Key Entities

- **HealthIntegrationStatus**: Objeto contendo o status global (`ok` ou `degraded`), identificador do serviço (`monolith`) e o mapa detalhado de diagnósticos individuais (`services`).
- **ProviderDiagnostic**: Resultado individual de conectividade de cada dependência externa (`provider-rest`, `provider-soap`, `payment-provider`), contendo `status`, `url`, `http_code`, `data` ou `error`.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: O tempo de resposta para requisições atendidas com cache hit DEVE ser inferior a **15 milissegundos**.
- **SC-002**: Sob rajada de 50 requisições consecutivas em 2 segundos ao endpoint `/api/health/integrations`, apenas **1 conjunto de chamadas externas** (3 requisições HTTP) deve ser disparado aos serviços externos.
- **SC-003**: 100% dos testes existentes do Cardok (110 testes do monólito) continuam passando sem regressões.

---

## Assumptions

- O driver de cache padrão do Laravel configurado para a aplicação (`array` em testes, `file`/`redis` em produção) suporta expiração por TTL em segundos.
- O intervalo de polling de sondas do Kubernetes / monitoramento varia entre 1s e 10s, tornando 5 segundos de TTL um equilíbrio ideal entre alívio de carga e precisão diagnóstica.
- O endpoint de saúde básico `GET /api/health` não faz chamadas externas e, portanto, não requer cache.

---

## Constitution & Architectural Alignment

- **Princípio I (Clean Architecture):** O cache de diagnóstico pertence estritamente à camada `Infrastructure` / `Http` de infraestrutura e telemetria. Nenhuma classe de domínio de débitos ou pagamentos é alterada.
- **Princípio II (Defensive Contracts):** O contrato de resposta JSON permanece inalterado (`status`, `service`, `services`), preservando compatibilidade com qualquer cliente existente.
- **Princípio IV (Resilience & Timeouts):** Preserva o timeout estrito configurado para provedores externos (`services.providers.timeout`).
- **Princípio VI (Observabilidade):** O cache preserva a geração de `Request-ID` para cada requisição HTTP recebida e registra o log da consulta com seu status.
- **Princípio VII (Test Discipline):** Novos testes cobrirão exaustivamente cenários de cache hit, expiração e persistência de status 503.
