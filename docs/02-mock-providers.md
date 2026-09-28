Você é meu pair programmer no projeto `cardok`.

A infraestrutura Docker da primeira etapa já está implementada.

Agora vamos implementar a **Fase 2 — Mock dos provedores externos de débitos veiculares**.

## Objetivo

Implementar dois serviços externos simulados:

* `provider-rest`
* `provider-soap`

Eles representam dois fornecedores externos independentes que fornecem os débitos de uma placa.

O objetivo desta etapa é criar os contratos externos e permitir que, posteriormente, o monólito implemente adapters diferentes para REST/JSON e SOAP/XML.

NÃO implemente ainda:

* domínio do monólito;
* `Debt`;
* `Money`;
* regras de juros;
* pagamentos;
* retry no monólito;
* fallback no monólito;
* Strategy;
* resolução de providers.

Nesta etapa estamos trabalhando somente nos serviços externos simulados.

---

# 1. Provider REST

O serviço `provider-rest` deve disponibilizar:

```http
GET /api/v1/vehicles/{plate}/debts
```

Para:

```text
ABC1234
```

deve retornar:

```json
{
    "vehicle": "ABC1234",
    "debts": [
        {
            "type": "IPVA",
            "amount": 1500.00,
            "due_date": "2024-01-10"
        },
        {
            "type": "MULTA",
            "amount": 300.50,
            "due_date": "2024-02-15"
        }
    ]
}
```

Utilize exatamente os nomes dos campos acima.

Não transforme os valores monetários em strings neste provider.

O formato externo deve representar um provider legado/terceiro e será posteriormente normalizado pelo monólito.

---

# 2. Provider SOAP

O serviço `provider-soap` deve implementar um endpoint SOAP/XML funcional.

Endpoint:

```http
POST /soap
```

O request deverá conter a placa.

Use uma estrutura simples e documente o contrato.

Exemplo conceitual:

```xml
<request>
    <plate>ABC1234</plate>
</request>
```

Para `ABC1234`, deve retornar:

```xml
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

IMPORTANTE:

Quando não houver débitos, o XML deve obrigatoriamente utilizar:

```xml
<debts/>
```

e NÃO:

```xml
<debts></debts>
```

nem:

```xml
<debts>
</debts>
```

Teste explicitamente esse comportamento.

---

# 3. Placas e dados simulados

Crie uma pequena fonte de dados fixa dentro de cada provider.

Não utilize banco de dados.

Inicialmente precisamos pelo menos dos seguintes cenários:

### ABC1234

Retorna:

```text
IPVA
1500.00
2024-01-10

MULTA
300.50
2024-02-15
```

### Placa sem débitos

Utilize uma placa válida diferente de `ABC1234`, por exemplo:

```text
DEF5678
```

Para essa placa:

REST:

```json
{
    "vehicle": "DEF5678",
    "debts": []
}
```

SOAP:

```xml
<response>
    <plate>DEF5678</plate>
    <debts/>
