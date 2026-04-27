# Домашнє завдання. Модуль 8, Урок 1

**Тема:** Навіщо потрібні логування та моніторинг у продакшені  

**Контекст:** У Модулі 7 ти побудував REST API CRM (transfers, accounts, invoices), стандартизував відповіді та помилки, задокументував контракт і покрив Feature-тестами. PM поставив завдання: **підготувати CRM до продакшену** з точки зору логування - визначити, які події логувати, з яким контекстом і рівнями, додати Correlation ID, створити карту подій і сценарій роботи з інцидентами. У фінансовій CRM без логів неможливо розслідувати, чому переказ не пройшов або рахунок-фактуру не вдалося створити. Це підготовка до Sentry (Урок 8.2) та лог-агрегації (Урок 8.3).



## Мета

Перевірити й закріпити: різницю між логуванням і моніторингом, призначення логів у продакшені (дебаг, аудит, розслідування), рівні логування (debug, info, warning, error, critical), структуровані логи з контекстом, Correlation ID, роль error tracking, базовий процес інцидент-менеджменту.



## Передумова

Проєкт `crm-laravel` з Module 6–7: TransferService, InvoiceService, API-ендпоінти transfers, accounts, invoices, Exception Handler для domain-виключень (InsufficientBalanceException, SameAccountTransferException). Код може містити окремі виклики Log::info/Log::error; потрібно систематизувати підхід до логування.



## Завдання 1. Карта подій для CRM: Transfers і Invoices

**Опис:** Склади таблицю подій, які варто логувати в модулях переказів і рахунків-фактур, з рівнями та мінімальним контекстом.

**Бізнес-контекст:** PM хоче єдиний підхід: яка подія - який рівень і які поля контексту. Це основа для послідовного впровадження логування в коді. У фінансовій CRM переказ і створення invoice - критичні операції; їх потрібно відстежувати для аудиту та розслідування.

**Що реалізувати:**
1. **Створи документ `docs/LOGGING_GUIDE.md`** з розділом «Карта подій»
2. **Таблиця для Transfers:**

   | Подія | Рівень | Мінімальний контекст |
   |-|--|-|
   | Переказ успішно створено | info | transfer_id, account_from_id, account_to_id, amount, currency, correlation_id |
   | Переказ не пройшов (недостатній баланс) | warning | account_from_id, amount, balance, correlation_id |
   | Переказ не пройшов (однакові рахунки) | warning | account_from_id, account_to_id, correlation_id |
   | Помилка при створенні транзакцій | error | transfer_id (якщо є), account_from_id, error_message, correlation_id |
   | ... | ... | ... |

3. **Таблиця для Invoices:**

   | Подія | Рівень | Мінімальний контекст |
   |-|--|-|
   | Рахунок-фактура створено | info | invoice_id, client_id, total_amount, items_count, correlation_id |
   | Помилка створення invoice (клієнт не знайдений) | warning | client_id, correlation_id |
   | Помилка створення invoice (послуга не знайдена) | warning | service_id, invoice_id (якщо є), correlation_id |
   | Неочікуваний exception при створенні invoice | error | invoice_id, client_id, error_message, correlation_id |
   | ... | ... | ... |

4. **Таблиця для Accounts** (опційно): перегляд балансу, оновлення балансу
5. **Правило:** для кожної нової події одразу визначати рівень і контекст - уникати хаотичного логування

**Критерії прийняття:**
- [ ] Є документ LOGGING_GUIDE.md з картою подій
- [ ] Є мінімум 5 подій для transfers
- [ ] Є мінімум 4 події для invoices
- [ ] Для кожної події вказано рівень (info, warning, error) і контекст
- [ ] correlation_id в контексті для всіх подій



## Завдання 2. Middleware: Correlation ID

**Опис:** Реалізуй middleware, який генерує Correlation ID на початку запиту і додає його до всіх логів у межах цього запиту.

**Бізнес-контекст:** Correlation ID (request ID) дозволяє зібрати всі логи одного HTTP-запиту в один ланцюжок. При інциденті «переказ не пройшов» можна знайти за correlation_id послідовність: запит → валідація → TransferService → репозиторії → відповідь. Без correlation_id логи - розрізнені події; важко відтворити сценарій.

**Що реалізувати:**
1. **Створи middleware** `App\Http\Middleware\AddCorrelationId`:
   ```bash
   php artisan make:middleware AddCorrelationId
   ```
