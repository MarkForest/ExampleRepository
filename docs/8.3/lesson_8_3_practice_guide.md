# Урок 8.3 — Практика (проверенный гайд для демонстрации)

Этот гайд проверен на текущем проекте LaravelExample и заточен под демонстрацию студентам без сбоев.

Цель практики:
- показать поток логов в Docker;
- показать структурированный лог ошибки платежа;
- показать `X-Correlation-ID` в запросе, ответе и логе;
- пройти mini incident-flow на базе логов.

## 0) Что важно знать до старта

В проекте по умолчанию в `.env`:
- `LOG_CHANNEL=stack`
- `LOG_STACK=single,sentry_logs`

То есть Laravel пишет в файл (`storage/logs/laravel.log`) + Sentry logs, а не в stdout контейнера.

Для урока 8.3, чтобы логи были видны через `docker compose logs`, временно переключаем канал на `stderr`.

## 1) Поднять и проверить окружение

```bash
docker compose up -d
docker compose ps
docker compose exec php php artisan test
```

Ожидаемо:
- все сервисы `Up` (`php`, `mysql`, `redis`, `queue-worker`, `adminer`);
- тесты проходят (в текущем состоянии: `26 passed`).

## 2) Включить вывод Laravel-логов в stdout/stderr контейнера (для демо)

### 2.1. Сделать backup `.env`

```bash
docker compose exec php sh -lc 'cp .env .env.lesson83.bak'
```

### 2.2. Временно переключить логирование

```bash
docker compose exec php sh -lc "sed -i 's/^LOG_CHANNEL=.*/LOG_CHANNEL=stderr/' .env && sed -i 's/^LOG_STACK=.*/LOG_STACK=stderr,sentry_logs/' .env"
docker compose restart php
```

После этого ошибки приложения будут видны в:

```bash
docker compose logs -f php
```

## 3) Демо 1 — Correlation ID в ответе API

Отправляем тестовый запрос с заранее заданным `X-Correlation-ID`:

```bash
curl -i -X POST http://localhost/api/v1/payments/demo-fail \
  -H "Accept: application/json" \
  -H "X-Correlation-ID: lesson83-demo-001"
```

Проверяем в ответе:
- статус `202 Accepted`;
- заголовок `X-Correlation-ID: lesson83-demo-001`;
- JSON с `correlation_id: lesson83-demo-001`.

## 4) Демо 2 — Структурированный лог ошибки в контейнерных логах

Сразу после запроса выше:

```bash
docker compose logs --since=2m php
```

Ожидаем строку вида:

```text
[2026-..] local.ERROR: Payment processing failed at gateway level {"correlation_id":"lesson83-demo-001","payment_id":999999,"user_id":0,"gateway_code":"DEMO_FAIL"}
```

Что это доказывает для урока:
- есть структурированный JSON-контекст;
- `correlation_id` сквозной (из запроса в лог);
- событие легко искать и фильтровать в лог-агрегации.

## 5) Демо 3 — Мини incident-flow (по мотивам слайда 13a)

Сгенерировать серию ошибок с разными correlation id:

```bash
for i in 1 2 3; do
  curl -s -o /dev/null -X POST http://localhost/api/v1/payments/demo-fail \
    -H "Accept: application/json" \
    -H "X-Correlation-ID: incident-lesson83-00${i}";
done
```

Проверка «масштаба» в логах:

```bash
docker compose logs --since=5m php
```

На разборе со студентами:
- подтверждаем всплеск `ERROR`;
- берем один `correlation_id` и показываем трассировку кейса;
- обсуждаем временные действия (rollback/ограничение функции/коммуникация);
- фиксируем, какие поля обязательны для post-mortem.

## 6) Привязка к слайдам

- **Слайд 6a**: показываем, что приложение пишет в поток контейнера (`stderr`), откуда это легко забирает любой collector.
- **Слайд 9a**: разбираем структуру `message + контекст` на реальном логе.
- **Слайд 13a**: делаем мини-сценарий инцидента на серии одинаковых ошибок.

## 7) Частые проблемы и быстрое решение

1. **Нет логов в `docker compose logs php`**
   - проверьте, что включили `LOG_CHANNEL=stderr`;
   - после изменения `.env` обязательно `docker compose restart php`.

2. **`curl` не получает `X-Correlation-ID`**
   - проверьте middleware `AssignCorrelationId` в API-стеке;
   - убедитесь, что запрос идет на `http://localhost/api/v1/...`.

3. **Контейнеры не стартуют**
   - выполните `docker compose ps`;
   - при необходимости: `docker compose up -d --build`.

## 8) Возврат к исходной конфигурации после урока

```bash
docker compose exec php sh -lc 'mv .env.lesson83.bak .env'
docker compose restart php
```

Это вернет стандартный режим проекта (`stack` + `single,sentry_logs`).

## 9) Короткий чек-лист перед занятием

- `docker compose ps` — все сервисы `Up`;
- `docker compose exec php php artisan test` — зеленый прогон;
- `curl /api/v1/payments/demo-fail` возвращает `202` и `X-Correlation-ID`;
- `docker compose logs php` показывает `Payment processing failed...` с JSON-контекстом и нужным `correlation_id`.
