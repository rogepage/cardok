# ==============================================================================
# Cardok — Makefile de Operação, Validação e Demonstração
# ==============================================================================

.DEFAULT_GOAL := help
.SHELL := /bin/sh

COMPOSE ?= docker compose
MONOLITH_SERVICE ?= monolith
CMD ?= list

.PHONY: help up down restart status logs test health check shell artisan demo demo-success demo-fallback demo-invalid-plate

help: ## Mostra esta lista de comandos disponíveis
	@echo "Cardok — Comandos disponíveis:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  make %-20s %s\n", $$1, $$2}'
	@echo ""

# ------------------------------------------------------------------------------
# Ciclo de Vida do Ambiente
# ------------------------------------------------------------------------------

up: ## Inicia todos os serviços em background
	@echo "==> Iniciando ambiente Cardok..."
	@$(COMPOSE) up -d
	@echo "==> Ambiente operacional. Execute 'make status' para acompanhar a saúde dos serviços."

down: ## Para todos os serviços (preserva volumes de dados)
	@echo "==> Encerrando ambiente Cardok..."
	@$(COMPOSE) down

restart: ## Reinicia todos os serviços
	@echo "==> Reiniciando serviços do Cardok..."
	@$(COMPOSE) restart

status: ## Mostra o status e saúde dos contêineres
	@echo "==> Verificando status dos serviços..."
	@$(COMPOSE) ps

logs: ## Acompanha os logs unificados em tempo real
	@$(COMPOSE) logs -f

# ------------------------------------------------------------------------------
# Testes, Saúde e Validação (CI/CD)
# ------------------------------------------------------------------------------

test: ## Executa a suíte de testes automatizados no monólito
	@echo "==> Executando testes automatizados..."
	@$(COMPOSE) exec $(MONOLITH_SERVICE) php artisan test

health: ## Executa diagnóstico de conectividade com serviços externos
	@echo "==> Verificando saúde e conectividade das integrações..."
	@$(COMPOSE) exec $(MONOLITH_SERVICE) php artisan cardok:check-services

check: ## Executa validação geral de conformidade (config, status, testes, saúde)
	@echo "==> [1/4] Validando sintaxe do Docker Compose..."
	@$(COMPOSE) config --quiet
	@echo "    ✓ Configuração do Docker Compose válida."
	@echo "==> [2/4] Verificando status dos contêineres..."
	@$(COMPOSE) ps
	@echo "==> [3/4] Executando suíte completa de testes..."
	@$(COMPOSE) exec $(MONOLITH_SERVICE) php artisan test
	@echo "==> [4/4] Executando diagnóstico de integrações..."
	@$(COMPOSE) exec $(MONOLITH_SERVICE) php artisan cardok:check-services
	@echo ""
	@echo "==> ✓ Todas as validações foram concluídas com sucesso!"

# ------------------------------------------------------------------------------
# Acesso Operacional e Artisan
# ------------------------------------------------------------------------------

shell: ## Abre um shell interativo no contêiner do monólito
	@echo "==> Abrindo shell no monólito..."
	@$(COMPOSE) exec $(MONOLITH_SERVICE) sh

artisan: ## Executa comandos Artisan (ex: make artisan CMD="about")
	@$(COMPOSE) exec $(MONOLITH_SERVICE) php artisan $(CMD)

# ------------------------------------------------------------------------------
# Demonstrações Técnicas
# ------------------------------------------------------------------------------

demo-success: ## Demonstração: consulta débitos com sucesso (placa ABC1234)
	@echo "==> [DEMO] Consultando débitos da placa ABC1234 (Sucesso com cálculo e parcelamento):"
	@curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
		-H "Content-Type: application/json" \
		-d '{"placa":"ABC1234"}' | (command -v jq >/dev/null 2>&1 && jq . || cat)
	@echo ""

demo-fallback: ## Demonstração: fallback real com falha controlada no REST, retries e sucesso no SOAP
	@./scripts/demo-fallback.sh

demo-invalid-plate: ## Demonstração: validação com formato de placa inválido
	@echo "==> [DEMO] Consultando com placa inválida INVALID (Validação e erro 400):"
	@curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
		-H "Content-Type: application/json" \
		-d '{"placa":"INVALID"}' | (command -v jq >/dev/null 2>&1 && jq . || cat)
	@echo ""

demo: demo-success demo-fallback demo-invalid-plate ## Executa todos os cenários de demonstração em sequência
	@echo "==> ✓ Todos os cenários de demonstração foram executados!"
