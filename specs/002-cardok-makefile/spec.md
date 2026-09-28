# Feature Specification: Makefile de Operação, Validação e Demonstração do Cardok

**Feature Branch**: `002-cardok-makefile`  
**Created**: 2026-09-28  
**Status**: Draft  
**Input**: User description: "FASE FINAL — MAKEFILE DO CARDOK. Quero criar um Makefile na raiz do projeto Cardok para facilitar a execução, demonstração e validação do Home Test."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Gerenciamento do Ciclo de Vida do Ambiente (Priority: P1)

Como avaliador técnico ou desenvolvedor do projeto, quero iniciar, parar, reiniciar e monitorar todos os contêineres do ecossistema Cardok através de comandos simples e padronizados no terminal, para que eu não precise memorizar opções complexas do Docker Compose.

**Why this priority**: É o ponto de entrada essencial de qualquer avaliação técnica ou rotina de desenvolvimento; sem inicialização e controle de ciclo de vida consistentes, nenhuma outra operação é possível.

**Independent Test**: Pode ser testado independentemente executando `make help`, `make up`, `make status`, `make logs` (com interrupção limpa) e `make down`.

**Acceptance Scenarios**:

1. **Given** que o Docker está em execução no host, **When** o usuário executa `make up`, **Then** os 4 serviços (`monolith`, `provider-rest`, `provider-soap`, `payment-provider`) são iniciados em background e atingem estado operacional saudável.
2. **Given** que os contêineres estão em execução, **When** o usuário executa `make status`, **Then** uma tabela legível é exibida listando cada serviço, suas portas expostas e seu status de saúde (`healthy`).
3. **Given** que os serviços estão ativos, **When** o usuário executa `make down`, **Then** todos os contêineres e a rede do projeto são encerrados com segurança sem perda de dados persistidos.
4. **Given** que o usuário deseja visualizar as opções disponíveis, **When** executa `make help` (ou apenas `make`), **Then** uma lista formatada e autoexplicativa de todos os comandos do Makefile é exibida.

---

### User Story 2 - Verificação de Saúde e Diagnóstico Integrado (Priority: P1)

Como avaliador técnico, quero verificar instantaneamente se o monólito consegue se comunicar com os provedores externos (REST, SOAP e Payment Provider) e obter um diagnóstico geral de conformidade do projeto através de um único comando.

**Why this priority**: Permite que o avaliador valide em segundos que a arquitetura distribuída e os adapters de comunicação estão 100% funcionais no ambiente de execução.

**Independent Test**: Pode ser testado independentemente executando `make health` e `make check`, verificando a saída tabular e os códigos de saída.

**Acceptance Scenarios**:

1. **Given** que o ambiente Cardok está ativo, **When** o usuário executa `make health`, **Then** o comando invoca a rotina real de diagnóstico do monólito (`cardok:check-services`) e imprime uma tabela com a URL, HTTP status, latência e disponibilidade de cada provedor externo.
2. **Given** que o usuário executa `make check`, **When** todas as etapas obrigatórias (validação do compose, verificação de containers saudáveis, testes e health check) passam com sucesso, **Then** o comando exibe mensagens de progresso claras e encerra com código de saída 0.
3. **Given** que um dos serviços obrigatórios está indisponível ou um teste falha durante o `make check`, **Then** o comando encerra imediatamente com código de saída diferente de zero para compatibilidade com pipelines de CI/CD.

---

### User Story 3 - Execução Determinística da Suíte de Testes (Priority: P1)

Como engenheiro de software ou avaliador, quero rodar toda a suíte de testes automatizados dentro do contêiner do monólito com isolamento total, sem precisar instalar PHP, extensões ou Composer na minha máquina hospedeira.

**Why this priority**: Garante a confiabilidade da entrega, assegurando que os 110 testes e 416 assertions passam em qualquer máquina ou pipeline de integração contínua.

**Independent Test**: Pode ser testado independentemente rodando `make test`, confirmando que a suíte executa no contêiner `monolith` e retorna código de saída real (sem mascaramento `|| true`).

