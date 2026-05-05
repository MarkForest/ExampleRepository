# Практичні приклади до уроку 9.2 - Кешування та оптимізація роботи з базою даних

> **Загальна ідея практики:**  
> Закріпити **усвідомлене використання кешу** та оптимізацію БД на реальних фінансових сценаріях:  
> - кешування довідників з інвалідацією,  
> - репозиторій платежів без N+1 (eager loading + пагінація),  
> - аналіз endpoint’ів на предмет пагінації/кеша,  
> - вибір індексів та перший практичний крок оптимізації.

Laravel‑приклади легко адаптувати під Symfony/Doctrine (аналогичные концепции с join/Repository/CacheInterface).



## 1. Блок 2: Кеш довідника → Слайд 5a

**Загальна ідея прикладу:**  
Показати **безпечный и полезный** кейс кеширования - список валют, который редко меняется, но активно используется при фильтрации и валидации.

### 1.1. Сервис для валют с кешом и инвалидацией (Laravel)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class CurrencyService
{
    private const CACHE_KEY = 'currencies:list';
    private const TTL_SECONDS = 3600; // 1 час

    /**
     * Список валют (кешируется). Инвалидировать при изменении справочника.
     *
     * @return Collection<int, Currency>
     */
    public function getAll(): Collection
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::TTL_SECONDS,
            static function (): Collection {
                return Currency::query()
                    ->orderBy('code')
                    ->get();
            }
        );
    }

    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
```

> **Идея:**  
> - Любой код, который раньше делал `Currency::all()`, теперь должен звать `CurrencyService::getAll()`.  
> - После изменения валют в админке вызывается `invalidateCache()`, следующий вызов `getAll()` перезагружает данные из БД и снова кладёт их в кеш.



## 2. Блок 5: Репозиторій з eager loading → Слайд 14a

**Загальна ідея прикладу:**  
Реализовать репозиторий, который возвращает **список платежей по счету** без N+1: связи `account` и `user` подгружаются заранее, а результат **пагинируется**, чтобы контролировать объём.

### 2.1. Laravel: PaymentRepository с eager loading + пагинацией

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class PaymentRepository
{
    /**
     * Список платежей счёта с account и user (eager loading + пагинация).
     *
     * @return LengthAwarePaginator<Payment>
     */
    public function getByAccountIdPaginated(int $accountId, int $perPage = 20): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['account', 'user'])
            ->where('account_id', $accountId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Последние платежи для дашборда (ограниченный набор, можно кешировать отдельным слоем).
     *
     * @return Collection<int, Payment>
     */
    public function getLatestByAccountId(int $accountId, int $limit = 10): Collection
    {
        return Payment::query()
            ->with(['account:id,name', 'user:id,email'])
            ->where('account_id', $accountId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
```

### 2.2. Symfony/Doctrine: аналогичный репозиторий

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
final class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Список платежей счёта с account и user (JOIN + пагинация).
     *
     * @return Paginator<Payment>
     */
    public function getByAccountIdPaginated(int $accountId, int $page = 1, int $perPage = 20): Paginator
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.account', 'a')->addSelect('a')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->where('p.account = :accountId')
            ->setParameter('accountId', $accountId)
            ->orderBy('p.createdAt', 'DESC');

        $query = $qb->getQuery()
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Paginator($query);
    }
}
```

> **Что показать на демонстрации:**  
> - как в обоих примерах связи подгружаются заранее (нет ленивых `->account`/`->user` в цикле);  
> - как пагинация ограничивает объём данных и стабилизирует время ответа;  
> - как легко поверх этого добавить кеш (например, `getLatestByAccountId()` для дашборда).



## 3. Блок 2/7: Индексы и анализ списков endpoint’ов

**Загальна ідея:**  
Помочь сформировать список endpoint’ов, где **обязательно нужна пагинация** и, возможно, кеш, а также определить **кандидатов на индексы**.

### 3.1. Пример списка endpoint’ов и требований

```text
GET /api/v1/accounts/{id}/payments
  - Обязательно: пагинация, индексы по account_id, created_at
  - Можно кешировать: агрегаты (итоговая сумма за период), не сами платежи

GET /api/v1/accounts
  - Пагинация: желательно (если счетов может быть много)
  - Индексы: по пользовательскому id, статусу

GET /api/v1/reports/daily-summary
  - Необходима: генерация в job + кеш результата на 5–15 минут
  - Индексы: по дате операций, account_id
```

> **Что сделать на практике:**  
> - для своих endpoint’ов составить похожую табличку:  
>   - есть/нет пагинации;  
>   - можно/нельзя кешировать (и что именно);  
>   - какие индексы стоит проверить/добавить.



## 4. Блок 4/8: Кеш и инвалидация (код‑скелет) → связка с практикой задания 4

**Загальна ідея:**  
Показать общий **шаблон кеша с fallback на БД и явной инвалидацией**, который можно адаптировать для любого справочника/настроек.

### 4.1. Общий шаблон сервиса с кешем и инвалидацией (Laravel)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * @template T
 */
final class GenericCachedConfigService
{
    public function __construct(
        private readonly string $cacheKey,
        private readonly int $ttlSeconds
    ) {
    }

    /**
     * @param callable():T $resolver
     * @return T
     */
    public function get(callable $resolver)
    {
        /** @var T $value */
        $value = Cache::remember(
            $this->cacheKey,
            $this->ttlSeconds,
            static fn () => $resolver()
        );

        return $value;
    }

    public function invalidate(): void
    {
        Cache::forget($this->cacheKey);
    }
}
```

> **Идея:**  
> - можно создавать отдельные экземпляры для разных типов настроек (комиссии, лимиты), передавая ключ и TTL;  
> - логика получения из БД инкапсулируется в замыкании `$resolver`;  
> - инвалидация вызывается из админки или обработчиков событий при изменении настроек.



## 5. Общий вывод практики

> - **Кеш** нужен там, где есть измеримая тяжелая операция, и вы можете описать правила обновления (TTL/инвалидация/события).  
> - **Eager loading + пагинация** - это базовый must‑have для любых списков из больших таблиц (платежи, счета, транзакции), особенно в финансовом домене.  
> - **Индексы** и анализ SQL через профайлеры зачастую дают наибольший прирост производительности, по сравнению с локальными оптимизациями PHP‑кода.  
> - **Первый шаг оптимизации** должен быть конкретным и измеримым: меньше запросов к БД, более быстрый ответ эндпоинта, меньше нагрузки на БД.

Эти практические блоки подготовят почву для Урока 9.3, где вы будете смотреть на кэширование уже на уровне HTTP (Cache‑Control, ETag) и работу API под высокой нагрузкой. 

