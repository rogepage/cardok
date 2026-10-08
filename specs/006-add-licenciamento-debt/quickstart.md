# Quickstart & Validação: Débito de Licenciamento

**Feature**: `006-add-licenciamento-debt`  

Este guia detalha como verificar e validar a implementação do débito de Licenciamento.

---

## 1. Execução de Testes Automatizados

Para rodar todos os testes unitários e de integração do monólito:

```bash
docker compose exec monolith php artisan test
```

Para rodar especificamente a nova suíte de testes de Licenciamento:

```bash
docker compose exec monolith php artisan test --filter=LicenciamentoInterestPolicyTest
```

---

## 2. Teste Manual via API (Demonstração)

Executar consulta de débitos enviando uma placa configurada com débito de Licenciamento (ou mock simulado):

```bash
curl -s -X POST http://localhost:8000/api/v1/vehicles/debts \
  -H "Content-Type: application/json" \
  -d '{"placa":"ABC1234"}' | jq .
```

### O que validar na resposta:
1. No array `debitos`, identificar o item com `"tipo": "LICENCIAMENTO"`.
2. Verificar que `dias_atraso` e `valor_atualizado` refletem o cálculo com taxa de 0,33% ao dia limitada ao teto de 20%.
3. No bloco `pagamentos.opcoes`, verificar a presença da opção:
   ```json
   {
     "tipo": "SOMENTE_LICENCIAMENTO",
     "valor_base": "...",
     "pix": {
       "total_com_desconto": "..."
     },
     "cartao_credito": {
       "parcelas": [...]
     }
   }
   ```
