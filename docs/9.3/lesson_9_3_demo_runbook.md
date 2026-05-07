# Lesson 9.3 — Demo Runbook (Laravel-only, с конкретным кодом)

Этот файл — единая инструкция для мита: **слайды -> этот runbook -> обратно к слайдам**.  
Во время демо `lesson_9_3_practice.md` не открываем.

---

## 0) Соответствие презентации

В `lesson_9_3_presentation.pdf` практические переходы помечены как «Слайд → demo». Покрываем их в runbook так:

- **Слайд 9a — HTTP-кеш для списков и справочников** → Блок A
- **Слайд 11a — объём ответа (DTO/Resources, поля, размер JSON)** → Блок B
- **Слайд 13a — rate limiting (`throttle`)** → Блок C
- **Слайд 15a — асинхронный экспорт через job (202 Accepted)** → Блок D
- **Финальный чек-лист production-ready API** → Блок E (используется как итог)

Важно: текущий рабочий код проекта (`AccountController`, `PaymentController`, `PaymentService`, `PaymentRepository`, тесты) **не переписываем**. Все примеры ниже — точечные «учебные» вставки, которые показываем во время мита.

---

## 1) Pre-flight (до начала лекции)

1. Проверить контейнеры:
  - `docker compose ps`
2. Проверить роуты:
  - `docker compose exec -T php php artisan route:list --path=api/v1`
3. Проверить тесты на SQLite:
  - `docker compose exec -T php php artisan test tests/Feature/Api/V1`
4. Проверить, что Telescope работает (поставлен ещё в 9.2):
  - `http://localhost/telescope/requests`

Если Telescope/Debugbar по какой-то причине отсутствуют — используй блоки установки из `docs/9.2/lesson_9_2_demo_runbook.md`, секция «Установка Telescope / Debugbar».

Базовый URL проекта при `docker compose` — `http://localhost`.

---

## 2) Блок A — HTTP-кеш (переход со слайда 9a)

### 2.1 Что говорить

- HTTP-кеш живёт **на клиенте/прокси**, а не в нашем коде.
- Дополняет серверный Redis-кеш (из 9.2), не заменяет его.
- Для финансовых данных — `private` и **короткий** `max-age` (либо `no-store`).
- Для публичных справочников (валюты, тарифы) — `public` + длинный `max-age`.

### 2.2 Учебная переделка `AccountController::payments` (показываем как пример)

Цель: для списка платежей рахунка отдавать `Cache-Control: private, max-age=15` и `ETag`, чтобы повторные запросы UI получали `304`.

```php
public function payments(Account $account, AccountPaymentsIndexRequest $request): \Illuminate\Http\JsonResponse
{
    $query = AccountPaymentsQueryDTO::fromValidated($request->validated());
    $payments = $this->paymentService->listPaymentsForAccount((int) $account->id, $query->getPerPage());

    $payload = PaymentResource::collection($payments)->response()->getData(true);
    $etag = '"' . sha1((string) json_encode($payload)) . '"';

    if ($request->headers->get('If-None-Match') === $etag) {
        return response()->json(null, 304)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, max-age=15');
    }

    return response()->json($payload)
        ->header('ETag', $etag)
        ->header('Cache-Control', 'private, max-age=15');
}
```

**Что в этом методе нового именно по теме 9.3 (HTTP-кеш):**

Базовая часть метода (DTO, сервис, `PaymentResource`) уже была сделана в 9.1 — её не разбираем, она здесь только чтобы получить тело ответа. Урок 9.3 добавляет три вещи поверх неё.

1. **Считаем `ETag` — «отпечаток» текущего ответа.**
  ```php
   $payload = PaymentResource::collection($payments)->response()->getData(true);
   $etag = '"' . sha1((string) json_encode($payload)) . '"';
  ```
   Берём финальный JSON, считаем от него SHA-1 и а в кавычки (так требует HTTP-стандарт: `ETag: "abc..."`). Идея простая: одинаковые данные → одинаковый ETag, изменился хотя бы один платёж → ETag другой. Это и есть «версия» ответа, которую клиент сможет сравнить.