</response>
```

Não é necessário implementar persistência.

---

# 4. Simulação de falhas

Precisaremos desses providers para testar retry e fallback posteriormente.

Portanto, implemente um mecanismo simples de simulação de falhas controlado por variável de ambiente.

Utilize:

```env
PROVIDER_MODE=success
```

Valores suportados:

```text
success
error
timeout
invalid_response
```

## success

Comportamento normal.

## error

O provider deve retornar HTTP 500.

## timeout

O provider deve atrasar a resposta por tempo suficiente para ultrapassar o timeout que será configurado posteriormente no monólito.

Não faça o timeout excessivamente longo.

Algo em torno de 5 segundos é suficiente.

## invalid_response

Retorne uma resposta HTTP 200, porém com conteúdo inválido para o contrato esperado.

Esse cenário será utilizado posteriormente para avaliar como o adapter trata respostas inválidas.

---

# 5. Configuração

Adicione ao `.env.example` de cada provider:

```env
PROVIDER_MODE=success
```

Não utilize configurações diferentes para REST e SOAP nesta etapa.

---

# 6. HTTP Status

Para `success`:

```text
200
```

Para `error`:

```text
500
```

Para `timeout`:

O serviço deve simplesmente demorar para responder.

Para `invalid_response`:

```text
200
```

com payload inválido.

---

# 7. Separação interna

Mesmo sendo mocks simples, mantenha o código organizado.

Não coloque toda a lógica diretamente no Controller.

Pode utilizar uma estrutura simples como:

```text
app/
├── Http/
│   └── Controllers/
├── Services/
└── Data/
```

Não crie abstrações desnecessárias.

Esses serviços existem para simular dependências externas, não para demonstrar arquitetura complexa.

---

# 8. SOAP

Para o SOAP, priorize simplicidade e funcionamento.

Não implemente autenticação.

Não implemente WSDL complexo se não for necessário.

Entretanto, se a implementação escolhida utilizar WSDL, documente onde ele está e como o serviço funciona.

O requisito principal é:

```text
POST /soap
```

receber uma placa e responder XML.

---

# 9. Health checks

Mantenha os endpoints de health criados na etapa anterior:

REST:

```http
GET /api/health
```

SOAP:

```http
GET /health
```

Não altere o contrato deles.

---

# 10. Testes

Crie testes automatizados para os providers.

No mínimo:

### REST

* `ABC1234` retorna os dois débitos;
* placa sem débitos retorna `debts: []`;
* modo `error` retorna HTTP 500;
* modo `invalid_response` retorna payload inválido.

### SOAP

* `ABC1234` retorna os dois débitos;
* placa sem débitos retorna `<debts/>`;
* modo `error` retorna HTTP 500;
* modo `invalid_response` retorna XML inválido.

Teste especificamente o XML de zero débitos.

---

# 11. README

Atualize o README principal do projeto para documentar os contratos dos providers.

Inclua uma seção:

```text
## External Providers
```

Documente:

### REST

```text
GET /api/v1/vehicles/{plate}/debts
```

### SOAP

```text
POST /soap
```

Documente exemplos de request/response.

Também documente:

```env
PROVIDER_MODE=success
```

e os quatro modos disponíveis:

```text
success
error
timeout
invalid_response
```

---

# 12. Teste manual

Ao terminar, deve ser possível executar:

```bash
docker compose up --build
```

E testar:

```bash
curl http://localhost:8001/api/v1/vehicles/ABC1234/debts
```

E o SOAP através de uma requisição HTTP adequada.

Também deve ser possível testar:

```text
ABC1234
```

e uma placa sem débitos.

---

# 13. Critério de conclusão

Esta etapa será considerada concluída quando:

1. REST retornar JSON conforme especificado.
2. SOAP retornar XML conforme especificado.
3. REST e SOAP representarem os mesmos dados de negócio.
4. REST retornar lista vazia para uma placa sem débitos.
5. SOAP retornar exatamente `<debts/>` para uma placa sem débitos.
6. Os modos de falha funcionarem.
7. Existirem testes automatizados.
8. Os health checks continuarem funcionando.
9. A documentação estiver atualizada.

---

# 14. Importante

NÃO implemente ainda nada no `monolith` relacionado aos providers.

O próximo passo será criar no monólito:

```text
VehicleDebtProvider
       ▲
       │
 ┌─────┴──────────┐
 │                │
