Você é meu pair programmer no projeto `cardok`.

Estamos chegando ao fechamento do backend do Home Test.

As fases anteriores implementaram:

* Laravel monólito;
* Docker Compose;
* provider REST mock;
* provider SOAP mock;
* adapter REST;
* adapter SOAP;
* modelo canônico;
* retry;
* fallback;
* tratamento de indisponibilidade;
* domínio de débitos;
* cálculo de juros;
* resumo;
* pagamento TOTAL;
* pagamentos parciais;
* PIX;
* cartão 1x, 6x e 12x;
* validação da placa;
* contrato HTTP;
* tratamento estruturado de erros.

Agora vamos executar a **Fase 8 — Testes de integração, cenários de falha e fechamento técnico do backend**.

O objetivo NÃO é adicionar novas funcionalidades.

O objetivo é garantir que tudo que já foi implementado funciona integrado e está pronto para apresentação.

---

# 1. Primeiro: análise do projeto

Antes de alterar qualquer código:

1. Analise toda a estrutura atual.
2. Analise os testes existentes.
3. Identifique testes duplicados.
4. Identifique responsabilidades que estejam no lugar errado.
5. Identifique possíveis violações das separações:

   * HTTP;
   * Application;
   * Domain;
   * Infrastructure.
6. Identifique código duplicado.
7. Identifique classes excessivamente grandes.
8. Liste os pontos que precisam de correção.

NÃO reescreva o projeto inteiro.

Faça somente as alterações necessárias.

---

# 2. Fluxo principal que precisamos garantir

O fluxo completo deve ser:

```text
HTTP Request
     ↓
Validation
     ↓
Application
     ↓
Provider Resolver
     ↓
REST / SOAP
     ↓
Canonical Model
     ↓
Domain
     ↓
Interest Calculation
     ↓
Payment Simulation
     ↓
HTTP Response
```

Nenhuma camada deve assumir responsabilidade de outra.

---

# 3. Cenário principal

Utilizar:

```text
placa = ABC1234
data = 2024-05-10
```

Provider REST:

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

O resultado deve conter:

```text
IPVA:
original = 1500.00
atualizado = 1800.00
vencimento = 2024-01-10
dias_atraso = 121

MULTA:
original = 300.50
atualizado = 555.93
vencimento = 2024-02-15
dias_atraso = 85

Resumo:
total_original = 1800.50
total_atualizado = 2355.93

Pagamentos:
- TOTAL: base = 2355.93 | PIX = 2238.13 | 1x = 2355.93 | 6x = 427.72 | 12x = 229.67
- SOMENTE_IPVA: base = 1800.00 | PIX = 1710.00 | 1x = 1800.00 | 6x = 326.79 | 12x = 175.48
- SOMENTE_MULTA: base = 555.93 | PIX = 528.13 | 1x = 555.93 | 6x = 100.93 | 12x = 54.20
```

---

# 4. Fechamento Técnico Implementado

1. **Separação Rígida de Camadas**:
   - **HTTP**: `VehicleDebtRequest` (validação de entrada e formatação de erro HTTP 400) e `VehicleDebtIntegrationController` (transporte HTTP enxuto).
   - **Application**: `GetVehicleDebtsUseCase` (orquestrador de fluxo entre provedor, cálculo de débitos e simulação de pagamento) e `VehicleDebtConsultationResult` (DTO).
   - **Domain**: `DebtCalculationService`, `PaymentSimulator`, policies, VO `Money` e `HalfUpRounder`.
   - **Infrastructure**: Clientes REST e SOAP com isolamento de falhas, retries e backoff linear.

2. **Validação da Placa**:
   - Suporte a placas tradicionais (`ABC1234`) e Mercosul (`ABC1D23`).
   - Normalização automática de espaços e maiúsculas.
   - Retorno HTTP 400 com erro claro quando ausente ou formato inválido.

3. **Limpeza e Cobertura de Testes**:
   - Remoção de testes boilerplate desnecessários.
   - 82 testes passando no monólito (282 asserções).
   - 14 testes passando nos mock providers (40 asserções).
   - Total de 96 testes e 322 asserções 100% green.

