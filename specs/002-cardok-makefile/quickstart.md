# Quickstart: Guia Rápido de Uso do Makefile

Este guia apresenta o fluxo ideal para avaliação técnica e validação rápida do Cardok.

---

## 1. Passo a Passo de Demonstração para Avaliadores

### Etapa 1: Listar comandos disponíveis
```bash
make help
```

### Etapa 2: Subir o ecossistema completo
```bash
make up
```
*Inicia os 4 serviços (`monolith`, `provider-rest`, `provider-soap`, `payment-provider`) e aguarda estarem prontos.*

### Etapa 3: Verificar status dos contêineres
```bash
make status
```
*Confirma que todos os 4 serviços estão em status `healthy`.*

### Etapa 4: Verificar saúde das integrações
```bash
make health
```
*Executa o diagnóstico que inspeciona REST, SOAP e Gateway de Pagamento.*

### Etapa 5: Executar a suíte de testes
```bash
make test
```
*Executa os 110 testes automatizados no monólito e valida todas as asserções.*

### Etapa 6: Validação geral consolidada (CI Check)
```bash
make check
```
*Executa validação sintática do Compose, status, suíte de testes e verificação de saúde.*

### Etapa 7: Executar demonstrações técnicas no terminal
```bash
# Executa todos os cenários demonstrativos
make demo

# Ou individualmente:
make demo-success        # Consulta placa ABC1234 com débitos, juros e parcelamento
make demo-fallback       # Consulta direcionada ao SOAP demonstrando fallback
make demo-invalid-plate  # Validação de formato de placa inválida
```

### Etapa 8: Encerrar o ambiente
```bash
make down
```