**Acceptance Scenarios**:

1. **Given** que o contêiner `monolith` está ativo, **When** o usuário executa `make test`, **Then** a suíte completa de testes do PHPUnit/Laravel é executada e o relatório detalhado de testes aprovados é exibido no terminal.
2. **Given** que ocorra qualquer falha em um teste automatizado, **When** `make test` finaliza, **Then** o processo do Makefile encerra com código de saída de erro correspondente ao PHPUnit.

---

### User Story 4 - Acesso Operacional e Linha de Comando (Priority: P2)

Como desenvolvedor, quero abrir um shell interativo no contêiner do monólito ou despachar comandos Artisan arbitrários sem precisar lembrar a sintaxe completa do Docker Compose.

**Why this priority**: Agiliza tarefas de depuração, inspeção de cache, banco de dados ou logs durante a apresentação técnica e desenvolvimento diário.

**Independent Test**: Pode ser testado executando `make shell` (para abrir o shell) e `make artisan CMD="about"`.

**Acceptance Scenarios**:

1. **Given** o monólito em execução, **When** o usuário executa `make shell`, **Then** uma sessão interativa de terminal (`sh`) é aberta no diretório `/var/www/html` do monólito.
2. **Given** o monólito em execução, **When** o usuário executa `make artisan CMD="route:list"`, **Then** o comando Artisan especificado é despachado e executado com segurança dentro do contêiner, exibindo sua saída no terminal hospedeiro.

---

### User Story 5 - Demonstrações Rápidas de Cenários de Negócio (Priority: P3)

Como apresentador do Home Test, quero disparar demonstrações determinísticas dos principais fluxos de negócio (consulta com sucesso e débitos, consulta com veículo regularizado, fallback de provedores e validação de placa inválida) para visualização rápida no terminal sem necessidade de formulários manuais.

**Why this priority**: Facilita a demonstração técnica e ao vivo para avaliadores, mostrando as respostas JSON estruturadas, regras de juros/multa, opções de parcelamento e tolerância a falhas.

**Independent Test**: Pode ser testado executando `make demo` ou os subcomandos `make demo-success`, `make demo-fallback`, `make demo-invalid-plate`.

**Acceptance Scenarios**:

1. **Given** o ambiente Cardok ativo, **When** o usuário executa `make demo-success`, **Then** uma consulta à placa `ABC1234` é executada e os débitos calculados, resumo e opções de pagamento (PIX 5% e Cartão em até 12x) são exibidos no terminal.
2. **Given** o ambiente Cardok ativo, **When** o usuário executa `make demo-fallback`, **Then** uma consulta direcionada ao provedor secundário ou cenário de tolerância a falhas é executada demonstrando a resiliência do sistema.
3. **Given** o ambiente Cardok ativo, **When** o usuário executa `make demo-invalid-plate`, **Then** uma consulta com placa fora do padrão é enviada e a resposta de erro padronizada (`invalid_plate`, HTTP 400) é exibida.
4. **Given** o ambiente Cardok ativo, **When** o usuário executa `make demo`, **Then** todos os cenários demonstrativos principais são executados em sequência com separadores legíveis.

---

### Edge Cases

