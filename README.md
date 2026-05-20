# Notification Service

Microsserviço de comunicação centralizado em Laravel 13, responsável por receber solicitações de envio por **e-mail**, **SMS** e **push notification**, processar de forma assíncrona via filas e manter rastreabilidade completa do ciclo de vida da mensagem.

Construído como resposta ao desafio descrito em [`desafiotecnico.txt`](desafiotecnico.txt), com os seguintes acréscimos:

- CRUD completo de **templates de notificação** por canal (com placeholders `{{variavel}}`).
- Logs de processamento em tabela dedicada (`communication_logs`).
- Cobertura de testes **unitários e de feature** (33 testes, 95 asserções).
- Coleção e environment para **Postman** em [`docs/postman/`](docs/postman/).
- **Makefile** com todos os comandos do projeto.

---

## Arquitetura

```
HTTP → Controller → Form Request → Service → DB + Queue
                                              ↓
                                  ProcessCommunicationJob
                                              ↓
                          ChannelSenderFactory → Email/SMS/Push
                                              ↓
                                CommunicationLog (rastreabilidade)
```

| Camada | Local |
|--------|-------|
| Rotas API | [`routes/api.php`](routes/api.php) |
| Controllers | [`app/Http/Controllers/Api`](app/Http/Controllers/Api) |
| Form Requests | [`app/Http/Requests`](app/Http/Requests) |
| Services | [`app/Services`](app/Services) |
| Job assíncrono | [`app/Jobs/ProcessCommunicationJob.php`](app/Jobs/ProcessCommunicationJob.php) |
| Canais | [`app/Channels`](app/Channels) |
| Models / Enums | [`app/Models`](app/Models), [`app/Enums`](app/Enums) |

---

## Pré-requisitos

- Docker e Docker Compose, **ou**
- PHP 8.4 + Composer + MySQL 8 + (opcional) Mailhog para receber e-mails.

---

## Quick start (Docker)

```bash
make setup    # cria .env, builda imagens, sobe containers, instala deps, migra e popula
make logs     # acompanha logs de app + worker da fila
```

URLs locais:

| Serviço | URL |
|---------|-----|
| API | http://localhost:8000/api |
| Mailhog (inbox de e-mails) | http://localhost:8025 |
| MySQL | `localhost:3306` (`notification` / `secret`) |

O worker da fila roda no serviço `queue` do compose (`php artisan queue:work`).

---

## Quick start (sem Docker)

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan queue:work --tries=3   # em outro terminal
php artisan serve
```

> Ajuste `DB_*` e `QUEUE_CONNECTION` no `.env` conforme seu ambiente local. O default `QUEUE_CONNECTION=database` usa a tabela `jobs` (já criada pelas migrations padrão do Laravel).

---

## Endpoints

### Criar comunicação

`POST /api/v1/communications`

Payload mínimo (do desafio):

```json
{
  "recipient": "usuario@email.com",
  "channel": "email",
  "subject": "Bem-vindo",
  "message": "Sua conta foi criada com sucesso.",
  "origin_system": "sistema-financeiro"
}
```

Usando template (extensão):

```json
{
  "recipient": "+5511999999999",
  "channel": "sms",
  "origin_system": "sistema-financeiro",
  "template_slug": "codigo-otp",
  "variables": { "codigo": "123456" }
}
```

Resposta: `202 Accepted` com o registro criado em `status=pending` e o `ProcessCommunicationJob` já enfileirado.

Exemplo `curl`:

```bash
curl -i -X POST http://localhost:8000/api/v1/communications \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "recipient": "usuario@email.com",
    "channel": "email",
    "subject": "Bem-vindo",
    "message": "Sua conta foi criada com sucesso.",
    "origin_system": "sistema-financeiro"
  }'
```

### Consultar comunicação

`GET /api/v1/communications/{id}` — retorna status atual + histórico de logs (`received`, `queued`, `processing`, `sent`, `failed`).

### Buscar comunicações

`GET /api/v1/communications` — lista paginada com filtros opcionais (combiná­veis):

| Query | Descrição |
|-------|-----------|
| `channel` | `email`, `sms` ou `push` |
| `status` | `pending`, `processing`, `sent`, `failed`, `cancelled` |
| `include_cancelled` | `1` para incluir comunicações canceladas (soft delete) |
| `origin_system` | match exato (ex.: `sistema-financeiro`) |
| `recipient` | match parcial (LIKE) no destinatário |
| `template_slug` | comunicações vinculadas a um template específico |
| `template_id` | mesmo filtro, por ID |
| `per_page` | tamanho da página (1–100, default 15) |

Exemplo:

```bash
curl "http://localhost:8000/api/v1/communications?channel=email&status=sent&origin_system=sistema-financeiro&recipient=usuario&template_slug=boas-vindas"
```

### Editar ou excluir antes do envio

Enquanto o status estiver `pending`, a comunicação ainda pode ser corrigida ou cancelada:

| Método | Rota | Descrição |
|--------|------|-----------|
| `PATCH/PUT` | `/api/v1/communications/{id}` | Atualiza `recipient`, `subject`, `message`, `origin_system`, `variables` ou troca `template_id`/`template_slug` (sempre no mesmo canal) |
| `DELETE` | `/api/v1/communications/{id}` | Cancela a comunicação pendente (status `cancelled` + soft delete) |
| `POST` | `/api/v1/communications/{id}/retry` | Reprocessa comunicação com status `failed` |

Tentar alterar ou excluir uma comunicação em `processing`, `sent` ou `failed` retorna `409 Conflict`:

```json
{
  "message": "A comunicação #1 não pode ser atualizada no estado atual (sent).",
  "error": "communication_not_editable",
  "status": "sent"
}
```

### Headers recomendados (POST communications)

| Header | Descrição |
|--------|-----------|
| `X-Request-Id` | Correlation ID para rastrear request → comunicação → logs (gerado automaticamente se omitido) |
| `Idempotency-Key` | Evita duplicar envios em retries do cliente (UUID recomendado) |

Rate limit: `60` requisições/min por `origin_system` (configurável via `NOTIFICATIONS_RATE_LIMIT`).

### Healthcheck

`GET /api/v1/health` — verifica banco, fila (`jobs` / `failed_jobs`) e versão da API.

### CRUD de templates

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/api/v1/notification-templates` | Lista (filtros: `channel`, `is_active`, `search`, `per_page`) |
| `POST` | `/api/v1/notification-templates` | Cria template |
| `GET` | `/api/v1/notification-templates/{id}` | Detalhe |
| `PATCH/PUT` | `/api/v1/notification-templates/{id}` | Atualiza |
| `DELETE` | `/api/v1/notification-templates/{id}` | Remove |

