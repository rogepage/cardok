# Technical Research: Adição do Débito de Licenciamento

**Feature**: `006-add-licenciamento-debt`  
**Status**: Concluído  

---

## 1. Política de Cálculo de Juros: Classe Dedicada vs. Compartilhada

### Decisão
Criar uma classe de domínio dedicada `App\Domain\Debt\Policies\LicenciamentoInterestPolicy` implementando `App\Domain\Debt\Policies\DebtInterestPolicyInterface`.

### Justificativa
1. **Separação de Conceitos Regulatórios (SRP)**: O IPVA é um tributo estadual administrado pela Secretaria da Fazenda (SEFAZ), enquanto o Licenciamento é uma taxa anual de serviço de trânsito (DETRAN/CONTRAN). Embora as regras numéricas atuais coincidam (0,33%/dia com teto de 20%), suas bases legais e regras futuras podem divergir.
2. **Open/Closed Principle (OCP)**: A arquitetura atual utiliza o padrão *Strategy & Registry*. A criação de uma nova classe respeita a integridade do domínio sem necessidade de alterar `IpvaInterestPolicy`.
3. **Legibilidade e Rastreabilidade do Domínio**: Em relatórios de auditoria e logs estruturados, fica evidente qual política específica foi executada para cada débito.

### Alternativas Consideradas
* **Reutilizar `IpvaInterestPolicy` fazendo com que ela suporte ambos os tipos (`supports(DebtType $type)` retornando `true` para IPVA e LICENCIAMENTO)**: Rejeitado porque quebra o princípio da responsabilidade única e cria acoplamento artificial entre impostos e taxas de trânsito.
* **Criar uma política genérica parametrizada `CappedDailyInterestPolicy`**: Rejeitado no nível de domínio para manter a ubiquidade da linguagem de negócio (Domain-Driven Design).

---

## 2. Expansão do Modelo Canônico (`DebtType` Enum)

### Decisão
Adicionar o caso `LICENCIAMENTO = 'LICENCIAMENTO'` ao enum tipado `App\Domain\Debt\DebtType`.

### Justificativa
1. O método `DebtType::tryFromNormalized($type)` já realiza `strtoupper(trim($type))`, garantindo normalização case-insensitive automática tanto para o provedor REST quanto para o provedor SOAP.
2. O simulador de pagamentos (`PaymentSimulator`) agrupa débitos dinamicamente usando `"SOMENTE_{$typeName}"`, garantindo que a opção `"SOMENTE_LICENCIAMENTO"` seja gerada de forma transparente e sem intervenção procedural.

---

## 3. Registro no Service Container (`AppServiceProvider`)

### Decisão
Injetar a nova instância `new LicenciamentoInterestPolicy()` na lista de políticas instanciadas no `DebtInterestPolicyRegistry` em `AppServiceProvider::register()`.

### Justificativa
O container IoC do Laravel resolve o `DebtInterestPolicyRegistry` como singleton. Ao adicionar a nova política na lista registrada, qualquer caso de uso ou serviço de domínio passa a suportar débitos de Licenciamento automaticamente.

---

## 4. Precisão Financeira e Arredondamento

### Decisão
Manter rigorosamente a aritmética baseada em centavos inteiros via Value Object `Money` com arredondamento `HALF_UP`:
* Taxa diária: $0{,}0033$ ($0{,}33\%$).
* Limite máximo (teto): $0{,}20$ ($20{,}00\%$).
* Fórmula: $\text{encargos} = \min(\text{original} \times \text{dias} \times 0{,}0033, \text{original} \times 0{,}20)$.
* Arredondamento final aplicado em centavos inteiros com `Money::roundHalfUp()`.
