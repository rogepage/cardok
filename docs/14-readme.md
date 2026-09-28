# FASE 14 — REESCRITA COMPLETA DO README DO CARDOK

Estamos na etapa final do Home Test do projeto `cardok`.

O README atual é o README padrão gerado pelo Laravel e precisa ser completamente substituído por uma documentação específica do projeto.

## OBJETIVO

Criar um README profissional, objetivo e orientado a avaliação técnica.

O README deve permitir que um avaliador da DOK Despachante consiga:

1. entender rapidamente o problema;
2. entender a arquitetura;
3. executar o projeto;
4. testar a API;
5. entender retry e fallback;
6. entender REST e SOAP;
7. entender as regras de negócio;
8. entender os padrões arquiteturais utilizados;
9. entender as decisões e trade-offs;
10. entender como os testes são executados;
11. entender a observabilidade;
12. entender como o projeto pode evoluir.

---

# REGRA PRINCIPAL

Antes de escrever qualquer conteúdo:

**INSPECIONE O CÓDIGO ATUAL DO CARDOK.**

Não invente:

* endpoints;
* comandos;
* portas;
* nomes de classes;
* quantidade de testes;
* variáveis de ambiente;
* comportamento;
* arquivos;
* métricas;
* funcionalidades.

O README deve refletir o código REAL atual.

Se houver divergência entre esta instrução e o código, o código atual deve ser considerado a fonte da verdade para a documentação operacional.

---

# 1. REMOVER O README PADRÃO DO LARAVEL

Remover o conteúdo atual relacionado exclusivamente ao Laravel, incluindo:

* About Laravel;
* Learning Laravel;
* Contributing ao Laravel;
* Code of Conduct do Laravel;
* Security Vulnerabilities do Laravel;
* License genérica do Laravel;
* badges do Laravel que não representam o Cardok.

Podemos manter uma referência ao Laravel na seção de tecnologia, mas o README não deve parecer documentação do framework.

---

# 2. TÍTULO

Criar:

```text
# Cardok
```

Subtítulo:

```text
Backend para consulta, atualização e simulação de pagamento de débitos veiculares.
```

Deixar claro que o projeto foi desenvolvido como Home Test técnico.

---

# 3. VISÃO GERAL

Explicar brevemente:

O Cardok consulta débitos veiculares através de múltiplos provedores externos, normaliza os diferentes formatos em um modelo canônico, aplica as regras de atualização dos débitos e disponibiliza simulações de pagamento por PIX e cartão.

Explicar que os providers são simulados para o Home Test.

Não afirmar que existe integração financeira real.

---

# 4. OBJETIVOS DO PROJETO

Criar uma lista baseada no Home Test:

* múltiplos provedores;
* REST;
* SOAP;
* normalização;
* cálculo de juros;
* PIX;
* cartão;
* pagamentos totais e parciais;
* retry;
* fallback;
* extensibilidade;
* testes;
* observabilidade.

Somente documentar aquilo que realmente está implementado.

---

# 5. ARQUITETURA

Criar uma seção:

```text
## Arquitetura
```

Explicar que o projeto utiliza um monólito Laravel modularizado e providers simulados em containers Docker.

Representar a arquitetura com um diagrama ASCII simples.

Exemplo conceitual:

```text
                   Client
                     |
                     v
              Laravel Monolith
                     |
          +----------+----------+
          |                     |
          v                     v
    Vehicle Debt             Payment
       Flow                   Flow
          |
          v
   Provider Port
          |
     +----+----+
     |         |
     v         v
   REST       SOAP
 Provider    Provider
```

Além disso, documentar o `payment-provider` somente de acordo com o que realmente estiver implementado.

---

# 6. ESTRUTURA DO PROJETO

Mostrar somente a estrutura relevante atual.

Por exemplo:

```text
app/
├── Application/
├── Domain/
├── Infrastructure/
└── Http/
```

Depois explicar resumidamente a responsabilidade de cada camada.

Destacar:

* Domain;
* Application;
* Infrastructure;
* HTTP;
* Providers;
* Policies/Strategies;
* Payment.

---

# 7. PADRÕES ARQUITETURAIS

Criar:

```text
## Padrões e princípios
```

Documentar somente os padrões realmente presentes no código.

