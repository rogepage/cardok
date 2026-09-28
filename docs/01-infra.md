Você é meu pair programmer responsável pela infraestrutura inicial do projeto cardok.

Estou realizando um Home Test para uma posição de Backend Engineer / Staff Engineer.

1. Contexto

O cardok será composto por:

um monólito Laravel, que será o sistema principal;
um serviço externo REST simulado para consulta de débitos veiculares;
um serviço externo SOAP simulado para consulta de débitos veiculares;
um serviço externo de pagamento, que inicialmente será apenas um mock.

Nesta etapa quero implementar SOMENTE a infraestrutura Docker.

Não implemente regras de negócio, domínio, adapters, retry, fallback ou pagamentos ainda.

2. Estrutura do projeto

A estrutura desejada é:

cardok/
├── docker-compose.yml
├── .env.example
├── README.md
│
├── monolith/
├── provider-rest/
├── provider-soap/
└── payment-provider/




Os três primeiros serviços podem utilizar Laravel.

O payment-provider deve ser apenas um serviço HTTP mínimo em PHP, sem necessidade de Laravel.

3. Serviços
Monolith

Aplicação principal Laravel.

Nome do serviço Docker:

monolith




Nome do container:

cardok-monolith




Porta:

8000:8000



Provider REST

Serviço Laravel que futuramente simulará um provedor externo REST/JSON.

Nome do serviço:

provider-rest




Nome do container:

cardok-provider-rest




Porta:

8001:8000



Provider SOAP

Serviço Laravel que futuramente simulará um provedor externo SOAP/XML.

Nome do serviço:

provider-soap




Nome do container:

cardok-provider-soap




Porta:

8002:8000




Nesta etapa NÃO é necessário implementar o protocolo SOAP.

Apenas deixe o serviço Laravel funcionando.

Payment Provider

Serviço HTTP simples em PHP.

Nome do serviço:

payment-provider




Nome do container:

cardok-payment-provider




Ele deve escutar internamente na porta:

8000




Não é necessário publicar essa porta para o host.

4. Rede Docker

Todos os serviços devem utilizar uma rede Docker chamada:

cardok-network




O monólito deverá conseguir acessar os serviços utilizando os nomes dos serviços Docker.

Exemplo:

http://provider-rest:8000
http://provider-soap:8000
http://payment-provider:8000




NÃO utilize localhost para comunicação entre containers.

5. Variáveis de ambiente

Crie:

.env.example




Com pelo menos:

APP_NAME=Cardok

PROVIDER_REST_URL=http://provider-rest:8000
PROVIDER_SOAP_URL=http://provider-soap:8000
PAYMENT_PROVIDER_URL=http://payment-provider:8000

PROVIDER_ORDER=rest,soap
PROVIDER_TIMEOUT=2
PROVIDER_RETRIES=2




Essas últimas três variáveis ainda não precisam ser utilizadas nesta etapa.

No Laravel, quando apropriado, utilize configuração através de config/services.php em vez de acessar env() diretamente dentro das classes da aplicação.

6. Dockerfiles

Crie Dockerfiles independentes para:

monolith/Dockerfile
provider-rest/Dockerfile
provider-soap/Dockerfile
payment-provider/Dockerfile




Utilize imagens oficiais apropriadas.

Para Laravel, utilize PHP CLI e Composer.

Não introduza nesta etapa:

Nginx
PHP-FPM
MySQL
MongoDB
Redis
RabbitMQ
Kubernetes
autenticação
observabilidade complexa
ferramentas externas desnecessárias

Quero uma infraestrutura simples, reproduzível e fácil de explicar em uma entrevista técnica.

7. Laravel

Crie os projetos Laravel necessários caso eles ainda não existam.

Use uma versão estável e atual do Laravel compatível com a versão de PHP escolhida.

O projeto deve conseguir iniciar através do Docker.

Não dependa de instalações manuais no host além de:

Docker
Docker Compose
Git



8. Health checks

Crie endpoints mínimos para validar os serviços.

Monolith
GET /api/health




Resposta:

{
    "status": "ok",
    "service": "monolith"
}



Provider REST
GET /api/health




Resposta:

{
    "status": "ok",
    "service": "provider-rest"
}



Provider SOAP

Nesta etapa apenas:

GET /health




Resposta:

{
    "status": "ok",
    "service": "provider-soap"
}



Payment Provider
GET /health