2. **Обрабатываем условный запрос `If-None-Match` → отдаём `304`.**
  ```php
   if ($request->headers->get('If-None-Match') === $etag) {
       return response()->json(null, 304)
           ->header('ETag', $etag)
           ->header('Cache-Control', 'private, max-age=15');
   }
  ```
   Клиент при повторном GET присылает прошлый ETag в заголовке `If-None-Match`. Если совпало — данные не менялись, и мы отвечаем `304 Not Modified` **без тела**: клиент возьмёт свою прошлую копию. Это и есть выигрыш HTTP-кеша — мы не сериализуем JSON и не гоним его по сети.
3. **На полном ответе ставим `ETag` и `Cache-Control`.**
  ```php
   return response()->json($payload)
       ->header('ETag', $etag)
       ->header('Cache-Control', 'private, max-age=15');
  ```
  - `ETag` клиент сохранит и пришлёт в следующий раз — без него весь механизм 304 не работает.
  - `Cache-Control: private, max-age=15` — политика кеша:
    - `private` — кешировать можно **только на самом клиенте** (браузер, мобильное приложение). Прокси/CDN — нельзя, потому что список платежей **персональный**.
    - `max-age=15` — 15 секунд клиент может использовать свою копию вообще не обращаясь к серверу. Для типичного UX (нажал «назад» / переключил вкладку) этого достаточно, при этом суммы не выглядят «протухшими».

> Коротко: ETag даёт «дешёвые 304» для повторных запросов, а `Cache-Control` говорит клиенту, **кому** и **на сколько** разрешено кешировать.

> **В проекте уже подготовлен пустой метод-заглушка:**
> - `AccountController::paymentsCached()` (рядом с `payments()`),  
> - роут `GET /api/v1/accounts/{account}/payments-cached` → `accounts.payments.cached`.  
>
> Сейчас он просто возвращает `{"data": [], "message": "TODO: ..."}`. На демо я открываю файл и **в живую набираю** код по схеме выше (DTO → service → ETag → 304 / 200 + `Cache-Control`). Старый `payments()` остаётся «как было», и студенты сразу видят разницу: один отдаёт обычный 200, другой — 200 с `ETag`/`Cache-Control` и затем 304 на повторе.

### 2.3 Учебный `CurrencyController` для публичного кеша (по слайду)

Для довідника валют пример с `public, max-age=3600`:

```php
public function index(): \Illuminate\Http\JsonResponse
{
    $currencies = ['USD', 'EUR', 'UAH'];

    return response()
        ->json(['data' => $currencies])
        ->header('Cache-Control', 'public, max-age=3600');
}
```

**Что в этом методе нового именно по теме 9.3 (HTTP-кеш):**

Сама выдача справочника тривиальна (массив или `CurrencyService::getAll()` из 9.2). Урок 9.3 добавляет один заголовок — и именно его и обсуждаем.

```php
->header('Cache-Control', 'public, max-age=3600');
```

- `public` — кешировать разрешено **где угодно**: браузер, корпоративный прокси, CDN. Это безопасно, потому что справочник валют **не персонализирован** и не содержит чувствительных данных. Сравни с примером выше, где для платежей мы ставим `private` именно из-за персональности.
- `max-age=3600` — час. До истечения этого срока клиенты и прокси даже **не будут** обращаться к нашему серверу за этим ресурсом — то есть мы выносим нагрузку за пределы приложения вообще.

И ещё одна важная мысль на этом примере: **HTTP-кеш и серверный Redis-кеш — это разные слои**. Если внутри метода стоит `CurrencyService::getAll()` с `Cache::remember(...)` из 9.2, мы получаем двойной выигрыш:

- HTTP-кеш гасит запросы **до** Laravel (клиент берёт из своей копии);
- Redis-кеш гасит работу **внутри** Laravel, когда запрос всё-таки дошёл.

Контраст двух примеров запомнить просто:

- список платежей акаунта — `private, max-age=15` (личное, ненадолго);
- справочник валют — `public, max-age=3600` (общее, надолго).

Выбор `public/private` и `max-age` — это и есть основное практическое решение по HTTP-кешу для каждого endpoint-а.

> **В проекте уже подготовлен пустой `CurrencyController`:**
> - класс `App\Http\Controllers\Api\V1\CurrencyController` с пустым методом `index()`,
> - роут `GET /api/v1/currencies` → `currencies.index`.
>
> На демо открываю файл и в живую дописываю возврат списка валют + заголовок `Cache-Control: public, max-age=3600`.

### 2.4 Live-демо (сравнение `payments` vs `payments-cached` vs `currencies`)

