# Rates

Краткое описание проекта (TBD).

## Requirements

- Docker 20+
- Docker Compose v2
- Свободные порты `8080`, `3306`, `6379` (проброшены на хост для удобства)

## Запуск через Docker

```bash
docker compose up -d --build
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate -n
```

Приложение будет доступно на [http://localhost:8080](http://localhost:8080).

## Полезные команды

**Проверить состояние сервисов**
```bash
docker compose ps
```

**Логи (все / конкретный сервис)**
```bash
docker compose logs -f
```

```bash
docker compose logs -f php
```

```bash
docker compose logs -f nginx
```

```bash
docker compose logs -f mysql
```

**Зайти в контейнер PHP**
```bash
docker compose exec php bash
```

**Очистить кэш Symfony**
```bash
docker compose exec php bin/console cache:clear
```

**Запустить миграции**
```bash
docker compose exec php bin/console doctrine:migrations:migrate -n
```

**Запустить тесты**
```bash
docker compose exec php vendor/bin/phpunit
```

**Установить/обновить зависимости**
```bash
docker compose exec php composer install
```

```bash
docker compose exec php composer update
```

**Остановить и убрать контейнеры** (с вольюмом — удалит данные MySQL)
```bash
docker compose down
```

```bash
docker compose down -v
```

## Сервисы и порты

| Сервис | Порт (хост→контейнер) | Назначение |
|---|---|---|
| nginx | 8080→80 | Web-сервер |
| php | — | PHP-FPM (внутренний) |
| mysql | 3306→3306 | БД (`symfony` / `symfony` / `symfony`) |
| redis | 6379→6379 | Кэш/мессенджер |

## Переменные окружения

Параметры хранятся в `.env` / `.env.dev`. БД и Redis уже сконфигурированы под имена сервисов `mysql` и `redis` из `docker-compose.yml`.
