# FASE 13 — ADOTAR SPEC KIT NO PROJETO CARDOK

Estamos trabalhando no projeto existente:

```text
/Users/roger/Desktop/docker/cardok
```

O projeto `cardok` é o Home Test de Backend Engineer que estamos preparando para avaliação técnica.

O objetivo desta fase é **instalar e configurar o GitHub Spec Kit no projeto existente**, preparando o ambiente para utilizar Spec-Driven Development em uma futura feature.

## REGRA ABSOLUTA

Nesta fase:

**NÃO modificar o código de aplicação do Cardok.**

Não alterar:

* `app/`
* `src/`
* `routes/`
* controllers;
* services;
* domain;
* infrastructure;
* providers;
* payment;
* Dockerfiles;
* `docker-compose.yml`;
* testes existentes;
* regras de negócio;
* APIs existentes.

Não corrigir bugs.

Não refatorar.

Não adicionar funcionalidades.

Não executar nenhuma feature do Cardok.

O único objetivo desta fase é preparar o Spec Kit.

---

# 1. PRIMEIRO: INSPECIONAR O ESTADO ATUAL

Antes de executar qualquer comando, verificar:

```bash
pwd
git status
git branch --show-current
git status --short
```

Confirmar que estamos na raiz:

```text
/Users/roger/Desktop/docker/cardok
```

Também verificar:

```bash
ls -la
```

e identificar:

* `.git/`
* `app/`
* `docs/`
* `tests/`
* `docker-compose.yml`
* `README.md`
* arquivos de configuração existentes.

Não modificar nada nesta etapa.

---

# 2. GARANTIR BASELINE REVERSÍVEL

Como este é um projeto existente, precisamos ter um baseline antes de inicializar o Spec Kit.

Primeiro verificar se existem alterações não commitadas:

```bash
git status --short
```

Se houver alterações locais, NÃO descartá-las.

Informar quais alterações existem.

Se o repositório já estiver limpo, verificar a branch atual.

Se for possível criar um commit sem alterar código, o baseline pode ser criado.

Se houver qualquer dúvida sobre alterações existentes, PARE e informe antes de executar qualquer operação destrutiva.

Nunca executar:

```bash
git reset --hard
git clean -fd
```

---

# 3. VERIFICAR PRÉ-REQUISITOS

Verificar se `uv` está disponível:

```bash
uv --version
```

Verificar se o Specify CLI já está instalado:

```bash
specify version
```

Se `specify` já existir, NÃO reinstalar automaticamente.

Primeiro informar:

```text
Spec Kit já instalado:
versão X
```

Se não existir, verificar se podemos instalar usando:

```bash
uv tool install specify-cli
```

Não utilizar Homebrew.

Não instalar Python globalmente.

Não utilizar `sudo`.

Não alterar `/usr/local`.

O ambiente do usuário utiliza instalações locais e queremos manter essa filosofia.

---

# 4. VERIFICAR INTEGRAÇÃO ANTIGRAVITY

O agente utilizado neste projeto é:

```text
Antigravity
```

A integração correta do Spec Kit para Antigravity é:

```text
agy
```

Não utilizar:

```text
gemini
```

para esta configuração do Antigravity.

Antes de inicializar, verificar a versão do Antigravity se possível.

A integração deverá utilizar os skills do Antigravity.

Esperamos encontrar posteriormente algo semelhante a:

```text
.agents/
└── skills/
```

com os skills do Spec Kit.

---

# 5. INICIALIZAR O SPEC KIT NO PROJETO EXISTENTE

Estamos em um projeto que já contém código.

Portanto, utilizar a modalidade de projeto existente.

O comando esperado é conceitualmente:

```bash
specify init --here --force --integration agy
```

Se a versão instalada do Specify CLI apresentar uma sintaxe diferente, NÃO inventar parâmetros.

Consultar:

```bash
specify init --help
```

e utilizar a sintaxe correspondente à versão instalada.

IMPORTANTE:

`--force` só deve ser utilizado depois de verificar o estado do Git e confirmar que as alterações existentes estão preservadas.

---

# 6. O QUE ESPERAMOS QUE O SPEC KIT ADICIONE

Esperamos que a inicialização adicione os arquivos próprios do Spec Kit, principalmente:

```text
.specify/
```

e a integração do agente, provavelmente:

```text
.agents/
```

Não assumir exatamente os arquivos antes de verificar o resultado.

Depois da inicialização:

```bash
git status --short
```

e:

```bash
find .specify -maxdepth 3 -type f | sort
```

e, se existir:

```bash
find .agents -maxdepth 4 -type f | sort
```

---

# 7. REVISAR O DIFF

Este passo é obrigatório.

Executar:

```bash
git status
```

e:

```bash
git diff --stat
```

e:

```bash
git diff -- .gitignore
```

Se houver arquivos novos não rastreados:

```bash
git status --short
```

Analisar exatamente o que o Spec Kit adicionou.

O objetivo é confirmar que:

```text
Código do Cardok = inalterado
```

e:

```text
Arquivos do Spec Kit = adicionados
```

Se qualquer arquivo de aplicação tiver sido alterado, investigar e reverter SOMENTE a alteração causada pelo Spec Kit, sem apagar alterações pré-existentes do usuário.

---

# 8. NÃO CRIAR A CONSTITUTION AINDA

Nesta fase NÃO executar:

```text
/speckit-constitution
```

Ainda não queremos criar ou alterar as regras do projeto.

Primeiro queremos apenas confirmar que o Spec Kit está corretamente instalado.

A Constitution será uma fase posterior.

---

# 9. NÃO EXECUTAR FEATURE

Também NÃO executar:

```text
/speckit-specify
/speckit-clarify
/speckit-plan
/speckit-checklist
/speckit-tasks
/speckit-analyze
/speckit-implement
/speckit-converge
```

Nesta fase não existe feature para implementar.

Estamos apenas preparando a infraestrutura de Spec-Driven Development.

---

# 10. VERIFICAR OS SKILLS

Depois da instalação, confirmar quais skills foram instalados.

Para Antigravity, esperamos comandos semelhantes a:

```text
/speckit-constitution
/speckit-specify
/speckit-clarify
/speckit-plan
/speckit-checklist
/speckit-tasks
/speckit-analyze
/speckit-implement
/speckit-converge
```

Não assumir que todos estão disponíveis.

Verificar os arquivos efetivamente instalados.

---

# 11. VERIFICAR COMPATIBILIDADE COM O CARDOK

O Spec Kit deve coexistir com a estrutura atual:

```text
cardok/
├── app/
├── tests/
├── docs/
├── docker-compose.yml
├── README.md
├── .specify/
└── .agents/
```

Não mover diretórios existentes.

Não reorganizar o projeto.

Não converter o projeto para outro framework.

Não criar outro repositório.

---

# 12. DOCUMENTAÇÃO

Não criar uma grande documentação nesta fase.

Se for necessário, criar apenas um pequeno arquivo explicando que o projeto passou a utilizar Spec-Driven Development.

Por exemplo, se fizer sentido:

```text
docs/spec-driven-development.md
```

Mas antes de criar esse arquivo verificar se já existe documentação semelhante.

Não duplicar documentação.

---

# 13. GITIGNORE

Verificar se a inicialização do Spec Kit modificou o `.gitignore`.

Precisamos garantir que os arquivos importantes do Spec Kit possam ser versionados.

Especialmente verificar:

```text
.specify/
.agents/
```

Não simplesmente adicionar tudo ao `.gitignore`.

Se houver regras criadas pelo Spec Kit, explicar o propósito delas.

---

# 14. TESTES DO CARDOK

Como esta fase não altera o código, os testes do Cardok não precisam ser modificados.

Porém, depois da inicialização, executar uma verificação rápida para confirmar que a infraestrutura do projeto não foi afetada.

Se for rápido:

```bash
docker compose config
```

e, se o ambiente estiver disponível:

```bash
docker compose ps
```

Não iniciar serviços apenas para esta verificação se isso não for necessário.

Não modificar Docker.

---

# 15. RESULTADO ESPERADO

Ao final, quero algo semelhante a:

```text
cardok/
├── .agents/
│   └── skills/
│       └── ...
├── .specify/
│   └── ...
├── app/
├── docs/
├── tests/
├── docker-compose.yml
├── README.md
└── ...
```

O código existente deve permanecer intacto.

---

# 16. RELATÓRIO FINAL

Ao terminar, apresentar um relatório com:

## Spec Kit

Versão instalada:

```text
X
```

## Integração

```text
Antigravity
integration = agy
```

## Arquivos adicionados

Listar os principais arquivos/diretórios.

## Arquivos modificados

Listar todos.

## Arquivos de aplicação modificados

Deve ser:

```text
NENHUM
```

Se não for, investigar antes de finalizar.

## Git

Mostrar o resultado de:

```bash
git status --short
```

## Cardok

Confirmar:

```text
Código de negócio não alterado
Testes não alterados
Docker não alterado
Regras de negócio não alteradas
APIs não alteradas
```

## Skills disponíveis

Listar os `/speckit-*` encontrados.

## Próximo passo sugerido

Não implementar nada.

Apenas informar que a próxima fase poderá ser:

```text
FASE 14 — Criar a Constitution do Cardok com /speckit-constitution
```

e depois escolheremos uma feature pequena para demonstrar o fluxo completo:

```text
Constitution
    ↓
Specify
    ↓
Clarify
    ↓
Plan
    ↓
Checklist
    ↓
Tasks
    ↓
Analyze
    ↓
Implement
    ↓
Converge
```

---

# REGRA FINAL

Esta fase é exclusivamente de **setup e adoção do Spec Kit**.

Se você identificar alguma melhoria arquitetural no Cardok durante a análise, NÃO implemente.

Apenas registre como:

```text
"Observação para fase futura"
```

O objetivo é terminar esta fase com:

**Spec Kit instalado + Antigravity integrado + Cardok intacto.**