Шаги во время мита:

1. Создать аккаунт (если ещё нет id):
   - `curl -s -X POST http://localhost/api/v1/accounts -H "Content-Type: application/json" -d '{"balance":"5000.00"}'`

2. **Базовый эндпоинт без HTTP-кеша** — посмотреть заголовки:
   - `curl -i "http://localhost/api/v1/accounts/ID/payments?per_page=20"`
   - В ответе **нет** `ETag` и `Cache-Control` — каждый клиентский запрос идёт «полностью».

3. **Эндпоинт с клиентским HTTP-кешом** (после того, как я дописал `paymentsCached`):
   - первый запрос — получаем тело + `ETag`:
     - `curl -i "http://localhost/api/v1/accounts/ID/payments-cached?per_page=20"`
   - копируем значение из заголовка `ETag: "..."`;
   - повторный запрос с `If-None-Match` — получаем `304 Not Modified` без тела:
     - `curl -i -H 'If-None-Match: "СКОПИРОВАННЫЙ_ETAG"' "http://localhost/api/v1/accounts/ID/payments-cached?per_page=20"`
   - смотрим заголовок `Cache-Control: private, max-age=15`.

4. **Публичный кеш для справочника валют** (после `CurrencyController::index`):
   - `curl -i "http://localhost/api/v1/currencies"`
   - в ответе `Cache-Control: public, max-age=3600` — клиент/прокси/CDN могут кешировать на час, к нашему серверу повторно не пойдут.

5. Подсветить разницу: один и тот же шаблон (`Cache-Control` + опционально `ETag`), но разные политики под разные данные:
   - персональный список → `private, max-age=15` + `ETag`;
   - публичный справочник → `public, max-age=3600`.

### 2.5 Что подчеркнуть

- HTTP-кеш — это **бесплатный выигрыш** на повторных GET.
- Для real-time денежных эндпоинтов (баланс перед списанием) — `no-store`.
- ETag + `If-None-Match` дают 304 без тела ответа.

---

## 3) Блок B — объём ответа: DTO/Resources (переход со слайда 11a)

### 3.1 Что говорить

- Меньше полей → меньше байт → быстрее сериализация и сеть.
- Resource/DTO — это и архитектура, и перформанс одновременно.
- Списки особенно чувствительны: каждое лишнее поле умножается на размер страницы.

### 3.2 Что уже есть в проекте (готово к запуску)

Для прямого сравнения «жирный vs чистый» в проекте уже подготовлено:

- **«Чистый» вариант** — `GET /api/v1/accounts/{account}/payments` через `App\Http\Resources\PaymentResource` (минимальный набор полей: `id`, `account_id`, `amount`, `currency`, `description`, `status`, `created_at`).
- **«Жирный» вариант** — `GET /api/v1/accounts/{account}/payments-fat` через `App\Http\Resources\PaymentFatResource` (тот же сервис/репозиторий, но Resource намеренно раздут: `amount_minor_units`, `commission`, `gateway_payment_id`, `gateway_raw_response` (вложенный объект с trace), `internal_note`, `audit.host/environment`, `created_at_iso`, `created_at_unix`, `updated_at`, `updated_at_iso`, `description_length`, `status_label`, `is_processed` и т.д.).

То есть оба эндпоинта возвращают **один и тот же набор платежей одного и того же аккаунта**, отличается только Resource — это даёт чистое сравнение по объёму ответа и времени сериализации.

### 3.3 Что показать на демо (живой замер в Telescope)

1. Открыть `http://localhost/telescope/requests`.
2. (Если данных мало) сгенерировать платежи фабрикой — это пригодится и для блока B, и для блока с индексами:
   - `docker compose exec -T php php artisan tinker --execute="\\App\\Models\\Payment::factory()->count(2000)->create(['account_id' => ID, 'status' => 'processed', 'currency' => 'USD']);"`
3. Сделать по 3 запроса каждого варианта, чтобы Telescope их зафиксировал:
   - `for i in {1..3}; do curl -s "http://localhost/api/v1/accounts/ID/payments?per_page=50" > /dev/null; done`
   - `for i in {1..3}; do curl -s "http://localhost/api/v1/accounts/ID/payments-fat?per_page=50" > /dev/null; done`