2. **Логіка middleware:**
   - Генеруй унікальний ID: `Str::uuid()->toString()` або `uniqid('req_', true)`
   - Зберігай в `request->attributes` або глобальному контексті (Log::withContext)
   - Додай заголовок відповіді `X-Correlation-Id` (щоб клієнт міг передати його в підтримку)
   - Використовуй `Log::withContext(['correlation_id' => $id])` - Laravel додасть контекст до всіх логів у межах запиту
3. **Реєстрація:** зареєструй middleware для групи `api` (або глобально)
4. **Контекст у логах:** усі виклики `Log::info()`, `Log::error()` тощо в межах запиту автоматично отримають correlation_id, якщо використовується withContext
5. **Альтернатива:** якщо Laravel не підтримує withContext у старій версії - створіть допоміжний клас або передавайте correlation_id явно в контекст кожного виклику Log

**Критерії прийняття:**
- [ ] Є middleware AddCorrelationId
- [ ] Correlation ID генерується на початку запиту
- [ ] ID додається до логів (через withContext або явно)
- [ ] Відповідь містить заголовок X-Correlation-Id
- [ ] Middleware застосовується до API-маршрутів



## Завдання 3. Структуроване логування в TransferService

**Опис:** Додай виклики Log у TransferService для ключових подій згідно з картою.

**Бізнес-контекст:** TransferService виконує критичну фінансову операцію. При успіху потрібно зафіксувати факт (аудит); при помилці - контекст для розслідування. InsufficientBalanceException і SameAccountTransferException вже викидаються - перед throw варто залогувати подію з рівнем warning.

**Що реалізувати:**
1. **Після успішного executeTransfer:**
   ```php
   Log::info('Transfer completed', [
       'transfer_id' => $transactionOut->id,
       'account_from_id' => $accountFrom->id,
       'account_to_id' => $accountTo->id,
       'amount' => $amount,
       'currency' => $currency,
       'correlation_id' => $this->getCorrelationId(), // або з Request
   ]);
   ```
2. **Перед throw InsufficientBalanceException:**
   ```php
   Log::warning('Transfer failed: insufficient balance', [
       'account_from_id' => $accountFrom->id,
       'amount' => $amount,
       'balance' => $accountFrom->balance,
       'correlation_id' => ...,
   ]);
   ```
3. **Перед throw SameAccountTransferException:**
   ```php
   Log::warning('Transfer failed: same account', [
       'account_from_id' => $accountFromId,
       'account_to_id' => $accountToId,
       'correlation_id' => ...,
   ]);
   ```
4. **При неочікуваному exception** (у Exception Handler або в catch біля виклику TransferService) - Log::error з контекстом
5. **Отримання correlation_id:** через `request()->header('X-Correlation-Id')` або з Request, якщо middleware зберігає його в attributes; або inject Request у сервіс (опційно - краще через middleware і Log::withContext)
6. **declare(strict_types=1)** і type hints

**Критерії прийняття:**
- [ ] Є Log::info після успішного переказу
- [ ] Є Log::warning перед InsufficientBalanceException і SameAccountTransferException
- [ ] Контекст містить transfer_id/account_from_id, amount, correlation_id
- [ ] Логи структуровані (масив як другий аргумент)



## Завдання 4. Структуроване логування в InvoiceService

**Опис:** Додай логування в InvoiceService для створення рахунку-фактури та помилок.

**Бізнес-контекст:** Створення invoice - фінансова операція; її потрібно фіксувати для аудиту. При помилках (клієнт не знайдений, послуга не знайдена, exception) - warning або error з контекстом для розслідування.

**Що реалізувати:**
1. **Після успішного createInvoice:**
   ```php
   Log::info('Invoice created', [
       'invoice_id' => $invoice->id,
       'client_id' => $invoice->client_id,
       'total_amount' => $totalAmount,
       'items_count' => count($items),
       'correlation_id' => ...,
   ]);
   ```
2. **Перед викиданням виключення** «клієнт не знайдений» або «послуга не знайдена»:
   ```php
   Log::warning('Invoice creation failed: client not found', [
       'client_id' => $clientId,
       'correlation_id' => ...,
   ]);
   ```
3. **При неочікуваному exception** - Log::error
4. **Контекст:** завжди invoice_id (якщо є), client_id, correlation_id
5. **Доповни LOGGING_GUIDE.md** прикладами коду для InvoiceService