Resposta:

{
    "status": "ok",
    "service": "payment-provider"
}



9. Comunicação entre containers

Além dos health checks, crie uma forma simples de validar que o container monolith consegue resolver e acessar:

provider-rest
provider-soap
payment-provider




Não precisa implementar ainda nenhuma regra de negócio.

Pode ser através de comandos de teste ou de um pequeno mecanismo temporário de diagnóstico, desde que isso não polua a aplicação.

10. Docker Compose

Crie um único:

docker-compose.yml




na raiz do projeto.

O comando principal para iniciar o ambiente deve ser:

docker compose up --build




O ambiente deve ser reproduzível a partir de uma máquina limpa que tenha Docker instalado.

11. Volumes

Durante desenvolvimento, prefira bind mounts para permitir alteração do código sem precisar reconstruir a imagem a cada alteração.

Exemplo conceitual:

volumes:
  - ./monolith:/var/www/html




Faça o mesmo para os projetos Laravel quando fizer sentido.

12. Healthcheck do Docker

Se for simples de implementar, adicione healthcheck aos containers.

Por exemplo, o Docker deve conseguir verificar se o serviço HTTP está respondendo.

Não crie uma solução complexa apenas para isso.

13. README

Crie ou atualize o README com SOMENTE a documentação da infraestrutura desta etapa.

Inclua:

Pré-requisitos
Docker
Docker Compose
Inicialização
docker compose up --build



Serviços

Explique as portas:

Monolith       localhost:8000
Provider REST  localhost:8001
Provider SOAP  localhost:8002
Payment        apenas rede interna



Health checks

Mostre os endpoints para validar cada serviço.

Arquitetura

Inclua um pequeno diagrama em Markdown mostrando:

                    ┌──────────────┐
                    │   Monolith   │
                    └──────┬───────┘
                           │
             ┌─────────────┼─────────────┐
             ↓             ↓             ↓
       Provider REST  Provider SOAP  Payment



14. Restrições importantes

NÃO implemente nesta etapa:

regras de juros;
cálculo de valores;
modelo Debt;
Money;
DebtType;
Strategy;
Adapter;
Ports & Adapters;
retry;
fallback;
circuit breaker;
pagamento;
PIX;
cartão;
RabbitMQ;
banco de dados;
autenticação.

Esses itens serão implementados em etapas posteriores.

15. Forma de trabalho

Antes de alterar arquivos:

Analise o conteúdo atual do repositório.
Verifique se o projeto já possui arquivos ou configurações.
Não sobrescreva arquivos existentes sem necessidade.
Informe quais arquivos serão criados ou alterados.
Explique brevemente as decisões técnicas.
Depois implemente.

Ao terminar, execute ou descreva os comandos necessários para validar:

docker compose config
docker compose up --build




E valide os quatro serviços.

16. Critério de conclusão

Considerarei esta etapa concluída quando:

docker compose up --build funcionar.
Os quatro containers estiverem executando.
O monólito responder em localhost:8000.
O provider REST responder em localhost:8001.
O provider SOAP responder em localhost:8002.

O payment provider estiver acessível pelo monólito através de:

http://payment-provider:8000

Os containers estiverem na rede cardok-network.
O projeto puder ser iniciado novamente sem configuração manual adicional.
O README documentar como executar e validar a infraestrutura.

Não avance para a implementação dos requisitos funcionais.

Ao finalizar, apresente:

árvore de diretórios;
docker-compose.yml;
Dockerfiles;
arquivos de configuração relevantes;
comandos utilizados para validação;
problemas encontrados;
decisões técnicas;
próximos passos possíveis, sem implementá-los.

Lembre-se: você é meu pair programmer. Não quero apenas código funcionando; quero uma solução que eu consiga entender e defender durante a apresentação técnica.

---

## 17. Implementação Realizada e Decisões Técnicas

Esta seção registra as decisões tomadas, a arquitetura final implementada e os resultados da validação da Etapa 01.

### 17.1. Decisões Técnicas e Racional de Engenharia

1. **Padronização na Imagem Base `php:8.4-cli-alpine`**:
   - **Decisão**: Todos os containers (`monolith`, `provider-rest`, `provider-soap` e `payment-provider`) utilizam rigorosamente a mesma imagem base: `php:8.4-cli-alpine`.
   - **Racional**: Além de garantir compatibilidade com as versões estáveis do Laravel (Laravel 12/13), o Alpine é enxuto (~100MB), possui tempos de build quase instantâneos e compartilha as camadas base de cache no Docker daemon. A imagem oficial já traz nativamente as extensões essenciais (`curl`, `pdo`, `sqlite3`, `mbstring`, `openssl`, `tokenizer`, `opcache`).

