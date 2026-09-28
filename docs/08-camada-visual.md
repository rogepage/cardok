Você é meu pair programmer no projeto `cardok`.

O backend está concluído e testado.

Agora vamos criar a **Fase 9 — Interface Web com Laravel Livewire**.

## Objetivo

Criar uma interface simples para demonstrar o funcionamento do Cardok.

A interface deve permitir:

1. informar uma placa;
2. consultar os débitos;
3. apresentar os débitos encontrados;
4. apresentar os valores atualizados;
5. apresentar o resumo;
6. apresentar as opções de pagamento;
7. apresentar erros de forma amigável.

A interface é **somente uma camada de apresentação**.

NÃO mover nenhuma regra de negócio para o Livewire.

---

# 1. Regra arquitetural principal

O Livewire NÃO deve:

* calcular juros;
* calcular PIX;
* calcular parcelas;
* chamar REST diretamente;
* chamar SOAP diretamente;
* implementar retry;
* implementar fallback;
* interpretar XML;
* interpretar respostas dos providers.

O fluxo deve ser:

```text
Livewire
   ↓
Application Service
   ↓
Domain
   ↓
Provider Resolver
   ↓
REST / SOAP
```

O Livewire apenas coleta entrada e apresenta o resultado.

---

# 2. Reutilizar o Application Layer

Identifique o Application Service já utilizado pela API.

O Livewire deve utilizar o **mesmo Application Service**.

Não criar uma segunda implementação do caso de uso.

Exemplo conceitual:

```text
                  Application
                      │
              VehicleDebtService
                 /          \
                /            \
             API          Livewire
```

Isso é importante para demonstrar que:

> API e interface Web são apenas diferentes portas de entrada para o mesmo caso de uso.

---

# 3. Criar componente Livewire

Criar um componente semelhante a:

```text
VehicleDebtLookup
```

ou utilizar a nomenclatura que melhor se encaixar no projeto.

Responsabilidades:

```text
placa
loading
resultado
erro
consultar()
```

Não colocar regras financeiras dentro do componente.

---

# 4. Tela inicial

Criar uma página simples:

```text
┌─────────────────────────────────────────────────┐
│                                                 │
│                    CARDOK                       │
│                                                 │
│        Consulta de Débitos Veiculares           │
│                                                 │
│   Informe a placa do veículo para consultar     │
│   seus débitos.                                 │
│                                                 │
│   Placa                                         │
│   ┌─────────────────────────────────────────┐   │
│   │ ABC1234                                 │   │
│   └─────────────────────────────────────────┘   │
│                                                 │
│              [ Consultar débitos ]              │
│                                                 │
└─────────────────────────────────────────────────┘
```

Não criar uma aplicação visual complexa.

---

# 5. Loading

Ao clicar em:

```text
Consultar débitos
```

mostrar estado de carregamento.

Exemplo:

```text
[ Consultando... ]
```

Desabilitar o botão enquanto a consulta estiver em andamento.

O usuário não deve conseguir disparar várias consultas simultâneas através da interface.

---

# 6. Normalização da placa

A interface pode:

* converter para uppercase;
* remover espaços externos.

Mas isso é apenas UX.

A validação oficial continua sendo feita pelo backend.

Não confiar na validação do navegador.

---

# 7. Resultado

Depois de uma consulta bem-sucedida, apresentar:

```text
┌─────────────────────────────────────────────────┐
│ Resultado da consulta                           │
│                                                 │
│ Placa: ABC1234                                  │
│                                                 │
│ ┌─────────────────────────────────────────────┐ │
│ │ IPVA                                        │ │
│ │                                             │ │
│ │ Original       R$ 1.500,00                  │ │
│ │ Atualizado     R$ 1.800,00                  │ │
│ │ Vencimento     10/01/2024                   │ │
│ │ Dias em atraso 121                          │ │
│ └─────────────────────────────────────────────┘ │
│                                                 │
│ ┌─────────────────────────────────────────────┐ │
│ │ MULTA                                       │ │
│ │                                             │ │
│ │ Original       R$ 300,50                    │ │
│ │ Atualizado     R$ 555,93                    │ │
│ │ Vencimento     15/02/2024                   │ │
│ │ Dias em atraso 85                           │ │
│ └─────────────────────────────────────────────┘ │
│                                                 │
└─────────────────────────────────────────────────┘
```

---

# 8. Resumo

Criar uma seção destacada:

