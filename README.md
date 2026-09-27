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

Os provedores podem retornar informações divergentes para a mesma placa. A estratégia atual prioriza disponibilidade e utiliza o primeiro provedor que responder com sucesso, portanto não realiza reconciliação entre provedores durante a requisição. Em uma evolução do sistema, poderíamos realizar consultas paralelas a múltiplos provedores e aplicar uma política de conciliação baseada em tipo, valor, vencimento e identificadores do débito, além de registrar divergências para análise.

---

## 9. Domínio e Regras de Juros

As regras de negócio relacionadas a débitos veiculares são estritamente isoladas na camada de **Domínio** (`App\Domain\Debt`), sem acoplamento com HTTP, Controllers, banco de dados ou provedores externos.

### 9.1. Data de Referência Fixa e Abstração de Relógio

Para garantir determinismo e reprodutibilidade nos cálculos de atraso, o sistema utiliza uma abstração de relógio (`App\Domain\Debt\Clock\ClockInterface`), instanciada por padrão com:

```text
2024-05-10T00:00:00Z (UTC)
```

Todas as comparações de data são normalizadas em UTC e utilizam `CarbonImmutable`.

### 9.2. Dias de Atraso

O cálculo dos dias de atraso considera a diferença entre a data de referência e a data de vencimento:

```text
dias_atraso = max(0, data_referencia - data_vencimento)
```

* Se `data_vencimento >= data_referencia` (`dias_atraso == 0`): não há incidência de juros (`juros = 0` e `valor_atualizado = valor_original`).

### 9.3. Políticas de Juros por Tipo de Débito

O cálculo utiliza o padrão Strategy / Policy Registry (`DebtInterestPolicyRegistry`), permitindo extensão para novos tipos sem alterar classes existentes:

| Tipo | Taxa Diária | Teto de Juros | Fórmula de Juros |
| :--- | :--- | :--- | :--- |
| `IPVA` | `0,33%` (`0.0033`) | `20%` do valor original | `min(valor_original × 0.0033 × dias_atraso, valor_original × 0.20)` |
| `MULTA` | `1,00%` (`0.01`) | *Sem teto* | `valor_original × 0.01 × dias_atraso` |

Após o cálculo dos juros com arredondamento `HALF_UP`:
```text
valor_atualizado = valor_original + juros
```

### 9.4. Política de Arredondamento (HALF_UP) e Precisão Monetária

A política de arredondamento é consistente em todo o domínio e obedece às seguintes regras:

* **Modo**: `HALF_UP` (arredonda frações $\ge 0,5$ centavos para cima, afastando de zero).
* **Escala**: 2 casas decimais (precisão ao centavo).
* **Sem Float**: É estritamente proibido o uso de tipos `float` para cálculos monetários no domínio, prevenindo erros de representação binária IEEE-754.
* **Preservação de Precisão**: Cálculos intermediários preservam a máxima precisão matemática através de aritmética inteira e frações racionais no utilitário de domínio `App\Domain\Debt\Rounding\HalfUpRounder`. Arredonda-se estritamente no ponto final de definição do valor monetário de cada débito.

#### Fluxo de Cálculo de Juros por Tipo

1. **MULTA**:
   * O juros é calculado com precisão total:
     $$\text{juros\_calculado} = \text{valor\_original} \times 0.01 \times \text{dias\_atraso}$$
   * Exemplo: $\text{R\$ } 300,50 \times 0.01 \times 85 = 255,425$.
   * O arredondamento `HALF_UP` é aplicado sobre o juros resultante: $255,425 \xrightarrow{\text{HALF\_UP}} 255,43$.
   * $\text{valor\_atualizado} = \text{valor\_original} + \text{juros\_arredondado} = 300,50 + 255,43 = 555,93$.

