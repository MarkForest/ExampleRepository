# Домашнє завдання. Модуль 8, Урок 2

**Тема:** Error Tracking та аналіз помилок: Sentry і продакшн-практики  

**Контекст:** У Уроці 8.1 ти підготував CRM до логування: карта подій, Correlation ID, структуровані логи в TransferService та InvoiceService, LOGGING_GUIDE та INCIDENT_RUNBOOK. PM поставив завдання: **інтегрувати Sentry** для error tracking - автоматично відловлювати неочікувані exceptions, додавати фінансовий контекст (transfer_id, invoice_id, account_id), фільтрувати очікувані бізнес-відмови, налаштувати середовища та release. У фінансовій CRM падіння переказу або створення invoice без error tracking означає, що команда дізнається про проблему від користувачів. Sentry дає сигнал і дані для швидкого розслідування. Це підготовка до лог-агрегації (Урок 8.3).



## Мета

Перевірити й закріпити: інтеграцію Sentry у Laravel, налаштування DSN та середовищ, виключення певних типов помилок, додавання контексту (tags, extra, user), captureException з фінансовим контекстом для transfers і invoices, аналіз issues у Sentry, пріоритизацію виправлень, зв’язок Sentry і логів при інцидентах.



## Передумова

Проєкт `crm-laravel` з Module 8.1: TransferService, InvoiceService з логуванням, middleware AddCorrelationId, Exception Handler для InsufficientBalanceException та SameAccountTransferException. Логи містять correlation_id. Sentry ще не інтегровано.



## Завдання 1. Інтеграція Sentry у Laravel

**Опис:** Встанови та налаштуй Sentry для автоматичного перехоплення необроблених exceptions.

**Бізнес-контекст:** Без error tracking команда дізнається про збої випадково або від підтримки; немає статистики, групування та алертів. Sentry дає один інтерфейс для помилок і дозволяє реагувати до масових скарг.

**Що реалізувати:**
1. **Встанови пакет:**
   ```bash
   composer require sentry/sentry-laravel
   ```
2. **Опублікуй конфіг:**
   ```bash
   php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
   ```
3. **Додай у `.env` (для локальної перевірки використовуй тестовий DSN з sentry.io):**
   ```env
   SENTRY_LARAVEL_DSN=https://...@sentry.io/...
   SENTRY_TRACES_SAMPLE_RATE=0.0
   SENTRY_PROFILES_SAMPLE_RATE=0.0
   ```
4. **Налаштуй `config/sentry.php`:**
   - `environment` - з `APP_ENV`
   - `send_default_pii` - `false` (не передавати PII без потреби)
   - `before_send` (опційно) - callback для фільтрації; використати для виключення певних exceptions
5. **Виключи з відправки в Sentry:**
   - `Illuminate\Validation\ValidationException` (422 - валідація)
   - `Symfony\Component\HttpKernel\Exception\NotFoundHttpException` (404)
   - `App\Exceptions\InsufficientBalanceException` (контрольована бізнес-відмова)
   - `App\Exceptions\SameAccountTransferException` (контрольована бізнес-відмова)
6. **Опційно:** для `local` не відправляти події (перевірка через умову в `before_send` або `dsn` null для local)
7. **Тест:** створи ендпоінт `GET /api/v1/test-sentry` (тільки для non-production або за захистом), що викидає `throw new \RuntimeException('Test Sentry integration')`; перевір, що подія з’явилась у Sentry

**Критерії прийняття:**
- [ ] Sentry встановлено та налаштовано
- [ ] ValidationException, NotFoundHttpException, InsufficientBalanceException, SameAccountTransferException не потрапляють у Sentry
- [ ] Тестовий exception з’являється в Sentry (при налаштованому DSN)
- [ ] `APP_ENV` передається в Sentry як environment
- [ ] Документ `docs/ERROR_TRACKING.md` створено з описом налаштування



## Завдання 2. Middleware: Correlation ID у Sentry Scope

**Опис:** Додай correlation_id у scope Sentry для кожного API-запиту, щоб він автоматично потрапляв у події.

**Бізнес-контекст:** При інциденті «переказ не пройшов» у Sentry з’являється exception. Якщо в події є correlation_id, можна одразу перейти в лог-агрегацію і знайти всі логи цього запиту. Без correlation_id зв’язок Sentry ↔ логи втрачається.

**Що реалізувати:**
1. **Розшир middleware AddCorrelationId** (або створи окремий SentryContextMiddleware):
   - Після встановлення correlation_id у request викликай:
   ```php
   \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($correlationId): void {
       $scope->setTag('correlation_id', $correlationId);
       $scope->setExtra('correlation_id', $correlationId);
   });
   ```
