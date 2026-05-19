# Notification Service

Notification Service is a Laravel-based microservice for asynchronous SMS and Email notification delivery.

The project demonstrates:

- asynchronous message processing;
- priority-based notification delivery;
- PostgreSQL persistence;
- Redis-based idempotency support;
- RabbitMQ message broker integration;
- Docker-based local environment;
- integration-test-ready architecture.

## Tech Stack

- PHP 8.3
- Laravel
- PostgreSQL
- Redis
- RabbitMQ
- Nginx
- Docker Compose

## Local Development

### 1. Clone repository

```bash
git clone <repository-url>
cd notification-service
```

### 2. Copy environment file

```bash
cp .env.example .env
```

### 3. Start Docker environment

```bash
docker compose up -d --build
```

### 4. Install dependencies

```bash
docker compose exec app composer install
```

### 5. Generate application key

```bash
docker compose exec app php artisan key:generate
```

### 6. Run migrations

```bash
docker compose exec app php artisan migrate
```

## Available Services

### Application

```text
http://localhost:8080
```

### Health Check

```text
GET http://localhost:8080/api/health
```

Expected response:

```json
{
    "status": "ok",
    "service": "notification-service"
}
```

### RabbitMQ Management UI

```text
http://localhost:15673
```

Credentials:

```text
Username: notification_user
Password: notification_password
```

### PostgreSQL

```text
Host: localhost
Port: 5433
Database: notification_service
Username: notification_user
Password: notification_password
```

### Redis

```text
Host: localhost
Port: 6380
```

## Docker Services

The project starts the following services:

| Service | Description |
|---|---|
| app | PHP-FPM application container |
| nginx | HTTP server |
| postgres | PostgreSQL database |
| redis | Redis in-memory storage |
| rabbitmq | RabbitMQ broker with management UI |

## Current Development Branch

```text
feature/project-foundation
```

This branch contains the base Laravel and Docker environment setup.
