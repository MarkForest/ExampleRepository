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

### 4.2 Учебный пример настройки (точечно в `routes/api.php`)

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

### 4.3 Live-демо лимита (без правки кода — учебная демонстрация)

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

### 4.4 Что подчеркнуть

- Лимиты для авторизации и тяжёлых отчётов всегда жёстче.
- Без лимитов один клиент может «положить» API.

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