2. **Додатково:** передай endpoint (method + path) у tags для фільтрації в Sentry:
   ```php
   $scope->setTag('endpoint', $request->method() . ' ' . $request->path());
   ```
3. **Якщо користувач авторизований** - `$scope->setUser(['id' => (string) $userId])` (Sentry може робити це автоматично через Laravel integration; переконайся, що user є в події)
4. **Порядок middleware:** AddCorrelationId має виконуватись перед обробкою запиту, щоб correlation_id був доступний і для логів, і для Sentry

**Критерії прийняття:**
- [ ] correlation_id потрапляє в Sentry scope для кожного API-запиту
- [ ] endpoint (method + path) додається в tags
- [ ] При будь-якому exception у цьому запиті в Sentry видно correlation_id та endpoint



## Завдання 3. TransferErrorReporter: відправка помилок з контекстом

**Опис:** Створи сервіс `TransferErrorReporter`, який логує й відправляє в Sentry неочікувані помилки при переказах з повним фінансовим контекстом.

**Бізнес-контекст:** У TransferService при неочікуваному exception (наприклад, помилка БД під час створення транзакцій) потрібно не лише залогувати, а й відправити в Sentry з контекстом: transfer_id, account_from_id, account_to_id, amount, correlation_id. Це дозволяє в Sentry одразу бачити, який переказ постраждав і за яким запитом.

**Що реалізувати:**
1. **Створи клас `App\Services\Monitoring\TransferErrorReporter`:**
   ```php
   <?php

   declare(strict_types=1);

   namespace App\Services\Monitoring;

   use Illuminate\Support\Facades\Log;
   use Throwable;

   final class TransferErrorReporter
   {
       public function report(
           Throwable $e,
           int $accountFromId,
           int $accountToId,
           string $amount,
           string $currency,
           ?int $transferId,
           ?string $correlationId
       ): void {
           // 1. Log
           Log::error('Transfer failed: unexpected error', [
               'account_from_id' => $accountFromId,
               'account_to_id' => $accountToId,
               'amount' => $amount,
               'transfer_id' => $transferId,
               'correlation_id' => $correlationId,
               'error_message' => $e->getMessage(),
           ]);

           // 2. Sentry з tags і extra
           \Sentry\withScope(function (\Sentry\State\Scope $scope) use (
               $e, $accountFromId, $accountToId, $amount, $currency, $transferId, $correlationId
           ): void {
               $scope->setTag('module', 'transfers');
               $scope->setTag('action', 'execute');
               $scope->setExtra('account_from_id', $accountFromId);
               $scope->setExtra('account_to_id', $accountToId);
               $scope->setExtra('amount', $amount);
               $scope->setExtra('currency', $currency);
               $scope->setExtra('transfer_id', $transferId);
               if ($correlationId !== null) {
                   $scope->setExtra('correlation_id', $correlationId);
               }
               \Sentry\captureException($e);
           });
       }
   }
   ```
2. **Інтегруй у TransferService:** у блоці `catch (Throwable $e)` після InsufficientBalanceException і SameAccountTransferException викликай `TransferErrorReporter::report()` з контекстом
3. **Не відправляй** InsufficientBalanceException і SameAccountTransferException в Sentry (вони вже виключені в before_send; при явному виклику report не передавай їх)
4. **Отримання correlation_id:** з request через `request()->header('X-Correlation-Id')` або з attributes middleware
5. **declare(strict_types=1)** і type hints

**Критерії прийняття:**
- [ ] Є клас TransferErrorReporter
- [ ] report() логує і відправляє exception у Sentry з tags (module, action) і extra (account_from_id, account_to_id, amount, transfer_id, correlation_id)
- [ ] TransferService використовує TransferErrorReporter при неочікуваних exceptions
- [ ] InsufficientBalanceException і SameAccountTransferException не потрапляють у Sentry



## Завдання 4. InvoiceErrorReporter: відправка помилок створення invoice

**Опис:** Створи сервіс `InvoiceErrorReporter` для відправки неочікуваних помилок при створенні рахунків-фактур у Sentry.

**Бізнес-контекст:** При exception під час createInvoice (помилка БД, неочікувана валідація на рівні репозиторію) потрібно відправити в Sentry з контекстом: invoice_id (якщо вже створено), client_id, total_amount, correlation_id. Це дозволяє швидко визначити масштаб (скільки клієнтів постраждали) та відтворити сценарій.

