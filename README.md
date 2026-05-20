# Notification Service

Notification Service is a Laravel-based asynchronous notification microservice responsible for reliable SMS and Email delivery using RabbitMQ queues.

The project demonstrates production-oriented backend engineering approaches including:

- asynchronous message processing;
- priority-based delivery;
- retry mechanisms;
- idempotent API design;
- integration testing;
- reliable queue consumption;
- Redis caching;
- RabbitMQ messaging;
- PostgreSQL persistence;
- Dockerized local development.

---

## Tech Stack

| Technology | Purpose |
|---|---|
| PHP 8.3 | Main programming language |
| Laravel | Application framework |
| PostgreSQL | Primary database |
| Redis | Idempotency storage |
| RabbitMQ | Message broker |
| Docker Compose | Local environment |
| Nginx | HTTP server |
| PHPUnit | Integration testing |

---

## Architecture Overview

The system follows asynchronous event-driven architecture.

```text
Client
   |
   v
REST API (Laravel)
   |
   v
PostgreSQL + Redis
   |
   v
RabbitMQ Exchange
   |
   +--> High Priority Queue
   |
   +--> Default Queue
   |
   v
Consumer Workers
   |
   v
Notification Providers
   |
   +--> SMS Provider
   |
   +--> Email Provider
```

---

## Main Features

### Notification API

Supports asynchronous notification creation:

- SMS notifications
- Email notifications
- Multiple recipients
- Priority-based routing

---

### RabbitMQ Queues

The service uses two queues:

| Queue | Purpose |
|---|---|
| notifications.high | transactional/high-priority notifications |
| notifications.default | marketing/default notifications |

Routing is handled through a direct exchange.

---

### Idempotency Support

The API supports idempotent requests using:

```http
Idempotency-Key
```

header.

Repeated requests with the same key return the same notification instead of creating duplicates.

Redis is used as fast idempotency storage.

---

### Retry Mechanism

Temporary provider failures trigger automatic retries.

Supported flow:

```text
queued
  ↓
temporary failure
  ↓
retry
  ↓
retry
  ↓
dropped
```

All delivery attempts are stored in database.

---

### Delivery Tracking

Each recipient contains delivery status:

| Status | Description |
|---|---|
| queued | waiting for processing |
| sent | successfully delivered |
| dropped | permanently failed |

Delivery history is available through API.

---

## Local Development

### 1. Clone repository

```bash
git clone <repository-url>
cd notification-service
```

---

### 2. Copy environment file

```bash
cp .env.example .env
```

---

### 3. Start Docker environment

```bash
docker compose up -d --build
```

---

### 4. Install dependencies

```bash
docker compose exec app composer install
```

---

### 5. Generate application key

```bash
docker compose exec app php artisan key:generate
```

---

### 6. Run migrations

```bash
docker compose exec app php artisan migrate
```

---

### 7. Setup RabbitMQ exchange and queues

```bash
docker compose exec app php artisan rabbitmq:setup
```

---

## Available Services

### Application

```text
http://localhost:8080
```

---

### Health Check

```http
GET /api/health
```

Example response:

```json
{
    "status": "ok",
    "service": "notification-service"
}
```

---

### RabbitMQ Management UI

```text
http://localhost:15673
```

Credentials:

```text
Username: notification_user
Password: notification_password
```

---

### PostgreSQL

```text
Host: localhost
Port: 5433
Database: notification_service
Username: notification_user
Password: notification_password
```

---

### Redis

```text
Host: localhost
Port: 6380
```

---

## API Examples

### Create Notification

```http
POST /api/notifications
```

Headers:

```http
Content-Type: application/json
Accept: application/json
Idempotency-Key: notification-001
```

Payload:

```json
{
    "channel": "sms",
    "priority": "transactional",
    "message": "Your verification code is 123456",
    "recipients": [101, 102, 103]
}
```

Example response:

```json
{
    "data": {
        "id": 1,
        "channel": "sms",
        "priority": "transactional",
        "message": "Your verification code is 123456",
        "recipients_count": 3
    }
}
```

---

### Subscriber Notification History

```http
GET /api/subscribers/{subscriberId}/notifications
```

Supported filters:

| Query Param | Description |
|---|---|
| status | queued/sent/dropped |
| channel | sms/email |
| page | pagination page |
| per_page | items per page |

Example:

```http
GET /api/subscribers/101/notifications?status=sent
```

---

## Queue Consumer

Start worker:

```bash
docker compose exec app php artisan notifications:consume
```

Process limited amount of messages:

```bash
docker compose exec app php artisan notifications:consume --limit=10
```

Process messages once and stop:

```bash
docker compose exec app php artisan notifications:consume --once
```

---

## Testing

Run all tests:

```bash
docker compose exec app php artisan test
```

Run notification tests only:

```bash
docker compose exec app php artisan test --filter=Notification
```

Current test coverage includes:

- notification creation;
- validation;
- idempotency;
- queue publishing;
- queue processing;
- retry handling;
- dropped flow;
- history API.

---

## Project Structure

```text
app/
├── Console/
├── Domain/
│   ├── Notification/
│   └── Subscriber/
├── Http/
├── Infrastructure/
├── Models/
└── Providers/

tests/
├── Feature/
└── Unit/
```

---

## Reliability Guarantees

The service currently guarantees:

- at-least-once queue delivery;
- idempotent API requests;
- retry handling for temporary failures;
- persistent delivery attempt history;
- transactional database operations.

---

## Future Improvements

Potential production improvements:

- dead-letter queues;
- delayed retries;
- real SMS/email providers;
- Prometheus metrics;
- OpenTelemetry tracing;
- rate limiting;
- supervisor-based worker management;
- Kubernetes deployment;
- OpenAPI/Swagger documentation.

---

## Postman Collection

The project includes a ready-to-use Postman collection for manual API testing.

```text
docs/api/postman/notification-service.postman_collection.json
```

### How to import

1. Open Postman.
2. Click `Import`.
3. Select `Files`.
4. Choose:

```text
docs/api/postman/notification-service.postman_collection.json
```

The collection uses the following base URL variable:

```text
base_url = http://localhost:8080
```

Available requests:

- Health Check
- Create SMS Transactional Notification
- Create Email Marketing Notification
- Create Retry Test Notification
- Create Invalid Notification Payload
- Subscriber Notification History
- Subscriber History Filtered By Status And Channel

---

## Author

Developed as backend engineering test assignment.
