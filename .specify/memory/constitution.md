# Constituição do Projeto Cardok

**Versão:** 1.0.0
**Idioma dos artefatos:** pt-BR
**Status:** Ratificada em 2026-09-28

## I. Arquitetura e Isolamento do Domínio — NÃO NEGOCIÁVEL

* `app/Domain` DEVE ser independente de Laravel, banco de dados, HTTP, SOAP e infraestrutura.
* Regras de negócio, Entities e Value Objects NÃO DEVEM depender de frameworks.
* Dependências externas DEVEM ser acessadas por contratos/portas.
* Implementações concretas DEVEM permanecer em `Infrastructure`.
* REST e SOAP DEVEM ser isolados por adapters.
* A arquitetura DEVE preservar inversão de dependência.

## II. Precisão Financeira — NÃO NEGOCIÁVEL

* Valores monetários DEVEM utilizar `Money` internamente em centavos inteiros (`int`).
* `float` NÃO DEVE representar valores monetários.
* Cálculos que exigirem `float` DEVEM limitar seu uso a coeficientes matemáticos.
* Valores monetários DEVEM utilizar arredondamento `HALF_UP`.
* Arredondamentos intermediários DEVEM ser evitados quando puderem alterar o resultado.
* A API DEVE retornar valores monetários como strings decimais.

## III. Integrações e Resiliência

* Providers externos DEVEM ser convertidos para um modelo canônico antes de entrar no domínio.
* Erros transitórios DEVEM possuir retry limitado.
* Retry DEVE utilizar backoff e, quando aplicável, jitter.
* Fallback DEVE seguir **First-Success-Wins**: provider primário → retry → próximo provider.
* Resposta válida com zero débitos NÃO DEVE disparar fallback.
* Providers NÃO DEVEM ser consultados concorrentemente no fluxo padrão.

## IV. Simulação de Pagamentos

O sistema DEVE somente simular pagamentos no escopo atual.

* PIX: desconto de 5%.
* Cartão: somente `1x`, `6x` e `12x`.
* Parcelamento: Price, 2,5% a.m.
* Opções: `TOTAL` e `SOMENTE_<TIPO>`.

O sistema NÃO DEVE executar cobranças reais, gerar PIX real ou conectar-se a adquirentes reais.

## V. Determinismo Temporal

* Regras dependentes de tempo DEVEM utilizar uma abstração de relógio injetável.
* O domínio NÃO DEVE utilizar diretamente `now()`, `Carbon::now()` ou `date()`.
* A data de referência do Home Test é `2024-05-10T00:00:00Z`.
* Comparações de data DEVEM utilizar UTC.

## VI. Observabilidade e Privacidade

* Requisições DEVEM possuir `Request-ID`.
* Logs DEVEM ser estruturados.
* Operações relevantes DEVEM registrar duração, tentativa, provider, fallback e resultado.
* Placas DEVEM ser mascaradas nos logs.
* Dados pessoais DEVEM ser minimizados conforme princípios da LGPD.

## VII. Testes

* Regras de negócio, cálculos financeiros, integrações, retry, fallback e contratos de API DEVEM possuir testes.
* A suíte de testes DEVE passar integralmente antes de uma entrega.
* Correções de bugs relevantes DEVEM possuir testes de regressão.

## VIII. Artefatos de Engenharia

* Constitution, specs, plans, tasks, ADRs, README e demais documentos DEVEM ser escritos em **pt-BR**.
* Identificadores técnicos, nomes de classes, métodos, APIs, comandos, bibliotecas e protocolos DEVEM permanecer em sua forma original.
* O código continua seguindo as convenções técnicas do projeto.

## IX. Desenvolvimento Assistido por IA

* Features relevantes DEVEM seguir:

```text
specify → plan → tasks → implement → validate
```

* Código gerado por IA DEVE obedecer às mesmas regras arquiteturais e de qualidade do código escrito manualmente.
* Requisitos oficiais têm precedência sobre sugestões da IA.
* Alterações DEVEM ser validadas por testes e pelos gates definidos nesta Constituição.

## X. Gates de Qualidade

Antes de concluir uma alteração, verificar:

1. **Arquitetura:** domínio isolado e dependências corretas.
2. **Financeiro:** precisão e `HALF_UP` preservados.
3. **Testes:** suíte passando.
4. **Integração:** retry/fallback preservados.
5. **Docker:** `docker compose config` válido e serviços saudáveis.
6. **Contrato:** requests, responses e códigos HTTP compatíveis.

## Governança

Esta Constituição é a referência arquitetural do projeto.

Alterações DEVEM possuir justificativa e atualizar a versão quando alterarem o significado das regras.

* **MAJOR:** mudança incompatível ou remoção de princípio.
* **MINOR:** novo princípio ou regra relevante.
* **PATCH:** correção ou esclarecimento sem mudança semântica.

**Versão:** 1.0.0
**Última alteração:** 2026-09-28
