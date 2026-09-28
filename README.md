# Cardok

> Backend para consulta, normalização, cálculo de juros e simulação de pagamento de débitos veiculares.  
> Projeto desenvolvido como **Home Test técnico** para a **DOK Despachante**.

---

## Sumário

- [1. Visão Geral](#1-visão-geral)
- [2. Objetivos Implementados](#2-objetivos-implementados)
- [3. Arquitetura da Solução](#3-arquitetura-da-solução)
- [4. Estrutura do Projeto](#4-estrutura-do-projeto)
- [5. Padrões de Projeto e Princípios](#5-padrões-de-projeto-e-princípios)
- [6. Fluxo de Consulta de Débitos](#6-fluxo-de-consulta-de-débitos)
- [7. Provedores Externos (REST e SOAP)](#7-provedores-externos-rest-e-soap)
- [8. Estratégia de Resiliência: Retry e Fallback](#8-estratégia-de-resiliência-retry-e-fallback)
- [9. Regras de Negócio e Cálculo de Juros](#9-regras-de-negócio-e-cálculo-de-juros)
- [10. Simulação de Pagamentos (PIX e Cartão)](#10-simulação-de-pagamentos-pix-e-cartão)
- [11. Documentação da API](#11-documentação-da-api)
- [12. Tratamento Defensivo de Erros](#12-tratamento-defensivo-de-erros)
- [13. Observabilidade e Telemetria](#13-observabilidade-e-telemetria)
- [Comandos úteis](#comandos-úteis)
- [14. Como Executar com Docker](#14-como-executar-com-docker)
- [15. Diagnóstico e Health Checks](#15-diagnóstico-e-health-checks)
- [16. Execução de Testes Automatizados](#16-execução-de-testes-automatizados)
- [17. Decisões Técnicas e Trade-offs](#17-decisões-técnicas-e-trade-offs)
- [18. Segurança da Aplicação](#18-segurança-da-aplicação)
- [19. Melhorias Futuras](#19-melhorias-futuras)
- [20. Desenvolvimento Assistido por IA e Spec Kit](#20-desenvolvimento-assistido-por-ia-e-spec-kit)
- [21. Licença e Contexto](#21-licença-e-contexto)

---

## 1. Visão Geral

O **Cardok** é um serviço backend desenhado para orquestrar consultas de débitos veiculares (como IPVA e Multas) distribuídas entre múltiplos provedores externos heterogêneos.

A aplicação resolve três desafios centrais de integração:
1. **Heterogeneidade de Protocolos**: Realiza chamadas síncronas contra serviços externos em formato **REST (JSON)** e **SOAP (XML)**, normalizando os dados em um modelo canônico de domínio.
2. **Atualização Financeira Determinística**: Aplica regras legais de encargos diários de mora (IPVA com teto percentual e Multa linear), utilizando tipos de valor monetário imutáveis baseados em centavos inteiros.
3. **Simulação de Meios de Pagamento**: Oferece opções de quitação total e parcial por categoria de débito, calculando desconto à vista para PIX e parcelamento no cartão de crédito via amortização pela Tabela Price.

> **Nota sobre o ambiente:** Os serviços externos (`provider-rest`, `provider-soap` e `payment-provider`) são simulados em containers Docker isolados para viabilizar testes de integração ponta a ponta sem dependência de serviços legados reais ou cobranças financeiras.

---

## 2. Objetivos Implementados

O projeto atende a todos os requisitos do Home Test:

- [x] **Múltiplos Provedores Externos**: Suporte a provedores REST e SOAP em portas e containers independentes.
- [x] **Normalização Canônica**: Desacoplamento entre o formato externo dos fornecedores e o modelo interno da aplicação.
- [x] **Cálculo Preciso de Juros**: Políticas de cálculo separadas para IPVA (0,33%/dia com teto de 20%) e MULTA (1,00%/dia sem teto).
- [x] **Simulação de Pagamentos**: Cálculo de PIX com 5% de desconto e Cartão em 1x, 6x e 12x a 2,5% a.m. (Tabela Price).
- [x] **Opções Totais e Parciais**: Geração de opção `TOTAL` e opções individuais agrupadas (`SOMENTE_IPVA`, `SOMENTE_MULTA`).
- [x] **Resiliência Transparente**: Retries lineares com backoff e fallback automático (*First Success Wins*).
- [x] **Design Defensivo**: Validação de formato de placa (Tradicional e Mercosul) e rejeição imediata de campos desconhecidos.
- [x] **Observabilidade Estruturada**: Mascaramento de dados sensíveis nos logs, propagação de `X-Request-ID`, contadores atômicos em `/api/metrics` e telemetria de cache com `X-Cache`.
- [x] **Testes Automatizados**: 100% de testes verdes (128 testes no ecossistema: 114 no monólito, 7 no REST e 7 no SOAP).

---

## 3. Arquitetura da Solução

O ecossistema é baseado em um **Monólito Modular** desenvolvido em Laravel 10 (PHP 8.2+) acompanhado de três containers satélites de suporte conectados através de uma rede bridge privada (`cardok-network`):

```text
                                 [ Cliente HTTP / API Caller ]
                                               │
                                               ▼
                              ┌───────────────────────────────────┐
                              │      Cardok Monolith (8000)       │
                              │   (Orquestração, Domínio e API)   │
                              └─────────────────┬─────────────────┘
                                                │
                     ┌──────────────────────────┼──────────────────────────┐
                     │ (cardok-network)         │                          │
                     ▼                          ▼                          ▼
         ┌───────────────────────┐  ┌───────────────────────┐  ┌───────────────────────┐
         │ cardok-provider-rest  │  │ cardok-provider-soap  │  │ cardok-payment-provider│
         │   (Host: 8001 / REST) │  │   (Host: 8002 / SOAP) │  │ (Apenas rede interna) │
         └───────────────────────┘  └───────────────────────┘  └───────────────────────┘
```

### Papel de Cada Container

| Container | Serviço Docker | Porta Host | Porta Interna | Responsabilidade |
| :--- | :--- | :--- | :--- | :--- |
| `cardok-monolith` | `monolith` | `8000` | `8000` | Núcleo da aplicação: validação, orquestração, regras de negócio e API |
| `cardok-provider-rest` | `provider-rest` | `8001` | `8000` | Provedor externo simulado que responde em JSON via HTTP REST |
| `cardok-provider-soap` | `provider-soap` | `8002` | `8000` | Provedor externo simulado que processa e responde envelopes XML |
| `cardok-payment-provider` | `payment-provider` | *N/A* | `8000` | Mock leve para validação de conectividade interna e saúde de rede |

---

## 4. Estrutura do Projeto

O código do monólito (`monolith/app`) adota separação estrita em camadas inspirada em Clean Architecture e DDD:

```text
monolith/app/
├── Application/                   # Casos de uso e orquestração de aplicação
│   ├── Support/                   # Utilitários (ex: PlateMasker)
│   └── VehicleDebt/               # Orquestração do fluxo de débitos
│       ├── GetVehicleDebtsUseCase.php
│       ├── ProviderExecutor.php   # Execução com retry linear e backoff
│       ├── ProviderResolver.php   # Resolução dinâmica e desacoplada de providers
│       ├── VehicleDebtConsultationResult.php
│       └── VehicleDebtService.php # Gerenciamento de fallback e agregação
│
├── Domain/                        # Núcleo de domínio (regras puras, zero Laravel)
│   ├── Debt/                      # Entidades e cálculos de débitos
│   │   ├── Clock/                 # Relógio injetável (FixedClock para testes)
│   │   ├── Contracts/             # Interfaces dos adaptadores de provedores
│   │   ├── Exceptions/            # Exceções ricas de domínio
│   │   ├── Interest/              # Políticas de cálculo de juros
│   │   │   ├── DebtInterestPolicyInterface.php
│   │   │   ├── DebtInterestPolicyRegistry.php
│   │   │   ├── IpvaInterestPolicy.php
│   │   │   └── MultaInterestPolicy.php
│   │   ├── Rounding/              # Arredondamento financeiro HALF_UP
│   │   ├── Debt.php               # Entidade canônica de débito
│   │   ├── DebtType.php           # Enum tipado (IPVA, MULTA)
│   │   └── Money.php              # Value Object imutável em centavos inteiros
│   └── Payment/                   # Simulação de meios de pagamento
│       ├── Contracts/             # Interfaces de calculadoras de pagamento
│       ├── DTO/                   # DTOs de opções, PIX e parcelas
│       └── Services/              # Calculadoras e simulador
│           ├── CreditCardCalculator.php # Tabela Price com taxa mensal
│           ├── PaymentSimulator.php     # Agrupamento TOTAL e SOMENTE_<TIPO>
│           └── PixCalculator.php        # Cálculo com 5% de desconto
│
├── Infrastructure/                # Adaptadores de infraestrutura e telemetria
│   ├── Observability/             # SimpleMetricsRegistry e métricas em memória
│   └── Providers/                 # Adaptadores de protocolo externo
│       ├── Rest/                  # RestVehicleDebtProvider (cliente HTTP/JSON)
│       └── Soap/                  # SoapVehicleDebtProvider (cliente HTTP/XML)
│
└── Http/                          # Camada de entrega HTTP
    ├── Controllers/               # VehicleDebtIntegrationController
    ├── Middleware/                # RequestIdMiddleware e segurança
    └── Requests/                  # VehicleDebtRequest (validação de placa e payload)
```

---

## 5. Padrões de Projeto e Princípios

- **Ports & Adapters (Hexagonal Architecture)**: O domínio define a porta `VehicleDebtProvider`. Os adaptadores `RestVehicleDebtProvider` e `SoapVehicleDebtProvider` traduzem chamadas externas específicas sem vazar detalhes de transporte (JSON ou XML) para o núcleo.
- **Strategy / Policy**: O cálculo de juros é desacoplado através de `DebtInterestPolicyInterface`. Novas regras tributárias podem ser introduzidas adicionando novas policies ao `DebtInterestPolicyRegistry` sem alterar classes existentes (Open/Closed Principle).
- **Value Object (`Money`)**: Encapsula valores monetários como inteiros representando centavos (`int`), prevenindo problemas clássicos de imprecisão de ponto flutuante binário.
- **Dependency Injection**: Todas as dependências (provedores, executores, políticas e calculadoras) são registradas no container de serviços do Laravel (`AppServiceProvider`) através de contratos abstratos.

---

## 6. Fluxo de Consulta de Débitos

```text
Requisição POST /api/v1/vehicles/debts
   │
   ▼
[ VehicleDebtRequest ] ─────────── (Invalida placa ou campos desconhecidos: HTTP 400)
   │ (Placa sanitizada e válida)
   ▼
[ GetVehicleDebtsUseCase ]
   │
   ▼
[ VehicleDebtService ]
   │
   ├─► Consulta Provedor 1 (ex: REST) via [ ProviderExecutor ]
   │      ├─ Tentativa 1 (falha de rede/500) ──► Backoff linear (100ms)
   │      ├─ Tentativa 2 (falha de rede/500) ──► Backoff linear (200ms)
   │      └─ Tentativa 3 (esgotado) ───────────► Log de falha
   │
   ├─► [ Fallback Automático ] ──► Consulta Provedor 2 (ex: SOAP)
   │      └─ Tentativa 1: Sucesso HTTP 200 ──► Normalização canônica
   │
   ▼
[ DebtCalculationService ] (Aplica juros moratórios com relógio fixado em 2024-05-10)
   │
   ▼
[ PaymentSimulator ] (Calcula PIX à vista e parcelamento Cartão via Price)
   │
   ▼
Resposta HTTP 200 JSON estruturada
```

---

## 7. Provedores Externos (REST e SOAP)

### Provedor REST (`provider-rest`)
- **Transporte**: HTTP GET com JSON.
- **URL Alvo**: `http://provider-rest:8000/api/v1/vehicles/{plate}/debts`
- **Porta no Host**: `8001`
- **Contrato de Resposta**:
  ```json
  {
    "vehicle": "ABC1234",
    "debts": [
      { "type": "IPVA", "amount": 1500.00, "due_date": "2024-01-10" },
      { "type": "MULTA", "amount": 300.50, "due_date": "2024-02-15" }
    ]
  }
  ```

### Provedor SOAP (`provider-soap`)
- **Transporte**: HTTP POST com XML.
- **URL Alvo**: `http://provider-soap:8000/soap`
- **Porta no Host**: `8002`
- **Payload Enviado**:
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  <request>
      <plate>ABC1234</plate>
  </request>
  ```
- **Contrato de Resposta**:
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
      </debts>
  </response>
  ```
- **Tratamento de Veículo Sem Débitos**: O provedor SOAP retorna a tag auto-fechada `<debts/>`. O adaptador do monólito reconhece a tag vazia e normaliza como uma coleção vazia sem disparar erros de parsing.

### Simulação de Falhas nos Provedores (`PROVIDER_MODE`)
É possível alterar o comportamento de ambos os provedores via variável de ambiente `PROVIDER_MODE` no `.env` ou `docker-compose.yml`:
- `success`: Responde normalmente com a massa de dados mockada (padrão).
- `error`: Retorna HTTP 500 simulando instabilidade externa.
- `timeout`: Aguarda 5 segundos antes de responder, estourando o timeout de 2s do monólito.
- `invalid_response`: Retorna payload corrompido para testar falha rápida.

---

## 8. Estratégia de Resiliência: Retry e Fallback

- **Ordem dos Provedores**: Definida por padrão como `rest,soap` (configurável via variável de ambiente `PROVIDER_ORDER`).
- **Quantidade de Tentativas**: Configurada por `PROVIDER_RETRIES` (padrão `2`). O total de tentativas por provedor é $1 + 2 = 3$.
- **Backoff Linear**: Intervalo progressivo entre retries calculado por `tentativa * 100ms` (100ms na 1ª repetição, 200ms na 2ª repetição).
- **Classificação de Falhas**:
  - *Retriable* (`ProviderUnavailableException`): Falhas de rede, timeouts ou erros HTTP 5xx acionam nova tentativa com backoff.
  - *Non-retriable* (`InvalidProviderResponseException`): Respostas corrompidas ou XML inválido realizam *fail-fast*, abortando retries no provedor atual e acionando o fallback imediatamente.
- **Fallback Automático**: Se o primeiro provedor esgotar suas tentativas sem sucesso, o monólito chaveia transparentemente para o próximo provedor configurado.
- **First Success Wins**: O primeiro provedor a responder com sucesso interrompe o ciclo e entrega os dados.
- **Exaustão Total**: Se todos os provedores falharem, a API responde HTTP 503 com `{"error": "all_providers_unavailable"}`.

---

## 9. Regras de Negócio e Cálculo de Juros

As atualizações de valores seguem estritamente as regras de encargos legais com relógio do sistema fixado em **`2024-05-10`** para garantir determinismo no Home Test:

| Tipo de Débito | Taxa de Juros Diária | Teto de Juros | Fórmula de Aplicação |
| :--- | :--- | :--- | :--- |
| **IPVA** | `0,33%` ao dia | `20%` do valor original | $J = \min(V_{\text{orig}} \times 0.0033 \times d, V_{\text{orig}} \times 0.20)$ |
| **MULTA** | `1,00%` ao dia | Sem teto | $J = V_{\text{orig}} \times 0.01 \times d$ |

- **Débitos Não Vencidos**: Se a data de vencimento for igual ou posterior à data de referência (`dias_atraso <= 0`), os juros calculados são rigorosamente `0.00`.
- **Tipos Desconhecidos**: Se o provedor retornar um tipo de débito não homologado pelo domínio, a requisição é rejeitada com HTTP 422 (`{"error": "unknown_debt_type", "type": "..."}`).
- **Arredondamento Financeiro**: Todos os cálculos parciais operam em centavos inteiros. O arredondamento na conversão monetária utiliza o padrão bancário **HALF_UP** (`round(..., 2, PHP_ROUND_HALF_UP)`).

---

## 10. Simulação de Pagamentos (PIX e Cartão)

Para cada cenário de quitação, o monólito gera opções de pagamento detalhadas:

### Opções Geradas
1. **`TOTAL`**: Quitação consolidada de todos os débitos atualizados do veículo.
2. **`SOMENTE_<TIPO>`**: Quitação individual por categoria (ex: `SOMENTE_IPVA`, `SOMENTE_MULTA`).
*(Caso o veículo não possua débitos, a lista de opções de pagamento é entregue vazia: `{"opcoes": []}`)*.

### Modalidades de Pagamento

#### PIX (À Vista)
- Aplica **5% de desconto** sobre o valor base:
  $$\text{Total PIX} = V_{\text{base}} \times 0.95$$

#### Cartão de Crédito (Parcelado)
- Modalidades obrigatórias: **1x**, **6x** e **12x**.
- **1x (À Vista no Cartão)**: Valor integral sem encargos adicionais ($\text{Parcela} = V_{\text{base}}$).
- **6x e 12x (Parcelado)**: Juros compostos de **2,5% ao mês** calculados pelo Sistema Francês de Amortização (**Tabela Price**):
  $$PMT = P \times \frac{i \times (1 + i)^n}{(1 + i)^n - 1}$$
  *Onde $P$ é o valor base em centavos, $i = 0.025$ e $n$ é o número de parcelas.*

---

## 11. Documentação da API

### Tabela de Endpoints

| Método | Endpoint | Proteção / Cache | Descrição |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/vehicles/debts` | Throttle: 60 req/min | Consulta débitos veiculares, atualiza juros e gera simulação de pagamento |
| `GET` | `/api/health` | Direto | Health check básico de inicialização do monólito |
| `GET` | `/api/health/integrations` | Cache: 5s (com bypass) | Diagnóstico completo de conectividade com todos os 3 serviços externos |
| `GET` | `/api/metrics` | Direto | Métricas operacionais em tempo real (contadores de requests, falhas, fallbacks) |

---

### Exemplo de Requisição

`POST /api/v1/vehicles/debts`
```bash
curl -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa": "ABC1234"}'
```

*(Opcional: é possível forçar um provedor específico enviando `"provider": "soap"` ou `"provider": "rest"`).*

---

### Exemplo Real de Resposta (HTTP 200 OK)

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
            { "quantidade": 1, "valor_parcela": "2355.93" },
            { "quantidade": 6, "valor_parcela": "427.72" },
            { "quantidade": 12, "valor_parcela": "229.67" }
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
            { "quantidade": 1, "valor_parcela": "1800.00" },
            { "quantidade": 6, "valor_parcela": "326.79" },
            { "quantidade": 12, "valor_parcela": "175.48" }
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
            { "quantidade": 1, "valor_parcela": "555.93" },
            { "quantidade": 6, "valor_parcela": "100.93" },
            { "quantidade": 12, "valor_parcela": "54.20" }
          ]
        }
      }
    ]
  }
}
```

---

## 12. Tratamento Defensivo de Erros

A API possui respostas padronizadas e sem vazamento de stack traces internos:

| Situação | Status HTTP | Payload de Resposta | Motivo |
| :--- | :--- | :--- | :--- |
| **Placa Ausente ou Inválida** | `400 Bad Request` | `{"error": "invalid_plate"}` | Placa não enviada ou em desacordo com o padrão brasileiro |
| **Campos Desconhecidos** | `400 Bad Request` | `{"error": "unknown_field", "unrecognized_fields": ["campo_estranho"]}` | Proteção contra payloads adulterados ou ataques de poluição de parâmetros |
| **Tipo de Débito Desconhecido** | `422 Unprocessable` | `{"error": "unknown_debt_type", "type": "SEGURO_DPVAT"}` | Fornecedor externo retornou categoria de débito não catalogada |
| **Todos os Provedores Indisponíveis**| `503 Unavailable` | `{"error": "all_providers_unavailable"}` | Todos os provedores externos falharam após retries e fallbacks |

---

## 13. Observabilidade e Telemetria

### Logs Estruturados
O sistema emite logs formatados em JSON direcionados para `stdout` / `stderr`. O identificador de correlação `request_id` é propagado em todas as etapas:

```json
{"request_id":"c8a29a1b-3f41-4c12-9214-e0c1f5412891","event":"provider.request","provider":"rest","operation":"get_debts","plate":"ABC****","attempt":1}
{"request_id":"c8a29a1b-3f41-4c12-9214-e0c1f5412891","event":"provider.response","provider":"rest","status":"success","duration_ms":3,"debts_count":2}
{"request_id":"c8a29a1b-3f41-4c12-9214-e0c1f5412891","event":"vehicle_debt.completed","plate":"ABC****","provider":"rest","debts_count":2,"duration_ms":4}
```

### Mascaramento de Dados Sensíveis
Em estrito cumprimento das diretrizes de privacidade, placas veiculares são ofuscadas em todos os logs através da classe `PlateMasker`:
`ABC1234` $\to$ `ABC****` | `BRA2E19` $\to$ `BRA****`.

### Métricas Operacionais (`GET /api/metrics`)
O endpoint expõe contadores atômicos mantidos em memória:
```json
{
  "status": "ok",
  "metrics": {
    "vehicle_debt_requests_total": 42,
    "vehicle_debt_success_total": 38,
    "vehicle_debt_error_total": 4,
    "provider_requests_total": 50,
    "provider_failures_total": 8,
    "provider_retries_total": 5,
    "provider_fallbacks_total": 3
  }
}
```

### Telemetria de Cache (`X-Cache`)
O endpoint `/api/health/integrations` inclui o cabeçalho HTTP:
- `X-Cache: HIT`: Resposta servida a partir da memória/cache transitório em `< 15ms`.
- `X-Cache: MISS`: Resposta originada de consulta ativa aos 3 provedores externos.

---

## Comandos úteis

O projeto conta com um `Makefile` na raiz para agilizar a operação, diagnóstico e validação técnica:

| Comando | Descrição |
| :--- | :--- |
| `make help` | Lista os comandos disponíveis |
| `make up` | Inicia o ambiente completo em background |
| `make down` | Para o ambiente (preserva volumes de dados) |
| `make restart` | Reinicia todos os serviços |
| `make status` | Mostra o status e saúde dos contêineres |
| `make logs` | Acompanha os logs unificados em tempo real |
| `make test` | Executa a suíte de testes automatizados no monólito |
| `make health` | Verifica a saúde e conectividade das integrações |
| `make check` | Executa validação geral de conformidade (config, status, testes, saúde) |
| `make shell` | Abre shell interativo no contêiner do monólito |
| `make artisan` | Executa comandos Artisan (ex: `make artisan CMD="about"`) |
| `make demo` | Executa todos os cenários de demonstração técnica |

---

## 14. Como Executar com Docker

### Pré-requisitos
- **Docker**: versão 24+ (ou compatível)
- **Docker Compose**: v2+

### 1. Inicializar o Ambiente
```bash
docker compose up --build -d
```

### 2. Verificar os Containers em Execução
```bash
docker compose ps
```

Os 4 containers devem apresentar status `Up (healthy)`:
- `cardok-monolith` (porta `8000`)
- `cardok-provider-rest` (porta `8001`)
- `cardok-provider-soap` (porta `8002`)
- `cardok-payment-provider` (rede interna)

---

## 15. Diagnóstico e Health Checks

### Diagnóstico via CLI (Comando Artisan)
Para verificar a resolução de DNS interno e a conectividade do monólito com os outros serviços:
```bash
docker compose exec monolith php artisan cardok:check-services
```
*Gera uma tabela formatada no terminal indicando URL, status, código HTTP e latência de cada serviço parceiro.*

### Diagnóstico via HTTP com Cache Transitório
O endpoint `GET /api/health/integrations` possui **cache de 5 segundos** para evitar exaustão de conexões durante probes frequentes de orquestradores (Kubernetes/ECS):
```bash
# 1ª consulta (Miss):
curl -i http://localhost:8000/api/health/integrations | grep -i "x-cache"
# => X-Cache: MISS

# 2ª consulta imediata (< 5s, Cache Hit):
curl -i http://localhost:8000/api/health/integrations | grep -i "x-cache"
# => X-Cache: HIT (< 15ms de latência)

# Bypass sob demanda (RFC 7234):
curl -i -H "Cache-Control: no-cache" http://localhost:8000/api/health/integrations | grep -i "x-cache"
# => X-Cache: MISS
```

---

## 16. Execução de Testes Automatizados

Para rodar toda a suíte de testes nos containers:

### 1. Testes do Monólito Principal (114 testes)
```bash
docker compose exec monolith php artisan test
```

### 2. Testes do Provedor REST (7 testes)
```bash
docker compose exec provider-rest php artisan test
```

### 3. Testes do Provedor SOAP (7 testes)
```bash
docker compose exec provider-soap php artisan test
```

### Resumo da Cobertura de Testes

| Componente | Testes | Asserções | Status | Cobertura Principal |
| :--- | :--- | :--- | :--- | :--- |
| **Monólito** | `114` | `461` | ✅ 100% Pass | Domínio, Money, Políticas de IPVA/Multa, Price, PIX, Providers, Retry/Fallback, Observabilidade e Cache |
| **Provider REST** | `7` | `13` | ✅ 100% Pass | Contrato JSON, modos de erro, timeout e health check |
| **Provider SOAP** | `7` | `27` | ✅ 100% Pass | Envelopes XML, tag `<debts/>`, falhas simuladas e health check |
| **Total do Ecossistema** | **`128`** | **`501`** | **✅ 100% Pass** | **Zero falhas registradas** |

---

## 17. Decisões Técnicas e Trade-offs

### 1. Monólito Modular vs Microserviços
- **Decisão**: O núcleo do Cardok foi construído como um monólito modular com separação de camadas.
- **Motivação**: Evita complexidade operacional desnecessária (RPCs entre serviços internos, transações distribuídas) mantendo alta coesão e permitindo futura extração de serviços caso o volume de requisições justifique.

### 2. Provedores Externos em Containers Separados
- **Decisão**: Os mocks de REST e SOAP rodam em containers e portas independentes.
- **Motivação**: Simula fielmente os desafios de rede do mundo real (latência TCP, DNS inter-container, timeouts de socket e indisponibilidade de servidores legados).

### 3. Ausência de Banco de Dados Relacional
- **Decisão**: O projeto não utiliza banco de dados para a consulta e simulação de débitos.
- **Motivação**: O escopo do Home Test é centrado na orquestração síncrona, tolerância a falhas e regras matemáticas de pagamento. A adição de persistência futura pode ser feita acoplando um repositório na camada de aplicação sem impactar o domínio.

### 4. Estratégia *First Success Wins*
- **Decisão**: O primeiro provedor que responder com sucesso entrega o resultado da consulta.
- **Motivação**: Provedores de débitos veiculares estaduais refletem a mesma base oficial (Detran/Sefaz). Consultar múltiplos provedores em paralelo sem necessidade duplicaria custos de requisição e sobrecarregaria parceiros externos.

### 5. Representação Monetária em Centavos Inteiros
- **Decisão**: O Value Object `Money` opera internamente com centavos inteiros (`int`).
- **Motivação**: Números de ponto flutuante em computadores sofrem com imprecisões binárias (ex: `0.1 + 0.2 !== 0.3`). O uso de centavos e o isolamento de floats exclusivamente para coeficientes analíticos (como na fórmula exponencial da Tabela Price) garante precisão matemática absoluta.

---

## 18. Segurança da Aplicação

### Implementado
- **Validação Estrita de Placas**: Expressão regular cobrindo padrão tradicional (`^[A-Z]{3}[0-9]{4}$`) e Mercosul (`^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$`).
- **Rejeição de Campos Desconhecidos**: Bloqueio de propriedades não declaradas no payload de consulta para prevenir *parameter pollution* e explorações inesperadas.
- **Headers HTTP de Segurança**: Respostas contêm `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection: 1; mode=block` e `Referrer-Policy: strict-origin-when-cross-origin`.
- **Proteção de Dados nos Logs**: Ofuscamento automático de placas veiculares nos arquivos de log.
- **Proteção contra Abuso (Rate Limit)**: Middleware `throttle:60,1` ativo na rota de débitos.

### Melhorias Recomendadas para Produção
- Autenticação e Autorização via tokens JWT ou OAuth2 (ex: Laravel Sanctum).
- Terminação TLS/HTTPS em API Gateway / Load Balancer reverso.
- Gestão centralizada de segredos através de Vault ou AWS Secrets Manager.

---

## 19. Melhorias Futuras

Caso o projeto evolua para ambiente de produção de larga escala, as seguintes extensões arquiteturais são recomendadas:
1. **Circuit Breaker**: Implementação do padrão Circuit Breaker para interromper temporariamente requisições a provedores externos que entrem em falha contínua.
2. **OpenTelemetry e Distributed Tracing**: Instrumentação completa para rastrear spans de rede através de Jaeger ou Datadog.
3. **Persistência e Histórico de Consultas**: Armazenamento relacional (PostgreSQL) com eventos de domínio para auditoria de cotações emitidas.
4. **Gateway Financeiro Real**: Integração de liquidação real através de webhooks assíncronos e verificação de idempotência (`Idempotency-Key`).

---

## 20. Desenvolvimento Assistido por IA e Spec Kit

A partir da Fase 13, o Cardok adotou formalmente a metodologia **Spec-Driven Development (SDD)** utilizando o [GitHub Spec Kit](https://github.com/github/spec-kit) e o agente de engenharia **Antigravity**:

```text
Constitution (.specify/memory/constitution.md)
      │
      ▼
Specification (/speckit-specify  ──>  spec.md)
      │
      ▼
Clarification (/speckit-clarify  ──>  desambiguação interativa)
      │
      ▼
Planning      (/speckit-plan     ──>  plan.md, data-model.md, contracts/)
      │
      ▼
Tasking       (/speckit-tasks    ──>  tasks.md, ordenação TDD)
      │
      ▼
Execution     (/speckit-implement ──>  código, testes, validação 100%)
```

### Funcionalidade Construída com Spec Kit
A funcionalidade de **Cache de Health Check** foi especificada e implementada integralmente através desse fluxo:
- **Especificação**: [`specs/001-health-check-cache/spec.md`](specs/001-health-check-cache/spec.md)
- **Plano**: [`specs/001-health-check-cache/plan.md`](specs/001-health-check-cache/plan.md)
- **Tarefas**: [`specs/001-health-check-cache/tasks.md`](specs/001-health-check-cache/tasks.md)
- **Registro**: [`docs/spec-driven-development.md`](docs/spec-driven-development.md)

> **Princípio de Isolamento**: O ferramental de IA e as especificações residem em diretórios dedicados (`.specify/`, `specs/`). Nenhuma classe de produção depende do ferramental de assistência de código.

---

## 21. Licença e Contexto

Este repositório foi desenvolvido exclusivamente para fins de avaliação técnica no processo seletivo da **DOK Despachante**. Todos os direitos sobre os critérios e o enunciado do teste pertencem à instituição organizadora.
