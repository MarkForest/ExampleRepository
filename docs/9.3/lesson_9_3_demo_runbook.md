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

### 2.4 Live-демо (можно показать без правки кода)

Показываем заголовки прямо у текущего endpoint:

1. Создаём аккаунт (если ещё нет id):
  - `curl -s -X POST http://localhost/api/v1/accounts -H "Content-Type: application/json" -d '{"balance":"5000.00"}'`
2. Смотрим заголовки ответа:
  - `curl -i "http://localhost/api/v1/accounts/ID/payments?per_page=20"`
3. Подсветить:
  - сейчас нет `Cache-Control` → каждый запрос идёт «полностью»;
  - после добавления `private, max-age=15` повторные запросы UI могут отдаваться из локального кеша.

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

### 3.2 Текущий ответ списка платежей

Endpoint: `GET /api/v1/accounts/{account}/payments`  
Resource: `app/Http/Resources/PaymentResource.php` уже отдаёт минимально необходимое (`id`, `account_id`, `amount`, `currency`, `description`, `status`, `created_at`).

### 3.3 Учебный пример «жирного» vs «чистого» ответа (на слайде/доске)

«Жирный» (что бывает по-умолчанию, если отдавать `$model->toArray()`):

```json
{
  "data": [{
    "id": 123,
    "account_id": 10,
    "user_id": 5,
    "amount": "250.00",
    "currency": "USD",
    "description": "Оплата рахунку №1001",
    "status": "processed",
    "gateway_payment_id": "gw_abc",
    "gateway_raw_response": "{...}",
    "internal_note": "debug...",
    "created_at": "2026-02-12T10:00:00Z",
    "updated_at": "2026-02-12T10:00:05Z"
  }]
}
```

Что лишнее: `gateway_raw_response`, `internal_note`, `updated_at` — клиенту не нужно, и часть данных вообще не должна «утекать».

«Чистый» (через `PaymentResource`/`PaymentListItemResource` для списков):

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'account_id' => $this->account_id,
        'amount' => $this->amount,
        'currency' => $this->currency,
        'description' => $this->description,
        'status' => $this->status,
        'created_at' => optional($this->created_at)->toIso8601String(),
    ];
}
```

### 3.4 Что измерить (через Telescope)

1. Открыть `http://localhost/telescope/requests`.
2. Выполнить:
  - `for i in {1..3}; do curl -s "http://localhost/api/v1/accounts/ID/payments?per_page=50" > /dev/null; done`
3. В Telescope посмотреть:
  - размер ответа (`Content-Length` или примерный размер тела);
  - количество SQL.
4. Идея демо: «уменьшение ответа = уменьшение времени сериализации и сети, особенно при большом `per_page`».

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

### 5.2 Учебный job для экспорта выписки

Файл: `app/Jobs/ExportAccountStatementJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ExportAccountStatementJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $accountId,
        private readonly string $periodFrom,
        private readonly string $periodTo,
        private readonly string $taskId,
    ) {}

    public function handle(): void
    {
        /** @var Account $account */
        $account = Account::query()->findOrFail($this->accountId);

        Log::info('Account statement export started', [
            'task_id'    => $this->taskId,
            'account_id' => $account->id,
            'from'       => $this->periodFrom,
            'to'         => $this->periodTo,
        ]);

        // TODO: собрать операции, сгенерировать CSV/PDF, сохранить, уведомить пользователя
    }
}
```

### 5.3 Учебный контроллер `ReportsController` (отвечает 202)

Файл: `app/Http/Controllers/Api/V1/ReportsController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\AntiPattern\Controller;
use App\Jobs\ExportAccountStatementJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ReportsController extends Controller
{
    public function generateAccountStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'from'       => ['required', 'date'],
            'to'         => ['required', 'date', 'after_or_equal:from'],
        ]);

        $taskId = (string) Str::uuid();

        ExportAccountStatementJob::dispatch(
            (int) $validated['account_id'],
            (string) $validated['from'],
            (string) $validated['to'],
            $taskId,
        );

        return response()->json([
            'status'  => 'accepted',
            'task_id' => $taskId,
            'message' => 'Report generation started.',
        ], 202);
    }
}
```

### 5.4 Live-демо

```bash
curl -i -X POST "http://localhost/api/v1/reports/account-statement" \
  -H "Content-Type: application/json" \
  -d '{"account_id":1,"from":"2026-01-01","to":"2026-01-31"}'
```

Ожидаем:

- `HTTP/1.1 202 Accepted`
- в JSON — `task_id`

В логах контейнера:

- `docker compose exec -T php sh -lc 'tail -n 50 storage/logs/laravel.log'`

### 5.5 Что подчеркнуть

- API всегда отвечает быстро (202).
- Воркер очереди (из Модуля 5) уже поднят в `docker-compose` (`queue-worker`).
- Дальнейший шаг в реальном проекте: endpoint статуса задачи / нотификации.

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

## 9) Что НЕ трогаем во время демо

- Текущие реализации `AccountController`, `PaymentController`, `PaymentService`, `PaymentRepository` и тесты.
- `PaymentResource` уже минималистичный — пример «жирного» JSON показываем только на доске.
- `ReportsController`, `ExportAccountStatementJob`, `CurrencyController` и `throttle`-варианты — это **учебные** примеры из практики; внедрять в runtime под мит не обязательно.

