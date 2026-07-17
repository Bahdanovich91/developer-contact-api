# Developer Contact API

Symfony 7 REST API для обработки контактных заявок с AI-анализом через OpenRouter и отправкой писем через Mailtrap Sandbox.

## Описание

Backend для формы обратной связи:

1. Принимает и валидирует заявку (`POST /api/contact`).
2. Сохраняет контакт в PostgreSQL.
3. Анализирует текст через OpenRouter (`sentiment`, `category`, `autoReply`).
4. Отправляет уведомление владельцу и auto-reply пользователю.
5. Защищает endpoint rate limit’ом.
6. Отдаёт health checks, metrics и Swagger UI.

При ошибке AI используется fallback-ответ; ошибки SMTP не ломают создание заявки.

---

## Стек технологий

| Технология | Назначение |
|---|---|
| PHP 8.4 | Runtime |
| Symfony 7.4 | Framework |
| Doctrine ORM + Migrations | PostgreSQL persistence |
| PostgreSQL 16 | База данных |
| Symfony Validator / Serializer | Валидация и DTO |
| Symfony HttpClient | OpenRouter API |
| Symfony Mailer | SMTP (Mailtrap) |
| Symfony RateLimiter | Лимит запросов |
| Monolog | Логирование (`api`, `ai`) |
| Nelmio ApiDoc / CORS | Swagger UI и CORS |
| PHPUnit | Unit + Functional тесты |
| Docker Compose | Локальный запуск |
| Render (`Dockerfile.prod`) | Production deployment |

---

## Архитектура

Слоистая архитектура без лишних абстракций:

```
Controller  →  DTO  →  Service  →  Repository  →  Entity / внешние API
```

| Слой | Ответственность |
|---|---|
| `Controller` | HTTP, JSON-ответы, OpenAPI атрибуты |
| `Dto` | Входные данные и контракты ответов |
| `Service` | Бизнес-логика (contact, AI, mail, health, metrics) |
| `Repository` | Doctrine-доступ к Contact |
| `Entity` / `Enum` | Модель и значения `sentiment` / `category` |
| `EventSubscriber` | Логирование API и единый JSON error handler |

Основной поток `POST /api/contact`:

```
ContactController
  → ContactService
      → ContactRepository::save()
      → OpenRouterAiService::analyze()
      → ContactRepository::save() (AI fields)
      → ContactMailerService::sendOwnerNotification()
      → ContactMailerService::sendUserAutoReply()
```

---

## Структура проекта

```
developer-contact-api/
├── bin/
├── config/
│   ├── packages/          # framework, doctrine, mailer, monolog, nelmio, …
│   ├── routes/
│   └── services.yaml
├── docker/
│   ├── nginx/             # default.conf (dev), render.conf.template (prod)
│   └── php/               # entrypoint, php.ini, entrypoint-prod.sh
├── migrations/
├── public/
├── src/
│   ├── Controller/        # Contact, Health, Metrics
│   ├── Dto/
│   ├── Entity/
│   ├── Enum/
│   ├── EventSubscriber/
│   ├── Exception/
│   ├── Repository/
│   └── Service/
├── tests/
│   ├── Functional/
│   └── Unit/
├── Dockerfile             # local PHP-FPM image
├── Dockerfile.prod        # nginx + PHP-FPM for Render
├── docker-compose.yml
├── render.yaml
├── .env                   # defaults (без секретов)
├── .env.dev / .env.test
└── README.md
```

---

## Docker (локально)

Сервисы Compose:

| Service | Описание |
|---|---|
| `app` | PHP 8.4-FPM (`contact_api_app`) |
| `nginx` | HTTP `:8080` → PHP-FPM |
| `postgres` | PostgreSQL 16 (`:5432` на хосте) |
| `mailer` | Mailpit (опционально; по умолчанию проект шлёт в Mailtrap) |
| `composer` | profile `tools` |

Production-образ: `Dockerfile.prod` (nginx слушает `$PORT`, migrations в entrypoint).

---

## Запуск проекта

```bash
git clone <repository>
cd developer-contact-api

# Секреты и локальные переопределения (не коммитить)
cp .env .env.local
# В .env.local задать:
#   OPENROUTER_API_KEY=...
#   MAILER_DSN=smtp://USERNAME:PASSWORD@sandbox.smtp.mailtrap.io:2525
#   OWNER_EMAIL=...
#   MAIL_FROM=...

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console cache:clear
```

Проверка:

```bash
curl http://localhost:8080/api/health
curl http://localhost:8080/api/doc
```

| URL | Назначение |
|---|---|
| http://localhost:8080 | API |
| http://localhost:8080/api/doc | Swagger UI |
| http://localhost:8080/api/doc.json | OpenAPI JSON |
| http://localhost:8025 | Mailpit UI (если используется локальный SMTP) |

Контейнер приложения: `app` (не `php`).

---

## Переменные окружения

