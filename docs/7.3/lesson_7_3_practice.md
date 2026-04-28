# Практичні приклади до уроку 7.3 - Документування API та автоматизоване тестування (HTTP / Feature tests)

> **Загальна ідея практики:**  
> На прикладі ресурсу **payments** показати, як:  
> 1) формально описати REST‑контракт у **OpenAPI** (ендпоінти, схеми, помилки),  
> 2) **Swagger UI** використати як живу документацію,  
> 3) написати **Feature‑тести** (успішні кейси й помилки) у Laravel,  
> 4) запускати їх у Docker/CI як частину перевірки контракту.  
> Приклади відрізняються від коду в основних уроках, але сумісні з архітектурою Module 7.



## 1. Блок 2: OpenAPI фрагмент → Слайд 6a

**Загальна ідея прикладу:**  
Показати невеликий, але реалістичний фрагмент **OpenAPI‑специфікації** для `POST /api/v1/payments` та `GET /api/v1/payments/{id}`: шляхи, схеми запиту/відповіді та помилок.

### 1.1. Приклад `openapi.yaml` (фрагмент)

```yaml
openapi: 3.0.3
info:
  title: Financial API
  version: 1.0.0

paths:
  /api/v1/payments:
    post:
      summary: Create a new payment
      tags:
        - Payments
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreatePaymentRequest'
      responses:
        '201':
          description: Payment created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Payment'
        '422':
          description: Validation error
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ValidationError'

  /api/v1/payments/{id}:
    get:
      summary: Get payment by ID
      tags:
        - Payments
      parameters:
        - in: path
          name: id
          required: true
          schema:
            type: integer
          description: Payment identifier
      responses:
        '200':
          description: Payment details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Payment'
        '404':
          description: Payment not found
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'

components:
  schemas:
    CreatePaymentRequest:
      type: object
      required:
        - account_id
        - amount
        - currency
      properties:
        account_id:
          type: integer
          example: 10
        amount:
          type: string
          pattern: '^\d+(\.\d{1,2})?$'
          example: '250.00'
        currency:
          type: string
          enum: [UAH, USD, EUR]
          example: 'USD'
        description:
          type: string
          maxLength: 500
          example: 'Оплата рахунку №123'

    Payment:
      type: object
      properties:
        id:
          type: integer
          example: 123
        account_id:
          type: integer
          example: 10
        amount:
          type: string
          example: '250.00'
        currency:
          type: string
          example: 'USD'
        description:
          type: string
          example: 'Оплата рахунку №123'
        status:
          type: string
          example: 'processed'
        created_at:
          type: string
          format: date-time
          example: '2026-02-12T10:00:00Z'

    ValidationError:
      type: object
      properties:
        message:
          type: string
          example: 'The given data was invalid.'
        errors:
          type: object
          additionalProperties:
            type: array
            items:
              type: string

    Error:
      type: object
      properties:
        message:
          type: string
          example: 'Payment not found.'
```

> **Що показати на демонстрації:**  
> - як OpenAPI‑опис напряму відповідає DTO/Resource/422‑формату з 7.2;  
> - як клієнт за цим фрагментом може згенерувати клієнтський код або форму валідації;  
> - як окремо описуються успіх (201/200) і помилки (422/404).

**Команди / процес для демонстрації**

- **Файл OpenAPI** (`openapi.yaml`) у стандартному Laravel **не генерується** `php artisan`; створіть його **вручну** (наприклад `openapi.yaml` у корені проєкту або `docs/openapi.yaml`) і підтримуйте в репозиторії поряд із кодом.

- **Перевірка синтаксису специфікації** (не Artisan; за бажанням, якщо встановлений Node):

```bash
npx @redocly/cli lint openapi.yaml
```

- **Перегляд локально** разом зі Swagger UI (після кроків з блоку 2): запуск HTTP‑сервера додатку:

```bash
php artisan serve
```

*(Symfony: OpenAPI часто тримають у `config/openapi.yaml` або `public/docs`; генерація з атрибутів — окремі пакети `nelmio/api-doc-bundle` тощо — за документацією пакета, не базова команда `bin/console`.)*

## 2. Блок 3: Swagger UI → Слайд 9a

**Загальна ідея прикладу:**  
Пояснити, як **Swagger UI** можна підключити до вашого `openapi.yaml` і використовувати як живу документацію, не заглиблюючись у деталі пакета.

### 2.1. Мінімальний сценарій використання Swagger UI (без привʼязки до пакета)

1. Зібрати або завантажити статичний Swagger UI (наприклад, з офіційного репозиторію `swagger-api/swagger-ui`).
2. Покласти `dist` у публічну директорію (наприклад, `public/docs/api`).
3. У `index.html` Swagger UI вказати шлях до вашого `openapi.yaml`:

```js
const ui = SwaggerUIBundle({
  url: "/openapi.yaml",
  dom_id: "#swagger-ui",
  presets: [SwaggerUIBundle.presets.apis],
  layout: "BaseLayout",
});
```

4. Віддавати `openapi.yaml` або як статичний файл з кореня проекту, або через окремий роут.

> **Що пояснити:**  
> - що Swagger UI - це лише фронт для вашого `openapi.yaml`;  
> - як фронтенд/партнери можуть самі «гратися» з API через UI, не дивлячись у код.

**Команди / процес для демонстрації**

- **Swagger UI** (статичні `html/js/css`) — **без Artisan**: зібрати `dist` з репозиторію [swagger-api/swagger-ui](https://github.com/swagger-api/swagger-ui), покласти, наприклад, у `public/docs/swagger-ui/`, у `index.html` вказати `url` на ваш `openapi.yaml` (як у п. 2.1).

- **Віддача `openapi.yaml`** — або як файл у `public/openapi.yaml` (**вручну**), або через **маршрут** у `routes/web.php` / контролер (**вручну**); окремої `php artisan make:openapi` у ядрі Laravel немає.

- **Запуск додатку** для перевірки в браузері:

```bash
php artisan serve
```

Далі відкрити в браузері сторінку з Swagger UI (наприклад `http://127.0.0.1:8000/docs/swagger-ui/index.html` — залежить від того, куди поклали файли).

*(Альтернатива з Composer, якщо обираєте пакет: наприклад `composer require darkaonline/l5-swagger` і публікація конфігу — за README пакета; це вже не «мінімальний сценарій без пакета».)*

## 3. Блок 5: Feature test create payment → Слайд 12a

**Загальна ідея прикладу:**  
Написати **Feature‑тест** для успішного створення платежу: `POST /api/v1/payments` з валідними даними, перевірка статусу й структури JSON.

### 3.1. Тест успішного створення платежу

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreatePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_payment_with_valid_data(): void
    {
        // Arrange
        /** @var Account $account */
        $account = Account::factory()->create([
            'balance' => 1000.00,
        ]);

        $payload = [
            'account_id' => $account->id,
            'amount' => '250.00',
            'currency' => 'USD',
            'description' => 'Оплата сервісу',
        ];

        // Act
        $response = $this->postJson('/api/v1/payments', $payload);

        // Assert
        $response
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'account_id',
                    'amount',
                    'currency',
                    'description',
                    'status',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.amount', '250.00')
            ->assertJsonPath('data.currency', 'USD');

        $this->assertDatabaseHas('payments', [
            'account_id' => $account->id,
            'amount' => '250.00',
            'currency' => 'USD',
        ]);
    }
}
```

> **Що показати на демонстрації:**  
> - як тест відображає контракт (201 + `data.*` поля);  
> - як `assertJsonStructure`/`assertJsonPath` фіксують формат;  
> - як `assertDatabaseHas` перевіряє побічний ефект.

**Команди / процес для демонстрації**

- **Feature‑тест** (PHPUnit), namespace як у прикладі:

```bash
php artisan make:test Api/V1/CreatePaymentTest
```

Якщо команда створює файл не в `tests/Feature/Api/V1/`, перенесіть каталог **вручну** або використайте шлях, який підтримує ваша версія `make:test` (наприклад `php artisan make:test Feature/Api/V1/CreatePaymentTest`).

- **Модель і фабрика** `Account` (у тесті використовується `Account::factory()`):

```bash
php artisan make:model Account -mf
php artisan make:factory AccountFactory --model=Account
```

Допишіть поля фабрики/міграції (`balance` тощо) **вручну** за вашою схемою БД.

- **Модель / міграція** `Payment`, якщо ще немає:

```bash
php artisan make:model Payment -mf
php artisan migrate
```

- **API, яке тестується**, має бути реалізоване (маршрути, контролер, FormRequest тощо — уроки 7.1–7.2); маршрути в `routes/api.php` — **вручну**.

Запуск лише цього тесту:

```bash
php artisan test tests/Feature/Api/V1/CreatePaymentTest.php
```

або всі Feature:

```bash
php artisan test --testsuite=Feature
```

*(Symfony: `php bin/console make:test WebTestCase` або PHPUnit; фікстури/фабрики — DoctrineFixturesBundle / власні фабрики; `php bin/console doctrine:migrations:migrate --env=test`.)*

## 4. Блок 6: Feature tests errors → Слайд 14a

**Загальна ідея прикладу:**  
Написати **два Feature‑тести** для помилок:  
1) 422 - валідація `POST /api/v1/payments` з невалідними даними,  
2) 404 - `GET /api/v1/payments/{id}` для неіснуючого платежу.

### 4.1. Тест 422 - помилка валідації

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreatePaymentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_422_when_amount_is_invalid(): void
    {
        $payload = [
            'account_id' => null,
            'amount' => '-10.00',
            'currency' => 'BTC',
            'description' => 'Test',
        ];

        $response = $this->postJson('/api/v1/payments', $payload);

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'account_id',
                    'amount',
                    'currency',
                ],
            ]);
    }
}
```