4. В Telescope (вкладка `Requests`) открыть оба варианта по очереди и зафиксировать на доске:
   - **Duration** (общее время запроса);
   - **Memory**;
   - размер тела ответа (видно в `Response` или по `Content-Length`);
   - **Queries** (число запросов и время) — должно быть одинаковым, потому что репозиторий тот же; разница идёт именно на сериализации и сети.

### 3.4 Что подчеркнуть студентам

- Resource — это и архитектура, и перформанс. Одна и та же выборка из БД может стать в **разы** тяжелее по сети только из-за «жирного» ответа.
- На больших `per_page` разница умножается: каждое лишнее поле × `per_page` строк × частота запросов = реальная нагрузка.
- «Жирные» поля типа `gateway_raw_response` или `internal_note` — это ещё и **утечка внутренних деталей**. Контроль набора полей в Resource = security by design.
- Правило: если поле не нужно ни одному текущему клиенту — его не должно быть в ответе. Под отдельный кейс делайте **отдельный** Resource (например, `PaymentDetailsResource` для страницы платежа), а не «всё и сразу» в списке.

---

## 4) Блок C — rate limiting (переход со слайда 13a)

### 4.1 Что говорить

- `throttle:N,M` — N запросов за M минут на пользователя/токен/IP.
- Разные эндпоинты — разные лимиты (обычные API и «тяжёлые» отчёты).
- 429 — это **нормальный** ответ, фронт должен показать сообщение и retry/backoff.

### 4.2 Что такое `throttle` и нужно ли его создавать

**Своё middleware создавать не нужно.** `throttle` — это встроенный alias на класс `Illuminate\Routing\Middleware\ThrottleRequests` (в API-стеке Laravel 11/12 — `ThrottleRequestsWithRedis`). Он зарегистрирован фреймворком и доступен «из коробки»: достаточно просто навесить его на маршруты.

Под капотом он:

- определяет ключ клиента (для гостя — IP, для авторизованного — id пользователя или токена Sanctum);
- инкрементирует счётчик в кеше (`config/cache.php`, обычно Redis в этом проекте);
- сравнивает с лимитом, на превышении возвращает `429 Too Many Requests` + `Retry-After`;
- на любом ответе ставит `X-RateLimit-Limit` и `X-RateLimit-Remaining`.

То есть `throttle` сам по себе — **не сервис**, не конфиг, не ваш файл; это middleware Laravel. Создавать новые классы под него не нужно.

### 4.3 Способ 1 — `throttle:N,M` прямо на маршрутах (минимум кода)

Самый простой способ — указать лимит как параметры алиаса прямо в `routes/api.php`:

```php
Route::prefix('v1')->group(function () {
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('accounts/{account}/payments', [AccountController::class, 'payments'])
            ->name('accounts.payments.index');
        Route::apiResource('payments', PaymentController::class)->except(['update']);
        Route::apiResource('accounts', AccountController::class)->except(['update']);
    });

    Route::middleware('throttle:5,1')->group(function () {
        Route::post('reports/account-statement', [\App\Http\Controllers\Api\V1\ReportsController::class, 'generateAccountStatement'])
            ->name('reports.account-statement');
    });
});
```

Что значат параметры:

- `throttle:60,1` → 60 запросов за 1 минуту;
- `throttle:5,1` → 5 запросов за 1 минуту (для тяжёлых отчётов).

Ключ группировки выбирается автоматически: для гостя — IP, для авторизованного — `Auth::id()`.

### 4.4 Способ 2 — именованный `RateLimiter` (рекомендуется для production)

Когда нужно больше гибкости (разные ключи, разные лимиты для гостя/юзера, кастомный ответ), Laravel предлагает регистрировать **именованные limiter-ы** через фасад `RateLimiter`. Это **встроенный механизм фреймворка**, своих классов писать не надо.

В `app/Providers/AppServiceProvider.php` (метод `boot`):

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('api', function (Request $request): Limit {
        return $request->user()
            ? Limit::perMinute(120)->by((string) $request->user()->id)
            : Limit::perMinute(30)->by((string) $request->ip());
    });

    RateLimiter::for('reports', function (Request $request): Limit {
        return Limit::perMinute(5)->by((string) ($request->user()?->id ?? $request->ip()));
    });
}
```

Тогда в `routes/api.php` достаточно сослаться на имя:

```php
Route::middleware('throttle:api')->group(function () {
    Route::get('accounts/{account}/payments', [AccountController::class, 'payments']);
});