**Що реалізувати:**
1. **Створи клас `App\Services\Monitoring\InvoiceErrorReporter`:**
   - Метод `report(Throwable $e, int $clientId, ?int $invoiceId, ?string $totalAmount, ?string $correlationId): void`
   - Log::error + Sentry\captureException з контекстом
   - Tags: `module=invoices`, `action=create`
   - Extra: client_id, invoice_id, total_amount, correlation_id
2. **Інтегруй у InvoiceService:** у catch неочікуваних exceptions викликай InvoiceErrorReporter::report()
3. **Не відправляй** в Sentry очікувані помилки (ClientNotFoundException, ServiceNotFoundException тощо - якщо вони є як окремі типи); додай їх у before_send для виключення або не викликай report для них
4. **Доповни docs/ERROR_TRACKING.md** розділом «Контекст для transfers та invoices»

**Критерії прийняття:**
- [ ] Є клас InvoiceErrorReporter
- [ ] report() логує і відправляє exception у Sentry з фінансовим контекстом
- [ ] InvoiceService використовує InvoiceErrorReporter
- [ ] Документація оновлена



## Завдання 5. Прив’язка до Release

**Опис:** Налаштуй передачу release (версія/коміт) у Sentry для виявлення регресій.

**Бізнес-контекст:** Після деплою нового релізу помилки можуть з’явитися вперше - це регресія. Sentry показує, після якого release помилка почала з’являтися, що допомагає вирішити: rollback або hotfix. Без release незрозуміло, чи це старий баг, чи наслідок останнього деплою.

**Що реалізувати:**
1. **Додай у `.env`:**
   ```env
   SENTRY_RELEASE=1.0.0
   ```
   Або генеруй з git: `git rev-parse --short HEAD` (через deployment-скрипт)
2. **У `config/sentry.php`** встанови `release` з `env('SENTRY_RELEASE')` або `null`
3. **Laravel Sentry** підтримує опцію `release` у конфігу; переконайся, що вона передається
4. **Для локальної розробки** release може бути `local` або `dev`
5. **Доповни docs/ERROR_TRACKING.md** розділом «Release та регресії» - як інтерпретувати «First seen in release X»

**Критерії прийняття:**
- [ ] Release передається в Sentry
- [ ] У події в Sentry видно release
- [ ] Документація містить опис використання release для аналізу регресій



## Завдання 6. Таблиця «Що відправляти в Sentry» (CRM)

**Опис:** Створи чітку матрицю: які типи помилок відправляти в Sentry, а які ні.

**Бізнес-контекст:** PM хоче єдиний регламент, щоб Sentry не переповнювався «шумом» (422, 404, контрольовані відмови) і показував лише те, що потребує уваги розробника. Це частина ERROR_TRACKING.md.

**Що реалізувати:**
1. **Доповни docs/ERROR_TRACKING.md** розділом «Матриця помилок»
2. **Таблиця:**

   | Тип помилки | Відправляти в Sentry | Причина |
   |-|-||
   | ValidationException (422) | Ні | Очікувана валідація клієнта |
   | NotFoundHttpException (404) | Ні | Очікувана відмова |
   | InsufficientBalanceException | Ні | Контрольована бізнес-відмова |
   | SameAccountTransferException | Ні | Контрольована бізнес-відмова |
   | ClientNotFoundException (invoice) | Ні | Контрольована бізнес-відмова |
   | PDOException / QueryException | Так | Критичний збій БД |
   | RuntimeException в TransferService | Так | Неочікувана помилка |
   | RuntimeException в InvoiceService | Так | Неочікувана помилка |
   | Будь-який необроблений Throwable | Так | Потенційний баг |
   | Exception зовнішнього API (timeout, 5xx) | Так (якщо критично) | Інтеграційний збій |

3. **Правило:** Sentry - для неочікуваних і критичних помилок; контрольовані бізнес-відмови - тільки в логи
4. **Посилання на before_send** у конфігу для виключених типів

**Критерії прийняття:**
- [ ] Є таблиця «Матриця помилок»
- [ ] Усі типи з CRM покриті
- [ ] Є пояснення для кожного рішення
- [ ] Згадано before_send



## Завдання 7. Сценарій інциденту: «Після деплою перекази падають»

**Опис:** Опиши сценарій інциденту з використанням Sentry: після деплою нової версії масово з’являються помилки при створенні переказів.

**Бізнес-контекст:** PM хоче runbook, який починається з Sentry: як виглядає інцидент, як аналізувати issue, як прийняти рішення (rollback/hotfix), як пов’язати Sentry і логи. Це продовження INCIDENT_RUNBOOK з Уроку 8.1.