**Критерії прийняття:**
- [ ] Є Log::info після створення invoice
- [ ] Є Log::warning при бізнес-помилках (клієнт/послуга не знайдені)
- [ ] Контекст згідно з картою подій
- [ ] Документ LOGGING_GUIDE.md доповнено



## Завдання 5. Налаштування каналу логів для структурованого формату

**Опис:** Налаштуй Laravel-канал логів для виводу структурованого (JSON) формату.

**Бізнес-контекст:** У продакшені логи збираються збирачами (Fluentd, Filebeat, CloudWatch тощо) і направляються в лог-агрегацію. Структурований формат (JSON) дозволяє фільтрувати за полями (payment_id, correlation_id, level). Laravel за замовчуванням пише текстові рядки; для агрегації краще JSON.

**Що реалізувати:**
1. **Онови `config/logging.php`:** додай канал `stack_json` або зміни існуючий `stack` для API:
   - Використай `formatter` з `Monolog\Formatter\JsonFormatter` (якщо доступно) або `\Illuminate\Log\LogManager::format`
   - Laravel 10+ підтримує `'formatter' => \Monolog\Formatter\JsonFormatter::class` для каналу
2. **Приклад конфігу:**
   ```php
   'single_json' => [
       'driver' => 'single',
       'path' => storage_path('logs/laravel.json'),
       'level' => env('LOG_LEVEL', 'debug'),
       'formatter' => \Monolog\Formatter\JsonFormatter::class,
       'formatter_with' => [],
   ],
   ```
3. **Або:** для stdout у Docker - канал `stderr` з JsonFormatter, щоб збирач логів контейнера отримував JSON
4. **LOG_CHANNEL:** в `.env` для production можна вказати `LOG_CHANNEL=single_json` (або stack з json-форматом)
5. **Доповни LOGGING_GUIDE.md** розділом «Налаштування каналів» з прикладом конфігу

**Критерії прийняття:**
- [ ] Є канал з JSON-форматом (або задокументовано, як налаштувати)
- [ ] При виклику Log::info з контекстом вивід містить JSON з полями
- [ ] Документ LOGGING_GUIDE.md оновлено
- [ ] Логи читабельні і структуровані



## Завдання 6. Сценарій інциденту: «Масово не проходять перекази»

**Опис:** Опиши сценарій інциденту та послідовність дій команди з використанням логів.

**Бізнес-контекст:** PM хоче, щоб команда знала, як діяти при інциденті. Типовий сценарій: користувачі масово повідомляють, що перекази не проходять. Без логів і correlation_id розслідування неможливе; з ними - можна швидко знайти причину (БД, зовнішній API, зміни в коді).

**Що реалізувати:**
1. **Створи документ `docs/INCIDENT_RUNBOOK.md`** (або розділ у LOGGING_GUIDE.md)
2. **Опиши інцидент:** «Користувачі повідомляють, що перекази не проходять: API повертає 500 або 422 з повідомленням про помилку. Інцидент почався о 14:00.»
3. **Послідовність дій:**
   - **Крок 1. Перевірка error tracking (Sentry):** чи є сплеск issues по transfers? Які типи винятків? (деталі в Уроці 8.2)
   - **Крок 2. Пошук по логах:** фільтр за часом (14:00–14:30), endpoint `/api/v1/transfers`, level error або warning
   - **Крок 3. Аналіз по correlation_id:** якщо є один проблемний correlation_id - взяти його з Sentry або з скарги користувача (заголовок X-Correlation-Id), знайти всі логи цього запиту
   - **Крок 4. Контекст:** перевірити account_from_id, amount, balance, error_message - що саме впало?
   - **Крок 5. Тимчасові дії:** якщо БД недоступна - перезапуск, якщо зовнішній сервіс - очікування або fallback
   - **Крок 6. Виправлення та post-mortem**
4. **Приклад пошуку в логах (псевдокод):** `grep "correlation_id.*req-xxx" laravel.log` або в лог-агрегації: `correlation_id:req-xxx`
5. **Чеклист для команди:** що перевірити першим (Sentry, логи, БД, черги)

**Критерії прийняття:**
- [ ] Є документ INCIDENT_RUNBOOK.md
- [ ] Описано сценарій інциденту
- [ ] Є послідовність дій (мінімум 5 кроків)
- [ ] Є пояснення ролі correlation_id
- [ ] Є чеклист



## Завдання 7. Розділення: що логувати vs що відправляти в Error Tracking

