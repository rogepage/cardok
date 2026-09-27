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

## 7. Comandos Úteis

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
