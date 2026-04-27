# Демо-інструкція: Урок 8.1 — Логування та моніторинг (з практикою API)

Цей файл — **покроковий сценарій для міта**: коли на слайдах з’являється «перехід на практику», повертайтеся сюди до відповідного блоку. Після демонстрації знову продовжуйте `lesson_8_1_slides.md`.

**Технічний стек демо:** усе через `docker-compose.yml`, тести PHPUnit — на **SQLite** (`phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). Зовнішні сервіси (Sentry, ELK тощо) **не підключаємо** — лише локальні логи контейнера.

---

## 0. Перед мітом (5 хв)

1. Переконайтеся, що Docker Desktop запущений.
2. У корені проєкту:
   ```bash
   cd /шлях/до/LaravelExample
   docker compose up -d
   ```
3. Міграції (якщо потрібно після змін схеми):
   ```bash
   docker compose exec php php artisan migrate --force
   ```
4. Швидка перевірка тестів API (SQLite у процесі тесту):
   ```bash
   docker compose exec php php artisan test tests/Feature/Api/V1/
   ```
5. Відкрийте три вкладки: `docs/8.1/lesson_8_1_slides.md`, цей файл, `docs/8.1/lesson_8_1_practice.md`.

---

## 1. Карта «слайди ↔ практика»

| Слайд у `lesson_8_1_slides.md` | Дія |
|--------------------------------|-----|
| **1–9** | Тільки теорія з конспекту `lesson_8_1.md`. |
| **10a** | Практика: розділ **1** у `lesson_8_1_practice.md` (таблиця подій/рівнів). |
| **11–13** | Теорія: структуровані логи, контекст, Correlation ID. |
| **13a** | Практика: розділ **2** у `lesson_8_1_practice.md` + **показ коду в репозиторії** (див. §3 нижче). |
| **14–18** | Теорія: error tracking, агрегація (без живого Sentry). |
| **19a** | Практика: розділ **3** у `lesson_8_1_practice.md` (інцидент). |
| **20–24** | Підсумок, типові помилки, ДЗ. |

---

## 2. Блок практики 10a (Слайд 10a) — ~3–5 хв

**Мета:** показати «карту подій» для payments/accounts.

1. Відкрийте `docs/8.1/lesson_8_1_practice.md`, розділ **«1. Блок 4: Події та рівні → Слайд 10a»**.
2. Прокоментуйте таблицю: **info** для успішних бізнес-подій, **warning** для контрольованих аномалій, **error/critical** для збоїв.
3. Коротко зв’яжіть з проєктом: після уроку в коді з’явилися `Log::info` після створення платежу/рахунку та middleware Correlation ID (деталі в §3–4).

**Повернення:** слайд 11 у `lesson_8_1_slides.md`.

---

## 3. Блок практики 13a (Слайд 13a) — ~5–7 хв

**Мета:** структурований лог + Correlation ID у Laravel.

### 3.1. Приклад з методички

У `lesson_8_1_practice.md`, розділ **2** — покажіть приклад `Log::error('Payment failed', [...])` та JSON-приклад (ідея полів для пошуку).

### 3.2. Що реально зроблено в проєкті (показати в IDE)

1. **Correlation ID для всіх API-запитів**  
   Файл: `app/Http/Middleware/AssignCorrelationId.php`  
   - Якщо клієнт передав заголовок `X-Correlation-ID` — використовується він; інакше генерується UUID.  
   - `Log::shareContext(['correlation_id' => ...])` — цей `correlation_id` **підмішується в усі наступні логи** в межах запиту.  
   - Той самий ID повертається у відповіді в заголовку `X-Correlation-ID`.  
   Підключення: `bootstrap/app.php` → `api(prepend: [AssignCorrelationId::class])`.

2. **Логи в сервісах (структурований контекст)**  
   - `app/Services/Api/V1/PaymentService.php` — після `createPayment`: `Log::info('Payment created', [...])`; після `deletePayment`: `Log::info('Payment deleted', [...])`.  
   - `app/Services/Api/V1/AccountService.php` — `Account created` / `Account deleted` з `account_id`, `balance` де доречно.

3. **Зв’язок із `processPayment`** (навчальний сценарій з тестів): метод знову приймає `CreatePaymentDTO`, перевіряє баланс через `AccountRepository` і створює платіж — це окремий шлях від REST `POST /api/v1/payments` (`createPayment`), але обидва шляхи узгоджені з доменом «платежі + рахунки».

**Що сказати учасникам:** «У лог-агрегаторі ви шукатимете за `correlation_id` і `payment_id` / `account_id`; сьогодні ми бачимо це в `docker compose logs`.»

**Повернення:** слайд 14.

---

## 4. Блок практики 19a (Слайд 19a) — ~4–6 хв

1. Відкрийте `lesson_8_1_practice.md`, розділ **«3. Блок 7: Сценарій інциденту → Слайд 19a»**.
2. Проговоріть кроки 1–6 (Sentry в теорії — «на уроці 8.2», тут лише як частина процесу).
3. Покажіть **на прикладі**: якби в логах були `correlation_id`, `gateway_code`, `endpoint` — як швидше знайти причину (без реального Sentry).

**Повернення:** слайд 20.

---

## 5. Технічний огляд API (для демонстрації після теорії або в кінці)

Усі ендпоінти під префіксом `/api/v1`. Ресурсні маршрути **без `update`** (PATCH/PUT вимкнені), як і для платежів: `routes/api.php`.

### 5.1. Рахунки (accounts) — за аналогією з payments

| Метод | Шлях | Опис |
|--------|------|------|
| GET | `/api/v1/accounts` | Список (пагінація) |
| POST | `/api/v1/accounts` | Створення (тіло: `balance`) |
| GET | `/api/v1/accounts/{id}` | Перегляд |
| DELETE | `/api/v1/accounts/{id}` | Видалення |

**Шари:** `AccountController` → `AccountService` → `AccountRepository` + `CreateAccountDTO` + `AccountStoreRequest` + `AccountResource`.

### 5.2. Платежі (payments)

| Метод | Шлях | Опис |
|--------|------|------|
| GET | `/api/v1/payments` | Список |
| POST | `/api/v1/payments` | Створення (`account_id`, `amount`, `currency`, `description?`) |
| GET | `/api/v1/payments/{id}` | Перегляд |
| DELETE | `/api/v1/payments/{payment}` | Видалення |

---

## 6. Curl / HTTP з хост-машини (контейнер `php` на порту 80)

Спочатку створіть рахунок, потім платіж (підставте `ID` з відповіді):

```bash
# Створити account
curl -sS -D - -X POST http://localhost/api/v1/accounts \
  -H "Content-Type: application/json" \
  -H "X-Correlation-ID: demo-meetup-001" \
  -d '{"balance":"5000.00"}'

# Список accounts
curl -sS http://localhost/api/v1/accounts -H "X-Correlation-ID: demo-meetup-002"

# Платіж (account_id = id щойно створеного рахунку)
curl -sS -D - -X POST http://localhost/api/v1/payments \
  -H "Content-Type: application/json" \
  -H "X-Correlation-ID: demo-meetup-003" \
  -d '{"account_id":1,"amount":"100.00","currency":"USD","description":"Demo"}'
```

У відповіді перевірте заголовок **`X-Correlation-ID`** — він збігається з переданим або згенерованим.

---

## 7. Логи через Docker

Під час демо виконайте кілька запитів (curl), потім:

```bash
# Останні записи з застосунку
docker compose logs php --tail=80

# Стежити в реальному часі (обережно з шумом)
docker compose logs php -f
```

**Що шукати в виводі:** рядки з `Payment created`, `Account created`, полем `correlation_id` (через `Log::shareContext` Monolog додасть контекст до запису залежно від формату каналу в `config/logging.php`; за замовчуванням це читабельний текст із масивом контексту).

Якщо потрібно **JSON у проді** — це тема уроку 8.3; на 8.1 достатньо показати наявність контексту та Correlation ID у логах контейнера.

---

## 8. Тести (SQLite) у контейнері

```bash
# Усі тести
docker compose exec php php artisan test

# Лише API v1
docker compose exec php php artisan test tests/Feature/Api/V1/
```

Очікування: зелені тести, зокрема `CreateAccount*`, `ListAccountsTest`, `DeleteAccountTest`, `ShowAccountNotFoundTest` та існуючі payment-тести.

---

## 9. Шпаргалка файлів (щоб швидко відкрити на міті)

| Тема | Файл |
|------|------|
| Correlation ID | `app/Http/Middleware/AssignCorrelationId.php`, `bootstrap/app.php` |
| Маршрути v1 | `routes/api.php` |
| Accounts API | `app/Http/Controllers/Api/V1/AccountController.php`, `app/Services/Api/V1/AccountService.php`, `app/Repositories/AccountRepository.php` |
| DTO / Request / Resource | `app/DTO/Api/V1/CreateAccountDTO.php`, `app/Http/Requests/Api/V1/Account/AccountStoreRequest.php`, `app/Http/Resources/AccountResource.php` |
| Payments + логи | `app/Services/Api/V1/PaymentService.php` |
| Тести accounts | `tests/Feature/Api/V1/CreateAccountTest.php`, `CreateAccountValidationTest.php`, `ShowAccountNotFoundTest.php`, `DeleteAccountTest.php`, `ListAccountsTest.php` |

---

## 10. Завершення міта (слайди 21–24)

Коротко повторіть «червону нить» з презентації: надійний фінансовий бекенд = код + тести + **спостережуваність** (логи, далі — Sentry і агрегація в 8.2–8.3).

Нагадайте ДЗ з `lesson_8_1.md` (проєктування подій, приклад `Log::error`, сценарій інциденту).

---

*Документ узгоджений з реалізацією в репозиторії на момент підготовки уроку 8.1.*