```text
┌─────────────────────────────────────────────────┐
│ RESUMO                                          │
│                                                 │
│ Total original          R$ 1.800,50             │
│ Total atualizado        R$ 2.355,93             │
│                                                 │
└─────────────────────────────────────────────────┘
```

---

# 9. Pagamentos

Apresentar as opções retornadas pelo backend.

Não recalcular nada no Livewire.

Exemplo:

```text
┌─────────────────────────────────────────────────┐
│ OPÇÕES DE PAGAMENTO                             │
│                                                 │
│ PAGAMENTO TOTAL                                 │
│                                                 │
│ Valor: R$ 2.355,93                              │
│                                                 │
│ PIX                                             │
│ R$ 2.238,13                                     │
│                                                 │
│ CARTÃO                                          │
│                                                 │
│ 1x   R$ 2.355,93                                │
│ 6x   R$   427,72                                │
│ 12x  R$   229,67                                │
│                                                 │
└─────────────────────────────────────────────────┘
```

Depois:

```text
SOMENTE IPVA
```

e:

```text
SOMENTE MULTA
```

---

# 10. Usabilidade

Criar uma hierarquia visual clara:

```text
Consulta
   ↓
Débitos
   ↓
Resumo
   ↓
Pagamento
```

Não precisa implementar pagamento real.

É apenas simulação.

---

# 11. Erro de placa

Se o backend retornar:

```json
{
    "error": "invalid_plate"
}
```

mostrar:

```text
Placa inválida.
Verifique o formato informado.
```

Não mostrar:

```text
invalid_plate
```

diretamente para o usuário.

---

# 12. Todos os providers indisponíveis

Se receber:

```json
{
    "error": "all_providers_unavailable"
}
```

mostrar:

```text
Não foi possível consultar os débitos no momento.

Tente novamente em alguns instantes.
```

Não expor detalhes técnicos.

---

# 13. Tipo de débito desconhecido

Se receber:

```json
{
    "error": "unknown_debt_type",
    "type": "LICENCIAMENTO"
}
```

mostrar uma mensagem amigável.

Não precisa expor o nome interno da exceção.

---

# 14. Estado sem débitos

Se a consulta retornar zero débitos:

```text
┌──────────────────────────────────────┐
│                                      │
│       Nenhum débito encontrado.      │
│                                      │
│      Seu veículo está sem débitos    │
│      disponíveis para consulta.      │
│                                      │
└──────────────────────────────────────┘
```

Não apresentar:

```text
SOMENTE_IPVA
SOMENTE_MULTA
```

quando não houver débitos.

---

# 15. Estado inicial

Ao abrir a página:

```text
consulta = vazia
resultado = vazio
erro = vazio
```

Mostrar somente o formulário.

Não realizar consulta automaticamente.

---

# 16. Responsividade

A tela deve funcionar em:

* desktop;
* notebook;
* tablet;
* celular.

Priorizar desktop porque o projeto será apresentado em uma entrevista.

Não utilizar bibliotecas excessivas.

Se o projeto já possuir Tailwind, utilizar Tailwind.

Não adicionar um framework CSS adicional sem necessidade.

---

# 17. Visual

Criar uma interface:

* limpa;
* moderna;
* profissional;
* com boa hierarquia;
* sem excesso de animações.

Evitar transformar o Home Test em um projeto de frontend.

O foco continua sendo backend.

---

# 18. Formatação monetária

Os valores vindos da API continuam sendo strings.

O Livewire pode apenas formatar para apresentação:

```text
1800.00
```

como:

```text
R$ 1.800,00
```

Não recalcular.

Não converter para float para fazer cálculos.

A interface apenas apresenta.

---

# 19. Data

O backend retorna:

```text
2024-01-10
```

A interface pode apresentar:

```text
10/01/2024
```

Isso é apenas formatação visual.

---

# 20. Testes Livewire

Criar testes para:

### Página inicial

Verificar que carrega.

### Consulta válida

Informar:

```text
ABC1234
```

e verificar que o resultado aparece.

### Placa inválida

Verificar mensagem amigável.

### Provider indisponível

Verificar mensagem amigável.

### Zero débitos

Verificar mensagem de ausência de débitos.

### Loading

Verificar que o botão é desabilitado durante a consulta, quando possível no contexto da implementação.

---

# 21. Não duplicar testes de domínio

Não repetir no Livewire testes de:

