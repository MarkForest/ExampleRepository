# Практичні приклади до уроку 8.3 - Лог-агрегація, структуровані логи та управління інцидентами

> **Загальна ідея практики:**  
> Показати на фінансовому прикладі, як мислити логами на рівні всієї системи:  
> - як уявити потік логів у docker‑оточенні,  
> - як спроєктувати структуру лог‑повідомлень для критичних подій (невдалий платіж),  
> - як використовувати лог‑агрегацію в сценаріях інцидентів,  
> - як correlation_id проходить через увесь платіжний флоу.  
> Конкретна інфраструктура (Elasticsearch/Kibana, Loki тощо) може відрізнятися, але принципи залишаються однаковими.



## 1. Блок 2: Потік логів у Docker → Слайд 6a

**Загальна ідея прикладу:**  
На концептуальному рівні показати, як логи з Laravel‑контейнера потрапляють у централізовану систему логування через stdout та збирач.

### 1.1. Спрощена схема `docker-compose` (концептуально)

```yaml
version: "3.9"

services:
  app:
    image: my-financial-app:latest
    container_name: financial_app
    environment:
      APP_ENV: production
      LOG_CHANNEL: stdout
    depends_on:
      - db
    # Важливо: логи йдуть у stdout/stderr

  db:
    image: mysql:8.0
    container_name: financial_db
    environment:
      MYSQL_DATABASE: app
      MYSQL_USER: app
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: secret

  log-collector:
    image: some-log-collector:latest   # наприклад, Filebeat/Fluentd або стуб
    container_name: log_collector
    # Цей сервіс читає stdout контейнера app (через docker socket або log‑driver)
    # і відправляє події в бекенд лог-агрегації (Elasticsearch/CloudWatch/Loki).
```

> **Що проговорити:**  
> - додаток (`app`) пише логи у stdout (канал `stdout` в Laravel/Monolog);  
> - `log-collector` читає stdout усіх контейнерів і шле записи в центральне сховище;  
> - розробник не працює з файлами всередині контейнера, а з UI (Kibana/Grafana) поверх агрегованих логів.



## 2. Блок 3: Структура лог‑повідомлення → Слайд 9a

**Загальна ідея прикладу:**  
Сформувати **еталонну структуру** одного лог‑повідомлення для події «невдале проведення платежу через таймаут шлюзу», яку буде легко індексувати та шукати в лог‑агрегації.

### 2.1. Пропонована структура (поля)

Обовʼязкові поля (верхній рівень):
- `level`: рівень логування (`error`);
- `message`: короткий опис події;
- `timestamp`: час у ISO 8601;
- `env`: середовище (`production`, `staging`).

Контекст (окремий об’єкт або плоскі поля):
- `correlation_id`: унікальний id запиту;
- `payment_id`: ідентифікатор платежу;
- `user_id`: id користувача;
- `account_id`: id рахунку;
- `endpoint`: HTTP‑метод + шлях (`POST /api/v1/payments/{id}/capture`);
- `gateway`: назва платіжного шлюзу;
- `gateway_code`: код помилки (наприклад, `TIMEOUT`);
- `attempt`: номер спроби;
- опційно: `amount`, `currency`.

### 2.2. Приклад JSON‑запису

```json
{
  "level": "error",
  "message": "Payment capture failed due to gateway timeout",
  "timestamp": "2026-02-12T12:00:00.000000Z",
  "env": "production",
  "correlation_id": "req-abc-123",
  "payment_id": 456,
  "user_id": 123,
  "account_id": 78,
  "endpoint": "POST /api/v1/payments/456/capture",
  "gateway": "some_gateway",
  "gateway_code": "TIMEOUT",
  "attempt": 3,
  "amount": "250.00",
  "currency": "USD"
}
```

### 2.3. Приклад коду в Laravel (Log::error з контекстом)

```php
<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;

final class PaymentGatewayLogger
{
    public function logTimeout(
        int $paymentId,
        int $userId,
        int $accountId,
        string $amount,
        string $currency,
        string $gateway,
        int $attempt,
        string $correlationId
    ): void {
        Log::error('Payment capture failed due to gateway timeout', [
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'account_id' => $accountId,
            'endpoint' => 'POST /api/v1/payments/' . $paymentId . '/capture',
            'gateway' => $gateway,
            'gateway_code' => 'TIMEOUT',
            'attempt' => $attempt,
            'amount' => $amount,
            'currency' => $currency,
            'correlation_id' => $correlationId,
        ]);
    }
}
```

