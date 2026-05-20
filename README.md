# Notification Service

Serviço de comunicação centralizado em Laravel 13 responsável por receber solicitações de envio por **e-mail**, **SMS** e **push notification**, processar de forma assíncrona via filas e manter rastreabilidade completa do ciclo de vida da mensagem.

Sistemas internos enviam requisições HTTP para esta API quando precisam disparar uma notificação. O serviço valida o payload, resolve o template (se houver), persiste a comunicação, enfileira o envio e expõe endpoints para acompanhar status, reprocessar falhas e cancelar antes do envio.

---

## Sumário

- [Arquitetura e padrões](#arquitetura-e-padrões)
- [Pré-requisitos](#pré-requisitos)
- [Quick start (Docker)](#quick-start-docker)
- [Quick start (sem Docker)](#quick-start-sem-docker)
- [Variáveis de ambiente](#variáveis-de-ambiente)
- [Comandos do Makefile](#comandos-do-makefile)
- [Endpoints da API](#endpoints-da-api)
- [Templates de notificação](#templates-de-notificação)
- [Canais e drivers](#canais-e-drivers)
- [Idempotência, rate limit e correlation ID](#idempotência-rate-limit-e-correlation-id)
- [Observabilidade](#observabilidade)
- [Testes](#testes)
- [Postman](#postman)
- [Estrutura de pastas](#estrutura-de-pastas)

---

## Arquitetura e padrões

```text
HTTP → Middleware → Form Request → Controller → Service → Repository → DB
                                                    ↓
                                                  Queue (jobs)
                                                    ↓
                                         ProcessCommunicationJob
                                                    ↓
                                       ChannelSenderFactory
                                                    ↓
                                       Email / SMS / Push
                                                    ↓
                              CommunicationLog + Domain Events
```

| Camada | Local |
|--------|-------|
| Rotas | [`routes/api.php`](routes/api.php) |
| Middlewares | [`app/Http/Middleware`](app/Http/Middleware) |
| Controllers | [`app/Http/Controllers/Api`](app/Http/Controllers/Api) |
| Form Requests | [`app/Http/Requests`](app/Http/Requests) |
| Services | [`app/Services`](app/Services) |
| Repositories | [`app/Repositories/Eloquent`](app/Repositories/Eloquent) |
| Contracts | [`app/Contracts`](app/Contracts) |
| Jobs | [`app/Jobs`](app/Jobs) |
| Canais | [`app/Channels`](app/Channels) |
| Events | [`app/Events`](app/Events) |
| Models / Enums | [`app/Models`](app/Models), [`app/Enums`](app/Enums) |

**Padrões e práticas utilizadas:**

- API REST versionada em `/api/v1`.
- Service Layer para regras de negócio.
- Repository Pattern com interfaces (`app/Contracts/Repositories`) para isolar persistência do domínio.
- Form Requests para validação na borda HTTP.
- Strategy via [`ChannelSenderInterface`](app/Contracts/ChannelSenderInterface.php) para canais de envio.
- Factory ([`ChannelSenderFactory`](app/Channels/ChannelSenderFactory.php)) para escolher o sender por canal.
- Domain Events (`CommunicationCreated`, `CommunicationSent`, `CommunicationFailed`) para evoluções futuras (webhooks, métricas).
- Jobs assíncronos com Laravel Queue.
- Idempotência por header `Idempotency-Key`.
- Rate limit nomeado por `origin_system`.
- Correlation ID propagado via header `X-Request-Id`.
- Soft delete + status `cancelled` para auditoria.
- Logs estruturados em JSON via Monolog.

---

## Pré-requisitos

- Docker e Docker Compose, **ou**
- PHP 8.4 + Composer 2 + MySQL 8 + (opcional) Mailhog para receber e-mails em desenvolvimento.

Portas usadas localmente:

| Porta | Serviço |
|-------|---------|
| 8000  | API (`app`) |
| 3306  | MySQL |
| 1025  | SMTP do Mailhog |
| 8025 (ou `MAILHOG_UI_PORT`) | Painel web do Mailhog |

---

## Quick start (Docker)

```bash
make setup
```

Esse comando faz tudo de uma vez:

1. cria o `.env` a partir do `.env.example` (se ainda não existir);
2. builda as imagens;
3. sobe os containers (`app`, `queue`, `mysql`, `mailhog`);
4. instala dependências PHP;
5. gera `APP_KEY`;
6. roda migrations;
7. roda seeders.

Em seguida:

```bash
make logs       # acompanha logs do app e do worker
make queue      # (opcional) executa um worker extra em foreground
```

URLs locais:

| Serviço | URL |
|---------|-----|
| API | http://localhost:8000/api/v1 |
| Healthcheck | http://localhost:8000/api/v1/health |
| Mailhog (inbox) | http://localhost:8025 |
| MySQL | `localhost:3306` (`notification` / `secret`) |

> O worker de produção roda no container `queue` do compose (`php artisan queue:work --tries=3 --timeout=60`).

---

## Quick start (sem Docker)

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed

# em outro terminal
php artisan queue:work --tries=3 --timeout=60

php artisan serve
```

Ajuste `DB_*`, `MAIL_*` e `QUEUE_CONNECTION` no `.env` conforme o ambiente local. O default `QUEUE_CONNECTION=database` usa a tabela `jobs` criada pelas migrations padrão do Laravel — não há dependência de Redis para subir.

---

## Variáveis de ambiente

Todas as chaves padrão estão em [`.env.example`](.env.example). Abaixo as mais relevantes para operar este serviço:

### Aplicação

| Chave | Default | Descrição |
|-------|---------|-----------|
| `APP_NAME` | `Laravel` | Nome exibido em logs e e-mails |
| `APP_ENV` | `local` | Ambiente (`local`, `staging`, `production`) |
| `APP_KEY` | gerado por `php artisan key:generate` | Chave de criptografia |
| `APP_DEBUG` | `true` em local | Desligar em produção |
| `APP_URL` | `http://localhost` | URL pública da aplicação |
| `APP_VERSION` | `1.0.0` | Exposto no healthcheck |
| `APP_PORT` | `8000` | Porta exposta pelo container `app` |

### Banco de dados

| Chave | Default | Descrição |
|-------|---------|-----------|
| `DB_CONNECTION` | `mysql` | Driver |
| `DB_HOST` | `mysql` (Docker) ou `127.0.0.1` | Host do MySQL |
| `DB_PORT` | `3306` | Porta do MySQL |
| `DB_DATABASE` | `notification_service` | Schema |
| `DB_USERNAME` | `notification` | Usuário da aplicação |
| `DB_PASSWORD` | `secret` | Senha da aplicação |
| `DB_ROOT_PASSWORD` | `root` | Usado apenas pelo container `mysql` |
| `DB_PORT_HOST` | `3306` | Porta exposta para o host |

### Filas

| Chave | Default | Descrição |
|-------|---------|-----------|
| `QUEUE_CONNECTION` | `database` | Driver da fila (`database`, `redis`, `sqs`, etc.) |

A tabela `jobs` é criada pelas migrations padrão do Laravel. Para usar Redis ou SQS basta trocar a variável e configurar `config/queue.php`.

### E-mail

| Chave | Default | Descrição |
|-------|---------|-----------|
| `MAIL_MAILER` | `smtp` (Docker) ou `log` | Driver |
| `MAIL_HOST` | `mailhog` (Docker) | Host SMTP |
| `MAIL_PORT` | `1025` | Porta SMTP |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | Remetente padrão |
| `MAIL_FROM_NAME` | `${APP_NAME}` | Nome do remetente |
| `MAILHOG_SMTP_PORT` | `1025` | Porta SMTP do Mailhog no host |
| `MAILHOG_UI_PORT` | `8025` | Porta do painel web do Mailhog |

### Logs

| Chave | Default | Descrição |
|-------|---------|-----------|
| `LOG_CHANNEL` | `stack` | Canal raiz |
| `LOG_STACK` | `single,stderr` (Docker) | Canais combinados |
| `LOG_LEVEL` | `debug` | Nível mínimo |
| `LOG_STDERR_FORMATTER` | `Monolog\Formatter\JsonFormatter::class` | Logs estruturados no `stderr` |

### Comportamento do serviço

| Chave | Default | Descrição |
|-------|---------|-----------|
| `NOTIFICATIONS_RATE_LIMIT` | `60` | Requisições por minuto por `origin_system` no `POST /communications` |
| `NOTIFICATIONS_IDEMPOTENCY_TTL_HOURS` | `24` | TTL das chaves de idempotência armazenadas |

---

## Comandos do Makefile

`make help` lista todos os targets disponíveis. Resumo dos mais usados:

| Comando | Descrição |
|---------|-----------|
| `make setup` | Bootstrap completo (env, build, deps, migrate, seed) |
| `make up` / `make down` | Sobe / derruba containers |
| `make restart` | Reinicia containers |
| `make logs` | Logs combinados `app` + `queue` |
| `make ps` | Lista containers |
| `make shell` | Bash no container `app` |
| `make mysql` | Client MySQL no container `mysql` |
| `make install` | `composer install` no container |
| `make key` | Gera `APP_KEY` |
| `make migrate` | Executa migrations |
| `make migrate-fresh` | Drop + migrate + seed |
| `make seed` | Executa seeders |
| `make queue` | Worker da fila em foreground |
| `make queue-restart` | Sinaliza workers para reiniciar |
| `make test` | Roda toda a suíte de testes |
| `make test-unit` | Apenas testes unitários |
| `make test-feature` | Apenas testes de feature |
| `make test-filter FILTER=NomeDoTeste` | Filtra um teste específico |
| `make pint` | Formata arquivos alterados |
| `make pint-test` | Checa formatação sem alterar |
| `make postman-validate` | Valida JSON da coleção do Postman |

---

## Endpoints da API

Base: `http://localhost:8000/api/v1`.

### Criar comunicação

`POST /api/v1/communications`

Payload mínimo:

```json
{
  "recipient": "usuario@email.com",
  "channel": "email",
  "subject": "Bem-vindo",
  "message": "Sua conta foi criada com sucesso.",
  "origin_system": "sistema-financeiro"
}
```

Com template:

```json
{
  "recipient": "+5511999999999",
  "channel": "sms",
  "origin_system": "sistema-financeiro",
  "template_slug": "codigo-otp",
  "variables": { "codigo": "123456" }
}
```

Resposta: `202 Accepted` com a comunicação em `status=pending` e o `ProcessCommunicationJob` enfileirado.

Exemplo `curl` com headers recomendados:

```bash
curl -i -X POST http://localhost:8000/api/v1/communications \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-Request-Id: $(uuidgen)" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "recipient": "usuario@email.com",
    "channel": "email",
    "subject": "Bem-vindo",
    "message": "Sua conta foi criada com sucesso.",
    "origin_system": "sistema-financeiro"
  }'
```

### Consultar comunicação

`GET /api/v1/communications/{id}` — retorna a comunicação com template carregado e histórico de logs (`received`, `queued`, `processing`, `sent`, `failed`, `retried`, `cancelled`).

### Listar comunicações

`GET /api/v1/communications` — lista paginada com filtros combináveis:

| Query | Descrição |
|-------|-----------|
| `channel` | `email`, `sms` ou `push` |
| `status` | `pending`, `processing`, `sent`, `failed`, `cancelled` |
| `include_cancelled` | `1` para incluir canceladas (soft delete) |
| `origin_system` | match exato (ex.: `sistema-financeiro`) |
| `recipient` | match parcial (LIKE) no destinatário |
| `template_id` | template usado, por ID |
| `template_slug` | template usado, por slug |
| `per_page` | tamanho da página (1–100, default 15) |

```bash
curl "http://localhost:8000/api/v1/communications?channel=email&status=sent&origin_system=sistema-financeiro&template_slug=boas-vindas"
```

### Editar, cancelar ou reprocessar

| Método | Rota | Pré-condição | Descrição |
|--------|------|--------------|-----------|
| `PATCH/PUT` | `/api/v1/communications/{id}` | status `pending` | Atualiza destinatário, conteúdo, origem, variáveis ou troca de template (mesmo canal) |
| `DELETE` | `/api/v1/communications/{id}` | status `pending` | Cancela: marca `status=cancelled` + soft delete |
| `POST` | `/api/v1/communications/{id}/retry` | status `failed` | Reenfileira para reprocessar |

Operações fora do estado válido respondem `409 Conflict` com payload amigável:

```json
{
  "message": "A comunicação #1 não pode ser atualizada no estado atual (sent).",
  "error": "communication_not_editable",
  "status": "sent"
}
```

### Healthcheck

`GET /api/v1/health` — verifica conexão com banco e tamanho da fila. Resposta:

```json
{
  "status": "ok",
  "version": "1.0.0",
  "checks": {
    "database": { "status": "ok" },
    "queue": { "status": "ok", "pending_jobs": 0, "failed_jobs": 0 }
  }
}
```

Retorna `503 Service Unavailable` se algum check falhar.

### CRUD de templates

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/api/v1/notification-templates` | Lista (filtros: `channel`, `is_active`, `search`, `per_page`) |
| `POST` | `/api/v1/notification-templates` | Cria template |
| `GET` | `/api/v1/notification-templates/{id}` | Detalhe |
| `PATCH/PUT` | `/api/v1/notification-templates/{id}` | Atualiza |
| `DELETE` | `/api/v1/notification-templates/{id}` | Remove |

---

## Templates de notificação

Os templates ficam em `notification_templates` e são identificados pelo par único `slug + channel`. Suportam:

- `name`, `slug`, `channel`, `subject`, `body`
- `required_variables` (JSON array) — checadas no momento de criar a comunicação
- `is_active` para desativar sem remover
- placeholders no padrão `{{variavel}}` em `subject` e `body`

Exemplo:

```json
{
  "name": "Boas-vindas",
  "slug": "boas-vindas",
  "channel": "email",
  "subject": "Bem-vindo, {{nome}}",
  "body": "Olá {{nome}}, sua conta na {{empresa}} foi criada.",
  "required_variables": ["nome", "empresa"],
  "is_active": true
}
```

Se a comunicação informar um template sem fornecer as `required_variables`, a API retorna `422` antes mesmo de enfileirar o job.

---

## Canais e drivers

| Canal | Driver padrão (dev) |
|-------|---------------------|
| `email` | `Mail::send` com `MAIL_MAILER=smtp` apontando para **Mailhog** no Docker (ou `log` fora dele) |
| `sms` | Implementação mock que registra envios em log estruturado |
| `push` | Implementação mock que registra envios em log estruturado |

Para integrar provedores reais (Twilio, FCM, SES etc.):

1. implemente [`ChannelSenderInterface`](app/Contracts/ChannelSenderInterface.php) com a chamada ao SDK;
2. registre a nova classe em [`ChannelSenderFactory`](app/Channels/ChannelSenderFactory.php);
3. adicione as credenciais ao `.env`;
4. atualize os testes correspondentes.

---

## Idempotência, rate limit e correlation ID

### Idempotency-Key

- Header opcional `Idempotency-Key` no `POST /api/v1/communications`.
- Recomendação: UUID gerado pelo cliente.
- Mesma key + mesmo body → devolve a resposta original.
- Mesma key + body diferente → `422 idempotency_conflict`.
- TTL configurável via `NOTIFICATIONS_IDEMPOTENCY_TTL_HOURS`.
- Implementado em [`HandleIdempotencyKey`](app/Http/Middleware/HandleIdempotencyKey.php), persistido na tabela `idempotency_keys`.

### Rate limit

- `POST /api/v1/communications` é protegido por `RateLimiter::for('communications')`.
- Chave: `origin_system` (fallback IP).
- Default: `60` requisições/min, configurável via `NOTIFICATIONS_RATE_LIMIT`.
- Resposta excedida: `429 Too Many Requests` com headers padrão do Laravel.

### Correlation ID

- Middleware [`EnsureCorrelationId`](app/Http/Middleware/EnsureCorrelationId.php) lê `X-Request-Id`; se não vier, gera um UUID.
- O valor é:
  - propagado em `Log::withContext()`;
  - persistido em `communications.correlation_id`;
  - retornado no header `X-Request-Id` da resposta.
- Permite rastrear uma request HTTP até a comunicação no banco e os logs do worker.

---

## Observabilidade

- **Logs estruturados** no canal `stderr` usando `JsonFormatter`. Em Docker, basta `docker compose logs -f queue` (ou `make logs`) para acompanhar.
- **Logs de negócio** em `communication_logs`, com eventos `received`, `queued`, `processing`, `sent`, `failed`, `retried`, `cancelled`.
- **Domain Events** (`CommunicationCreated`, `CommunicationSent`, `CommunicationFailed`) prontos para receber listeners (webhooks, métricas).
- **Healthcheck** em `GET /api/v1/health` para readiness/liveness em orquestradores.
- **Filas** com `--tries=3` e `--backoff=10s`; falhas definitivas vão para `failed_jobs`.

---

## Testes

```bash
make test              # toda a suíte
make test-unit         # apenas testes unitários
make test-feature      # apenas testes de feature
make test-filter FILTER=CommunicationStoreTest
```

Sem Docker:

```bash
php artisan test --compact
```

Cobertura inclui:

- Endpoints da API v1 (criar, consultar, listar, editar, cancelar, retry, healthcheck).
- CRUD completo de templates por canal.
- Validações de canal, subject, recipient e variáveis obrigatórias.
- Job assíncrono (`ProcessCommunicationJob`) em cenários de sucesso, template renderizado e falha após esgotar tentativas.
- Middlewares de idempotência, rate limit e correlation ID.
- Disparo dos Domain Events.
- Camada de serviços e renderer de templates.

---

## Postman

Coleção e environment prontos em [`docs/postman/`](docs/postman/):

- `notification-service.postman_collection.json`
- `notification-service.postman_environment.json`

No Postman: **Import** → selecione os dois arquivos → ative o environment **Notification Service - Local**.

Para validar o JSON:

```bash
make postman-validate
```

---

## Estrutura de pastas

```text
app/
  Channels/                     senders por canal + factory
  Contracts/                    interfaces (ChannelSenderInterface, repositórios)
  DTOs/                         OutboundMessage
  Enums/                        CommunicationChannel|Status|LogEvent
  Events/                       CommunicationCreated|Sent|Failed
  Exceptions/                   exceções HTTP customizadas
  Http/
    Controllers/Api/            CommunicationController, NotificationTemplateController, HealthCheckController
    Middleware/                 EnsureCorrelationId, HandleIdempotencyKey
    Requests/                   Form Requests por endpoint
    Resources/                  JSON Resources
  Jobs/                         ProcessCommunicationJob
  Mail/                         CommunicationMail
  Models/                       Communication, CommunicationLog, NotificationTemplate, IdempotencyKey
  Repositories/Eloquent/        implementações de repositórios
  Services/                     CommunicationService, NotificationTemplateService, TemplateRenderer
  Support/                      traits utilitárias
bootstrap/                      registro de middlewares, exception handlers e rate limiters
config/                         config Laravel + config/notifications.php
database/
  factories/                    factories para testes
  migrations/                   schema completo
  seeders/                      templates de exemplo
docker/                         Dockerfile (PHP 8.4)
docker-compose.yml              app + queue + mysql + mailhog
docs/postman/                   coleção e environment
Makefile                        comandos do projeto
routes/api.php                  rotas REST versionadas em /api/v1
tests/
  Feature/Api/                  endpoints
  Feature/Events/               domain events
  Feature/Jobs/                 job assíncrono
  Feature/Middleware/           correlation, idempotência
  Unit/                         services, renderer, senders, factory
```
