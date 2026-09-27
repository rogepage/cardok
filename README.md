# Cardok - Infraestrutura Inicial

Ambiente Docker inicial para o projeto **Cardok**, composto pelo monólito principal e pelos serviços externos simulados (REST, SOAP e Payment).

---

## 1. Arquitetura

O sistema é orquestrado via Docker Compose, com todos os serviços integrados em uma rede bridge isolada (`cardok-network`). O monólito se comunica com os providers utilizando os nomes dos serviços Docker como hostname.

```text
                    ┌────────────────────────┐
                    │   Monolith (Laravel)   │
                    │    localhost:8000      │
                    └───────────┬────────────┘
                                │
             ┌──────────────────┼──────────────────┐
             ↓                  ↓                  ↓
 ┌──────────────────────┐  ┌──────────────────────┐  ┌──────────────────────┐
 │ Provider REST        │  │ Provider SOAP        │  │ Payment Provider     │
 │ (Laravel)            │  │ (Laravel)            │  │ (PHP HTTP Mínimo)    │
 │ localhost:8001       │  │ localhost:8002       │  │ (Apenas rede interna)│
 └──────────────────────┘  └──────────────────────┘  └──────────────────────┘
```

---

## 2. Pré-requisitos

- **Docker**: versão 24+ (ou compatível)
- **Docker Compose**: v2+
- **Git**

> *Não é necessário ter PHP ou Composer instalados no host. Toda a execução e dependências são gerenciadas dentro dos containers.*

---

## 3. Serviços e Portas

| Serviço | Container | Porta Host | Porta Interna | Descrição |
| :--- | :--- | :--- | :--- | :--- |
| `monolith` | `cardok-monolith` | `8000` | `8000` | Sistema principal (Laravel) |
| `provider-rest` | `cardok-provider-rest` | `8001` | `8000` | Provedor externo simulado REST (Laravel) |
| `provider-soap` | `cardok-provider-soap` | `8002` | `8000` | Provedor externo simulado SOAP (Laravel) |
| `payment-provider` | `cardok-payment-provider` | *N/A* | `8000` | Provedor de pagamento mock (PHP nativo, apenas interno) |

---

## 4. Inicialização

1. Caso queira customizar variáveis de ambiente, copie o arquivo de exemplo:
   ```bash
   cp .env.example .env
   ```

2. Construa as imagens e inicialize todos os containers:
   ```bash
   docker compose up --build -d
   ```

3. Acompanhe os logs (opcional):
   ```bash
   docker compose logs -f
   ```

---

## 5. Health Checks e Validação dos Endpoints

Endpoints disponíveis para verificação de integridade de cada serviço:

### Monolith
```bash
curl -i http://localhost:8000/api/health
```
**Resposta esperada (HTTP 200):**
```json
{
    "status": "ok",
    "service": "monolith"
}
```

### Provider REST
```bash
curl -i http://localhost:8001/api/health
```
**Resposta esperada (HTTP 200):**
```json
{
    "status": "ok",
    "service": "provider-rest"
}
```

### Provider SOAP
```bash
curl -i http://localhost:8002/health
```
**Resposta esperada (HTTP 200):**
```json
{
    "status": "ok",
    "service": "provider-soap"
}
```

### Payment Provider (via Monolith / Rede Interna)
O serviço de pagamento não expõe porta para o host. Ele pode ser validado através de uma chamada executada dentro da rede interna:
```bash
docker compose exec monolith curl -i http://payment-provider:8000/health
```
**Resposta esperada (HTTP 200):**
```json
{
    "status": "ok",
    "service": "payment-provider"
}
```

---

## 6. Validação da Comunicação entre Containers

Para verificar se o container `monolith` consegue resolver o DNS e acessar todos os três serviços externos via rede interna Docker (`cardok-network`), há duas formas:

### Opção A: Comando Artisan (CLI)
```bash
docker compose exec monolith php artisan cardok:check-services
```
*Exibe uma tabela formatada no terminal detalhando o status, código HTTP, latência e payload de resposta de cada serviço.*

### Opção B: Endpoint HTTP de Diagnóstico
```bash
curl -s http://localhost:8000/api/health/integrations | jq .
```
*Retorna o status agregado e os detalhes de conectividade para cada dependência.*

---

## 7. External Providers

Serviços externos simulados para consulta de débitos veiculares por placa.

### Modos de Operação e Simulação de Falhas (`PROVIDER_MODE`)

Configurado via variável de ambiente em cada provider ou no arquivo `.env` raiz:

```env
PROVIDER_MODE=success
```

| Modo | HTTP Status | Comportamento |
| :--- | :--- | :--- |
| `success` | `200` | Resposta normal com os débitos ou lista vazia |
| `error` | `500` | Simulação de falha interna do provedor |
| `timeout` | `200` | Atraso proposital de 5 segundos antes de responder |
| `invalid_response` | `200` | Resposta com payload/XML corrompido fora do contrato |

---

### Provider REST

Endpoint para consulta de débitos via JSON:

```http
GET /api/v1/vehicles/{plate}/debts
```

#### Exemplo de Requisição (com débitos):
```bash
curl -i http://localhost:8001/api/v1/vehicles/ABC1234/debts
```

**Resposta (HTTP 200):**
```json
{
    "vehicle": "ABC1234",
    "debts": [
        {
            "type": "IPVA",
            "amount": 1500.0,
            "due_date": "2024-01-10"
        },
        {
            "type": "MULTA",
            "amount": 300.5,
            "due_date": "2024-02-15"
        }
    ]
}
```