Esperamos encontrar:

### Ports & Adapters

Explicar que o domínio não depende diretamente dos providers externos.

### Adapter

Explicar REST e SOAP como adaptadores dos contratos externos para o modelo interno.

### Strategy / Policy

Explicar as políticas de juros e, se realmente implementado dessa maneira no código, os cálculos de pagamento.

### Dependency Injection

Explicar como as dependências são resolvidas.

Não usar nomes de padrões apenas por efeito decorativo.

---

# 8. FLUXO DE CONSULTA

Documentar o fluxo real.

Exemplo conceitual:

```text
Request
   |
   v
Validação da placa
   |
   v
Provider configurado
   |
   v
Retry
   |
   +---- sucesso ----> normalização
   |
   +---- falha ------> próximo provider
                          |
                          v
                       fallback
                          |
                          v
                      domínio
                          |
                          v
                       resposta
```

Explicar exatamente o comportamento de retry/fallback implementado.

---

# 9. PROVIDERS

Criar seção:

```text
## Providers
```

Documentar:

### REST Provider

* formato;
* endpoint/configuração real;
* comportamento;
* resposta.

### SOAP Provider

* formato;
* endpoint/configuração real;
* comportamento;
* tratamento de `<debts/>`.

Não inventar endpoints.

Usar os valores encontrados no código/configuração.

---

# 10. RETRY E FALLBACK

Explicar:

* quantidade de tentativas;
* backoff;
* jitter, se realmente implementado;
* ordem dos providers;
* quando ocorre fallback;
* comportamento quando todos falham.

Documentar a estratégia real.

Se houver configuração por environment variable, mostrar.

---

# 11. REGRAS DE NEGÓCIO

Documentar as regras do Home Test que estão implementadas.

### IPVA

* 0,33% ao dia;
* teto de 20%;
* juros simples.

### MULTA

* 1% ao dia;
* sem teto.

### Débito não vencido

* juros zero.

### Tipos desconhecidos

* HTTP 422;
* payload correspondente.

Não modificar as regras apenas para melhorar o texto.

---

# 12. PAGAMENTOS

Documentar:

### PIX

5% de desconto.

### Cartão

* 1x;
* 6x;
* 12x;
* Price;
* 2,5% a.m.

Explicar pagamentos:

* TOTAL;
* SOMENTE_<TIPO>.

Somente documentar comportamentos existentes.

---

# 13. API

Criar uma tabela:

| Método | Endpoint | Descrição        |
| ------ | -------- | ---------------- |
| POST   | ...      | Consulta débitos |

Preencher usando os endpoints reais encontrados no código.

Depois incluir exemplo de request.

Exemplo:

```json
{
  "placa": "ABC1234"
}
```

E um exemplo realista de response.

Não inventar campos que não existem.

---

# 14. TRATAMENTO DE ERROS

Documentar os códigos reais implementados.

Especialmente:

```text
400 invalid_plate
422 unknown_debt_type
503 all_providers_unavailable
```

Somente incluir se realmente estiverem implementados.

---

# 15. OBSERVABILIDADE

Criar:

```text
## Observabilidade
```

Documentar somente o que realmente existe.

Se disponível:

* request_id;
* provider;
* tentativa;
* duração;
* fallback;
* status;
* logs estruturados;
* mascaramento de placa.

Incluir exemplos reais de logs somente se forem seguros e não contiverem dados sensíveis.

---

# 16. TESTES

Criar:

```text
## Testes
```

Explicar:

```bash
php artisan test
```

ou o comando realmente utilizado no projeto.

Depois documentar as categorias de testes existentes:

* domínio;
* juros;
* pagamentos;
* providers;
* retry;
* fallback;
* API;
* casos de borda.

NÃO informar quantidade total de testes sem executar o comando e confirmar o número.

---

# 17. EXECUÇÃO COM DOCKER

Criar uma seção muito clara:

```text
## Como executar
```

Descobrir os comandos reais necessários.

Exemplo conceitual:

```bash
docker compose build
docker compose up -d
docker compose ps
```

Depois explicar como verificar os serviços.

Usar os nomes reais retornados por:

```bash
docker compose config --services
```

Não assumir nomes.

---

# 18. HEALTH CHECK / DIAGNÓSTICO