Placeholders no `body`/`subject` seguem o padrão `{{variavel}}` e são substituídos pelas `variables` enviadas na criação da comunicação.

---

## Canais e drivers

| Canal | Driver padrão (dev) |
|-------|---------------------|
| `email` | `Mail::send` com `MAIL_MAILER=smtp` apontando para **Mailhog** no Docker (ou `log` fora dele). |
| `sms` | Log estruturado em `storage/logs/laravel.log` (`SmsChannelSender`). |
| `push` | Log estruturado em `storage/logs/laravel.log` (`PushChannelSender`). |

Para integrar provedores reais (Twilio, FCM etc.) basta implementar [`ChannelSenderInterface`](app/Contracts/ChannelSenderInterface.php) e ajustar [`ChannelSenderFactory`](app/Channels/ChannelSenderFactory.php).

---

## Testes

```bash
make test            # toda a suíte
make test-unit       # apenas tests/Unit
make test-feature    # apenas tests/Feature
make test-filter FILTER=ProcessCommunicationJobTest
```

Sem Docker:

```bash
php artisan test --compact
```

Cobertura inclui:

- Criação e validações do endpoint `POST /api/v1/communications` (todos os canais, com e sem template).
- `GET /api/v1/communications/{id}` com logs.
- CRUD completo de `notification-templates` por canal.
- `ProcessCommunicationJob` (sucesso, renderização de template, falha esgotando tentativas).
- `TemplateRenderer`, `CommunicationService`, `ChannelSenderFactory` e senders.

---

## Postman

Coleção e environment prontos em [`docs/postman/`](docs/postman/):

- `notification-service.postman_collection.json`
- `notification-service.postman_environment.json`

No Postman: **Import** → selecione os dois arquivos → ative o environment **Notification Service - Local**.

Valide o JSON via `make postman-validate` (executa dentro do container).

---

## Comandos do Makefile

```bash
make help            # lista todos os targets disponíveis
make setup           # bootstrap completo
make up | down       # sobe / derruba containers
make migrate         # migrations
make migrate-fresh   # recria DB + seed
make seed            # roda seeders
make queue           # worker da fila em foreground
make queue-restart   # sinaliza restart
make test            # roda toda a suíte
make pint            # formata arquivos alterados
make shell           # bash no container app
make logs            # logs combinados app + queue
make mysql           # client mysql no container
```

---

## Estrutura de pastas (resumo)

```
app/
  Channels/          drivers de envio (email/sms/push) + factory
  Contracts/         ChannelSenderInterface
  DTOs/              OutboundMessage
  Enums/             CommunicationChannel|Status|LogEvent
  Exceptions/        MissingTemplateVariableException
  Http/
    Controllers/Api/ CommunicationController, NotificationTemplateController
    Requests/        Form Requests com validação por canal
    Resources/       JSON Resources
  Jobs/              ProcessCommunicationJob (ShouldQueue, tries=3)
  Mail/              CommunicationMail (Mailable)
  Models/            Communication, CommunicationLog, NotificationTemplate
  Services/          CommunicationService, NotificationTemplateService, TemplateRenderer
database/
  factories/         CommunicationFactory, NotificationTemplateFactory
  migrations/        notification_templates, communications, communication_logs
  seeders/           NotificationTemplateSeeder (templates de exemplo)
docker/              Dockerfile (PHP 8.4)
docker-compose.yml   app + queue + mysql + mailhog
docs/postman/        coleção e environment
Makefile             comandos do projeto
routes/api.php       rotas REST
tests/
  Feature/Api/       endpoints
  Feature/Jobs/      job assíncrono
  Unit/              services, renderer, senders, factory
```

---

## Variáveis de ambiente relevantes

| Chave | Default | Descrição |
|-------|---------|-----------|
| `DB_*` | `mysql` / `notification_service` / `notification` / `secret` | Conexão com o MySQL (do compose) |
| `QUEUE_CONNECTION` | `database` | Usa a tabela `jobs` para enfileirar |
| `MAIL_MAILER` | `smtp` | Apontado para `mailhog:1025` no Docker |
| `APP_PORT` | `8000` | Porta exposta pelo `app` |
