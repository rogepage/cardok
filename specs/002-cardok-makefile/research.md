# Research: Makefile de Operação, Validação e Demonstração do Cardok

**Feature**: `002-cardok-makefile`  
**Status**: Concluído  

---

## 1. Dialeto de Make e Compatibilidade Multiplataforma (macOS & Linux)

### Decisão
Utilizar sintaxe POSIX padrão compatível tanto com GNU Make (Linux) quanto com BSD Make (macOS padrão).
Declarar `.PHONY` para todos os alvos conceituais e configurar `.DEFAULT_GOAL := help`.

### Racional
O avaliador técnico pode executar o projeto em macOS ou distribuições Linux. Não podemos depender de recursos exclusivos do GNU Make v4+ ou de utilitários externos pesados instalados via Homebrew.

### Alternativas Consideradas
- **Scripts Bash individuais em `scripts/`**: Rejeitado porque o requisito explícito do projeto é uma interface centralizada `make <comando>`.
- **Taskfile / Justfile**: Rejeitado porque exige instalação prévia de binários de terceiros (`task` ou `just`), violando a premissa de fricção zero para avaliação.

---

## 2. Invocação do Docker Compose e Mapeamento de Serviços

### Decisão
Definir variáveis customizáveis no topo do Makefile com fallback:
```make
COMPOSE ?= docker compose
MONOLITH_SERVICE ?= monolith
```
Utilizar os nomes reais de serviço definidos em `docker-compose.yml`:
- Monólito: `monolith` (contêiner: `cardok-monolith`)
- REST Provider: `provider-rest` (contêiner: `cardok-provider-rest`)
- SOAP Provider: `provider-soap` (contêiner: `cardok-provider-soap`)
- Payment Provider: `payment-provider` (contêiner: `cardok-payment-provider`)

### Racional
O Docker Compose v2 utiliza a sintaxe `docker compose`. Garantir que o comando utilize o nome do serviço `monolith` para executar comandos internos evita erros de serviço não encontrado.

### Alternativas Consideradas
- Chamar `docker exec cardok-monolith`: Funciona, mas ignora o contexto do Compose e falha caso o projeto seja executado com outro `COMPOSE_PROJECT_NAME`. Chamar `docker compose exec $(MONOLITH_SERVICE)` é a prática canônica recomendada pela Docker.

---

## 3. Diagnóstico e Verificações Reais do Projeto

### Decisão
- **`make health`**: Executar o comando nativo existente `$(COMPOSE) exec $(MONOLITH_SERVICE) php artisan cardok:check-services`. Este comando já valida a conectividade HTTP, resolução DNS, status e latência dos provedores REST, SOAP e Payment.
- **`make test`**: Executar `$(COMPOSE) exec $(MONOLITH_SERVICE) php artisan test`. O Make propaga diretamente o exit code do comando PHPUnit.
- **`make check`**: Executar uma cadeia sequencial rigorosa:
  1. Validação estática da configuração Compose: `$(COMPOSE) config --quiet`
  2. Verificação de status dos contêineres: `$(COMPOSE) ps`
  3. Execução dos testes automatizados: `php artisan test`
  4. Diagnóstico de integração: `php artisan cardok:check-services`
  Se qualquer etapa falhar, o Make interrompe imediatamente a execução e retorna exit code != 0.

### Racional
Atende 100% aos requisitos de não inventar comandos inexistentes e manter compatibilidade com automações e pipelines de CI/CD.

---

## 4. Execução de Demonstrações Determinísticas (`make demo*`)

### Decisão
Implementar targets de demonstração técnica utilizando chamadas HTTP simples contra o Monólito:
- **`demo-success`**: `POST http://localhost:8000/api/v1/vehicles/debts` com payload `{"placa":"ABC1234"}`
- **`demo-fallback`**: `POST http://localhost:8000/api/v1/vehicles/debts` com payload `{"placa":"ABC1234","provider":"soap"}`
- **`demo-invalid-plate`**: `POST http://localhost:8000/api/v1/vehicles/debts` com payload `{"placa":"INVALID"}`
- **`demo`**: Executa todas as demonstrações em sequência com cabeçalhos e separadores formatados.
- Formatação: Caso o utilitário `jq` esteja presente no host, o Makefile pode canalizar a saída através dele; caso contrário, exibe o corpo JSON diretamente sem quebrar a execução.

### Racional
Permite ao avaliador inspecionar instantaneamente o cálculo de juros, multas, regras financeiras, parcelamento e tratamento de erros de forma determinística e amigável.
