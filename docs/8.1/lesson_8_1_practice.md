# Практичні приклади до уроку 8.1 - Навіщо потрібні логування та моніторинг у продакшені

> **Загальна ідея практики:**  
> На фінансовому прикладі показати, **що і як логувати**: які події важливі, які рівні обрати, який контекст додавати (особливо для платежів), як виглядає **структурований лог** та як його використати в сценарії інциденту.  
> Усі приклади - концептуальні; конкретна інтеграція з Sentry та лог‑агрегацією буде в наступних уроках (8.2, 8.3).



## 1. Блок 4: Події та рівні → Слайд 10a

**Загальна ідея прикладу:**  
Скласти **карту подій** для модуля платежів/рахунків і підібрати для кожної **рівень логування** та **мінімальний контекст**. Це основа для подальшого впровадження логування в коді.

### 1.1. Таблиця подій для payments/accounts

```text
Подія                                  Рівень      Мінімальний контекст
-  -  
Платіж успішно створено               info        payment_id, user_id, account_id, amount, currency
Платіж успішно підтверджено           info        payment_id, user_id, gateway_code, amount
Баланс рахунку оновлено               info        account_id, user_id, delta_amount, new_balance

Спроба платежу з недостатнім балансом warning     user_id, account_id, amount, balance
Перевищено ліміт спроб підтвердження  warning     payment_id, user_id, attempts_count
Незвично велика сума платежу          warning     payment_id, user_id, amount, average_amount

Помилка платіжного шлюзу (відмова)    error       payment_id, user_id, amount, gateway_code, gateway_message
Помилка запису в аудит                error       payment_id, user_id, error_message
Неочікуваний виняток у сервісі        error       class, message, payment_id (якщо відомий), correlation_id

База даних недоступна                 critical    service="db", correlation_id
Платіжний провайдер повністю “лежить” critical    provider="gateway_name", correlation_id
```

> **Що зробити на практиці:**  
> - розширити таблицю під свій домен (наприклад, `withdrawals`, `transfers`);  
> - для кожної нової події одразу визначати рівень і контекст - так ви уникаєте хаосу «що куди логувати».



## 2. Блок 5: Структурований лог → Слайд 13a

**Загальна ідея прикладу:**  
Показати приклад **структурованого лог‑запису** для події «платіж не пройшов», який потім можна буде легко шукати/фільтрувати в централізованій системі логів.

### 2.1. PHP‑код логування в Laravel

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

