# Interface Contract: Alvos do Makefile do Cardok

**Feature**: `002-cardok-makefile`  
**Status**: Concluído  

---

## Especificação dos Comandos (Targets)

### 1. `make help` (Alvo Padrão)
* **Entrada**: Nenhuma (ou chamada direta de `make`).
* **Comando Equivalente**: Extração de documentação dos alvos.
* **Saída Esperada**: Lista formatada dos comandos, agrupada por categoria, com descrições em pt-BR.
* **Exit Code**: `0`.

---

### 2. `make up`
* **Entrada**: Nenhuma.
* **Comando Equivalente**: `docker compose up -d` (com verificação de containers saudáveis).
* **Saída Esperada**: Mensagem `==> Iniciando Cardok em background...` seguida da inicialização e relatório de containers.
* **Exit Code**: `0` se os containers subirem; `!= 0` caso haja erro de porta, daemon ou montagem.

---

### 3. `make down`
* **Entrada**: Nenhuma.
* **Comando Equivalente**: `docker compose down`.
* **Saída Esperada**: Mensagem `==> Encerrando ambiente Cardok...` e encerramento limpo da rede e containers (volumes de dados preservados).
* **Exit Code**: `0`.

---

### 4. `make restart`
* **Entrada**: Nenhuma.
* **Comando Equivalente**: `docker compose restart`.
* **Saída Esperada**: Mensagem `==> Reiniciando serviços do Cardok...`.
* **Exit Code**: `0` em sucesso.

---

### 5. `make status`
* **Entrada**: Nenhuma.
* **Comando Equivalente**: `docker compose ps`.
* **Saída Esperada**: Tabela do Docker Compose mostrando `SERVICE`, `STATUS` e `PORTS`.
* **Exit Code**: `0`.

---

### 6. `make logs`
* **Entrada**: Nenhuma.
* **Comando Equivalente**: `docker compose logs -f`.
* **Saída Esperada**: Stream contínuo de logs formatados e coloridos de todos os 4 serviços.
* **Exit Code**: `0` ao encerrar com Ctrl+C.

---

### 7. `make test`
* **Entrada**: Nenhuma.
* **Comando Equivalente**: `docker compose exec monolith php artisan test`.
* **Saída Esperada**: Execução dos 110 testes automatizados (416 asserções) com relatório do PHPUnit.
* **Exit Code**: Código de saída real do PHPUnit (`0` para todos aprovados, `!= 0` se houver qualquer falha).

---

### 8. `make health`
* **Entrada**: Nenhuma.
* **Comando Equivalente**: `docker compose exec monolith php artisan cardok:check-services`.
* **Saída Esperada**: Tabela com o status de cada provedor externo (REST, SOAP, Payment Provider), código HTTP e latência.
* **Exit Code**: `0` em sucesso; `!= 0` em falha de conectividade.

---

### 9. `make check`
* **Entrada**: Nenhuma.
* **Comando Equivalente**: Execução encadeada de:
  1. `docker compose config --quiet`
  2. `docker compose ps`
  3. `docker compose exec monolith php artisan test`
  4. `docker compose exec monolith php artisan cardok:check-services`
* **Saída Esperada**: Relatórios sucessivos de cada etapa de validação.
* **Exit Code**: `0` se todas as etapas passarem com sucesso; encerra no primeiro erro com código `!= 0`.

---

### 10. `make shell`
* **Entrada**: Nenhuma.
* **Comando Equivalente**: `docker compose exec monolith sh`.
* **Saída Esperada**: Shell interativo aberto dentro do contêiner do monólito em `/var/www/html`.
* **Exit Code**: Código do shell ao sair.

---

### 11. `make artisan`
* **Entrada**: `CMD="<subcomando>"` (ex: `make artisan CMD="route:list"` ou `make artisan CMD="about"`).
* **Comando Equivalente**: `docker compose exec monolith php artisan $(CMD)`.
* **Saída Esperada**: Saída do comando Artisan executado.
* **Exit Code**: Código do comando Artisan.

---

### 12. `make demo-success`, `make demo-fallback`, `make demo-invalid-plate`, `make demo`
* **Entrada**: Nenhuma.
* **Comando Equivalente**: Chamadas HTTP locais contra a API do Cardok.
* **Saída Esperada**: Headers/Body das respostas JSON demonstrando as regras de negócio em tempo real.
* **Exit Code**: `0`.