2. **Inicialização Idempotente e Resiliente (`entrypoint.sh`)**:
   - **Decisão**: Foi criado um script `entrypoint.sh` para os três serviços Laravel.
   - **Racional**: Elimina atritos em clones novos ou máquinas limpas. O script checa e cria o `.env` a partir de `.env.example` caso inexista, executa `composer install` caso a pasta `vendor` não esteja presente no host (devido ao bind mount), e gera a `APP_KEY` se ausente. O ambiente sobe 100% pronto com um único `docker compose up --build`.

3. **Orquestração com Health Checks e Startup Order**:
   - **Decisão**: Definição de `healthcheck` nativo em todos os containers usando `wget` embutido no Alpine, e `depends_on` no `monolith` com `condition: service_healthy` para os 3 serviços externos.
   - **Racional**: Previne "race conditions" na subida dos containers. O monólito só fica operacional quando seus dependentes já estão respondendo HTTP 200 em seus respectivos endpoints de saúde.

4. **Isolamento de Segurança da Rede e do Payment Provider**:
   - **Decisão**: Apenas `monolith` (8000), `provider-rest` (8001) e `provider-soap` (8002) possuem mapeamento de portas (`ports:`) para o host. O `payment-provider` utiliza apenas `expose: ["8000"]` conectado à bridge `cardok-network`.
   - **Racional**: Simula fielmente uma topologia de rede privada de backend, impedindo que serviços estritamente internos sejam acessados fora da rede de containers da aplicação.

5. **Abstração de Configuração no Laravel (`config/services.php`)**:
   - **Decisão**: Variáveis de ambiente como `PROVIDER_REST_URL`, `PROVIDER_SOAP_URL` e `PAYMENT_PROVIDER_URL` foram mapeadas centralizadamente em `monolith/config/services.php`.
   - **Racional**: Segue as boas práticas do framework e 12-Factor App, garantindo que o código de negócio e os comandos acessem a configuração via `config('services.providers.*')` e `config('services.payment.*')`, permitindo cache de configuração (`config:cache`) e facilidade de mock em testes.

6. **Mecanismo Duplo de Diagnóstico de Rede**:
   - **Decisão**: Criado o comando Artisan `php artisan cardok:check-services` e o endpoint `GET /api/health/integrations` no `monolith`.
   - **Racional**: Permite inspecionar a resolução DNS do Docker e a conectividade tanto interativamente no terminal (exibindo tabela com status, HTTP code e latência em ms) quanto via automações/health aggregators via HTTP.

### 17.2. Problemas Encontrados e Resoluções

- **Docker Desktop não inicializado**:
  - *Sintoma*: Socket `/Users/roger/.docker/run/docker.sock` inacessível.
  - *Resolução*: Inicializado o aplicativo `Docker Desktop` e verificado o retorno positivo do daemon antes dos builds.
- **Checagem de plataforma do Composer (`platform_check.php`)**:
  - *Sintoma*: Incompatibilidade inicial caso o build tentasse rodar em PHP 8.3 após dependências geradas com constraints do PHP 8.4.
  - *Resolução*: Fixada a versão `8.4-cli-alpine` em todos os Dockerfiles, uniformizando o ecossistema.

### 17.3. Validação Executada

```bash
# 1. Sintaxe Compose
docker compose config  # Código de saída 0

# 2. Inicialização
docker compose up --build -d  # 4 containers criados e saudáveis

# 3. Health checks diretos (Host)
curl -s http://localhost:8000/api/health
# {"status":"ok","service":"monolith"}

curl -s http://localhost:8001/api/health
# {"status":"ok","service":"provider-rest"}

curl -s http://localhost:8002/health
# {"status":"ok","service":"provider-soap"}

# 4. Conectividade interna (Monolith -> Provedores)
docker compose exec monolith php artisan cardok:check-services
# Provider REST    | http://provider-rest:8000/api/health | ONLINE | 200
# Provider SOAP    | http://provider-soap:8000/health     | ONLINE | 200
# Payment Provider | http://payment-provider:8000/health  | ONLINE | 200
```