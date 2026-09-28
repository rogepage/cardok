# Fase 9 — Segurança, Hardening e Defesa em Profundidade

Esta especificação define os requisitos, diretrizes e implementação das práticas de segurança, conformidade e hardening para a plataforma **Cardok**.

---

# 1. Contexto e Motivação

O Cardok atua como um hub integrador de dados veiculares críticos, comunicando-se com serviços externos simulados (REST e SOAP) e expondo interfaces públicas tanto programáticas (API REST) quanto visuais (Laravel Livewire).

Por operar cálculos de débitos e simulações financeiras em um ambiente distribuído com tolerância a falhas (retries, timeouts e fallbacks), a plataforma está sujeita a riscos específicos:
1. **Exaustão de Recursos (Slow DoS)**: requisições concorrentes explorando timeouts e retries podem travar os workers PHP do servidor.
2. **Injeção de Entrada (XML/URL Manipulation)**: entradas maliciosas ou caracteres especiais podem comprometer os adaptadores dos provedores externos.
3. **Exposição de Informações Sensíveis (LGPD / Information Disclosure)**: vazamento de identificadores de veículos em logs ou stack traces em respostas de erro.
4. **Vulnerabilidades de Navegação Web**: ataques de Clickjacking, MIME-sniffing e ausência de políticas defensivas de cabeçalhos HTTP.

---

# 2. Objetivos de Segurança

1. **Prevenir injeção e corrupção de payloads** nos adaptadores de infraestrutura (REST e SOAP).
2. **Proteger o sistema contra negação de serviço e sobrecarga de workers** via limitação de taxa (Rate Limiting).
3. **Blindar a camada HTTP da API e Web** através de cabeçalhos de segurança padronizados.
4. **Garantir validação estrita de entradas** no contrato HTTP antes que qualquer processamento de aplicação ou domínio ocorra.
5. **Preservar a privacidade de dados (LGPD)** mantendo mascaramento de identificadores nos logs e evitando exposição de stack traces ao cliente.

---

# 3. Especificação Técnica

### 3.1. Proteção contra Injeção de XML (SOAP Provider)
- **Risco**: Interpolação direta de parâmetros não sanitizados no XML enviado ao provedor SOAP permitindo corrupção de tags (`</plate><injected>`).
- **Defesa**:
  - Sanitização de entidades XML obrigatória com `htmlspecialchars($plate, ENT_XML1, 'UTF-8')`.
  - Manutenção do parser com `LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING` para prevenir ataques de XXE (*XML External Entity*).

### 3.2. Prevenção de Path Traversal e URL Injection (REST Provider)
- **Risco**: Passagem de sequências de escape, `../` ou query strings no parâmetro de rota.
- **Defesa**:
  - Codificação estrita via `rawurlencode()` sobre a placa normalizada antes de injetar na URI do provedor REST.

### 3.3. Validação Estrita de Entrada e Allowlist (`VehicleDebtRequest`)
- **Risco**: Parâmetros opcionais não validados disparando exceções não tratadas (ex.: `InvalidArgumentException` no `ProviderResolver`), gerando HTTP 500.
- **Defesa**:
  - Validação de formato de placa por Expressão Regular:
    ```regexp
    /^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$|^[A-Z]{3}[0-9]{4}$/
    ```
  - **Allowlist para Provedor**: O parâmetro opcional `provider` deve aceitar estritamente `['rest', 'soap']` (`in:rest,soap`). Qualquer outro valor deve responder imediatamente com **HTTP 400 Bad Request**.

### 3.4. Rate Limiting e Proteção contra DoS
- **Risco**: Requisições massivas abusando de rotas que disparam até 3 tentativas de 2 segundos cada, esgotando o pool PHP-FPM.
- **Defesa**:
  - Aplicação do middleware `throttle:60,1` no endpoint `POST /api/v1/vehicles/debts`, limitando a 60 requisições por minuto por IP.

### 3.5. Cabeçalhos de Segurança HTTP (`SecurityHeadersMiddleware`)
- **Defesa**:
  - Inclusão global dos cabeçalhos em todas as respostas HTTP da aplicação:
    - `X-Content-Type-Options: nosniff`: impede que o navegador ignore o MIME-type declarado.
    - `X-Frame-Options: SAMEORIGIN`: mitiga ataques de Clickjacking.
    - `X-XSS-Protection: 1; mode=block`: ativa proteções nativas contra XSS em clientes legados.
    - `Referrer-Policy: strict-origin-when-cross-origin`: protege o vazamento de referrers para origens externas.

