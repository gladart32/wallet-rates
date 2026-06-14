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
docker compose exec php bin/console doctrine:fixtures:load -n
```

Либо миграции и сидинг одной командой (после `composer install`):

```bash
docker compose exec php composer db:setup
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

**Заполнить БД тестовыми данными (фикстуры)**
```bash
docker compose exec php bin/console doctrine:fixtures:load -n
```
> ⚠️ Команда **очищает таблицы** (`merchant`, `provider`, `rate`) и вставляет тестовые данные заново. Запускать только в dev-окружении.
>
> Что загружается:
> - 3 мерчанта: `Acme Payments` (USD), `EuroGateway` (EUR), `LegacyLtd` (GBP, disabled)
> - 5 провайдеров: `binance`, `coinbase`, `kraken`, `bybit` (active), `okx` (disabled)
> - 100 курсов: 25 валютных пар × 4 активных провайдера, с реалистичным разбросом ±2%
>
> Чтобы добавить новые данные, не удаляя существующие:
> ```bash
> docker compose exec php bin/console doctrine:fixtures:load -n --append
> ```

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
| mysql | 3306→3306 | БД (`wallet-rates` / `user` / `123456`) |
| redis | 6379→6379 | Кэш/мессенджер |

## Переменные окружения

Параметры хранятся в `.env` / `.env.dev`. БД и Redis уже сконфигурированы под имена сервисов `mysql` и `redis` из `docker-compose.yml`.
