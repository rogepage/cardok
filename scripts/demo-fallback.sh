#!/bin/sh
set -e

REST_SIMULATION_URL="http://localhost:8001/api/simulation/mode"
MONOLITH_URL="http://localhost:8000/api/v1/vehicles/debts"
RESTORE_CMD="curl -s -X POST $REST_SIMULATION_URL -H 'Content-Type: application/json' -d '{\"mode\":\"success\"}' >/dev/null 2>&1 || true"

trap "$RESTORE_CMD" EXIT INT TERM

echo "==> [DEMO] Cenário de Resiliência: Falha REST -> Retries -> Fallback SOAP -> Sucesso"
echo "  1. Configurando condição controlada de falha no provider REST (HTTP 500)..."
curl -s -X POST "$REST_SIMULATION_URL" \
    -H "Content-Type: application/json" \
    -d '{"mode":"error"}' >/dev/null

REQ_ID="demo-fallback-$(date +%s)"
echo "  2. Executando consulta normal de placa (ABC1234) sem especificar provedor (X-Request-ID: $REQ_ID)..."
echo ""

RESP=$(curl -s -X POST "$MONOLITH_URL" \
    -H "Content-Type: application/json" \
    -H "X-Request-ID: $REQ_ID" \
    -d '{"placa":"ABC1234"}')

echo "==> [LOGS] Eventos de Resiliência registrados pelo Monólito:"
python3 scripts/format-demo-logs.py "$REQ_ID" || true
echo ""

echo "==> [RESPOSTA DA API] Resposta recebida pelo cliente (processada com sucesso via SOAP):"
if command -v jq >/dev/null 2>&1; then
    echo "$RESP" | jq .
else
    echo "$RESP"
fi
echo ""

echo "  3. Restaurando provider REST para o estado operacional normal..."
eval "$RESTORE_CMD"
echo "==> ✓ Demonstração de fallback concluída com sucesso e estado original restaurado!"
echo ""