REST Adapter    SOAP Adapter
```

e então implementaremos o modelo canônico.

Portanto, nesta etapa altere somente:

```text
provider-rest/
provider-soap/
README.md
```

e arquivos de configuração necessários.

Antes de alterar os arquivos:

1. analise o estado atual do projeto;
2. liste os arquivos que serão modificados;
3. explique brevemente a abordagem;
4. implemente;
5. execute os testes;
6. mostre os resultados.

Não avance para a próxima etapa.

---

## 15. Implementação Realizada e Decisões Técnicas

Esta seção consolida as decisões tomadas, a arquitetura implementada e os resultados da validação da Etapa 02.

### 15.1. Decisões Técnicas e Racional de Engenharia

1. **Separação Interna Limpa e Sem Overengineering**:
   - **Estrutura**: Ambos os providers (`provider-rest` e `provider-soap`) adotaram a estrutura com camadas segregadas:
     - `app/Data/MockVehicleDebtData.php`: encapsula os dados estáticos em memória de cada provedor.
     - `app/Services/`: encapsula a lógica de negócio dos mocks, avaliação de `PROVIDER_MODE` e construção do payload (JSON ou XML).
     - `app/Http/Controllers/`: camada fina responsável apenas por receber a requisição e delegar ao serviço.
   - **Racional**: Garante legibilidade e facilidade de manutenção para pair programming e avaliação técnica, sem criar camadas desnecessárias de repositórios ou banco de dados.

2. **Garantia Contratual Estrita de Tag Auto-fechada no XML SOAP**:
   - **Decisão**: A geração do XML em `SoapDebtService` foi desenhada para garantir explicitamente a emissão da tag `<debts/>` no caso de placas sem débitos (ex: `DEF5678`), evitando rigorosamente `<debts></debts>` ou quebras de linha entre tags de fechamento.
   - **Racional**: Provedores SOAP legados frequentemente exigem conformidade exata de serialização XML para parsing eficiente e semântica de coleção vazia.

3. **Isenção de CSRF no Laravel 11 para Endpoint SOAP**:
   - **Decisão**: O arquivo `provider-soap/bootstrap/app.php` foi configurado com `$middleware->validateCsrfTokens(except: ['soap', 'soap/*'])`.
   - **Racional**: Como o endpoint `POST /soap` foi registrado em `routes/web.php` (para responder no path raiz `/soap` e não em `/api/soap`), a verificação de token de sessão CSRF precisava ser isenta para chamadas machine-to-machine via HTTP puro.

4. **Gerenciamento do Modo de Simulação (`PROVIDER_MODE`)**:
   - **Decisão**: Configurado em `config/services.php` (`provider_mode => env('PROVIDER_MODE', 'success')`) e repassado via `docker-compose.yml` e arquivos `.env`.
   - **Racional**: Permite que os testes automatizados sobrescrevam dinamicamente os modos via `config(['services.provider_mode' => ...])` sem poluir variáveis de ambiente do processo, ao mesmo tempo em que permite alternar o comportamento em runtime via Docker.

### 15.2. Problemas Encontrados e Resoluções

- **Precedência de Variáveis de Ambiente no PHPUnit**:
  - *Sintoma*: Testes com `putenv()` e `$_ENV` não alteravam a resposta porque o Laravel 11 popula `$_SERVER` no boot a partir do `.env`.
  - *Resolução*: Mapeamento da variável em `config/services.php` e controle do mock nos testes através de `config(['services.provider_mode' => '...'])`.
- **Compatibilidade de Asserções no PHPUnit 11**:
  - *Sintoma*: Tentativa inicial de uso de `assertDoesNotMatchString` inexistente no PHPUnit.
  - *Resolução*: Utilização do método padrão `assertDoesNotMatchRegularExpression('/<debts>\s*<\/debts>/', $content)`.

### 15.3. Validação dos Testes Automatizados

```bash
# Provider REST (7 testes, 13 asserções)
docker compose exec provider-rest php artisan test

# Provider SOAP (7 testes, 27 asserções)
docker compose exec provider-soap php artisan test
```

### 15.4. Validação Manual dos Endpoints

```bash
# REST - com débitos (200 OK)
curl -s http://localhost:8001/api/v1/vehicles/ABC1234/debts

# REST - sem débitos (200 OK)
curl -s http://localhost:8001/api/v1/vehicles/DEF5678/debts

# SOAP - com débitos (200 OK)
curl -s -X POST http://localhost:8002/soap -H "Content-Type: application/xml" -d '<request><plate>ABC1234</plate></request>'

# SOAP - sem débitos (200 OK com <debts/>)
curl -s -X POST http://localhost:8002/soap -H "Content-Type: application/xml" -d '<request><plate>DEF5678</plate></request>'
```
