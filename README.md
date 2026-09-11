                                               Property Offers API

REST API на Laravel 12 / PHP 8.2 / MySQL 8: асинхронний імпорт пропозицій житла від
постачальників, пошук найдешевшої актуальної пропозиції для кожного об'єкта та
безпечне бронювання.

Репозиторій: https://github.com/yurabh/property-offers-api

Стек

PHP 8.2 (`php:8.2-cli`, власний `Dockerfile`)
Фреймворк: Laravel 12
БД: MySQL 8.0 (контейнер 'mysql')
Черга: драйвер database (таблиця `jobs`)
Тести: PHPUnit 11, окрема схема `property_offers_test`

Запуск

Потрібен лише Docker - PHP і Composer живуть усередині контейнера app.

cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

API доступний на `http://localhost:8000`.

Міграції

docker compose exec app php artisan migrate

Сидери

Створюють двох постачальників - `supplier-a` і `supplier-b`:

docker compose exec app php artisan db:seed

Міграції й сидери разом, або перестворити базу з нуля:

docker compose exec app php artisan migrate --seed
docker compose exec app php artisan migrate:fresh --seed

Queue worker

Обробка імпорту виконується у джобі, тож воркер має бути запущений - інакше імпорти
лишаються в статусі `pending`:

docker compose exec app php artisan queue:work

Тести

docker compose exec app php artisan test

Тести проходять на MySQL (схема `property_offers_test`, створюється автоматично з
`docker/mysql/init.sql`), а не на SQLite — щоб реально перевірялися `SELECT ... FOR UPDATE`
і віконні функції, які інші движки емулюють інакше. Dev-дані тести не чіпають.

Запуск з IDE хостовим PHP (наприклад, PhpStorm): у `phpunit.xml` стоїть
`DB_HOST=mysql` - це ім'я сервісу, яке резолвиться лише всередині docker-мережі. Для
хоста задайте `DB_HOST=127.0.0.1` змінною середовища в конфігурації запуску тестів:
PHPUnit не перезаписує вже задані змінні, тож обидва способи працюють паралельно.

Консоль MySQL

docker compose exec mysql mysql -uroot -psecret property_offers

.env.example` не містить справжніх секретів: `DB_PASSWORD=secret` - це локальний
пароль контейнера MySQL із `docker-compose.yml`, за межами docker-мережі він не існує.

Ендпоінти

Усі запити - з заголовками `Content-Type: application/json` і `Accept: application/json`.
Без `Accept` Laravel відповідає на помилки валідації редиректом замість JSON `422`.

Приклади `expires_at` нижче взято дослівно з ТЗ (`2026-09-10T23:59:59Z`) - ця дата вже
минула, тож така пропозиція не з'явиться в пошуку й не забронюється. Для ручної
перевірки ставте дату в майбутньому.

POST /api/imports` → `202 Accepted

Валідує структуру, фіксує факт імпорту, ставить обробку в чергу і одразу відповідає.
Жодна пропозиція в HTTP-запиті не обробляється.

json
{
"supplier": "supplier-a",
"external_import_id": "import-2026-09-01-001",
"sent_at": "2026-09-01T10:00:00Z",
"offers": [
{
"external_id": "offer-a-10001",
"property": {
"code": "BCN-0001",
"name": "Apartment near Sagrada Familia",
"city": "Barcelona"
},
"check_in": "2026-10-10",
"check_out": "2026-10-15",
"max_guests": 4,
"price": 72500,
"currency": "EUR",
"available_units": 2,
"expires_at": "2026-09-10T23:59:59Z"
}
]
}

json
{
"data": {
"id": 15,
"status": "pending"
}
}

GET /api/imports/{import}` → `200 OK

json
{
"data": {
"id": 15,
"supplier": "supplier-a",
"external_import_id": "import-2026-09-01-001",
"sent_at": "2026-09-01T10:00:00Z",
"status": "completed",
"total_offers": 20,
"processed_offers": 20,
"error": null,
"created_at": "2026-09-01T10:00:02Z",
"completed_at": "2026-09-01T10:00:04Z"
}
}

Статуси: `pending` → `processing` → `completed` | `failed`.

GET /api/properties` → `200 OK

`GET /api/properties?city=Barcelona&check_in=2026-10-10&check_out=2026-10-15&guests=2&page=1`

Параметри: `check_in`, `check_out` (обов'язкові), `guests` (за замовчуванням 1),
`city`, `per_page` (1–100, за замовчуванням 15).

Пропозиція вважається актуальною, якщо дати збігаються з пошуком, `max_guests >= guests`,
`available_units > 0`, `expires_at > now()` і місто збігається (якщо передано).

json
{
"data": [
{
"code": "BCN-0001",
"name": "Apartment near Sagrada Familia",
"city": "Barcelona",
"best_offer": {
"id": 125,
"supplier": "supplier-a",
"price": 72500,
"currency": "EUR",
"available_units": 2,
"expires_at": "2026-09-10T23:59:59Z"
}
}
],
"links": {
"first": "...",
"last": "...",
"prev": null,
"next": "..."
},
"meta": {
"current_page": 1,
"per_page": 15,
"total": 42
}
}

POST /api/offers/{offer}/reservations` → `201 Created

`{offer}` - id пропозиції (його видно в `best_offer.id` у відповіді пошуку).

json
{
"client_reference": "web-order-9f782b1c",
"customer_name": "John Smith",
"customer_email": "john@example.com"
}

Відповідь `201 Created` - створене бронювання. Ціна й валюта фіксуються знімком на
момент бронювання:

json
{
"data": {
"id": 1,
"offer_id": 125,
"client_reference": "web-order-9f782b1c",
"customer_name": "John Smith",
"customer_email": "john@example.com",
"price": 72500,
"currency": "EUR",
"created_at": "2026-09-11T10:00:00Z"
}
}

Коди відповідей: `201` - бронювання створено; `200` - повтор із тим самим
`client_reference`, повертається вже створене бронювання; `409` - одиниць не
лишилось або пропозиція протермінована; `404` - невідома пропозиція; `422` - валідація.

Тіло відповіді `409`:

json
{
"message": "Offer has no available units left."
}

json
{
"message": "Offer has expired."
}

Структура коду

Бізнес-логіка живе в `app/Actions` — один клас на один use case, з єдиним публічним
методом `handle()`. Контролери й джоба лише передають у дію валідовані дані та
загортають результат у ресурс. Контролери, Form Requests і API Resources згруповані
за доменом так само: `Http/Controllers/Import`, `Http/Requests/Property`,
`Http/Resources/Reservation` тощо.

| Дія                                 | Хто викликає                                                |
|-------------------------------------|-------------------------------------------------------------|
| `Actions\Import\RegisterImport`     | `POST /api/imports` - фіксує імпорт і ставить джобу в чергу |
| `Actions\Import\ProcessImport`      | `ProcessImportJob` - розбирає пропозиції                    |
| `Actions\Property\SearchProperties` | `GET /api/properties`                                       |
| `Actions\Reservation\ReserveOffer`  | `POST /api/offers/{offer}/reservations`                     |

Схема бази

```
suppliers ──< imports ──< offers >── properties
                            │
                            └──< reservations