### 3.6. Privacidade e Mascaramento de Dados (LGPD)
- **Defesa**:
  - Todas as placas emitidas em logs estruturados de retry, falha ou sucesso continuam sendo mascaradas com `ProviderExecutor::maskPlate($plate)` (ex.: `ABC****`).
  - Erros internos da infraestrutura são convertidos para respostas de domínio opacas (`all_providers_unavailable` - HTTP 503; `unknown_debt_type` - HTTP 422), sem expor hosts internos, portas ou stack traces.

---

# 4. Arquitetura e Componentes

```text
[Cliente HTTP / Web]
        │
        ▼ (SecurityHeadersMiddleware: nosniff, SAMEORIGIN, etc.)
  [Middleware Pipeline]
        │
        ▼ (Rate Limiting: throttle:60,1)
  [FormRequest: VehicleDebtRequest]
        ├── Validação Regex de Placa (Mercosul / Cinza)
        └── Allowlist de Provider (in:rest,soap) ──> Se inválido: HTTP 400
        │
        ▼ (Entrada Sanitizada)
  [GetVehicleDebtsUseCase / VehicleDebtService]
        ├── Logs estruturados com mascaramento LGPD (ABC****)
        │
        ├── [RestVehicleDebtProvider] ──> rawurlencode($plate)
        └── [SoapVehicleDebtProvider] ──> htmlspecialchars($plate, ENT_XML1) + LIBXML_NONET
```

---

# 5. Componentes Criados e Modificados

1. **`monolith/app/Http/Middleware/SecurityHeadersMiddleware.php`**:
   - Middleware responsável por injetar os 4 cabeçalhos de segurança HTTP em todas as respostas.
2. **`monolith/bootstrap/app.php`**:
   - Registro do `SecurityHeadersMiddleware` na pipeline global da aplicação.
3. **`monolith/routes/api.php`**:
   - Adição do middleware `throttle:60,1` na rota `POST /v1/vehicles/debts`.
4. **`monolith/app/Http/Requests/VehicleDebtRequest.php`**:
   - Inclusão da regra `in:rest,soap` e mensagens de validação customizadas com retorno HTTP 400.
5. **`monolith/app/Infrastructure/Providers/Soap/SoapVehicleDebtProvider.php`**:
   - Escapamento de caracteres XML (`ENT_XML1`) no payload SOAP.
6. **`monolith/app/Infrastructure/Providers/Rest/RestVehicleDebtProvider.php`**:
   - Codificação de URL via `rawurlencode()` na rota externa.
7. **`monolith/tests/Feature/VehicleDebtIntegrationTest.php`**:
   - Testes automatizados para validação de provedor inválido e verificação de cabeçalhos de segurança.

---

# 6. Testes Automatizados

Foram adicionados cenários específicos para garantir regressão zero e validação de segurança:

1. `test_endpoint_returns_bad_request_when_provider_is_invalid`:
   - Envia um provedor fora da allowlist (`unsupported_provider`) e valida que a API retorna HTTP 400 em vez de disparar exceção interna HTTP 500.
2. `test_responses_contain_security_headers`:
   - Faz uma requisição à API e valida a presença e os valores exatos de `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection` e `Referrer-Policy`.

### Resultado da Suíte:
- **Monolith**: 91 testes passando (335 asserções).
- **Provider REST**: 7 testes passando (13 asserções).
- **Provider SOAP**: 7 testes passando (27 asserções).
- **Total**: **105 testes passando (375 asserções), 100% green**.

---

# 7. Critérios de Aceite

- [x] O XML do provedor SOAP não deve permitir quebra de tags por caracteres especiais.
- [x] A URL do provedor REST deve codificar a placa para evitar path traversal.
- [x] Provedores desconhecidos passados na requisição devem resultar em HTTP 400 e não HTTP 500.
- [x] A rota de débitos deve possuir proteção de rate limiting.
- [x] Todas as respostas HTTP devem conter os cabeçalhos de segurança essenciais.
- [x] Nenhuma placa deve ser exposta integralmente em logs de auditoria.
- [x] Todos os 105 testes automatizados do ecossistema devem passar sem falhas.