**Що реалізувати:**
1. **Онови docs/INCIDENT_RUNBOOK.md** (або створи окремий розділ) - «Інцидент: після деплою перекази падають»
2. **Опис сценарію:** Після деплою release 2.1.0 користувачі почали повідомляти про помилки при створенні переказів. API повертає 500. Алерт з Sentry: сплеск нового issue.
3. **Кроки команди:**
   - **1. Sentry:** відкрити issue, перевірити frequency, affected users, release (чи 2.1.0?)
   - **2. Контекст:** tags (module=transfers, action=execute), extra (account_from_id, amount, correlation_id)
   - **3. Логи:** по correlation_id з Sentry знайти всі логи цього запиту в лог-агрегації
   - **4. Причина:** stack trace + логи - що саме впало (БД, зміни в коді, зовнішній API)
   - **5. Рішення:** rollback release 2.1.0 або hotfix, деплой, перевірка
   - **6. Post-mortem:** чому сталося, як уникнути (тести, код-ревʼю)
4. **Чеклист для on-call:** що перевірити в Sentry (frequency, release, context), як шукати в логах
5. **Приклад:** як виглядає issue в Sentry (title, tags, extra, affected users)

**Критерії прийняття:**
- [ ] Є опис сценарію інциденту
- [ ] Є послідовність кроків (мінімум 6)
- [ ] Згадано зв’язок Sentry ↔ логи (correlation_id)
- [ ] Є чеклист
- [ ] Є приклад issue



## Завдання 8. Пріоритизація issues у Sentry

**Опис:** Склади короткий гайд: як пріоритизувати issues в Sentry для CRM.

**Бізнес-контекст:** У Sentry може бути багато issues; не всі однаково критичні. PM хоче правила: що виправляти першим (перекази, invoices), що можна відкласти (допоміжні сторінки), як використовувати frequency і affected users.

**Що реалізувати:**
1. **Доповни docs/ERROR_TRACKING.md** розділом «Пріоритизація»
2. **Критерії пріоритету:**
   - **P0 (критично):** модуль transfers або invoices, масові помилки (>10 events/год або >5 users), критичний функціонал (створення переказу, invoice)
   - **P1 (високий):** transfers/invoices, одиничні помилки; або інші модулі, масові
   - **P2 (середній):** інші модулі, одиничні
   - **P3 (низький):** не критичні, рідкісні
3. **Таблиця для CRM:**
   - Transfers (execute) - P0 при масовах, P1 при одиничних
   - Invoices (create) - P0 при масовах, P1 при одиничних
   - Accounts - P1/P2 залежно від впливу
   - 404, валідація - не в Sentry
4. **Правило:** спочатку P0, потім P1; використовувати Sentry сортування за frequency та users

**Критерії прийняття:**
- [ ] Є розділ «Пріоритизація»
- [ ] Є критерії P0–P3
- [ ] Є таблиця для модулів CRM
- [ ] Є правило порядку виправлень



## Формат здачі

**Структура проєкту (доповнення):**
```
crm-laravel/
├── app/Http/Middleware/
│   └── AddCorrelationId.php     - додано Sentry scope (correlation_id, endpoint)
├── app/Services/
│   ├── TransferService.php      - інтегровано TransferErrorReporter
│   └── InvoiceService.php       - інтегровано InvoiceErrorReporter
├── app/Services/Monitoring/
│   ├── TransferErrorReporter.php
│   └── InvoiceErrorReporter.php
├── config/
│   └── sentry.php               - before_send, release
├── docs/
│   ├── ERROR_TRACKING.md        - налаштування, матриця, пріоритизація
│   └── INCIDENT_RUNBOOK.md      - оновлено сценарієм з Sentry
└── .env                         - SENTRY_LARAVEL_DSN, SENTRY_RELEASE
```

**Перевірка:**
- Тестовий exception (`/api/v1/test-sentry`) з’являється в Sentry з correlation_id та endpoint
- Неочікуваний exception у TransferService/InvoiceService потрапляє в Sentry з контекстом
- InsufficientBalanceException, ValidationException НЕ потрапляють у Sentry
- ERROR_TRACKING.md та оновлений INCIDENT_RUNBOOK існують



## Рекомендації PM

- Контекст (transfer_id, invoice_id, correlation_id) важливіший за stack trace: він дозволяє швидко відтворити сценарій.
- Sentry і логи - доповнюють одне одного: Sentry для виявлення та пріоритизації, логи - для детального аналізу.
- Не відправляй у Sentry те, що можна очікувати (422, 404, InsufficientBalance). Інакше проєкт переповниться шумом.
- Release - основа для виявлення регресій; налаштовуй його в CI/CD.



**Орієнтовний час виконання:** 120–150 хвилин