Route::middleware('throttle:reports')->group(function () {
    Route::post('reports/account-statement', [ReportsController::class, 'generateAccountStatement']);
});
```

Что это даёт:

- разные лимиты для гостя и авторизованного на одном и том же эндпоинте;
- единая точка правды (поправил в `AppServiceProvider` — изменилось везде);
- можно делать `Limit::perMinute(...)`, `Limit::perHour(...)`, цепочки лимитов (`return [Limit::perMinute(60), Limit::perDay(1000)];`), кастомный `response(...)` на 429.

### 4.5 Это единственный способ?

Нет. На практике встречаются три уровня:

1. **`throttle:N,M` в роутах** — быстрый минимум, для уроков и небольших API.
2. **`RateLimiter::for(...)` + `throttle:имя`** — стандартный production-подход в Laravel.
3. **Внешний слой (Nginx, Cloudflare, API Gateway, AWS WAF)** — лимиты до того, как запрос вообще дошёл до PHP. Это не замена throttle в Laravel, а **дополнительный** контур: внешний слой режет грубые атаки, Laravel — бизнес-логику и пользовательские лимиты.

Своё middleware с нуля писать имеет смысл только если у вас совсем нестандартная логика (например, лимит по конкретному телу запроса). В 95% случаев хватает встроенного `throttle` + `RateLimiter::for(...)`.

### 4.6 Live-демо лимита (без правки кода — учебная демонстрация)

Покажи реакцию на превышение лимита (если временно ограничить, например, до `throttle:3,1`):

```bash
for i in {1..5}; do
  curl -s -o /dev/null -w "%{http_code}\n" "http://localhost/api/v1/accounts/ID/payments?per_page=10"
done
```

Ожидаем: после нескольких 200 — `429 Too Many Requests`.

В заголовках:

- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `Retry-After` (на 429)

### 4.7 Что подчеркнуть

- Лимиты для авторизации и тяжёлых отчётов всегда жёстче.
- Без лимитов один клиент может «положить» API.
- В production почти всегда используют именно **именованные** limiter-ы через `RateLimiter::for(...)`, а не голые `throttle:60,1` в роутах — это и удобнее поддерживать, и точнее по бизнес-смыслу.

---

## 5) Блок D — jobs для тяжёлых операций (переход со слайда 15a)

### 5.1 Что говорить

- Тяжёлая работа в HTTP-запросе = таймауты и плохой UX.
- Решение: API ставит job → возвращает **202 Accepted** + `task_id`.
- Воркер делает работу в фоне, фронт опрашивает статус или ждёт нотификацию.

### 5.2 Что уже есть в проекте (готово к запуску)

В проекте под этот блок уже подготовлено всё необходимое — на демо достаточно открыть файлы и запустить `curl`:

- **Контроллер** `App\Http\Controllers\Api\V1\ReportsController` — метод `generateAccountStatement(Request $request)`:
  - валидирует `account_id`, `from`, `to`;
  - генерирует `task_id` (UUID);
  - диспатчит `ExportAccountStatementJob::dispatch(...)`;
  - возвращает `202 Accepted` с `{ status, task_id, message }`.
- **Job** `App\Jobs\ExportAccountStatementJob` — `implements ShouldQueue`:
  - конструктор принимает `accountId`, `periodFrom`, `periodTo`, `taskId`;
  - `handle()` пишет в лог `"Account statement export started"` с `task_id`,
  - находит аккаунт, имитирует тяжёлую работу `sleep(3)`,
  - пишет `"Account statement export finished"`.
- **Роут** `POST /api/v1/reports/account-statement` → `reports.account-statement`.
- **Воркер очереди** — сервис `queue-worker` в `docker-compose.yml` уже подхватывает джобы.

### 5.3 Live-демо (поэтапно во время мита)

1. В одном терминале — следим за логами в реальном времени:
   - `docker compose exec -T php sh -lc 'tail -f -n 0 storage/logs/laravel.log'`
2. В другом — открываем UI воркера/Telescope:
   - `http://localhost/telescope/jobs` (вкладка `Jobs`).
3. Делаем запрос на отчёт — он должен ответить **мгновенно**, несмотря на `sleep(3)` внутри джобы:
   ```bash
   curl -i -X POST "http://localhost/api/v1/reports/account-statement" \
     -H "Content-Type: application/json" \
     -d '{"account_id":1,"from":"2026-01-01","to":"2026-01-31"}'
   ```
   Ожидаем: `HTTP/1.1 202 Accepted`, в JSON — `task_id`.
