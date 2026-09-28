# Fase 10 — Arquitetura de Pagamentos: Simulação vs. Liquidação Real

Este documento formaliza as decisões arquiteturais, separação de responsabilidades, independência operacional e trade-offs relacionados ao ecossistema de pagamentos na plataforma **Cardok**.

---

# 1. Objetivo e Escopo do Home Test

O Cardok foi concebido com uma fronteira clara de escopo:
> **O sistema deve SIMULAR formas de pagamento (PIX e cartão de crédito), mas NÃO deve realizar pagamentos reais.**

### Diretrizes Estritas:
- **Não implementar** cobrança financeira real;
- **Não implementar** adquirente ou gateway transacional;
- **Não implementar** tela de checkout ou carrinho;
- **Não implementar** geração de QR Code PIX (EMV / copia-e-cola);
- **Não criar** autenticação ou credenciais de pagamento;
- **Não criar** persistência ou conciliação de transações no banco de dados.

---

# 2. Simulação de Pagamentos no Domínio Puro

O fluxo de cálculo das opções de pagamento é executado inteiramente na camada de domínio (`App\Domain\Payment`), sendo totalmente determinístico e desacoplado de dependências externas:

```text
       CalculatedVehicleDebts (Domínio de Débitos)
                    │
                    ▼
            PaymentSimulator (Domínio de Pagamentos)
             ├── PixCalculator (Desconto de 5% com HalfUpRounder)
             └── CreditCardCalculator (Tabela Price a 2,5% a.m. - 1x, 6x, 12x)
                    │
                    ▼
          PaymentSimulationResult (DTO Canônico)
           ├── TOTAL
           ├── SOMENTE_IPVA
           └── SOMENTE_MULTA
```

- **Agrupamento Determinístico**: Múltiplos débitos da mesma categoria são consolidados em uma única opção parcial (`SOMENTE_<TIPO>`).
- **Ordem de Primeira Aparição**: As opções parciais respeitam estritamente a ordem com que aparecem na lista de débitos.
- **Sem Float Binário**: Toda a aritmética financeira utiliza inteiros (centavos) e arredondamento `HALF_UP` padronizado.

---

# 3. Separação Conceitual: Simulação vs. Liquidação Real

A arquitetura separa explicitamente duas perguntas de negócios fundamentais:

| Dimensão | Simulação (Escopo Atual) | Liquidação (Evolução Futura) |
| :--- | :--- | :--- |
| **Pergunta** | *"Quanto o cliente pagaria?"* | *"Realizar efetivamente a transação financeira."* |
| **Localização** | Camada de Domínio (`App\Domain\Payment`) | Camada de Infraestrutura / Integração com Gateway |
| **Dependências** | Nenhuma (aritmética pura e políticas de domínio) | Gateway externo, adquirente bancária, webhook |
| **Efeito Colateral** | Imutável / Idempotente (sem efeitos de rede) | Movimentação financeira, conciliação e recibo |

---

# 4. Decisão Arquitetural: Não Criar Abstração Desnecessária (*YAGNI*)

### Avaliação da `PaymentGatewayInterface`:
Avaliou-se a criação antecipada de uma interface de cobrança:
```php
interface PaymentGatewayInterface
{
    public function charge(PaymentOrder $order): PaymentReceipt;
}
```