- **Docker daemon inativo:** Se o daemon do Docker não estiver respondendo quando um comando for acionado, o comando deve falhar com mensagem amigável sem loops infinitos.
- **Comandos de inspeção antes de subir o ambiente:** Se o usuário executar `make test`, `make health` ou `make shell` com os contêineres parados, o comando deve falhar claramente informando que os contêineres precisam ser iniciados com `make up`.
- **Comando Artisan sem parâmetro CMD:** Se o usuário executar `make artisan` sem passar `CMD="qualquer"`, o Makefile deve exibir mensagem de instrução ou executar `php artisan list` por padrão de forma segura.
- **Múltiplas chamadas concorrentes:** Evitar dependências de alvos circulares para permitir execução previsível no Make.
- **Ambiente Unix heterogêneo:** O Makefile deve evitar flags exclusivas de GNU Make ou utilitários específicos de uma distribuição, funcionando identicamente no macOS (BSD) e Linux.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema DEVE disponibilizar o comando `make help` como alvo padrão do Makefile, listando comandos agrupados e descrições claras em pt-BR.
- **FR-002**: O sistema DEVE disponibilizar `make up` executando a inicialização desacoplada dos serviços via Docker Compose com verificação de integridade.
- **FR-003**: O sistema DEVE disponibilizar `make down` para interrupção limpa do ecossistema Docker Compose preservando volumes persistentes.
- **FR-004**: O sistema DEVE disponibilizar `make restart` para reiniciar todos os serviços do ecossistema de forma ordenada.
- **FR-005**: O sistema DEVE disponibilizar `make status` exibindo o status operacional dos contêineres através de `docker compose ps`.
- **FR-006**: O sistema DEVE disponibilizar `make logs` para acompanhamento contínuo dos logs unificados através de `docker compose logs -f`.
- **FR-007**: O sistema DEVE disponibilizar `make test` executando a suíte do PHPUnit dentro do contêiner `monolith`, propagando integralmente o exit code original do runner de testes (sem mascaramento `|| true`).
- **FR-008**: O sistema DEVE disponibilizar `make health` executando a rotina real de verificação `php artisan cardok:check-services` no contêiner do monólito.
- **FR-009**: O sistema DEVE disponibilizar `make check` executando de ponta a ponta as validações de ambiente, contêineres, testes e saúde das dependências, retornando código diferente de zero caso qualquer verificação falhe.
- **FR-010**: O sistema DEVE disponibilizar `make shell` abrindo uma sessão interativa no contêiner do serviço `monolith` utilizando `/bin/sh`.
- **FR-011**: O sistema DEVE disponibilizar `make artisan` permitindo despachar subcomandos parametrizados via variável `CMD` sem uso inseguro de `eval`.
- **FR-012**: O sistema DEVE disponibilizar comandos de demonstração técnica (`make demo`, `make demo-success`, `make demo-fallback`, `make demo-invalid-plate`) acionando requisições determinísticas contra os endpoints HTTP reais.
- **FR-013**: O Makefile NÃO DEVE exigir dependências externas no host além de `make`, `docker` e `docker compose`.
- **FR-014**: O README.md do projeto DEVE ser atualizado em pt-BR contendo uma seção de comandos rápidos com tabela descritiva correspondente exatamente aos alvos implementados.

---

### Key Entities

- **Target do Makefile**: Diretiva de execução contendo nome, pré-requisitos, instruções shell e mensagens de status legíveis.
- **Cenário Demonstrativo**: Caso de teste executável via linha de comando simulando interação real de usuário ou sistema externo contra a API do Cardok.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um avaliador técnico sem conhecimento prévio do projeto consegue clonar, subir o ecossistema, rodar testes e verificar a saúde com apenas 3 comandos (`make up`, `make health`, `make test`) em menos de 2 minutos.
- **SC-002**: 100% dos comandos do Makefile retornam códigos de saída compatíveis com pipelines de CI/CD (código 0 para sucesso, código diferente de zero para falhas).
- **SC-003**: A suíte de 110 testes automatizados executa com sucesso via `make test` exibindo o resumo do PHPUnit no terminal sem erros.
- **SC-004**: O comando `make health` reporta o status ONLINE de todos os 3 provedores externos (`provider-rest`, `provider-soap`, `payment-provider`) em menos de 5 segundos.
- **SC-005**: Zero alterações em regras de negócio no domínio (`app/Domain`), contratos de API ou arquitetura existente.

---

## Assumptions

- O host possui Docker e Docker Compose (v2) instalados e operacionais.
- O utilitário `make` está disponível nativamente no sistema operacional (macOS / Linux).
- O monólito expõe a porta 8000 e os provedores expõem as portas 8001 e 8002 no host local conforme especificado no `docker-compose.yml`.
- A porta de rede não está bloqueada por outros processos no momento da execução.