Symfony загружает (по приоритету, реальные env Docker побеждают файлы):

1. `.env`
2. `.env.local` (gitignored)
3. `.env.$APP_ENV` (например `.env.dev`)
4. `.env.$APP_ENV.local`

Docker Compose читает `.env` / `.env.local` и пробрасывает значения в контейнер `app`.

| Variable | Описание |
|---|---|
| `APP_ENV` | `dev` / `test` / `prod` |
| `APP_SECRET` | Symfony secret |
| `DATABASE_URL` | PostgreSQL DSN |
| `MAILER_DSN` | SMTP DSN (Mailtrap Sandbox) |
| `OWNER_EMAIL` | Email владельца (уведомление о заявке) |
| `MAIL_FROM` | From address |
| `MAIL_FROM_NAME` | From display name |
| `OPENROUTER_API_KEY` | Ключ OpenRouter |
| `OPENROUTER_BASE_URL` | `https://openrouter.ai/api/v1` |
| `OPENROUTER_MODEL` | например `openai/gpt-4o` |
| `OPENROUTER_TIMEOUT` | Таймаут HTTP (сек) |
| `OPENROUTER_FALLBACK_REPLY` | Ответ при ошибке AI |
| `CORS_ALLOW_ORIGIN` | Regex / origin для CORS |
| `RUN_MIGRATIONS` | `1` — миграции при старте контейнера |

Секреты не хранить в git: только в `.env.local` или в Render Dashboard.

---

## OpenRouter

Сервис: `App\Service\OpenRouterAiService`.

- Endpoint: `POST {OPENROUTER_BASE_URL}/chat/completions`
- Auth: `Authorization: Bearer {OPENROUTER_API_KEY}`
- В запросе задаётся `max_tokens` (иначе возможен HTTP 402 из‑за дефолтного бюджета токенов)
- Ответ модели — JSON: `sentiment`, `category`, `autoReply`
- Health probe: `GET {OPENROUTER_BASE_URL}/models`
- Логи: Monolog channel `ai` (без утечки API key)
- При любой ошибке — `AiAnalysisResult::fallback()`, заявка всё равно сохраняется

---

## Mailtrap Sandbox

Используется Symfony Mailer + `ContactMailerService`.

DSN:

```env
MAILER_DSN=smtp://USERNAME:PASSWORD@sandbox.smtp.mailtrap.io:2525
```

Пример (подставить свои credentials из Mailtrap → Email Testing → SMTP Settings):

```env
MAILER_DSN=smtp://b3a250f01bf3f5:YOUR_PASSWORD@sandbox.smtp.mailtrap.io:2525
```

На одно `POST /api/contact` уходит два письма:

1. **Владельцу** (`OWNER_EMAIL`) — данные заявки + AI-анализ.
2. **Пользователю** — auto-reply из OpenRouter (или fallback).

`TransportException` логируется и **не** прерывает создание Contact.

На free-плане Mailtrap Sandbox второе письмо подряд может получить `550 Too many emails per second`.
`ContactMailerService` делает один повтор с паузой; оба письма всё равно появляются в Inbox.

Проверка: [Mailtrap](https://mailtrap.io/) → Email Testing → Sandbox → Inbox.

---

## Swagger

- UI: http://localhost:8080/api/doc
- JSON: http://localhost:8080/api/doc.json

Документация строится атрибутами OpenAPI на контроллерах (Nelmio ApiDoc Bundle).

---

## API

### `POST /api/contact`

Request:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "comment": "I need help with my order"
}
```

Response `201`:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "comment": "I need help with my order",
    "sentiment": "neutral",
    "category": "support",
    "auto_reply": "Thank you for contacting us…",
    "created_at": "2026-07-17T12:00:00+00:00"
  }
}
```

Ошибки:

| HTTP | Случай |
|---|---|
| `422` | Validation failed |
| `429` | Rate limit (`Retry-After`) |

Rate limit (по умолчанию): 10 запросов / минуту с IP (`config/packages/rate_limiter.yaml`).

### `GET /api/health`

Проверяет database, OpenRouter, mailer. Статусы: `ok` | `degraded` | `error`.  
HTTP `503` только при overall `error` (обычно недоступна БД).

### `GET /api/metrics`

Агрегаты по заявкам: total, by sentiment/category, last 24h / 7d.

---

## PHPUnit

```bash
docker compose exec app php bin/phpunit
```

Покрытие:

- Unit: DTO, OpenRouterAiService, ContactMailerService, HealthCheckService, MetricsService
- Functional: Contact / Health / Metrics endpoints

Тестовый env: `.env.test` (`MAILER_DSN=null://null`, stub OpenRouter URL).

---

## Команды Symfony

```bash
docker compose exec app php bin/console about
docker compose exec app php bin/console cache:clear
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console doctrine:schema:validate
docker compose exec app php bin/console lint:container
docker compose exec app php bin/console debug:dotenv
docker compose logs -f app nginx
```

---
