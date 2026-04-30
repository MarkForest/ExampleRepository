# Lesson 8.2 Demo Runbook (Slides + Live Practice)

Этот файл — единая инструкция для выступления.  
Работаешь только с двумя окнами: слайды и этот runbook.

## 1) Подготовка перед митом

1. Открой слайды: `docs/8.2/lesson_8_2_slides.md`.
2. Открой этот файл: `docs/8.2/lesson_8_2_demo_runbook.md`.
3. Проверь, что контейнеры запущены:
  - `docker compose ps`
4. Если что-то не запущено:
  - `docker compose up -d`
5. Проверка API роутов урока:
  - `docker compose exec -T php php artisan route:list --path=api/v1`

Ожидаемые роуты:

- `GET|POST /api/v1/payments`
- `GET|DELETE /api/v1/payments/{payment}`
- `GET|POST /api/v1/accounts`
- `GET|DELETE /api/v1/accounts/{account}`

## 2) Базовый сценарий переключения

Для каждого практического слайда:

1. Показываешь идею на слайде (30-60 сек).
2. Переходишь в терминал и выполняешь команды из соответствующего блока ниже.
3. Коротко фиксируешь результат.
4. Возвращаешься к слайдам.

## 3) Практика для слайда 8a (интеграция Sentry)

Цель блока: показать, как Sentry подключается в Laravel и где это живет в окружении Docker.

1. Озвучить, что пакет ставится в контейнере приложения:
  - `docker compose exec -T php composer require sentry/sentry-laravel`
2. Показать переменные окружения (через `.env` в проекте):
  - `SENTRY_LARAVEL_DSN=...`
  - `SENTRY_TRACES_SAMPLE_RATE=0.0`
  - `SENTRY_PROFILES_SAMPLE_RATE=0.0`
3. Пояснить, что в этом уроке сторонние сервисы локально не поднимаем: отправка идет в SaaS Sentry по DSN.
4. Сказать, что в local можно отключать отправку ошибок, а в staging/production включать.

### Быстрый Sentry UI flow (30 секунд)

1. Открыть `sentry.io` → нужная Organization → нужный Project.
2. Перейти в `Issues` (или `Explore -> Errors`).
3. Выставить фильтры:
  - `Environment: All` (или `local`);
  - `Time range: Last 24 hours`;
  - `Status: All`.
4. Быстро отправить тестовое событие из контейнера:
  - `docker compose exec -T php php artisan sentry:test`
5. Найти событие:
  - либо по тексту `This is a test exception sent from the Sentry Laravel SDK`;
  - либо по event id из вывода команды `sentry:test`.
6. Показать карточку issue:
  - stack trace;
  - environment;
  - breadcrumbs/tags/extra.

### Anti-fail чек перед live-демо

1. Очистить кеш конфига:
  - `docker compose exec -T php php artisan config:clear`
2. Проверить, что DSN подхватился:
  - `docker compose exec -T php php artisan config:show sentry`
3. Вызвать тестовый endpoint:
  - `curl -i http://localhost/api/v1/sentry-test`
4. Если в UI не видно:
  - снять фильтры в Sentry;
  - проверить, что открыт правильный project;
  - проверить `storage/logs/laravel.log` в контейнере.

Фраза перехода обратно к слайдам:  
"Интеграция базово простая; ценность начинается, когда мы правильно добавляем контекст и фильтрацию."

## 4) Практика для слайда 11a (контекст ошибок)

Цель блока: показать, что без контекста ошибка почти бесполезна.

1. Проговорить минимальный полезный контекст:
  - `user_id`, `payment_id`, `account_id`, `gateway_code`, `correlation_id`, `endpoint`.
2. Проговорить разделение:
  - `tags` — для фильтрации.
  - `extra` — для детализации.
3. Подчеркнуть правило безопасности:
  - не кладем в Sentry PII/секреты/полные платежные данные.