4. В логах ровно через ~3 секунды появится связка строк с этим же `task_id`:
   - `Account statement export started ... task_id=...`
   - `Account statement export finished ... task_id=...`
5. В Telescope в `Jobs` видно, что джоба обработана воркером, не веб-процессом, и **время API-ответа** не зависит от времени джобы.

### 5.4 Что подчеркнуть

- HTTP-ответ возвращается за десятки миллисекунд, тяжёлая работа — за пределами запроса. Это и есть то, ради чего вообще нужны очереди в API.
- Воркер очереди уже поднят в `docker-compose` (`queue-worker`) — никаких ручных запусков на демо не требуется.
- Реальный следующий шаг (вне этого урока): отдельный эндпоинт `GET /reports/{task_id}` со статусом задачи и ссылкой на готовый файл.

---

## 6) Блок E — финальный чек-лист production-ready API (слайд 19)

Используем как «закрытие» урока на доске:

- ✅ оптимизированная БД (индексы, отсутствие N+1, измерения через Telescope) — Модули 9.1–9.2
- ✅ серверный кеш (Redis) для справочников/агрегатов — 9.2
- ✅ HTTP-кеш (`Cache-Control` / `ETag` / 304) — 9.3 Блок A
- ✅ DTO / API Resources, контроль объёма ответа — 9.3 Блок B
- ✅ пагинация всех списков — 9.1
- ✅ rate limiting (`throttle`) — 9.3 Блок C
- ✅ jobs для тяжёлых операций (202 + воркер) — 9.3 Блок D
- ✅ тесты, логи, Sentry — Модули 7–8

---

## 7) Тесты и логи в конце демо

1. Прогнать API-набор (SQLite):
  - `docker compose exec -T php php artisan test tests/Feature/Api/V1`
2. Логи:
  - `docker compose exec -T php sh -lc 'tail -n 100 storage/logs/laravel.log'`
3. Очереди (если показывали jobs):
  - `docker compose logs queue-worker --tail=50`

---

## 8) Anti-fail (30 секунд до старта)

1. `docker compose exec -T php php artisan config:clear`
2. `docker compose exec -T php php artisan route:list --path=api/v1`
3. Открыть и обновить:
  - `http://localhost/telescope/requests`
4. Сделать один прогон:
  - `curl -i "http://localhost/api/v1/accounts/1/payments?per_page=10"`

---

## 9) Карта подготовленного кода под этот урок

### Что уже добавлено в проект (можно сразу запускать)

- `app/Http/Resources/PaymentFatResource.php` — «жирный» Resource (блок B).
- `app/Http/Controllers/Api/V1/AccountController::paymentsFat()` — метод, который возвращает тот же список платежей через `PaymentFatResource` (блок B).
- `app/Http/Controllers/Api/V1/ReportsController` — `generateAccountStatement()` (блок D).
- `app/Jobs/ExportAccountStatementJob` — рабочий job с логированием и `sleep(3)` (блок D).
- В `routes/api.php` добавлены роуты: `payments-cached`, `payments-fat`, `currencies`, `reports/account-statement`.

### Что подготовлено как **пустые заглушки** под live-набор кода

- `AccountController::paymentsCached()` — для блока A (HTTP-кеш для персональных данных). Я вживую дописываю `ETag` + `Cache-Control: private, max-age=15` + 304 на `If-None-Match`.
- `App\Http\Controllers\Api\V1\CurrencyController::index()` — для блока A (публичный кеш). Я вживую дописываю возврат списка валют + `Cache-Control: public, max-age=3600`.

### Что НЕ трогаем во время демо

- Существующие реализации `AccountController::payments()`, `PaymentController`, `PaymentService`, `PaymentRepository`, `PaymentResource` и тесты.
- Базовый эндпоинт `GET /api/v1/accounts/{account}/payments` — он остаётся «как есть», именно для контраста с `payments-cached` и `payments-fat`.
- `RateLimiter` / `throttle` (блок C) — отдельный код добавлять под мит не нужно: `throttle` встроен во фреймворк, в runbook показана только конфигурация на роутах и через `RateLimiter::for(...)`.