#### Exemplo de Requisição (sem débitos):
```bash
curl -i http://localhost:8001/api/v1/vehicles/DEF5678/debts
```

**Resposta (HTTP 200):**
```json
{
    "vehicle": "DEF5678",
    "debts": []
}
```

---

### Provider SOAP

Endpoint funcional para consulta de débitos via XML:

```http
POST /soap
```

#### Exemplo de Requisição (com débitos):
```bash
curl -i -X POST http://localhost:8002/soap \
  -H "Content-Type: application/xml" \
  -d '<request><plate>ABC1234</plate></request>'
```

**Resposta (HTTP 200):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
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

#### Exemplo de Requisição (sem débitos):
```bash
curl -i -X POST http://localhost:8002/soap \
  -H "Content-Type: application/xml" \
  -d '<request><plate>DEF5678</plate></request>'
```

**Resposta (HTTP 200):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<response>
    <plate>DEF5678</plate>
    <debts/>
</response>
```

> **Atenção:** Quando não há débitos, o XML utiliza obrigatoriamente a tag auto-fechada `<debts/>` em vez de `<debts></debts>`.

---

### Execução de Testes Automatizados

Para rodar os testes dos provedores diretamente via Docker:

```bash
# Testes do Provider REST
docker compose exec provider-rest php artisan test

# Testes do Provider SOAP
docker compose exec provider-soap php artisan test
```

---

## 8. Resiliência: Retry, Fallback e Observabilidade

O monólito Cardok implementa uma estratégia de resiliência baseada em **Retry com Backoff Linear** e **Fallback Sequencial** entre provedores externos de débitos veiculares.

### 8.1. Parâmetros de Configuração

Configurados no arquivo `.env`:

```env
# Ordem de tentativa dos provedores (separados por vírgula)
PROVIDER_ORDER=rest,soap

# Timeout máximo em segundos por tentativa individual de cada provider
PROVIDER_TIMEOUT=2

# Número de retentativas após a tentativa inicial (2 = 1 inicial + 2 retries = 3 tentativas totais)
PROVIDER_RETRIES=2

# Tempo base de espera entre retentativas em milissegundos
PROVIDER_BACKOFF_MS=100
```

### 8.2. Fluxo de Execução e Fallback

```text
POST /api/v1/vehicles/debts {"placa":"ABC1234"}
   │
   ▼
[Provider 1 - ex: REST]
   ├── Tentativa 1 (timeout 2s)  ──> Falha (5xx ou Timeout)
   ├── Backoff (100ms)
   ├── Tentativa 2 (Retry #1)    ──> Falha
   ├── Backoff (200ms)
   └── Tentativa 3 (Retry #2)    ──> Falha
   │
   ▼ (Fallback Automático)
[Provider 2 - ex: SOAP]
   └── Tentativa 1 (timeout 2s)  ──> Sucesso (200 OK)
   │
   ▼
Resposta Unificada ao Cliente (HTTP 200 com Modelo Canônico)
```

### 8.3. Política de Retry e Fallback Imediato

* **Falhas de Infraestrutura (Elegíveis para Retry)**:
  * Timeouts de conexão ou leitura;
  * Conexão recusada / falhas de DNS;
  * Erros de servidor HTTP (`500`, `502`, `503`, `504`).
* **Erros Contratuais (Fallback Imediato sem Retry)**:
  * Resposta estruturalmente inválida (`invalid_response`), XML malformado ou ausência de campos essenciais. Nesses casos, uma retentativa imediata não consertaria a estrutura da resposta do provedor, logo o sistema faz fallback imediato para o próximo provedor.

### 8.4. Comportamento Quando Todos os Provedores Falham

Se todos os provedores da cadeia (`PROVIDER_ORDER`) esgotarem suas tentativas sem sucesso, o monólito responde com **HTTP 503 Service Unavailable**:

```json
{
    "error": "all_providers_unavailable"
}
```

> **Segurança**: Detalhes técnicos e stack traces não são expostos na resposta HTTP, sendo registrados exclusivamente nos logs estruturados.

### 8.5. Observabilidade e Logs Estruturados

Eventos de falha, retentativas e fallback são emitidos com contexto estruturado e **mascaramento de dados sensíveis** (LGPD / Segurança):

* **Formato da Placa nos Logs**: os últimos caracteres são mascarados (ex: `ABC****`).
* **Campos Registrados**: `event`, `provider`, `attempt`, `error`, `duration_ms`, `plate`.

Exemplo de log emitido:
```json
{
    "event": "vehicle_provider_failed",
    "provider": "rest",
    "attempt": 2,
    "error": "REST provider responded with server error status 500",
    "duration_ms": 2004,
    "plate": "ABC****",
    "is_fatal_for_provider": false
}
```

### 8.6. Consistência e Decisão Arquitetural

O fallback existe **estritamente para disponibilidade**. Se o primeiro provedor responder com sucesso, os provedores subsequentes não são consultados. Caso o primeiro falhe e o segundo assuma, a resposta retornada pelo segundo provedor é considerada autoritativa para a operação. Não há conciliação ou merge de débitos entre provedores concorrentes nesta fase.

---

## 9. Comandos Úteis

- **Validar sintaxe do Docker Compose**:
  ```bash
  docker compose config
  ```
- **Verificar status e saúde dos containers**:
  ```bash
  docker compose ps
  ```
- **Parar o ambiente**:
  ```bash
  docker compose down
  ```
- **Parar e remover volumes**:
  ```bash
  docker compose down -v
  ```