> **Що пояснити на демонстрації:**  
> - як ця структура дозволяє в Kibana/Loki легко робити запити: за payment_id, gateway_code, endpoint, correlation_id;  
> - чому важливо мати єдину структуру для всіх сервісів, щоб один запит міг працювати на всіх логах.



## 3. Блок 5: Сценарій інциденту → Слайд 13a

**Загальна ідея прикладу:**  
Розглянути сценарій інциденту «масові 500 на POST /api/v1/payments» і показати, як лог‑агрегація використовується на кожному етапі.

### 3.1. Сценарій

> Моніторинг показує різкий ріст 500‑відповідей на `POST /api/v1/payments` за останні 10 хвилин. Sentry також показує новий issue або сплеск існуючого exception, повʼязаного з платіжним модулем.

### 3.2. Кроки розслідування через лог‑агрегацію

1. **Підтвердження інциденту**
   - Відкрити дашборд логів, подивитися кількість `level=error` по endpoint `POST /api/v1/payments` за останні 10–15 хвилин.
   - Переконатися, що це не одиничний спайк, а стійкий тренд.

2. **Локалізація**
   - Знайти кілька останніх логів з подією `Payment capture failed` або подібним message.
   - Взяти один `correlation_id` і побудувати повну хронологію запиту: від входу до помилки.
   - Дивитися, на якому етапі з’являється проблема: валідація, сервіс, шлюз, БД.

3. **Аналіз масштабу**
   - Порахувати, скільки унікальних `payment_id` і `user_id` мають такі помилки за годину.
   - Визначити, чи це всі платежі, чи лише певний підмножина (наприклад, певна валюта або шлюз).

4. **Тимчасові дії**
   - Якщо проблема в одному шлюзі - переключитися на резервний (якщо передбачено) або вимкнути певний тип платежів;
   - Якщо проблема в новому релізі логіки - розглянути rollback;
   - Повідомити підтримку/клієнтів про тимчасові труднощі (якщо потрібно).

5. **Пост‑мортем**
   - Зібрати факти з логів (час початку, типи помилок, кількість постраждалих платежів/користувачів);
   - Задокументувати причину (баг у коді, зміни в API шлюзу, інфраструктурна проблема) і рішення;
   - Виробити дії, щоб уникнути повторення (додати нові логи, алерти, тести).

> **Що проговорити:**  
> - без централізованої лог‑агрегації ці кроки перетворюються на хаос із SSH та grep’ом;  
> - з нею інцидент‑менеджмент базується на даних, а не на здогадках.



## 4. Блок 4: Correlation ID у потоці → привʼязка до middleware (з уроку)

**Загальна ідея:**  
Показати, як correlation_id, встановлений у middleware, з’являється у всіх логах і відповіді, дозволяючи зв’язати дані Sentry, лог‑агрегації й клієнта.

### 4.1. Коротке нагадування middleware CorrelationIdMiddleware

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-ID') ?? Str::uuid()->toString();

        $request->attributes->set('correlation_id', $correlationId);
        Log::withContext(['correlation_id' => $correlationId]);

        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
```

> **Ідея:**  
> - будь‑який лог усередині цього запиту автоматично містить correlation_id;  
> - клієнт отримує correlation_id у заголовку відповіді і може надіслати його в підтримку;  
> - у Sentry ми можемо додавати correlation_id у `extra`/`tags`, щоб звʼязати exception з логами.



## 5. Примітки щодо середовища / docker-compose

- Для навчальних цілей достатньо розуміти, що ваш Laravel/Symfony додаток пише **структуровані логи** у stdout, а далі це забирає збирач логів і кладе у лог‑агрегацію (Elasticsearch/Kibana, Loki, Datadog тощо).  
- У реальному проєкті реалізацією збирача та стеку займається DevOps/інфраструктурна команда; вам важливо забезпечити **якісний вміст** логів: структуру, контекст, correlation_id, коректні рівні.

> **Головна ідея практичної частини уроку 8.3:**  
> Лог‑агрегація - це не лише «місце, де складаються всі логи», а **інструмент аналізу та інцидент‑менеджменту**. Структуровані логи з correlation_id, payment_id, user_id, endpoint та gateway дають змогу швидко відновити будь‑який фінансовий сценарій, оцінити масштаб інциденту і побудувати надійний процес реагування.  
> Навіть якщо зараз у вас немає повноцінного Elasticsearch/Kibana, варто вже сьогодні проектувати логи так, ніби вони там є. Це спростить майбутній перехід до повноцінної лог‑агрегації. 

