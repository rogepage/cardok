# Quickstart & Validação: Provedor de Débitos com IA em Plaintext CSV

**Feature**: `007-add-ai-csv-provider`  
**Status**: Planejado  

Este guia detalha como subir o ambiente, executar os testes automatizados e validar o funcionamento do provedor de IA integrado ao ecossistema Cardok.

---

## 1. Subida dos Serviços no Docker Compose

Para subir o monólito e os serviços de provedores (incluindo o novo container `provider-csv`):

```bash
docker compose up -d --build
```

Verifique se todos os containers estão saudáveis:

```bash
docker compose ps
```

O container `cardok-provider-csv` deverá estar ativo e respondendo na porta `8003`.

---

## 2. Execução dos Testes Automatizados

Para executar todos os testes da nova funcionalidade no container do monólito:

```bash
docker compose exec monolith php artisan test --filter=AiVehicleDebtProviderTest
```

Para validar a cadeia completa de provedores e fallback:

```bash
docker compose exec monolith php artisan test --filter=ProviderResolverTest
docker compose exec monolith php artisan test --filter=VehicleDebtServiceTest
```

Para rodar a suíte completa de regressão:

```bash
docker compose exec monolith php artisan test
```

---

## 3. Validação Manual da Integração via API

### Cenário 1: Consulta Direta pelo Provedor de IA

Configure `PROVIDER_ORDER=ai,rest,soap` (ou execute a chamada direta apontando para o monólito):

```bash
curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa":"ABC1234"}' | jq .
```

**Verificação**:
- O campo `provider` no log ou cabeçalho deve indicar `ai`.
- A lista de `debitos` deve conter os débitos extraídos do CSV retornado pelo `provider-csv`.
- O bloco de `resumo` e `pagamentos` deve conter opções de PIX e Cartão de Crédito calculadas normalmente.

### Cenário 2: Simulação de Falha e Fallback Transparente (*First-Success-Wins*)

1. Altere o modo do provedor de IA para falha:
```bash
curl -s -X POST http://localhost:8003/api/simulation/mode \
  -H "Content-Type: application/json" \
  -d '{"mode":"error"}'
```

2. Realize nova consulta no monólito:
```bash
curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa":"ABC1234"}' | jq .
```

**Verificação**:
- O monólito deve realizar retry, detectar a indisponibilidade do provedor de IA e chavear automaticamente para o próximo provedor (`rest`), completando a consulta sem erro para o cliente.

3. Restaure o modo de sucesso:
```bash
curl -s -X POST http://localhost:8003/api/simulation/mode \
  -H "Content-Type: application/json" \
  -d '{"mode":"success"}'
```
