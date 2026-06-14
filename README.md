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

## Тестирование

### 1. Подготовка тестового окружения (один раз после клонирования)

Пользователь `user` в `docker-compose.yml` не имеет права `CREATE DATABASE`, поэтому тестовую БД нужно создать вручную через `root`:

```bash
docker compose exec mysql mysql -uroot -proot \
  -e 'CREATE DATABASE IF NOT EXISTS `wallet-rates_test`; \
      GRANT ALL PRIVILEGES ON `wallet-rates_test`.* TO "user"@"%"; \
      FLUSH PRIVILEGES;'
```

Затем — миграции и фикстуры для тестового окружения:

```bash
docker compose exec php bin/console --env=test doctrine:migrations:migrate -n
docker compose exec php bin/console --env=test doctrine:fixtures:load -n
```

### 2. Запуск всех тестов

```bash
docker compose exec php vendor/bin/phpunit
```

### 3. Запуск конкретного файла

```bash
docker compose exec php vendor/bin/phpunit tests/Controller/RateControllerTest.php
docker compose exec php vendor/bin/phpunit tests/Security/SignatureBuilderTest.php
```

### 4. Запуск одного метода (по имени)

```bash
docker compose exec php vendor/bin/phpunit --filter testListReturnsActiveRates
docker compose exec php vendor/bin/phpunit tests/Controller/RateControllerTest.php --filter testGetOneReturns404
```

### 5. Полезные флаги

```bash
# human-readable вывод (testdox)
docker compose exec php vendor/bin/phpunit --testdox

# остановиться на первой ошибке
docker compose exec php vendor/bin/phpunit --stop-on-failure

# указать конфигурацию явно (например, в CI)
docker compose exec php vendor/bin/phpunit -c phpunit.dist.xml
```

## API

Все эндпоинты API (кроме `/api/doc*`) требуют авторизацию по двум заголовкам:

| Заголовок | Описание |
|---|---|
| `X-API-Key` | Публичный идентификатор мерчанта (`apiKey`). |
| `X-API-Signature` | `hex(HMAC-SHA512)` от канонической строки запроса, ключ — `apiSecret` мерчанта. |

Базовый URL: `http://localhost:8080/api/v1`.

### Эндпоинты

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/api/v1/rates` | Список всех активных курсов (без дублей по провайдеру). |
| `GET` | `/api/v1/rates/{currency}` | Активный курс пары `merchant.baseCurrency → currency`. Регистр валюты не важен. |

Коды ответов:
- `200` — успех (тело — массив или объект `RateResponse`).
- `401` — `{"error":"unauthorized","message":"..."}` (нет/неверный ключ или подпись, мерчант неактивен).
- `404` — `{"error":"not_found","message":"..."}` (нет активного курса для пары).

### Swagger UI / OpenAPI

- UI: `http://localhost:8080/api/doc`
- JSON-схема: `http://localhost:8080/api/doc.json`

Документация сгенерирована через `nelmio/api-doc-bundle` и автоматически отражает контроллеры, атрибуты OpenAPI и DTO.

### Формирование подписи (X-API-Signature)

Каноническая строка собирается по шаблону:

```
METHOD\nPATH\nCANONICAL_QUERY\nSHA256_HEX(BODY)
```

Где:
- `METHOD` — HTTP-метод в верхнем регистре (`GET`, `POST`, ...).
- `PATH` — `$request->getPathInfo()` (например, `/api/v1/rates/EUR`).
- `CANONICAL_QUERY` — query-параметры, отсортированные по ключу, значения URL-кодируются через `rawurlencode`, объединяются через `&`. Пустая строка, если query нет.
- `SHA256_HEX(BODY)` — `hash('sha256', $rawBody)`; для пустого тела это константа `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`.

Финальная подпись:

```
X-API-Signature = hash_hmac('sha512', canonical_string, apiSecret)  // hex, lowercase
```

#### Пример: `GET /api/v1/rates/EUR` для мерчанта Acme Payments