Se existir no projeto:

Documentar comandos como:

```bash
php artisan cardok:check-services
```

e endpoints de diagnóstico.

Confirmar primeiro que eles existem atualmente.

Não documentar comandos que não funcionam.

---

# 19. DECISÕES E TRADE-OFFS

Criar:

```text
## Decisões técnicas e trade-offs
```

Explicar de maneira objetiva decisões como:

### Monólito modular

Por que o núcleo é um monólito em vez de microserviços.

### Providers em containers separados

Por que os providers simulados ficam separados.

### Sem banco

O Home Test não exige persistência.

Explicar que persistência poderia ser adicionada atrás de uma porta/repository sem acoplar o domínio.

### First Success Wins

Explicar a decisão, se essa for realmente a estratégia implementada.

### Dinheiro

Explicar o uso de representação segura para valores monetários.

---

# 20. SEGURANÇA

Documentar apenas o que realmente existe.

Se implementado:

* validação de placa;
* limite de body;
* rejeição de campos desconhecidos;
* mascaramento de placa nos logs.

Separar claramente:

```text
Implementado
```

de:

```text
Melhoria futura
```

---

# 21. MELHORIAS FUTURAS

Criar uma lista curta.

Possíveis exemplos, somente se fizerem sentido:

* circuit breaker;
* métricas externas;
* tracing distribuído;
* persistência;
* autenticação;
* gateway de pagamento real;
* cache;
* fila;
* idempotência;
* rate limiting.

Não afirmar que são necessárias para o Home Test.

Explicar que são evoluções possíveis.

---

# 22. SPEC KIT E IA

Como o projeto passou a utilizar Spec Kit, criar uma seção:

```text
## Desenvolvimento assistido por IA
```

Explicar de forma profissional que ferramentas de IA são utilizadas no processo de desenvolvimento.

Se o Spec Kit estiver realmente configurado:

```text
Constitution
    ↓
Specification
    ↓
Plan
    ↓
Tasks
    ↓
Implementation
    ↓
Validation
```

Explicar que o objetivo é manter rastreabilidade entre requisito, implementação e validação.

Não afirmar que features históricas foram desenvolvidas pelo Spec Kit se isso não for verdade.

---

# 23. LICENÇA

Verificar o contexto real do repositório.

Não copiar automaticamente a licença do Laravel.

Se o projeto não possuir licença definida, informar isso de maneira simples ou não criar uma licença sem autorização.

---

# 24. REGRAS DE QUALIDADE DO README

O README final deve:

* ser profissional;
* ser objetivo;
* ser fácil de ler;
* ter exemplos;
* não conter texto genérico do Laravel;
* não conter links desnecessários;
* não inventar informações;
* não conter informações contraditórias com o código;
* não documentar funcionalidades inexistentes;
* não exagerar a arquitetura;
* não usar linguagem de marketing.

O README deve parecer documentação de um projeto que será entregue para uma equipe de engenharia.

---

# 25. VALIDAÇÃO FINAL

Depois de escrever o README:

Executar:

```bash
git diff -- README.md
```

Verificar:

1. todos os comandos existem;
2. todos os endpoints existem;
3. todos os nomes de serviços estão corretos;
4. os exemplos correspondem ao código;
5. as regras matemáticas estão corretas;
6. retry/fallback estão documentados corretamente;
7. nenhum recurso inexistente foi documentado;
8. nenhum segredo foi incluído;
9. nenhuma alteração de código foi feita.

Executar também:

```bash
git status --short
```

e confirmar que **somente o README foi alterado nesta fase**, salvo arquivos de documentação explicitamente necessários.

---

# RESULTADO ESPERADO

Ao final, quero um README que permita ao avaliador:

```text
Clone
  ↓
docker compose up
  ↓
entender arquitetura
  ↓
executar consulta
  ↓
simular falha
  ↓
observar retry/fallback
  ↓
executar testes
  ↓
entender decisões arquiteturais
```

Antes de finalizar, apresente um resumo:

```text
README atualizado: SIM/NÃO

Endpoints verificados: X
Comandos verificados: X
Funcionalidades documentadas: X
Funcionalidades não documentadas por falta de evidência: X

Código alterado: NÃO
```

Não faça nenhuma implementação de código nesta fase.
