# Data Model: Entidades e Alvos do Makefile do Cardok

**Feature**: `002-cardok-makefile`  
**Status**: Concluído  

---

## 1. Entidade: Alvo do Makefile (Make Target)

Representa uma operação executável via linha de comando padronizada.

### Atributos
* `name`: Identificador do comando (ex: `up`, `down`, `test`, `health`).
* `category`: Agrupamento funcional para exibição no `make help` (Ambiente, Qualidade/Testes, Diagnóstico, Operação, Demonstração).
* `description`: Texto resumido em pt-BR descrevendo o propósito do comando.
* `prerequisites`: Lista de dependências que devem rodar antes do alvo (ex: `check` depende conceitualmente de ambiente ativo).
* `shell_command`: Sequência de instruções POSIX a serem executadas.
* `exit_code_behavior`: Propagação direta de erro (código != 0) sem mascaramento (`|| true`).

---

## 2. Entidade: Serviço do Ecossistema Cardok

Mapeamento dos serviços orquestrados via Docker Compose.

| Serviço (`docker-compose.yml`) | Nome do Contêiner | Função | Porta Mapeada |
|---|---|---|---|
| `monolith` | `cardok-monolith` | Aplicação central Laravel 11 / Livewire 3 / API de Débitos | `8000:8000` |
| `provider-rest` | `cardok-provider-rest` | Mock REST Provider de débitos | `8001:8000` |
| `provider-soap` | `cardok-provider-soap` | Mock SOAP Provider de débitos | `8002:8000` |
| `payment-provider` | `cardok-payment-provider` | Mock Gateway de Pagamentos | Acesso interno (`8000`) |

---

## 3. Entidade: Cenário de Demonstração (Demo Scenario)

Representa um fluxo de negócio determinístico executável via comando de demonstração.

| Alvo | Placa | Parâmetros | Endpoint | Resultado Esperado |
|---|---|---|---|---|
| `demo-success` | `ABC1234` | `{}` | `POST /api/v1/vehicles/debts` | Débitos calculados (IPVA + Multa), juros Price, descontos PIX e parcelamento 1x/6x/12x |
| `demo-fallback` | `ABC1234` | `{"provider":"soap"}` | `POST /api/v1/vehicles/debts` | Consulta direcionada via provider SOAP, demonstrando resiliência e modelo canônico |
| `demo-invalid-plate` | `INVALID` | `{}` | `POST /api/v1/vehicles/debts` | HTTP 400 com erro estruturado `invalid_plate` e validação semântica |