**Опис:** Визнач, які події лишаються лише в логах, а які мають потрапляти в Sentry (error tracking).

**Бізнес-контекст:** Error tracking (Sentry) збирає exceptions і показує їх як «issues». Не все варто відправляти в Sentry: очікувана валідація (422) або контрольована відмова «недостатньо коштів» - це бізнес-логіка, не баг. Sentry - для неочікуваних помилок, які потребують уваги розробника.

**Що реалізувати:**
1. **Доповни LOGGING_GUIDE.md** розділом «Логи vs Error Tracking»
2. **Таблиця:**

   | Подія | Логувати (Log) | Відправляти в Sentry |
   |-|-|-|
   | Переказ успішно | Так (info) | Ні |
   | InsufficientBalanceException | Так (warning) | Ні (очікувана бізнес-помилка) |
   | SameAccountTransferException | Так (warning) | Ні |
   | Неочікуваний exception у TransferService | Так (error) | Так |
   | Помилка БД при створенні транзакції | Так (error) | Так |
   | 422 від валідації (FormRequest) | Ні (Laravel логує окремо за потреби) | Ні |

3. **Правило:** Sentry - для необроблених exceptions та критичних помилок; контрольовані бізнес-відмови - лише в логи
4. **Доповни:** які винятки в Exception Handler НЕ передавати в Sentry (наприклад, InsufficientBalanceException, ValidationException)

**Критерії прийняття:**
- [ ] Є таблиця «Логи vs Error Tracking»
- [ ] Є правило розділення
- [ ] Описано, які exceptions не відправляти в Sentry
- [ ] Документ читабельний



## Завдання 8. Документ LOGGING_GUIDE.md: підсумок

**Опис:** Збери всі правила логування CRM в один документ.

**Бізнес-контекст:** LOGGING_GUIDE - єдине джерело правди для розробників: що логувати, які рівні, який контекст, як працювати з інцидентами. Це частина онбордингу та операційної культури.

**Що реалізувати:**
1. **Структура LOGGING_GUIDE.md:**
   - Розділ 1: Карта подій (Transfers, Invoices)
   - Розділ 2: Correlation ID (middleware, як використовувати)
   - Розділ 3: Рівні логування (коли info, warning, error)
   - Розділ 4: Структуровані логи (приклад JSON)
   - Розділ 5: Налаштування каналів (JSON)
   - Розділ 6: Логи vs Error Tracking
   - Розділ 7: Посилання на INCIDENT_RUNBOOK.md
2. **Приклад структурованого логу** у форматі JSON (як виглядає запис після Log::info з контекстом)
3. **Команди для перегляду логів:** `tail -f storage/logs/laravel.log`, для Docker - `docker compose logs -f app`

**Критерії прийняття:**
- [ ] Є повноцінний документ LOGGING_GUIDE.md
- [ ] Усі розділи заповнені
- [ ] Є приклад JSON-логу
- [ ] Є посилання на INCIDENT_RUNBOOK
- [ ] Документ читабельний



## Формат здачі

**Структура проєкту (доповнення):**
```
crm-laravel/
├── app/Http/Middleware/
│   └── AddCorrelationId.php
├── app/Services/
│   ├── TransferService.php    - додано Log::info, Log::warning
│   └── InvoiceService.php     - додано Log::info, Log::warning
├── config/
│   └── logging.php            - канал з JSON (опційно)
├── docs/
│   ├── LOGGING_GUIDE.md       - карта подій, рівні, контекст, канали
│   └── INCIDENT_RUNBOOK.md    - сценарій інциденту
└── bootstrap/app.php або Kernel.php  - реєстрація middleware
```

**Перевірка:**
- POST /api/v1/transfers - у логах зʼявляється запис (info або warning залежно від результату)
- Відповідь містить заголовок X-Correlation-Id
- LOGGING_GUIDE.md та INCIDENT_RUNBOOK.md існують і заповнені
- У TransferService та InvoiceService є виклики Log з контекстом



## Рекомендації PM

- Не логуй чутливі дані (паролі, токени) - лише ідентифікатори та суми.
- Контекст важливіший за текст повідомлення: «Transfer failed» + контекст (account_from_id, amount, balance) корисніший, ніж довгий текст без полів.
- Correlation ID - основа трасування; без нього логи розрізнені.
- Error tracking не замінює логи - він доповнює їх для швидкого огляду помилок.



**Орієнтовний час виконання:** 120–150 хвилин
