# Documentação Arquitetural: Padrão Adapter e Modelo Canônico

Este documento detalha as decisões arquiteturais, o design de software e os benefícios práticos do uso do **Padrão de Projeto Adapter (GoF)** combinado ao conceito de **Modelo Canônico de Dados (Canonical Data Model)** e à arquitetura de **Portas e Adaptadores (Hexagonal Architecture)** na plataforma **Cardok**.

---

# 1. O Problema de Negócio e de Integração

O ecossistema Cardok precisa consultar débitos veiculares em múltiplos provedores externos que não compartilham padrões técnicos, arquiteturais ou semânticos:

| Aspecto | Provedor REST | Provedor SOAP |
| :--- | :--- | :--- |
| **Protocolo de Transporte** | HTTP GET | HTTP POST |
| **Formato de Dados** | JSON | XML com Envelope |
| **Identificador do Débito** | `type` (`"IPVA"`, `"MULTA"`) | `category` (`"IPVA"`, `"MULTA"`) |
| **Valor Monetário** | `amount` (float numérico primitivo) | `value` (string formatada decimal) |
| **Data de Vencimento** | `due_date` (`"YYYY-MM-DD"`) | `expiration` (`"YYYY-MM-DD"`) |
| **Cenário Sem Débitos** | Array vazio: `"debts": []` | Tag auto-fechada: `<debts/>` |

### O Risco do Alto Acoplamento:
Se a camada de aplicação ou domínio consumisse esses serviços diretamente:
1. O domínio seria poluído com lógica de serialização/deserialização (SimpleXMLElement, json_decode, Guzzle HTTP).
2. As regras de cálculo de juros teriam que lidar com diferentes tipos (`float` vs `string`).
3. Adicionar um novo provedor exigiria espalhar blocos `if/else` ou `switch` por todo o sistema.
4. Testar a resiliência (retry, backoff, fallback) exigiria mockar detalhes de transporte HTTP de cada fornecedor.

---

# 2. A Solução: Padrão Adapter (GoF)

O padrão Adapter atua como um tradutor bidirecional entre uma interface que o sistema deseja consumir e a interface incompatível exposta por um serviço externo.

```text
               ┌────────────────────────────────────────────────────────┐
               │                  CAMADA DE APLICAÇÃO                   │
               │         VehicleDebtService / ProviderExecutor          │
               └───────────────────────────┬────────────────────────────┘
                                           │
                                           │ Consome apenas a Porta
                                           ▼
               ┌────────────────────────────────────────────────────────┐
               │                   CAMADA DE DOMÍNIO                    │
               │          <<interface>> VehicleDebtProvider             │
               │    +getDebts(string $plate): ProviderDebtResponse      │
               └───────────────────────────▲────────────────────────────┘
                                           │
                        ┌──────────────────┴──────────────────┐
                        │ Implementa                          │ Implementa
                        │                                     │
      ┌─────────────────┴─────────────────┐ ┌─────────────────┴─────────────────┐
      │       CAMADA DE INFRAESTRUTURA    │ │       CAMADA DE INFRAESTRUTURA    │
      │       RestVehicleDebtProvider     │ │       SoapVehicleDebtProvider     │
      └─────────────────┬─────────────────┘ └─────────────────┬─────────────────┘
                        │ HTTP GET (JSON)                     │ HTTP POST (XML)
                        ▼                                     ▼
             [Serviço Externo REST]                [Serviço Externo SOAP]
```

---

# 3. Componentes do Padrão no Cardok

