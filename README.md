# Developer Contact API

Symfony REST API для обработки контактных заявок.

Проект реализует backend для формы обратной связи:

- приём и валидация заявок;
- сохранение данных в базу;
- AI-анализ текста через OpenRouter;
- определение категории и тональности сообщения;
- автоматический ответ пользователю;
- отправка уведомлений владельцу;
- rate limit защиты;
- health checks;
- метрики;
- покрытие PHPUnit тестами.

---

# Stack

- PHP 8.4
- Symfony 7
- Doctrine ORM
- MySQL 8.4
- Symfony Validator
- Symfony Serializer
- Symfony Mailer
- Symfony RateLimiter
- Symfony HttpClient
- Monolog
- PHPUnit
- Docker Compose
- OpenRouter API
- Mailpit / Mailtrap

---

# Architecture

Используется простая слоистая архитектура:

```
Controller
    ↓
DTO
    ↓
Service
    ↓
Repository
    ↓
Entity
```

## Ответственность слоёв

| Слой | Назначение |
|---|---|
| Controller | HTTP запросы, ответы API, Swagger |
| DTO | Входные данные и валидация |
| Service | Бизнес-логика |
| Repository | Работа с базой данных |
| Entity | Модель данных Doctrine |

---

# Features

## Contact API

### Создание заявки

```
POST /api/contact
```

Процесс:

1. Проверка входных данных.
2. Проверка rate limit.
3. Создание Contact.
4. Сохранение в MySQL.
5. Отправка текста в OpenRouter.
6. Получение анализа:
    - sentiment
    - category
    - auto reply
7. Отправка email владельцу.
8. Отправка ответа пользователю.
9. Возврат результата API.

---

# API

## POST `/api/contact`

### Request

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "comment": "I need help with my order"
}
```

---

### Response 201

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "comment": "I need help with my order",
    "sentiment": "positive",
    "category": "support",
    "auto_reply": "Thank you for contacting us",
    "created_at": "2026-07-16T17:00:00+00:00"
  }
}
```

---

## Validation error

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": [
    {
      "field": "email",
      "message": "This value is not valid."
    }
  ]
}
```

---

## Rate limit

При превышении лимита:

```
HTTP 429
```

Ответ содержит:

```
Retry-After
```

---

# Health Check

```
GET /api/health
```

Проверяет:

- Database connection
- OpenRouter availability
- Mail configuration


Пример:

```bash
curl http://localhost:8080/api/health
```

---

# Metrics

```
GET /api/metrics
```

Возвращает статистику заявок:

- количество заявок;
- распределение по категориям;
- распределение по тональности.

---

# AI Integration

Используется:

OpenRouter API

Сервис:

```
App\Service\OpenRouterAiService
```

Возможности:

- анализ текста;
- определение категории;
- определение эмоциональной окраски;
- генерация ответа пользователю.

Если API недоступен:

- ошибка логируется;
- используется fallback ответ;
- запрос пользователя не теряется.

---

# Email

Используется Symfony Mailer.

Локально:

```
Mailpit
```

В production:

```
Mailtrap SMTP
```

Отправляются два письма:

## Владельцу

Содержит:

- данные пользователя;
- текст сообщения;
- AI анализ.

## Пользователю

Содержит:

- автоматический ответ.

Ошибки отправки email не ломают создание заявки.

---

# Docker

Проект запускается через Docker Compose.

Сервисы:

| Service | Назначение |
|-|-|
| nginx | Web сервер |
| php | PHP-FPM приложение |
| mysql | База данных |
| mailpit | Локальный SMTP сервер |

---

# Запуск проекта

## Клонирование

```bash
git clone <repository>

cd developer-contact-api
```

---

## Создание окружения

```bash
cp .env .env.local
```

Настроить переменные:

```
DATABASE_URL
MAILER_DSN
OPENROUTER_API_KEY
OWNER_EMAIL
```

---

## Запуск Docker

```bash
docker compose up -d --build
```

---

## Установка зависимостей

```bash
docker compose exec php composer install
```

---

## Миграции

```bash
docker compose exec php php bin/console doctrine:migrations:migrate
```

---

## Очистка кеша

```bash
docker compose exec php php bin/console cache:clear
```

---

# URLs

| URL | Назначение |
|-|-|
| http://localhost:8080 | API |
| http://localhost:8080/api/doc | Swagger |
| http://localhost:8025 | Mailpit |

---

# Environment Variables

| Variable | Description |
|-|-|
| DATABASE_URL | MySQL connection |
| MAILER_DSN | SMTP connection |
| OWNER_EMAIL | Email владельца |
| MAIL_FROM | Email отправителя |
| MAIL_FROM_NAME | Имя отправителя |
| OPENROUTER_API_KEY | OpenRouter API key |
| OPENROUTER_MODEL | AI model |
| OPENROUTER_FALLBACK_REPLY | Ответ при ошибке AI |
| CONTACT_RATE_LIMIT | Количество запросов |
| CONTACT_RATE_INTERVAL | Интервал rate limit |
| APP_SECRET | Symfony secret |

---

# Tests

Используется PHPUnit.

Запуск:

```bash
docker compose exec php php bin/phpunit
```

Покрыты:

## Unit tests

- DTO validation;
- AI service;
- Mail service;
- Health checks;
- Metrics service.

## Functional tests

- API endpoints;
- Validation responses;
- HTTP responses.

---

# Useful commands

Проверка контейнера:

```bash
docker compose ps
```

Логи:

```bash
docker compose logs -f php nginx
```

Symfony:

```bash
docker compose exec php php bin/console about
```

Проверка Doctrine:

```bash
docker compose exec php php bin/console doctrine:schema:validate
```

Проверка контейнера:

```bash
docker compose exec php php bin/console lint:container
```

---

# Project Structure

```
src/
├── Controller/
│
├── DTO/
│
├── Entity/
│
├── Repository/
│
├── Service/
│
├── Exception/
│
└── Enum/


config/
├── packages/
└── services.yaml


migrations/

tests/
├── Functional/
└── Unit/
```

---

# Deployment

Проект готов к развёртыванию через Docker.

Для production необходимо:

1. Настроить MySQL.
2. Настроить SMTP.
3. Добавить OpenRouter API key.
4. Выполнить миграции.
5. Запустить контейнеры.