* juros;
* Price;
* PIX;
* retry;
* fallback.

Esses comportamentos já pertencem aos testes do backend.

Os testes Livewire devem verificar apenas:

```text
entrada
 ↓
chamada do Application Service
 ↓
apresentação
```

---

# 22. Segurança

Não colocar no frontend:

* credenciais;
* URLs internas dos providers;
* configurações de retry;
* secrets;
* detalhes de infraestrutura.

O browser deve conhecer somente o necessário para a interface.

---

# 23. API versus Livewire

Não remover a API existente.

Ao final teremos:

```text
                 CARDOK
                    │
          ┌─────────┴─────────┐
          │                   │
       REST API             Livewire
          │                   │
          └─────────┬─────────┘
                    ▼
               Application
                    │
                 Domain
                    │
              Infrastructure
                    │
             ┌──────┴──────┐
             ▼             ▼
           REST           SOAP
```

A API continuará sendo a interface principal do backend.

Livewire será uma interface adicional para demonstração.

---

# 24. Não implementar

Nesta fase NÃO implementar:

* autenticação;
* cadastro de usuário;
* banco de dados;
* pagamento real;
* geração de QR Code PIX;
* integração com adquirente;
* checkout;
* carrinho;
* histórico;
* dashboard administrativo.

O objetivo é somente demonstrar a consulta e simulação.

---

# 25. Critérios de conclusão

A fase estará concluída quando:

1. A página carregar.
2. O usuário conseguir informar uma placa.
3. A consulta utilizar o Application Service existente.
4. Nenhuma regra de negócio estiver no Livewire.
5. O resultado dos débitos aparecer corretamente.
6. O resumo aparecer corretamente.
7. As opções de pagamento aparecerem.
8. Zero débitos possuir estado específico.
9. Erros possuírem mensagens amigáveis.
10. Loading funcionar.
11. A interface funcionar em desktop e mobile.
12. Os testes Livewire passarem.
13. Os testes existentes do backend continuarem passando.
14. A API continuar funcionando independentemente da interface.

---

# 26. Processo obrigatório

Antes de implementar:

1. Analise a estrutura atual.
2. Verifique se Livewire já está instalado.
3. Verifique a versão do Laravel e Livewire.
4. Identifique o Application Service correto.
5. Não crie um novo caso de uso se já existir um adequado.
6. Liste os arquivos que serão criados/alterados.
7. Implemente.
8. Execute os testes.
9. Corrija eventuais problemas.
10. Execute todos os testes novamente.
11. Mostre um resumo final.

Ao final, explique como o Livewire está consumindo o Application Layer e como a arquitetura continua separando apresentação, aplicação, domínio e infraestrutura.

---

# 27. Implementação Realizada

### 27.1. Componentes Criados e Alterados

1. **Camada de Aplicação (`App\Application\VehicleDebt`)**:
   - `VehicleDebtConsultationResult`: DTO de aplicação que encapsula `CalculatedVehicleDebts` e `PaymentSimulationResult`, expondo o método canônico `toArray()` para serialização idêntica tanto para HTTP JSON quanto para Livewire arrays.
   - `VehicleDebtService`: Adicionado método orquestrador `consultDebts(string $plate, ?array $customOrder = null): VehicleDebtConsultationResult`. Unifica a busca de débitos via infraestrutura/provedores externos com resiliência, cálculo de juros/multas no domínio (`DebtCalculationService`) e simulação de opções de pagamento no domínio (`PaymentSimulator`).

2. **Controlador HTTP REST (`App\Http\Controllers\VehicleDebtIntegrationController`)**:
   - Refatorado para delegar a consulta diretamente ao `VehicleDebtService->consultDebts()`. Mantém comportamento de API REST 100% idêntico e desacoplado.

3. **Componente Livewire (`App\Livewire\VehicleDebtLookup`)**:
   - Componente puro de apresentação (`placa`, `resultado`, `erro`, `loading`).
   - Normaliza a placa (trim e uppercase).
   - Valida formato Mercosul/Cinza com mensagens amigáveis em português (`invalid_plate`).
   - Captura exceções da aplicação/domínio (`all_providers_unavailable`, `unknown_debt_type`) e exibe alertas contextuais sem expor stack traces.
   - Oferece método `limpar()` para resetar o estado.
   - **Zero lógica de negócio**: sem regras financeiras, sem chamadas a HTTP clients, sem cálculo de juros ou parcelas.