2. **IPVA**:
   * O cálculo dos juros e do teto preserva a precisão antes de qualquer arredondamento:
     $$\text{juros\_calculado} = \text{valor\_original} \times 0.0033 \times \text{dias\_atraso}$$
     $$\text{juros\_teto} = \text{valor\_original} \times 0.20$$
     $$\text{juros\_aplicado} = \min(\text{juros\_calculado}, \text{juros\_teto})$$
   * O arredondamento `HALF_UP` para 2 casas é aplicado sobre o `juros\_aplicado`.
   * $\text{valor\_atualizado} = \text{valor\_original} + \text{juros\_aplicado}$.

#### Totais Consolidados

Os totais do resumo (`resumo`) são calculados pela soma dos valores monetários já normalizados para 2 casas decimais (centavos inteiros):
* $\text{total\_original} = \sum \text{valores\_originais}$
* $\text{total\_atualizado} = \sum \text{valores\_atualizados}$

Não há múltiplos arredondamentos em cascata nem arredondamento sobre a soma de frações não arredondadas.

### 9.5. Tratamento de Tipos de Débito Desconhecidos (HTTP 422)

Os tipos suportados nesta fase são estritamente `IPVA` e `MULTA`:
* Se o provedor retornar um tipo não suportado (ex: `LICENCIAMENTO`), o domínio lança `UnknownDebtTypeException`.
* A camada HTTP intercepta essa exceção de domínio e responde com **HTTP 422 Unprocessable Content**:

```json
{
    "error": "unknown_debt_type",
    "type": "LICENCIAMENTO"
}
```

> **Atenção**: Nenhum débito desconhecido é descartado silenciosamente ou renomeado para "OUTROS".

### 9.6. Exemplo de Resposta Completa da API

```http
POST /api/v1/vehicles/debts
Content-Type: application/json

{"placa": "ABC1234"}
```

**Resposta (HTTP 200):**
```json
{
    "placa": "ABC1234",
    "debitos": [
        {
            "tipo": "IPVA",
            "valor_original": "1500.00",
            "valor_atualizado": "1800.00",
            "vencimento": "2024-01-10",
            "dias_atraso": 121
        },
        {
            "tipo": "MULTA",
            "valor_original": "300.50",
            "valor_atualizado": "555.93",
            "vencimento": "2024-02-15",
            "dias_atraso": 85
        }
    ],
    "resumo": {
        "total_original": "1800.50",
        "total_atualizado": "2355.93"
    },
    "pagamentos": {
        "opcoes": [
            {
                "tipo": "TOTAL",
                "valor_base": "2355.93",
                "pix": {
                    "total_com_desconto": "2238.13"
                },
                "cartao_credito": {
                    "parcelas": [
                        {
                            "quantidade": 1,
                            "valor_parcela": "2355.93"
                        },
                        {
                            "quantidade": 6,
                            "valor_parcela": "427.72"
                        },
                        {
                            "quantidade": 12,
                            "valor_parcela": "229.67"
                        }
                    ]
                }
            },
            {
                "tipo": "SOMENTE_IPVA",
                "valor_base": "1800.00",
                "pix": {
                    "total_com_desconto": "1710.00"
                },
                "cartao_credito": {
                    "parcelas": [
                        {
                            "quantidade": 1,
                            "valor_parcela": "1800.00"
                        },
                        {
                            "quantidade": 6,
                            "valor_parcela": "326.79"
                        },
                        {
                            "quantidade": 12,
                            "valor_parcela": "175.48"
                        }
                    ]
                }
            },
            {
                "tipo": "SOMENTE_MULTA",
                "valor_base": "555.93",
                "pix": {
                    "total_com_desconto": "528.13"
                },
                "cartao_credito": {
                    "parcelas": [
                        {
                            "quantidade": 1,
                            "valor_parcela": "555.93"
                        },
                        {
                            "quantidade": 6,
                            "valor_parcela": "100.93"
                        },
                        {
                            "quantidade": 12,
                            "valor_parcela": "54.20"
                        }
                    ]
                }
            }
        ]
    }
}
```

---