### Decisão: NÃO Criar a Interface em Código Neste Momento
- **Justificativa**: Não há caso de uso, controlador ou serviço que consuma essa interface no escopo atual. Criar classes órfãs (`PaymentGatewayInterface`, `PaymentOrder`, `PaymentReceipt`) sem uso imediato viola o princípio **YAGNI** (*You Aren't Gonna Need It*) e introduz **Overengineering** (generalidade especulativa).
- **Abordagem Adotada**: O contrato e o blueprint foram formalizados na documentação arquitetural. Quando o checkout for desenvolvido, a porta será criada no momento oportuno, guiada por requisitos reais de negócio.

---

# 5. O Papel do Container `payment-provider` no Docker Compose

O serviço `payment-provider` (`cardok-payment-provider`) permanece ativo na rede Docker (`cardok-network`):
- **Finalidade Atual**: Simula a presença de um provedor externo de pagamentos para testes de infraestrutura e conectividade de rede.
- **Isolamento de Rede**: Não expõe portas para a máquina host (`ports`), sendo acessível apenas internamente via `expose: ["8000"]`.
- **Endpoints Expostos**:
  1. `GET /health`: Health check de infraestrutura.
  2. `POST /charge`: Endpoint mock que simula uma resposta de autorização financeira (`status: approved`, `transaction_id`, `simulated: true`), demonstrando o contrato futuro sem executar qualquer transação bancária.
- **Regra Rígida**: O endpoint `POST /charge` **NÃO** é chamado pelo fluxo principal do monólito Cardok.

---

# 6. Garantia de Independência e Resiliência

O fluxo de consulta veicular é completamente independente do estado do provedor de pagamentos:

```text
[Cliente] ──> POST /api/v1/vehicles/debts
                    │
                    ▼
         GetVehicleDebtsUseCase
                    ├── 1. Consulta Provedores (REST / SOAP com Retry & Fallback)
                    ├── 2. Normalização para Modelo Canônico
                    ├── 3. Cálculo de Juros e Multas (DebtCalculationService)
                    └── 4. Simulação de Pagamentos (PaymentSimulator)
                    │
                    ▼
          [HTTP 200 OK com Débitos e Opções de Pagamento]
```

O `payment-provider` não faz parte dessa esteira. Se o container de pagamentos cair, for reiniciado ou falhar, a consulta de débitos e a simulação de opções continuarão respondendo com sucesso (HTTP 200).

---

# 7. Diagnósticos e Health Checks de Infraestrutura

A verificação do `payment-provider` existe estritamente em ferramentas de diagnóstico operacional:
- **Comando Artisan**: `php artisan cardok:check-services` (testa DNS e HTTP nos 3 serviços externos).
- **Endpoint HTTP**: `GET /api/health/integrations` (monitora integridade agregada dos serviços).

Esses diagnósticos são estritamente isolados da camada de aplicação e de domínio.

---

# 8. Blueprint para Evolução Futura (Liquidação Real)

Quando a plataforma evoluir para suportar checkout real, a integração seguirá o padrão de **Portas e Adaptadores (Hexagonal Architecture)** já validado nos débitos:

```text
             [Checkout Controller / Webhook]
                          │
                          ▼
            [Payment Application Service]
                          │
                          ▼ (Porta de Domínio)
             <<interface>> PaymentGateway
                          ▲
                          │ Implementa
             [HttpPaymentGatewayAdapter] (Infraestrutura)
                          │ HTTP POST /charge
                          ▼
                  [payment-provider]
```

---

# 9. Testes Automatizados

Foram adicionados testes em [`monolith/tests/Feature/VehicleDebtIntegrationTest.php`](file:///Users/roger/Desktop/docker/cardok/monolith/tests/Feature/VehicleDebtIntegrationTest.php):

1. `test_payment_provider_unavailability_does_not_affect_debt_consultation_and_simulation`:
   - Simula o `payment-provider` offline / retornando HTTP 500.
   - Valida que a consulta de débitos e a simulação das opções de pagamento (`TOTAL`, `SOMENTE_IPVA`, `SOMENTE_MULTA`, `pix`, `cartao_credito`) continuam funcionando com HTTP 200 e valores exatos.
2. `test_health_integrations_endpoint_reports_service_status`:
   - Valida que o endpoint de diagnóstico `/api/health/integrations` reporta com precisão a saúde do `payment-provider` sem interferir na aplicação.

### Resultado da Suíte Completa:
- **Monolith**: 93 testes passando (346 asserções)
- **Provider REST**: 7 testes passando (13 asserções)
- **Provider SOAP**: 7 testes passando (27 asserções)
- **Total**: **107 testes passando (386 asserções), 100% green**.

---

# 10. Trade-offs Arquiteturais

### 10.1. Simulação no Domínio vs. Chamada a Provedor Externo
> *Optamos por manter a simulação de pagamentos dentro do domínio/aplicação porque o requisito do teste é calcular as opções de pagamento, e não efetivar uma transação. O payment-provider permanece provisionado como infraestrutura preparada para uma futura integração de liquidação. Isso evita introduzir uma dependência externa desnecessária no fluxo atual, mantendo um ponto claro de extensão para pagamentos reais.*

### 10.2. Manutenção do Container `payment-provider` sem Participação na Regra Atual
- **Trade-off Negativo (Custo)**: Execução de um container adicional no Docker Compose consumindo memória e CPU (desprezíveis com imagem leve PHP), além de adicionar um serviço a mais no arquivo de orquestração.
- **Trade-off Positivo (Benefício)**: 
  1. **Prontidão de Infraestrutura**: Toda a infraestrutura de rede (`cardok-network`), roteamento DNS interno e monitoramento já nascem validados e operacionais.
  2. **Zero Risco de Regressão**: Quando a fase de liquidação for construída, não haverá necessidade de alterar a topologia do Docker ou reconfigurar conectividade de rede entre serviços.
  3. **Contrato Documentado**: O endpoint mock `POST /charge` serve como especificação viva (*Living Documentation*) para qualquer desenvolvedor que assumir a etapa seguinte do projeto.