final class PaymentLoggingService
{
    public function logFailedPayment(
        int $paymentId,
        int $userId,
        string $amount,
        string $currency,
        string $gatewayCode,
        string $gatewayMessage,
        string $correlationId
    ): void {
        Log::error('Payment failed', [
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => $currency,
            'gateway_code' => $gatewayCode,
            'gateway_message' => $gatewayMessage,
            'correlation_id' => $correlationId,
        ]);
    }
}
```

### 2.2. Як це може виглядати в JSON‑каналі

```json
{
  "level": "error",
  "message": "Payment failed",
  "payment_id": 12345,
  "user_id": 10,
  "amount": "250.00",
  "currency": "USD",
  "gateway_code": "TIMEOUT",
  "gateway_message": "Gateway did not respond within 30s",
  "correlation_id": "req-20260212-abc123",
  "timestamp": "2026-02-12T10:15:30Z",
  "env": "production"
}
```

> **Що пояснити на демонстрації:**  
> - як за `payment_id` або `correlation_id` можна миттєво знайти всі повʼязані логи;  
> - чому важливо мати окремі поля для коду/повідомлення шлюзу (`gateway_code`, `gateway_message`), а не один «розмазаний» текст.



## 3. Блок 7: Сценарій інциденту → Слайд 19a

**Загальна ідея прикладу:**  
Продемонструвати, як **логи та error tracking** використовуються в реальному сценарії інциденту «масово не проходять платежі».

### 3.1. Опис інциденту

> Користувачі й підтримка повідомляють, що за останню годину багато платежів не проходять: фронтенд показує помилку «платіж не підтверджено», час від часу зʼявляється 500 на `/api/v1/payments`.

### 3.2. Послідовність дій команди

1. **Перевірити алерти та error tracking (Sentry)**  
   - Чи є нові/сплеск issues, повʼязаних з платежами?  
   - Які типи винятків найчастіші (наприклад, `GatewayTimeoutException`, `DatabaseConnectionException`)?

2. **Пошук по логах за часом і endpoint’ом**
   - Фільтр по `endpoint="/api/v1/payments"` і `level="error"` за останню годину.  
   - Порахувати кількість помилок, подивитися типові `gateway_code`/`gateway_message`.

3. **Пошук по конкретному payment_id / correlation_id**
   - Взяти один з проблемних payments з Sentry або з БД.  
   - По `payment_id` або `correlation_id` знайти всі повʼязані записи логів:  
     - створення платежу (info);  
     - виклик шлюзу (debug/info);  
     - помилка шлюзу (error);  
     - можливо, повторні спроби (warning).

4. **Виявлення причини**
   - Наприклад: `gateway_code="TIMEOUT"` для 80% failed payments → проблема на стороні шлюзу.  
   - Або: `DatabaseConnectionException`/`SQLSTATE[HY000]` → проблема з БД.

5. **Реакція**
   - Тимчасові дії: переключення на резервний шлюз / вимкнення деяких фіч / rollback останнього релізу.  
   - Комунікація з підтримкою та, за потреби, клієнтами.

6. **Post‑mortem**
   - Яку інформацію в логах не вистачало (наприклад, не було `gateway_code` або `correlation_id`)?  
   - Які додаткові логи або метрики варто додати, щоб наступного разу діагностика була швидшою?

> **Що проговорити на демонстрації:**  
> - як добре продуманий контекст у логах скорочує аналіз інциденту з годин до хвилин;  
> - що error tracking (Sentry) і лог‑агрегація доповнюють одне одного: перший показує “де й що падає”, другий дає деталі.



## 4. Блок 5 (завдання 5): Приклад коду структурованого логу → привʼязка до практики

**Загальна ідея:**  
Показати, як вимогу із завдання 5 («метод сервісу, що приймає paymentId, userId, amount, gatewayCode» і логує подію) можна реалізувати у вигляді окремого сервісного методу (п. 2.1 вже дає готовий шаблон).

Якщо ви вже використовуєте `PaymentService`, логування невдалого платежу може виглядати так:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\Api\V1\CreatePaymentDTO;
use App\Exceptions\InsufficientBalanceException;
use Illuminate\Support\Facades\Log;

final class LoggedPaymentService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly string $environment
    ) {
    }

    public function tryCreatePayment(CreatePaymentDTO $dto, string $correlationId): void
    {
        try {
            $payment = $this->paymentService->createPayment($dto);

            Log::info('Payment created', [
                'payment_id' => $payment->id,
                'user_id' => auth()->id(),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'correlation_id' => $correlationId,
                'env' => $this->environment,
            ]);
        } catch (InsufficientBalanceException $exception) {
            Log::warning('Payment declined: insufficient balance', [
                'user_id' => auth()->id(),
                'account_id' => $dto->accountId,
                'amount' => $dto->amount,
                'currency' => $dto->currency,
                'correlation_id' => $correlationId,
                'env' => $this->environment,
            ]);

            throw $exception;
        }
    }
}
```

> **Загальна ідея коду:**  
> - **успішний платіж** - `info` лог з `payment_id`;  
> - **недостатній баланс** - `warning` з контекстом;  
> - в обох випадках - `correlation_id` та `env` для подальшого аналізу в лог‑агрегаторі.



## 5. Примітки щодо docker-compose / середовища

- Для цього уроку спеціальні зміни в `docker-compose.yml` не потрібні: важливо лише, щоб застосунок логував у **stdout/stderr** або в окремий файл, який збирається інфраструктурою (наприклад, `docker compose logs`, log‑агрегатор).  
- Додаючи структуровані логи, переконайтеся, що форматтер Laravel‑логів (Monolog) налаштований на JSON для продакшн‑каналу.

> **Головна ідея практичної частини уроку 8.1:**  
> Логування - це **не “println для дебагу”**, а спроєктований шар спостережуваності: що логувати, на якому рівні, з яким контекстом і як це допоможе вам завтра, коли в продакшені щось піде не так.  
> Сконцентруйтесь на критичних фінансових сценаріях: створення/провал платежу, оновлення балансу, виклики зовнішніх платіжних API - там логи приносять найбільшу користь. 