## 10. Domínio de Pagamentos e Simulação de Opções

O domínio de **Pagamentos** (`App\Domain\Payment`) é completamente desacoplado do domínio de débitos (`App\Domain\Debt`), gateways externos e controllers HTTP. O simulador (`PaymentSimulator`) recebe os débitos já calculados (`CalculatedVehicleDebts`) e gera as alternativas de liquidação disponíveis.

### 10.1. Valor Base: Atualizado vs. Original

O `valor_base` de qualquer modalidade de pagamento é **sempre o valor atualizado** com juros de atraso (`valor_atualizado`), nunca o valor original de face (`valor_original`).
* Exemplo: IPVA original de R$ 1.500,00 com R$ 300,00 de juros resulta em `valor_base = 1800.00`.

### 10.2. Agrupamento por Tipo e Ordem Determinística

1. **Opção `TOTAL`**:
   * Sempre apresentada como primeira opção.
   * Utiliza o `total_atualizado` consolidado de todos os débitos do veículo.
2. **Opções Parciais (`SOMENTE_<TIPO>`)**:
   * Débitos do mesmo tipo são consolidados em uma única opção parcial.
   * A ordem das opções segue estritamente a ordem de primeira aparição na lista de débitos original.
   * Se um veículo possuir múltiplos débitos de IPVA (ex: R$ 100, R$ 200, R$ 300), é gerada apenas uma opção `SOMENTE_IPVA` com `valor_base = 600.00`.
3. **Veículo sem Débitos**:
   * Quando o veículo não possui débitos pendentes, o simulador retorna `"pagamentos": {"opcoes": []}`, sem inventar opções fictícias.

### 10.3. PIX com Desconto de 5%

* Aplicado sobre o `valor_base` de **todas** as opções (TOTAL e parciais):
  $$\text{total\_com\_desconto} = \text{valor\_base} \times 0.95$$
* Arredondamento executado via `HALF_UP` para 2 casas decimais.

### 10.4. Cartão de Crédito e Tabela Price (PMT)

O cartão de crédito oferece exatamente 3 modalidades de parcelamento:
* **1x (à vista)**: sem juros ($\text{valor\_parcela} = \text{valor\_base}$).
* **6x e 12x**: amortização pelo sistema Francês/Price com taxa de juros compostos de **2,5% ao mês** ($i = 0.025$).
  $$\text{PMT} = \frac{\text{base} \times i \times (1+i)^n}{(1+i)^n - 1}$$
* Preserva-se precisão matemática total nas etapas de potenciação e quociente, aplicando `HALF_UP` para 2 casas exclusivamente no valor final da parcela.

### 10.5. Tabela Resumo do Cenário Oficial (Placa `ABC1234`)

| Opção | Valor Base | PIX (5% desc.) | Cartão 1x | Cartão 6x (2,5% a.m.) | Cartão 12x (2,5% a.m.) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TOTAL** | R$ 2.355,93 | R$ 2.238,13 | R$ 2.355,93 | R$ 427,72 | R$ 229,67 |
| **SOMENTE_IPVA** | R$ 1.800,00 | R$ 1.710,00 | R$ 1.800,00 | R$ 326,79 | R$ 175,48 |
| **SOMENTE_MULTA** | R$ 555,93 | R$ 528,13 | R$ 555,93 | R$ 100,93 | R$ 54,20 |

---

## 11. Comandos Úteis

- **Validar sintaxe do Docker Compose**:
  ```bash
  docker compose config
  ```
- **Verificar status e saúde dos containers**:
  ```bash
  docker compose ps
  ```
- **Executar todos os testes automatizados**:
  ```bash
  docker compose exec monolith php artisan test
  docker compose exec provider-rest php artisan test
  docker compose exec provider-soap php artisan test
  ```
- **Parar o ambiente**:
  ```bash
  docker compose down
  ```
- **Parar e remover volumes**:
  ```bash
  docker compose down -v
  ```