### 3.1. A Porta / Interface Alvo (*Target Interface*)
Localizada no núcleo do domínio em [`App\Domain\Debt\Contracts\VehicleDebtProvider`](file:///Users/roger/Desktop/docker/cardok/monolith/app/Domain/Debt/Contracts/VehicleDebtProvider.php):

```php
namespace App\Domain\Debt\Contracts;

use App\Domain\Debt\ProviderDebtResponse;

interface VehicleDebtProvider
{
    /**
     * Consulta débitos de um veículo e retorna um modelo canônico padronizado.
     */
    public function getDebts(string $plate): ProviderDebtResponse;
}
```

### 3.2. O Modelo Canônico de Dados (*Canonical Data Model*)
Em vez de trafegar arrays soltos ou estruturas de terceiros, o contrato devolve estruturas de domínio fortemente tipadas:
- [`ProviderDebtResponse`](file:///Users/roger/Desktop/docker/cardok/monolith/app/Domain/Debt/ProviderDebtResponse.php): encapsula a placa e a coleção canônica `Debt[]`.
- [`Debt`](file:///Users/roger/Desktop/docker/cardok/monolith/app/Domain/Debt/Debt.php): entidade imutável contendo:
  - `type` (`string` normalizada);
  - `amount` (Value Object [`Money`](file:///Users/roger/Desktop/docker/cardok/monolith/app/Domain/Debt/Money.php) garantindo precisão em centavos inteiros, sem `float`);
  - `dueDate` (`CarbonImmutable` em UTC).

### 3.3. Os Adaptadores Concretos (*Concrete Adapters*)

#### A) [`RestVehicleDebtProvider`](file:///Users/roger/Desktop/docker/cardok/monolith/app/Infrastructure/Providers/Rest/RestVehicleDebtProvider.php)
- **Responsabilidade**: Traduz o protocolo REST/JSON para o Modelo Canônico.
- Executa HTTP GET via Laravel Http Client com timeout configurável.
- Sanitiza o parâmetro da placa via `rawurlencode()`.
- Valida a estrutura JSON retornada (`debts` array, campos `type`, `amount`, `due_date`).
- Mapeia cada item para instâncias canônicas de `Debt` e `Money`.

#### B) [`SoapVehicleDebtProvider`](file:///Users/roger/Desktop/docker/cardok/monolith/app/Infrastructure/Providers/Soap/SoapVehicleDebtProvider.php)
- **Responsabilidade**: Traduz o protocolo SOAP/XML para o Modelo Canônico.
- Constrói o envelope XML escapando caracteres com `htmlspecialchars(..., ENT_XML1)`.
- Executa HTTP POST com header `Content-Type: application/xml`.
- Faz o parsing seguro do XML via `simplexml_load_string` com proteção `LIBXML_NONET` contra XXE.
- Normaliza a variação sintática da tag auto-fechada `<debts/>` para lista vazia.
- Mapeia `<category>`, `<value>` e `<expiration>` para as entidades canônicas `Debt` e `Money`.

---

# 4. Benefícios Práticos na Camada de Aplicação

### 4.1. Polimorfismo e Reuso no [`ProviderExecutor`](file:///Users/roger/Desktop/docker/cardok/monolith/app/Application/VehicleDebt/ProviderExecutor.php)
Observe como a execução do provedor é desacoplada de detalhes tecnológicos:

```php
public function execute(string $providerKey, VehicleDebtProvider $provider, string $plate): ProviderDebtResponse
{
    $totalAttempts = 1 + max(0, $this->maxRetries);

    for ($attempt = 1; $attempt <= $totalAttempts; $attempt++) {
        $startTime = microtime(true);

        try {
            // O executor desconhece se $provider é REST, SOAP ou outro protocolo:
            return $provider->getDebts($plate);
        } catch (InvalidProviderResponseException $e) {
            // Falha contratual: fallback imediato sem retry
            throw $e;
        } catch (ProviderUnavailableException $e) {
            // Falha de infraestrutura: aplica retries com backoff linear
            if ($attempt >= $totalAttempts) {
                throw $e;
            }
            ($this->sleeper)($attempt * $this->initialBackoffMs);
        }
    }
}
```

O `ProviderExecutor` é 100% agnóstico de protocolo:
- Não sabe como o XML é parseado;
- Não sabe como o JSON é serializado;
- Trata apenas de **políticas de resiliência, retry, linear backoff, medição de latência (`duration_ms`) e logs estruturados com mascaramento LGPD**.

### 4.2. Padronização de Exceções (Tratamento Uniforme de Erros)
Cada adaptador captura erros de transporte e os converte em exceções previsíveis de domínio:
- Falhas de conexão, timeouts ou status HTTP 5xx $\rightarrow$ `ProviderUnavailableException` (elegível a retry e fallback).
- XML malformado, JSON corrompido ou payload sem campos obrigatórios $\rightarrow$ `InvalidProviderResponseException` (falha fatal no provedor, dispara fallback imediato sem retentativa inútil).

---

# 5. Princípios SOLID Aplicados

1. **Single Responsibility Principle (SRP)**:
   - Os adapters cuidam apenas da comunicação e serialização com seus respectivos fornecedores.
   - O `ProviderExecutor` cuida exclusivamente de resiliência e retries.
   - O `VehicleDebtService` cuida exclusivamente da orquestração de fallback entre provedores.
   - O `DebtCalculationService` cuida exclusivamente das regras de juros e multas.
2. **Open/Closed Principle (OCP)**:
   - Para integrar um novo provedor (ex.: um webservice gRPC ou banco de dados legado), basta criar `GrpcVehicleDebtProvider implements VehicleDebtProvider`. **Nenhuma linha** de código do domínio, dos controllers ou da lógica de retry precisa ser modificada.
3. **Liskov Substitution Principle (LSP)**:
   - Qualquer implementação de `VehicleDebtProvider` pode substituir outra de forma transparente na cadeia de execução.
4. **Dependency Inversion Principle (DIP)**:
   - A camada de aplicação e o executor dependem da abstração (`VehicleDebtProvider`), nunca de implementações concretas (`RestVehicleDebtProvider`).

---

# 6. Roteiro para Entrevistas e Apresentações Técnicas

Se for questionado sobre o padrão Adapter na apresentação do projeto:

> **"Por que você usou o padrão Adapter?"**
> *"Utilizamos o padrão Adapter porque os provedores externos de débitos veiculares são heterogêneos: um opera via REST com JSON e outro via SOAP com XML. O Adapter encapsula essas discrepâncias de protocolo e serialização na borda da infraestrutura. Com isso, o domínio e a aplicação interagem apenas com uma interface comum (`VehicleDebtProvider`) e um modelo canônico (`ProviderDebtResponse`)."*

> **"Onde está a maior vantagem desse desenho?"**
> *"A maior vantagem está no `ProviderExecutor`. A esteira de resiliência — com retries, backoff linear, cálculo de latência e fallback — opera puramente sobre a interface genérica. Se amanhã adicionarmos um terceiro provedor, todo o mecanismo de retry, fallback e observabilidade já funcionará de fábrica, respeitando o princípio Aberto/Fechado."*