Фраза перехода обратно к слайдам:  
"Дальше посмотрим, как этот контекст помогает анализировать issue и искать регрессии."

## 5) Практика для слайда 14a (анализ issue)

Цель блока: научить читать issue как источник решений.

Порядок разбора:

1. Частота (`events`) за период.
2. Сколько пользователей затронуто (`affected users`).
3. Первый/последний event.
4. `release`/`environment`.
5. `tags` и `extra` для локализации причины.

Что озвучить:

- если после нового релиза резко растет ошибка в `payments`, это кандидат на регрессию;
- `Sentry` дает "что и где ломается", а логи — "как именно ломается".

## 6) Практика для слайда 19a (инцидент после релиза)

Цель блока: показать управляемый процесс реакции.

Сценарий:

1. Видим всплеск ошибок по платежам в Sentry.
2. Сверяем `release`, `endpoint`, `affected users`.
3. Проверяем логи по `correlation_id`.
4. Решаем: `hotfix` или `rollback`.
5. После стабилизации — короткий post-mortem.

Фраза перехода обратно к слайдам:  
"Sentry не заменяет логи, он ускоряет обнаружение и приоритизацию."

## 7) Блок API accounts (по аналогии с payments)

На уроке можно отдельно показать, что `accounts` сделаны тем же API-паттерном:

- Controller: `app/Http/Controllers/Api/V1/AccountController.php`
- Service: `app/Services/Api/V1/AccountService.php`
- Repository: `app/Repositories/AccountRepository.php`
- DTO: `app/DTO/Api/V1/CreateAccountDTO.php`
- FormRequest: `app/Http/Requests/Api/V1/Account/AccountStoreRequest.php`
- Tests: `tests/Feature/Api/V1/*Account*`

Проверка роутов:

- `docker compose exec -T php php artisan route:list --path=api/v1/accounts`

Проверка ручкой (быстрый live smoke):

1. Создать аккаунт:
  - `curl -s -X POST http://localhost/api/v1/accounts -H "Content-Type: application/json" -d '{"balance":"1200.00"}'`
2. Получить список:
  - `curl -s http://localhost/api/v1/accounts`
3. Получить один аккаунт:
  - `curl -s http://localhost/api/v1/accounts/{id}`
4. Удалить аккаунт:
  - `curl -i -X DELETE http://localhost/api/v1/accounts/{id}`

## 8) Тесты урока на SQLite (обязательно показать)

В проекте для тестов уже настроен SQLite in-memory (`phpunit.xml`):

- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`

Команда прогона API тестов:

- `docker compose exec -T php php artisan test tests/Feature/Api/V1`

Что зафиксировать вслух:

- тесты гоняются в контейнере;
- база для тестов — SQLite;
- `accounts` и `payments` проходят feature-покрытие по основным endpoint-ам.

## 9) Проверка логов через Docker-контейнер

В рамках этого урока логи проверяем только через контейнер, без внешних систем.

Варианты:

1. Laravel лог файл:
  - `docker compose exec -T php sh -lc 'tail -n 100 storage/logs/laravel.log'`
2. Если запущен `pail` внутри контейнера:
  - `docker compose exec -T php php artisan pail --timeout=10`

Что проговорить:

- по `correlation_id` или `payment_id` связываем событие из Sentry с логами приложения.

## 10) Финальный чек-лист перед завершением урока

- Показал связку: логи + Sentry.
- Показал, какие ошибки отправляем, а какие нет.
- Показал ценность контекста (`tags/extra`).
- Показал сценарий инцидента и принятие решений.
- Показал API `accounts` по аналогии с `payments`.
- Запустил тесты API в Docker на SQLite.
- Показал чтение логов из контейнера.

Если нужно быстро повторить демо:

1. `docker compose ps`
2. `docker compose exec -T php php artisan route:list --path=api/v1`
3. `docker compose exec -T php php artisan test tests/Feature/Api/V1`

