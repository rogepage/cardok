# Contrato de Integração: API do Provedor de IA em Plaintext CSV

**Feature**: `007-add-ai-csv-provider`  
**Data**: 2026-10-08  
**Status**: Concluído  

---

## 1. Endpoint Principal: Consulta de Débitos do Veículo

### Requisição

- **Método**: `GET`
- **Caminho**: `/api/v1/debts/{plate}`
- **Parâmetros de Rota**:
  - `plate` (string, obrigatório): Placa do veículo normalizada (ex.: `ABC1234`, `LIC2024`).
- **Headers**:
  - `Accept`: `text/plain, text/csv`
  - `X-Request-ID`: `string` *(opcional, propagado pelo monólito para correlação)*

### Respostas

#### 200 OK — Sucesso com Débitos

- **Content-Type**: `text/plain; charset=UTF-8`
- **Corpo (Exemplo com Vírgula)**:
  ```text
  tipo,valor,vencimento
  IPVA,1500.00,2024-01-15
  MULTA,250.00,2024-02-10
  LICENCIAMENTO,100.00,2024-03-05
  ```

- **Corpo (Exemplo com Ponto e Vírgula e Blocos Markdown Aceitos)**:
  ````text
  ```csv
  tipo;valor;vencimento
  IPVA;1500,00;2024-01-15
  LICENCIAMENTO;100,00;2024-03-05
  ```
  ````

#### 200 OK — Sucesso sem Débitos (Veículo Quite)

- **Content-Type**: `text/plain; charset=UTF-8`
- **Corpo**:
  ```text
  tipo,valor,vencimento
  ```
  *(ou corpo vazio / ausência de linhas)*

#### 500 Internal Server Error — Erro Transitório do Provedor

- **Content-Type**: `text/plain; charset=UTF-8`
- **Corpo**:
  ```text
  Erro interno de processamento do modelo de IA
  ```

---

## 2. Endpoints Auxiliares do Container de Mock (`provider-ai`)

Para suportar testes determinísticos e simulação de cenários de contingência:

### `GET /health`
- **Descrição**: Healthcheck do container Docker.
- **Resposta**: `200 OK` `{"status": "ok", "service": "provider-ai"}`

### `POST /simulation/mode`
- **Descrição**: Altera dinamicamente o comportamento de simulação do container.
- **Corpo JSON**:
  ```json
  {
    "mode": "success"
  }
  ```
  *Modos suportados*:
  - `success`: Retorna débitos da placa em formato CSV.
  - `error`: Responde status HTTP 500 para acionar fallback.
  - `timeout`: Demora mais tempo que o timeout configurado (ex.: 5 segundos).
  - `invalid_response`: Retorna payload malformado para testar tratamento defensivo.
  - `empty`: Retorna resposta sem débitos para testar não-acionamento de fallback.

### `GET /simulation/mode`
- **Descrição**: Retorna o modo ativo de simulação.