Bash + openssl:
```bash
SECRET='sk_acme_dev_secret_8a1b6f2c4e9d3a7b'   # apiSecret мерчанта
API_KEY='mk_acme_dev_key_0001'                 # apiKey мерчанта
METHOD='GET'
PATH_='/api/v1/rates/EUR'                      # без query, без тела
QUERY=''
BODY=''

# sha256 от пустого тела
BODY_HASH=$(printf '%s' "$BODY" | openssl dgst -sha256 -hex | awk '{print $NF}')

# каноническая строка (4 строки, разделены \n)
CANONICAL=$(printf '%s\n%s\n%s\n%s' "$METHOD" "$PATH_" "$QUERY" "$BODY_HASH")

# HMAC-SHA512 → hex (lowercase)
SIGNATURE=$(printf '%s' "$CANONICAL" | openssl dgst -sha512 -hmac "$SECRET" -hex | awk '{print $NF}')

curl -i "http://localhost:8080${PATH_}" \
  -H "X-API-Key: $API_KEY" \
  -H "X-API-Signature: $SIGNATURE"
```

PHP:
```php
$method   = 'GET';
$path     = '/api/v1/rates/EUR';
$query    = '';                  // или ksort($params) + rawurlencode
$body     = '';
$bodyHash = hash('sha256', $body);
$canonical = "$method\n$path\n$query\n$bodyHash";
$signature = hash_hmac('sha512', $canonical, $apiSecret);
```

Python:
```python
import hashlib, hmac, requests

secret  = "sk_acme_dev_secret_8a1b6f2c4e9d3a7b"
api_key = "mk_acme_dev_key_0001"
method  = "GET"
path    = "/api/v1/rates/EUR"
query   = ""
body    = ""

body_hash = hashlib.sha256(body.encode()).hexdigest()
canonical = "\n".join([method, path, query, body_hash])
signature = hmac.new(secret.encode(), canonical.encode(), hashlib.sha512).hexdigest()

r = requests.get(
    f"http://localhost:8080{path}",
    headers={"X-API-Key": api_key, "X-API-Signature": signature},
)
```

#### Пример с query-параметрами: `GET /api/v1/rates?provider=binance&pair=BTC%2FUSDT`

```bash
SECRET='sk_acme_dev_secret_8a1b6f2c4e9d3a7b'
API_KEY='mk_acme_dev_key_0001'
METHOD='GET'
PATH_='/api/v1/rates'
QUERY_RAW='pair=BTC%2FUSDT&provider=binance'   # порядок не важен — на стороне сервера сортируется

BODY_HASH=$(printf '' | openssl dgst -sha256 -hex | awk '{print $NF}')
CANONICAL=$(printf '%s\n%s\n%s\n%s' "$METHOD" "$PATH_" "$QUERY_RAW" "$BODY_HASH")
SIGNATURE=$(printf '%s' "$CANONICAL" | openssl dgst -sha512 -hmac "$SECRET" -hex | awk '{print $NF}')

curl -i "http://localhost:8080${PATH_}?${QUERY_RAW}" \
  -H "X-API-Key: $API_KEY" \
  -H "X-API-Signature: $SIGNATURE"
```

> ⚠️ `CANONICAL_QUERY` строится на стороне сервера из сырого query-string запроса: значения декодируются через `parse_str`, затем сортируются по ключу и повторно URL-кодируются через `rawurlencode`. Поэтому в подписи порядок параметров и формат кодирования не критичны — главное, чтобы значения совпадали.

**Создать нового мерчанта (генерация apiKey + apiSecret)**
```bash
# Через аргументы
docker compose exec php bin/console app:merchant:create "Acme Payments" USD

# Интерактивно (спросит имя и базовую валюту, по умолчанию USD)
docker compose exec php bin/console app:merchant:create

# Справка по аргументам
docker compose exec php bin/console help app:merchant:create
```
> Команда генерирует:
> - `apiKey` — `mk_` + 32 hex-символа
> - `apiSecret` — 64 hex-символа (32 случайных байта), готов для `hash_hmac('sha512', $payload, $secret)`
>
> **API secret показывается только один раз** при создании — сохраните его сразу. Валидация: `name` ≤ 128 символов, `baseCurrency` ≤ 16 символов (нормализуется в uppercase).

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