```

Ціни зберігаються в мінорних одиницях (`72500` = 725.00 EUR) — жодної арифметики
з плаваючою комою.

Ключі та індекси підібрані під конкретні запити, а не «про всяк випадок»:

| Таблиця        | Ключ / індекс                                    | Навіщо                                             |
|----------------|--------------------------------------------------|----------------------------------------------------|
| `suppliers`    | `unique(code)`                                   | постачальник приходить рядком, це природний ключ   |
| `properties`   | `unique(code)`                                   | `firstOrCreate` по `property.code` під час імпорту |
| `properties`   | `index(city)`                                    | фільтр пошуку                                      |
| `imports`      | `unique(supplier_id, external_import_id)`        | ідемпотентність імпорту                            |
| `offers`       | `unique(supplier_id, external_id)`               | ідемпотентність пропозиції (`updateOrCreate`)      |
| `offers`       | `index(check_in, check_out, property_id, price)` | план «від offers» - коли `city` не передано        |
| `offers`       | `index(property_id, check_in, check_out, price)` | план «від properties» - коли `city` звужує вибірку |
| `reservations` | `unique(client_reference)`                       | ідемпотентність бронювання                         |

Два складені індекси на `offers` - не дублікати: MySQL обирає різні плани залежно від
того, чи переданий `city`. Перевірено `EXPLAIN` на 10 000 об'єктів / 50 000 пропозицій:

| Запит              | Ведуча таблиця                             | Обраний ключ на `offers`             | Рядків прочитано |
|--------------------|--------------------------------------------|--------------------------------------|------------------|
| з `city=Barcelona` | `properties` через `properties_city_index` | `offers_property_idx` (`key_len` 14) | ~1 на об'єкт     |
| без `city`         | `offers`                                   | `offers_search_idx` (`key_len` 6)    | ~17 900          |

Прибрати будь-який із них не можна: без `offers_property_idx` запит із містом
втратив би точковий доступ до пропозицій конкретного об'єкта, без `offers_search_idx`
запит без міста впав би у full scan.

При цьому `EXPLAIN` показує `Using temporary; Using filesort` на підзапиті: MySQL 8
матеріалізує й сортує проміжний результат для віконної функції навіть тоді, коли
індекс уже дає потрібний порядок. Тобто індекси тут економлять саме читання рядків,
а не сортування.

`max_guests`, `available_units` та `expires_at` у складені індекси не входять: це три
діапазонні умови, і після рівності по датах індекс на них усе одно не спрацює -
вони відсіюються index condition pushdown'ом. Окремі індекси на FK-колонки не
створюються, бо InnoDB робить їх під foreign key автоматично (а `offers.supplier_id`
покривається лівим префіксом унікального ключа).

Пошук виконується базою, а не PHP

`app/Actions/Property/SearchProperties.php` будує один запит: підзапит ранжує пропозиції
всередині кожного об'єкта через `ROW_NUMBER() OVER (PARTITION BY property_id ORDER BY price, id)`,
зовнішній запит бере рядки з `rn = 1`. Сортування й пагінація - теж на боці MySQL.
Колекції PHP нічого не групують.

Фільтр по місту стоїть усередині підзапиту: інакше вікна рахувалися б по всіх
містах, а зайве відкидалося б уже після обчислення.

Ідемпотентність імпорту

Три незалежні рівні:

1. Унікальність імпорту. `unique(supplier_id, external_import_id)`. `RegisterImport`
   робить `firstOrCreate` по цій парі, а гонку двох одночасних запитів ловить як
   порушення унікального ключа і просто перечитує наявний рядок.
2. Одноразовий диспатч. `ProcessImportJob` ставиться в чергу лише якщо запис
   щойно створено (`wasRecentlyCreated`). Повторний `POST` з тим самим
   `external_import_id` повертає той самий `id` і не запускає обробку вдруге.
   Додатково джоба реалізує `ShouldBeUnique` по `import_id`, а на вході перевіряє,
   що імпорт ще не `completed`.
3. Унікальність пропозиції. `unique(supplier_id, external_id)` + `updateOrCreate`.
   Якщо пропозиція вже існує (навіть з іншого імпорту), її дані оновлюються, дубль
   не створюється. Завдяки цьому повторний запуск джоби після збою безпечний.

Пропозиція оновлюється завжди, коли приходить у новому імпорті - виграє той
імпорт, який оброблено останнім. Від дублювання захищає не порядок обробки, а
унікальний ключ `unique(supplier_id, external_id)`.

Помилка обробки → статус `failed` і текст у полі `error`; після успіху — `completed`
і `completed_at`. Джоба має до трьох спроб (`$tries = 3`): на повторній спробі статус
знову стає `processing`, а остаточний `failed` фіксується після вичерпання спроб.
Лічильник `processed_offers` оновлюється по ходу, тож прогрес видно через
`GET /api/imports/{id}`.

Захист від двох одночасних бронювань останньої одиниці

Основний механізм - песимістичне блокування рядка в транзакції
(`app/Actions/Reservation/ReserveOffer.php`):


private function reserveLockedUnit(Offer $offer, array $data): Reservation
{
    return DB::transaction(function () use ($offer, $data) {
        $locked = $this->lockOffer($offer);   // SELECT ... FOR UPDATE
        $this->assertBookable($locked);       // протермінована або 0 одиниць → 409
        $locked->decrement('available_units');
        return $this->createReservation($locked, $data);
    });
}

`lockForUpdate()` — це `SELECT ... FOR UPDATE`. Перший запит блокує рядок пропозиції
до кінця транзакції. Другий, що прийшов одночасно, чекає на цьому ж рядку і
продовжує лише після коміту першого — тобто читає вже зменшений `available_units`,
бачить `0` і отримує `409 Conflict`. Перевірка й декремент відбуваються всередині
однієї транзакції, тож класичного «read-then-write» вікна тут немає.

Другий рівень - `unique(client_reference)`: якщо клієнт ретраїть той самий запит (мережевий таймаут, повторний
клік), другого бронювання не з'явиться навіть при  повній одночасності - унікальний ключ відсіче дубль,
і у відповідь повернеться вже створене бронювання з кодом `200`.

Окремий тест із паралельними процесами не писався (за умовою не обов'язковий),
але поведінка «останню одиницю можна забрати рівно один раз» покрита у
`tests/Feature/ReservationTest.php`.

Тести

`tests/Feature/`:

| Файл                   | Що перевіряє                                                                                                                                                                                      |
|------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `ImportSubmissionTest` | 202 і постановка в чергу, ідемпотентність повторного POST, розділення за постачальниками, валідація                                                                                               |
| `ProcessImportJobTest` | створення об'єктів і пропозицій, оновлення пропозиції з іншого імпорту, оновлення пропозиції незалежно від `sent_at` (виграє останній оброблений імпорт), повторний запуск джоби, статус `failed` |
| `ImportStatusTest`     | формат відповіді про стан імпорту, 404                                                                                                                                                            |
| `PropertySearchTest`   | найдешевша пропозиція на об'єкт, відсів неактуальних, фільтр по місту, сортування й пагінація, валідація                                                                                          |
| `ReservationTest`      | 201 і списання одиниці, повтор `client_reference`, 409 на sold out і протерміновану, остання одиниця, 404, валідація                                                                              |
| `SupplierSeederTest`   | сидер створює `supplier-a` і `supplier-b` та не дублює їх                                                                                                                                         |
