# Урок 8.3 — Практика (проверенный гайд для демонстрации)

Этот гайд проверен на текущем проекте LaravelExample и заточен под демонстрацию студентам без сбоев.

Цель практики:
- показать поток логов в Docker;
- показать структурированный лог ошибки платежа;
- показать `X-Correlation-ID` в запросе, ответе и логе;
- пройти mini incident-flow на базе логов.

## 0) Добавление Context в проект (перед практикой)

`Context` в Laravel — фасад для хранения контекста текущего выполнения (request / job / command), чтобы единообразно прокидывать метаданные в логи и error tracking.

Что добавлено в проект:
- в `AssignCorrelationId` теперь используется `Illuminate\Support\Facades\Context`;
- в `Context` пишутся базовые поля запроса: `correlation_id`, `endpoint`, `request_method`, `request_path`, `user_id`;
- `Log::shareContext(...)` получает данные из `Context::all()`;
- `PaymentErrorReporter` объединяет доменные поля ошибки с `Context::all()` и передает их в лог/Sentry.

Зачем это нужно:
- один источник правды для метаданных диагностики;
- меньше дублирования (`correlation_id` не нужно вычислять в каждом сервисе);
- проще связывать API-ответ, логи и Sentry-события.

Где хранятся данные `Context`:
- это in-memory хранилище в рамках текущего процесса выполнения;
- для HTTP-запроса контекст живет в памяти текущего запроса;
- для queue/job контекст может быть сериализован/восстановлен механизмом Laravel, если передавать его через pipeline выполнения задач.

Базовые возможности:
- `Context::add('correlation_id', $id)` — добавить значение;
- `Context::get('correlation_id')` — получить значение;
- `Context::all()` — получить весь контекст одним массивом.

### 0.1 До внедрения Context (кейс с ошибкой)

Без `Context` типовой поток выглядит так:
- middleware выставляет только header и частичный log-context;
- сервисы отдельно достают `X-Correlation-ID` из запроса;
- при росте числа полей (`tenant_id`, `request_id`, `user_id`, `endpoint`) растет дублирование.

Пример:
- в контроллере берется `X-Correlation-ID` из `Request`;
- в `Log::error(...)` вручную передается набор полей;
- в Sentry `extra` добавляются поля отдельными вызовами.

### 0.2 После внедрения Context (текущий проект)

Теперь поток централизован:
- middleware один раз наполняет `Context`;
- контроллер/сервисы читают `Context::get(...)` при необходимости;
- логирование ошибок автоматически получает общий контекст через `Context::all()`.

Практический эффект:
- ошибки платежей содержат и доменные поля (`payment_id`, `gateway_code`), и request-метаданные (`correlation_id`, endpoint);
- меньше риска потерять диагностические поля при новых точках логирования;
- проще эволюционировать observability-подход без широких рефакторингов.

## 1) Что важно знать до старта

В проекте по умолчанию в `.env`:
- `LOG_CHANNEL=stack`
- `LOG_STACK=single,sentry_logs`

То есть Laravel пишет в файл (`storage/logs/laravel.log`) + Sentry logs, а не в stdout контейнера.

Для урока 8.3, чтобы логи были видны через `docker compose logs`, временно переключаем канал на `stderr`.

## 2) Поднять и проверить окружение

```bash
docker compose up -d
docker compose ps
docker compose exec php php artisan test
```

Ожидаемо:
- все сервисы `Up` (`php`, `mysql`, `redis`, `queue-worker`, `adminer`);
- тесты проходят (в текущем состоянии: `26 passed`).

## 3) Включить вывод Laravel-логов в stdout/stderr контейнера (для демо)

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

## 4) Демо 1 — Correlation ID в ответе API

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

## 5) Демо 2 — Структурированный лог ошибки в контейнерных логах

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

## 6) Демо 3 — Мини incident-flow (по мотивам слайда 13a)

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

## 7) Привязка к слайдам

- **Слайд 6a**: показываем, что приложение пишет в поток контейнера (`stderr`), откуда это легко забирает любой collector.
- **Слайд 9a**: разбираем структуру `message + контекст` на реальном логе.
- **Слайд 13a**: делаем мини-сценарий инцидента на серии одинаковых ошибок.

## 8) Частые проблемы и быстрое решение

1. **Нет логов в `docker compose logs php`**
   - проверьте, что включили `LOG_CHANNEL=stderr`;
   - после изменения `.env` обязательно `docker compose restart php`.

2. **`curl` не получает `X-Correlation-ID`**
   - проверьте middleware `AssignCorrelationId` в API-стеке;
   - убедитесь, что запрос идет на `http://localhost/api/v1/...`.

3. **Контейнеры не стартуют**
   - выполните `docker compose ps`;
   - при необходимости: `docker compose up -d --build`.

## 9) Возврат к исходной конфигурации после урока

```bash
docker compose exec php sh -lc 'mv .env.lesson83.bak .env'
docker compose restart php
```

Это вернет стандартный режим проекта (`stack` + `single,sentry_logs`).

## 10) Короткий чек-лист перед занятием

- `docker compose ps` — все сервисы `Up`;
- `docker compose exec php php artisan test` — зеленый прогон;
- `curl /api/v1/payments/demo-fail` возвращает `202` и `X-Correlation-ID`;
- `docker compose logs php` показывает `Payment processing failed...` с JSON-контекстом и нужным `correlation_id`.