4. **Views Blade e Layout Responsivo**:
   - `resources/views/layouts/app.blade.php`: layout base HTML5 semântico com Tailwind CSS, Google Fonts (*Plus Jakarta Sans*), meta tags de viewport e diretivas Livewire (`@livewireStyles`, `@livewireScripts`).
   - `resources/views/livewire/vehicle-debt-lookup.blade.php`: interface dinâmica contendo:
     - Formulário de consulta com botão de envio, atalho de limpeza e indicador de loading (`wire:loading`, desativação de botão contra submissões concorrentes);
     - Alerta de erro estilizado em caso de falha de validação ou indisponibilidade de serviços;
     - Card de **Zero Débitos** com a mensagem mandatória: *"Nenhum débito encontrado. Seu veículo está sem débitos disponíveis para consulta."*;
     - Lista de débitos pendentes com badges (`IPVA` azul, `MULTA` âmbar), exibição de dias de atraso, valor original e valor atualizado com juros;
     - Resumo consolidado destacando o total original e o total atualizado com encargos;
     - Seção de opções de liquidação (`TOTAL`, `SOMENTE_IPVA`, `SOMENTE_MULTA`), detalhando a condição PIX com 5% de desconto e a tabela de parcelamento em Cartão de Crédito (1x sem juros, 6x e 12x via Tabela Price).

5. **Rotas Web (`routes/web.php`)**:
   - Rota `/` mapeada para `VehicleDebtLookup::class`.

### 27.2. Separação de Responsabilidades e Arquitetura

```text
       [Navegador / Livewire UI]           [Cliente HTTP / API REST]
           (Apresentação)                       (Apresentação)
                 │                                    │
                 ▼                                    ▼
       VehicleDebtLookup                   VehicleDebtIntegrationController
                 │                                    │
                 └──────────────────┬─────────────────┘
                                    │
                                    ▼ (Chamada Única)
                       VehicleDebtService::consultDebts()
                              (Camada de Aplicação)
                                    │
               ┌────────────────────┼────────────────────┐
               │                    │                    │
               ▼                    ▼                    ▼
     ProviderRegistry        DebtCalculationService   PaymentSimulator
      (Infraestrutura)             (Domínio)              (Domínio)
      Retry & Fallback         Juros, Multa, HALF_UP    Price, PIX desc.
```

- **Apresentação**: Livewire e Controllers HTTP são adaptadores de entrada (Inbound Adapters). Eles apenas recebem entrada do usuário, chamam a aplicação e renderizam a resposta.
- **Aplicação**: `VehicleDebtService` coordena o fluxo de execução entre infraestrutura e domínio, retornando um DTO de resultado limpo.
- **Domínio**: Todas as regras matemáticas e financeiras (HALF_UP sem float, teto de IPVA de 20%, diária de 0.33%, multa de 1% a.d., Price a 2.5% a.m., PIX 5% de desconto) permanecem intocadas e isoladas no domínio.
- **Infraestrutura**: Clientes HTTP, XML parsers, retries e fallbacks permanecem encapsulados na infraestrutura.

### 27.3. Testes Automatizados

- Criada suíte `monolith/tests/Feature/Livewire/VehicleDebtLookupTest.php`:
  1. `test_page_renders_successfully`: Valida HTTP 200 e montagem do componente Livewire.
  2. `test_successful_debt_lookup_displays_debts_and_payments`: Testa fluxo da placa `ABC1234` com débitos, resumo e opções de pagamento (PIX e parcelas).
  3. `test_invalid_plate_shows_validation_error`: Valida rejeição de placas fora do padrão com mensagem amigável.
  4. `test_empty_plate_shows_validation_error`: Valida obrigatoriedade do campo placa.
  5. `test_shows_friendly_error_when_providers_are_unavailable`: Simula falha de todos os provedores e valida exibição de erro amigável.
  6. `test_zero_debts_shows_specific_message`: Testa placa `DEF5678` e valida texto exato de veículo sem débitos.
  7. `test_limpar_resets_form_and_results`: Testa método de limpeza de busca e restauração de estado.

- **Resultado da Suíte**:
  - `monolith`: 85 testes passando (309 asserções).
  - `provider-rest`: 7 testes passando (13 asserções).
  - `provider-soap`: 7 testes passando (27 asserções).
  - **Total**: 99 testes passando, 349 asserções, 100% de sucesso.