### 4.2. Тест 404 - платіж не знайдено

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShowPaymentNotFoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_404_when_payment_not_found(): void
    {
        $response = $this->getJson('/api/v1/payments/999999');

        $response
            ->assertNotFound()
            ->assertJsonStructure([
                'message',
            ]);
    }
}
```

> **Що показати на демонстрації:**  
> - як тести гарантують, що API завжди повертає 422 з `errors` і 404 з `message`;  
> - що будь‑яка зміна формату (наприклад, відсутність `errors`) буде одразу виявлена.

**Команди / процес для демонстрації**

```bash
php artisan make:test Api/V1/CreatePaymentValidationTest
php artisan make:test Api/V1/ShowPaymentNotFoundTest
```

(За потреби скоригуйте шлях під фактичну структуру `tests/Feature/...`, як у блоці 3.)

- Логіка ендпоінтів `POST /api/v1/payments` і `GET /api/v1/payments/{id}` уже має бути в проєкті; **інтерфейси / сервіси** для цих тестів окремо не генеруються — тест б’є по HTTP.

Запуск обох класів або всього suite Feature:

```bash
php artisan test tests/Feature/Api/V1/CreatePaymentValidationTest.php
php artisan test tests/Feature/Api/V1/ShowPaymentNotFoundTest.php
```

## 5. Блок 7: Docker/CI → Слайд 17a

**Загальна ідея прикладу:**  
Показати, як запускати Feature‑тести у Docker та інтегрувати їх у CI як обов’язковий крок.

### 5.1. Запуск Feature‑тестів у Docker

```bash
docker compose exec app php artisan test --testsuite=Feature
```

або:

```bash
docker compose exec app ./vendor/bin/phpunit --testsuite=Feature
```

### 5.2. Приклад кроку в CI (умовний YAML)

```yaml
steps:
  - name: Install dependencies
    run: composer install --no-interaction --prefer-dist

  - name: Run Feature tests
    run: php artisan test --testsuite=Feature
```

> **Що пояснити:**  
> - що тести мають запускатися в тому ж середовищі, що й додаток (PHP‑версія, розширення, БД);  
> - що падіння Feature‑тестів блокує merge і не дає «тихо» зламати контракт.

**Команди / процес для демонстрації**

У контейнері (як у прикладі п. 5.1):

```bash
docker compose exec app php artisan test --testsuite=Feature
```

або:

```bash
docker compose exec app ./vendor/bin/phpunit --testsuite=Feature
```

**Локально** (без Docker):

```bash
composer install --no-interaction
php artisan test --testsuite=Feature
```

Підготовка залежностей перед тестами в CI зазвичай така ж, як у п. 5.2 (`composer install`), далі — `php artisan test --testsuite=Feature`.

## 6. Примітки щодо середовища

- Використовуйте окрему тестову БД (наприклад, SQLite in‑memory або окрему MySQL) через `.env.testing` / `phpunit.xml`.  
- У тестах завжди застосовуйте `RefreshDatabase` або аналог, щоб ізолювати дані між тестами.  
- Інтегруйте запуск тестів у локальний сценарій перед commit/push (наприклад, `composer test`), а також у CI.

**Команди / процес для демонстрації**

- Налаштування тестового оточення (файли **вручну**): скопіювати/створити `.env.testing`, перевірити `phpunit.xml` (зокрема `DB_CONNECTION` для SQLite in-memory тощо).

- Застосувати міграції в тестовому оточенні (зазвичай `RefreshDatabase` у тестах робить це автоматично); за потреби явно:

```bash
php artisan migrate --env=testing
```

- Запуск Feature‑тестів після змін у коді або конфігу:

```bash
php artisan config:clear
php artisan test --testsuite=Feature
```

- Якщо у `composer.json` є скрипт `test`:

```bash
composer test
```

> **Головна ідея практичної частини уроку 7.3:**  
> **OpenAPI** формально описує, *як має поводитися* ваш фінансовий API, а **Feature‑тести** щодня перевіряють, *що він так і поводиться*. Разом вони перетворюють API з крихкого набору ендпоінтів у **надійний контракт**, якому можуть довіряти frontend, мобільні клієнти та зовнішні партнери. 

