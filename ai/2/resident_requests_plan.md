# EPIC2 — план реализации обращений жителей

Дата проверки: 2026-11-14. Основание: `AGENTS.md`, `ai/EPIC2.md`, текущий код и тесты; план актуализирован по HEAD `900e813` (`DTOs`).

Статус: планирование завершено, реализация не начата. В рамках подготовки создан только этот документ. Уже существующие изменения пользователя (`ai/EPIC.md` → `ai/EPIC1.md`, новый `ai/EPIC2.md`) сохраняются.

## 1. Что уже реализовано

| Область | Фактическое состояние и файлы |
| --- | --- |
| Модульная структура | Есть `app/Modules/Identity` и `app/Modules/Communities`; контроллеры тонкие, действия имеют метод `handle()`, зависимости передаются через DI. |
| Сессионная авторизация | `Identity/Models/User.php`, `Actions/AuthenticateUser.php`, `Actions/LogoutUser.php`, `Http/Requests/LoginRequest.php`, `Http/Controllers/AuthenticatedSessionController.php`. Есть регенерация сессии, logout, limiter входа, стандартная CSRF-защита web-маршрутов. |
| Сообщества и роли | `Communities/Models/Community.php`, `CommunityMember.php`, `Enums/CommunityRole.php`; роль находится в членстве, а не в User. Миграции и factories существуют. |
| Контекст tenant | `Communities/CurrentCommunity.php` зарегистрирован как scoped в `app/Providers/AppServiceProvider.php`. `ResolveCommunity.php` проверяет актуальное членство и очищает контекст в `finally`. Отсутствие членства даёт 404. |
| Авторизация сообщества | `Communities/Policies/CommunityPolicy.php`, явная регистрация через Gate; обзор доступен участникам, список участников — manager. |
| DTO результатов выборок | `Communities/Data/CommunityListItemData.php` и `CommunityMemberData.php`: `final readonly`, типизированные публичные свойства через constructor promotion, enum внутри DTO и явный `toArray()` с array-shape PHPDoc. `ListUserCommunities`/`ListCommunityMembers` возвращают списки DTO; контроллеры преобразуют их в массивы внутри ленивых Inertia props. |
| Маршруты | `routes/web.php`: login/logout, список сообществ, dashboard и members. Проверенный `route:list` показывает семь маршрутов приложения, маршрутов Requests нет. |
| Inertia-контракт | `app/Http/Middleware/HandleInertiaRequests.php`: явные безопасные массивы, текущий пользователь, community и именованные URL. Контекст и ссылки обновляются также при partial reload через `always`. |
| Интерфейс | `resources/js/layouts/AuthenticatedLayout.vue`, страницы Auth/Communities, TypeScript-типы. Есть адаптивность, dark mode, состояния форм. На `Communities/Dashboard.vue` обращения представлены только заглушкой «Скоро». |
| Демоданные | `database/seeders/DatabaseSeeder.php`: два сообщества, пять пользователей, шесть членств, повторный запуск без дубликатов. `multi@example.test` — resident в `sosnovy` и manager в `lesnoy`. |
| Тестовая инфраструктура | PHPUnit, PostgreSQL `saas_test_testing`, защита от запуска на рабочей БД в `tests/TestCase.php`, `RefreshDatabase`, factories. Есть Feature-тесты Identity, Communities, Inertia и Database. |
| Проверки frontend | `package.json`: `typecheck` и `build`; build собирает клиент и SSR. Новые зависимости для EPIC2 не нужны. |

Проверены установленные PHP-пакеты через `./x composer show --direct`: Laravel **13.42.0**, inertia-laravel **3.7.0**, PHPUnit **12.5.44**, Pint **1.32.3**. В `package.json`: Inertia Vue/Vite `^3.11.0`, Vue `^3.5.45`, Tailwind `^4.0.0`, TypeScript `^6.0.3`, Vite `^8.0.0`.

Boost `database-schema` подтвердил PostgreSQL и наличие таблиц пользователей/сообществ/членств; таблиц обращений, комментариев и истории нет. `.ai/rules` отсутствует. Прочитаны профильные навыки Laravel, Inertia/Vue, Tailwind и тестирования; через Boost проверена документация Laravel 13/Inertia 3 по транзакциям, блокировкам, авторизации Form Requests и формам.

Наличие тестов подтверждено чтением файлов, а не новым прогоном: при подготовке плана тесты, миграции, formatter и frontend build не запускались.

## 2. Чего не хватает

- Всего модуля `app/Modules/Requests`: трёх моделей, enum, policy, действий, DTO в `Data/`, Form Requests и контроллеров.
- Трёх таблиц, внешних ключей, индексов и factories для согласованных данных обращений.
- Шести именованных маршрутов внутри существующей группы `auth` + `community`.
- Выборок с обязательным ограничением текущим сообществом и отдельной проверкой авторства/роли.
- Атомарного создания обращения с историей, комментариев, четырёх переходов статуса с блокировкой строки.
- Страниц `Requests/Index.vue`, `Create.vue`, `Show.vue`, фильтра, пагинации, ленты событий, подтверждений действий и навигации.
- Демонстрационных обращений в обоих сообществах и всех 42 сценариев тестирования из EPIC2.
- Обновления README: раздел «Будущий модуль Requests» больше не должен описывать реализованный модуль как отсутствующий.

## 3. Решения и допущения

1. **Переиспользовать контекст без изменения его API.** В существующем классе есть `community()`, `membership()`, `role()`, но нет `id()`. Использовать `$currentCommunity->community()->id`. Не переделывать Identity/Communities и не добавлять глобальный tenant scope.
2. **Обращение искать после определения сообщества.** Сейчас `SubstituteBindings` выполняется до `ResolveCommunity`. Поэтому `{request}` остаётся scalar ID с числовым ограничением; общий `FindCommunityRequest` выполняет `where('community_id', ...)->findOrFail(...)`. Нельзя добавлять обычный implicit binding `ServiceRequest` или глобальный поиск по ID. Для записи Form Request сначала находит обращение этим способом и вызывает policy, затем валидирует payload: чужой ID должен дать 404 даже при некорректном теле запроса.
3. **Policy — единое место правил доступа.** `viewAny`, `create`, `view`, `addMessage`, `changeStatus` учитывают текущий tenant, переданного пользователя и его актуальное членство. Не доверять только роли из контекста, если пользователь не совпадает с владельцем этого контекста. Несовпадение tenant/отсутствие членства — 404; недостаточные права участника своего сообщества — 403. Действия записи также вызывают эту policy, чтобы прямой вызов не обходил HTTP-защиту.
4. **Создание только resident.** Для MVP считать manager не имеющим права создавать обращения, в том числе от собственного имени. Это допущение следует из матрицы возможностей EPIC; проверить GET create и POST store. Пользователь с двумя членствами может создавать там, где он resident.
5. **Недоверенные поля игнорировать.** Следовать существующему подходу login: из формы создания использовать только `title`/`description`, комментария — только `body`; автора, tenant, родителя, начальный статус и даты назначать сервером. При смене статуса принимать только валидный целевой `status`, полномочия не получать из payload.
6. **Возвращать DTO из действий чтения, сериализовать на HTTP-границе.** Следовать коммиту `900e813`: классы `app/Modules/Requests/Data/*Data.php` — `final readonly`, strict types, типизированные публичные свойства конструктора и явный `toArray()` с array-shape PHPDoc. `ListRequests` возвращает Laravel paginator с DTO элементов, `ShowRequest` — DTO деталей; контроллер преобразует результат в массивы внутри ленивых Inertia props. Вложенные DTO также сериализуются явно. Enum статуса/роли хранить типизированным, преобразовывать в строковое значение в `toArray()`; даты выдавать в ISO 8601 UTC. DTO не выполняют запросов, авторизацию и генерацию URL: данные и именованные ссылки готовят действия с DI. Eloquent-модели и их отношения не помещать в DTO. Для автора использовать отдельный минимальный DTO id/name, а не `CommunityMemberData`, который также раскрывает email. Shared props сохраняют текущий подход middleware; DTO входных команд, универсальный базовый DTO, repositories, Resources и новые зависимости не требуются. Отображение дат во Vue — в едином формате с одинаковой timezone для SSR и клиента.
7. **Роль автора комментария относится к сообществу обращения.** В заданной схеме нет исторического снимка роли. Для MVP показывать текущую роль членства автора в этом сообществе, загружая членства пакетно; после удаления членства — нейтральную подпись «Бывший участник». Не брать роль из другого tenant и не выдавать текущую роль за роль на момент отправки. Исторический снимок потребовал бы отдельного изменения требований/схемы.
8. **Комментарий считается обновлением обращения.** Предлагается обновлять `service_requests.updated_at` вместе с добавлением сообщения в одной транзакции, чтобы свежий ответ поднимал обращение в списке. Статус и `resolved_at` при этом не меняются.
9. **Пагинация и сортировка.** Размер страницы — 15 (допущение), сортировка `updated_at DESC, id DESC`, серверный фильтр `status`; «Все» — отсутствие фильтра. При смене фильтра сбрасывать страницу; pagination сохраняет только разрешённые query-параметры. Некорректный статус фильтра возвращает ошибку валидации.
10. **Удаления через HTTP не добавлять.** Для новых FK предлагается каскад от сообщества к обращениям и от обращения к дочерним записям; удаление автора обращения/комментария ограничить, чтобы не терять обсуждение; nullable автора истории обнулять при удалении User. Это политика новых таблиц, а не разрешение на удаление существующих данных. Проверить её совместимость с текущими тестами; миграции EPIC1 не переписывать.
11. **Минимальный объём.** Без уведомлений, вложений, редактирования/удаления обращений и комментариев, API-дубля Inertia-маршрутов, Redis-логики, очередей и новых зависимостей. Enum содержит `New`, `InProgress`, `Resolved`; модели Eloquent не делать readonly, действия/policy делать final readonly там, где это совместимо с их поведением.

## 4. Поэтапная реализация

Этапы последовательные; тесты добавляются вместе с поведением. Имена новых файлов ниже — целевые, timestamp миграций выдаёт Artisan. Каждый этап заканчивается узкими проверками и отчётом о фактическом результате.

### Этап 1. Схема, модели, статусы и factories

**Работы**

- Создать `service_requests` с полями из §4 EPIC2: обязательные community/user, title(255), description, status(32) с default `new`, timestamps, nullable resolved_at.
- Добавить обязательные индексы `(community_id, status)`, `(community_id, created_at)`, `(community_id, user_id, created_at)`.
- Создать `request_messages` с обязательными обращением/автором, body и timestamps; `request_status_histories` с обращением, nullable автором/from_status, to_status и только created_at. Настроить историю без записи updated_at.
- Добавить FK и индексы дочерних выборок; не считать, что PostgreSQL автоматически индексирует ссылающуюся сторону FK.
- Создать enum, casts статусов/дат и связи, перечисленные в EPIC. Обратные связи в старых Community/User не добавлять без реального потребителя.
- Подготовить factories с состояниями статусов и согласованным `resolved_at`; автор обращения должен иметь resident-членство в соответствующем сообществе. Явная передача сообщества/пользователя в тесте должна переиспользовать их, не порождая случайных tenant.

**Новые файлы**

- `database/migrations/<timestamp>_create_service_requests_table.php`
- `database/migrations/<timestamp>_create_request_messages_table.php`
- `database/migrations/<timestamp>_create_request_status_histories_table.php`
- `app/Modules/Requests/Enums/RequestStatus.php`
- `app/Modules/Requests/Models/ServiceRequest.php`
- `app/Modules/Requests/Models/RequestMessage.php`
- `app/Modules/Requests/Models/RequestStatusHistory.php`
- `database/factories/ServiceRequestFactory.php`
- `database/factories/RequestMessageFactory.php`
- `database/factories/RequestStatusHistoryFactory.php`
- `tests/Feature/Requests/RequestPersistenceTest.php`

**Тесты и критерий завершения**

- Factories создают связанные записи; enum/date casts работают при повторной загрузке из БД.
- Новый запрос получает `new`, `resolved_at` равен null; история допускает null from_status/user_id и сохраняется без updated_at.
- Проверить значимые ограничения новых связей: нельзя сохранить осиротевшие записи, удаление родителя не оставляет дочерние данные, ограничение удаления автора сохраняет обсуждение.
- Миграции выполняются через `RefreshDatabase` только на выделенной PostgreSQL-БД; узкий тест проходит, затем Pint. HTTP и страницы на этом этапе не меняются.

### Этап 2. Создание, список и просмотр — первый сквозной сценарий

**Работы**

- Зарегистрировать `ServiceRequestPolicy` явно в существующем provider. Реализовать общую tenant-scoped загрузку и выборки: resident — только собственные обращения, manager — все текущего сообщества.
- `CreateRequest`: проверка policy и членства; только доверенные серверные ID; создание обращения и первой истории `null → new` в одной транзакции, автор истории — создатель.
- Form Requests: title required/string/5–255, description required/string/10–5000; русские сообщения. Отдельно валидировать query фильтра/страницы списка.
- Добавить GET index/create/show и POST store в существующую группу. Маршрут `/create` объявить до `/{request}`, ID ограничить числом. Имена: `communities.requests.index/create/store/show`.
- Query-действия создают DTO из заранее загруженных данных и URL. `ServiceRequestListItemData` содержит номер, title, status, created_at, updated_at и автора для manager; описание и дочерние записи в список не загружаются. `ServiceRequestData` содержит детали обращения, `RequestAuthorData`, даты, разрешённые операции/ссылки и список `RequestStatusHistoryData` с начальным событием. Автор истории nullable, from_status nullable. DTO списка и деталей разделены, чтобы не раскрывать лишние поля.
- Сначала выполнять пагинацию tenant-scoped запроса в БД, затем преобразовывать только элементы текущей страницы в DTO. В контроллере сериализовать элементы через `toArray()`, сохранив pagination metadata и ссылки с фильтром; не передавать DTO/Eloquent как сырые объекты и не загружать всю выборку для последующей пагинации в PHP. `ShowRequest` возвращает `ServiceRequestData`, контроллер вызывает его `toArray()` внутри ленивого prop. Действия записи и `FindCommunityRequest` могут возвращать модели для дальнейших операций; в Inertia они напрямую не передаются.
- Добавить реальные страницы Index/Create/Show: `useForm`, ошибки и processing, пустые состояния, фильтрация через GET и pagination, переход после создания на Show с одноразовым подтверждением Inertia.
- Добавить `urls.requests` в shared props/типы, ссылку в layout и заменить заглушку dashboard. Ссылка должна меняться вместе с tenant и очищаться вне сообщества, включая partial reload. Ссылки отдельных обращений остаются page props, а не общим состоянием layout.

**Новые файлы**

- `app/Modules/Requests/Policies/ServiceRequestPolicy.php`
- `app/Modules/Requests/Actions/FindCommunityRequest.php`
- `app/Modules/Requests/Actions/CreateRequest.php`
- `app/Modules/Requests/Actions/ListRequests.php`
- `app/Modules/Requests/Actions/ShowRequest.php`
- `app/Modules/Requests/Data/RequestAuthorData.php`
- `app/Modules/Requests/Data/ServiceRequestListItemData.php`
- `app/Modules/Requests/Data/ServiceRequestData.php`
- `app/Modules/Requests/Data/RequestStatusHistoryData.php`
- `app/Modules/Requests/Http/Requests/StoreRequestRequest.php`
- `app/Modules/Requests/Http/Requests/IndexRequestRequest.php`
- `app/Modules/Requests/Http/Controllers/RequestController.php`
- `resources/js/pages/Requests/Index.vue`
- `resources/js/pages/Requests/Create.vue`
- `resources/js/pages/Requests/Show.vue`
- `tests/Feature/Requests/ServiceRequestPolicyTest.php`
- `tests/Feature/Requests/StoreRequestTest.php`
- `tests/Feature/Requests/RequestIndexTest.php`
- `tests/Feature/Requests/RequestShowTest.php`
- `tests/Feature/Requests/RequestTenantIsolationTest.php`

**Изменяемые файлы**

- `app/Providers/AppServiceProvider.php` — регистрация policy.
- `routes/web.php` — четыре маршрута.
- `app/Http/Middleware/HandleInertiaRequests.php` — ссылка раздела.
- `resources/js/types/index.ts` — контракт сериализованных DTO обращений, пагинация, разрешения, типы flash при необходимости. TypeScript описывает результат `toArray()`, включая строковые enum/даты и nullable-поля, а не PHP-типы свойств DTO.
- `resources/js/layouts/AuthenticatedLayout.vue` — навигация и одноразовое подтверждение.
- `resources/js/pages/Communities/Dashboard.vue` — работающая ссылка вместо «Скоро».
- `tests/Feature/Inertia/SharedPropsTest.php` — смена/очистка ссылки, partial reload.

**Тесты и критерий завершения**

- Полная матрица policy через Gate; прямые вызовы действий не обходят права или проверку tenant.
- HTTP-сценарии EPIC **1–18**, а также часть **37, 40–42**: успешное создание с redirect и историей; manager/гость не создают; подмена ID/status/role не влияет на запись; проверка пустых, коротких, длинных и нестроковых полей, допустимых границ.
- Сбой записи первой истории откатывает и обращение. Проверять реальную БД, не подменять transaction/query builder моками; вызвать контролируемый сбой внутри сохранения истории и очистить тестовый обработчик после проверки.
- Resident получает 403 на чужое обращение своего сообщества; чужой tenant и отсутствующий ID — 404. Пользователь с членством в двух tenant не видит чужую запись при подстановке её ID в URL текущего tenant.
- Фильтр, размер/границы страниц, стабильная сортировка при одинаковых timestamps, сохранение query и tenant в pagination URL; отсутствие чужих данных и секретных полей во всех props.
- В `RequestIndexTest`/`RequestShowTest` проверить точный публичный контракт сериализации DTO через HTTP: поля списка/деталей, вложенный автор только с id/name, строковые статусы, единый формат дат, null resolved_at/from_status/автора истории. Проверить отсутствие email, паролей, загруженных отношений и описания/истории в списке, а также сохранение pagination metadata после преобразования DTO. Использовать осмысленные значения в payload, не ограничиваться проверкой типа ответа. Отдельные тесты для каждого простого конструктора/getter не нужны.
- Eager loading проверяется на наборе обращений с разными авторами: число запросов не растёт на каждого автора. Не фиксировать хрупкое общее число SQL всего HTTP-запроса.
- Удаление членства/смена роли учитываются при следующем запросе; ссылки корректны при переходе A → B → список сообществ.
- После узких Feature-тестов: Pint, typecheck, build клиента/SSR. Resident уже может создать обращение и найти его в списке; manager может его прочитать.

### Этап 3. Комментарии и хронологическая лента

**Работы**

- Добавить `AddRequestMessage`, Form Request с body required/string/1–3000, контроллер и POST `communities.requests.messages.store` по пути `/{request}/messages`.
- Tenant-scoped загрузку и policy выполнять до валидации тела. Автор — текущий пользователь, родитель — проверенное обращение. Комментарии разрешены во всех статусах, включая resolved.
- Сохранять комментарий и обновление updated_at обращения атомарно; не изменять status/resolved_at и не создавать ложное событие смены статуса.
- Дополнить `ShowRequest` формированием `RequestMessageData`: id, body, `RequestAuthorData`, nullable `CommunityRole` автора в сообществе обращения, дата сообщения. Заранее загрузить нужные данные без N+1; добавить типизированный список комментариев в `ServiceRequestData` и его явную вложенную сериализацию. При отсутствии членства передавать null role для подписи «Бывший участник».
- Объединить комментарии и историю в хронологическую ленту. Для одинакового времени задать стабильный порядок по типу/ID, создание показывать первым; явно не обещать точность времени, которой нет в timestamp БД.
- Добавить форму комментария в Show, ошибки, processing, очистку после успеха и сохранение позиции страницы. Текст выводить Vue-интерполяцией с переносами, без `v-html`.

**Новые файлы**

- `app/Modules/Requests/Actions/AddRequestMessage.php`
- `app/Modules/Requests/Data/RequestMessageData.php`
- `app/Modules/Requests/Http/Requests/StoreRequestMessageRequest.php`
- `app/Modules/Requests/Http/Controllers/RequestMessageController.php`
- `tests/Feature/Requests/StoreRequestMessageTest.php`

**Изменяемые файлы**

- `routes/web.php`, `app/Modules/Requests/Actions/ShowRequest.php`.
- `app/Modules/Requests/Data/ServiceRequestData.php` — список DTO комментариев и сериализация.
- `app/Modules/Requests/Policies/ServiceRequestPolicy.php` — если метод `addMessage` не завершён на этапе 2.
- `resources/js/pages/Requests/Show.vue`, `resources/js/types/index.ts`.
- `tests/Feature/Requests/ServiceRequestPolicyTest.php`, `RequestShowTest.php`, `RequestIndexTest.php`, `RequestTenantIsolationTest.php`.

**Тесты и критерий завершения**

- EPIC **19–25, 38**, расширение **40–42**: собственный комментарий resident, manager своего tenant; чужой автор своего tenant — 403, чужой tenant — 404, гость — login.
- Подмена user_id/community_id/service_request_id/role/status не меняет автора, родителя и состояние обращения; валидировать required/string/min/max, пробельное тело и границы длины.
- После комментария в resolved сохраняются статус и resolved_at, история статусов не растёт; обновляется позиция обращения в списке согласно принятому допущению.
- Лента содержит правильных авторов, роли соответствующего tenant, даты и стабильный порядок; корректна после удаления членства автора. Отдельно проверить смешанные роли пользователя в двух сообществах.
- Расширить `RequestShowTest`: сериализованный комментарий содержит только разрешённые поля, вложенного автора, строковую роль или null и дату в общем формате. Преобразование DTO в ответе не вызывает дополнительных запросов и не раскрывает членства автора в других сообществах.
- Feature-тесты проверяют данные Inertia; безопасность Vue-вывода дополнительно проверить в реальном интерфейсе строками с HTML/script. Само отсутствие скрипта в JSON не доказывает безопасность DOM.
- Узкие тесты, Pint, typecheck и build; житель и управляющий могут вести обсуждение.

### Этап 4. Смена статусов и повторное открытие

**Работы**

- Реализовать `ChangeRequestStatus`, Form Request, контроллер и PATCH `communities.requests.status.update` по пути `/{request}/status`.
- Разрешить только manager текущего сообщества. Валидировать enum, а допустимость перехода проверять после повторного tenant-scoped чтения через `lockForUpdate()` внутри транзакции.
- Разрешить ровно `new → in_progress`, `new → resolved`, `in_progress → resolved`, `resolved → in_progress`. Тот же статус, возврат в new и неизвестные значения отклонять понятной ошибкой поля status.
- Обновление статуса/resolved_at и добавление истории с фактическим from_status и автором выполнять атомарно. При resolved ставить время, при повторном открытии обнулять.
- Дополнить `ServiceRequestData` типизированным списком разрешённых `RequestStatus`, сериализуемым в строковые значения, а Show — управлением статусом только для manager. Новые события передавать через существующий `RequestStatusHistoryData`. Данные UI — подсказка, сервер проверяет всё повторно. Ошибки комментария и статуса не должны смешиваться.

**Новые файлы**

- `app/Modules/Requests/Actions/ChangeRequestStatus.php`
- `app/Modules/Requests/Http/Requests/UpdateRequestStatusRequest.php`
- `app/Modules/Requests/Http/Controllers/RequestStatusController.php`
- `tests/Feature/Requests/ChangeRequestStatusTest.php`

**Изменяемые файлы**

- `routes/web.php`, `app/Modules/Requests/Enums/RequestStatus.php` — единое описание переходов.
- `app/Modules/Requests/Actions/ShowRequest.php`, при необходимости `Policies/ServiceRequestPolicy.php`.
- `app/Modules/Requests/Data/ServiceRequestData.php` — разрешённые переходы и их сериализация; `RequestStatusHistoryData.php` переиспользуется для новых событий.
- `resources/js/pages/Requests/Show.vue`, `resources/js/types/index.ts`.
- `tests/Feature/Requests/ServiceRequestPolicyTest.php`, `RequestShowTest.php`, `RequestTenantIsolationTest.php`.

**Тесты и критерий завершения**

- EPIC **26–36, 39**, завершение **40–42**: все четыре разрешённых перехода; resident — 403, чужой manager — 404, гость — login; неизвестный статус, одинаковый статус, запрещённые переходы, пустые/нестроковые значения.
- Для каждого успеха проверить текущее состояние БД, resolved_at, ровно одну новую запись истории и правильного автора. Для отказа — отсутствие изменений во всех трёх таблицах.
- В `RequestShowTest` проверить DTO-контракт истории после смены статуса: строковые from_status/to_status, безопасный автор, дата; разрешённые переходы соответствуют текущему состоянию и роли, resident получает пустой список. Изменения PHP DTO согласованы с TypeScript-типами.
- Временные проверки использовать с замороженным временем; повторное открытие и повторное решение дают согласованные даты.
- Контролируемый сбой сохранения истории откатывает статус и resolved_at.
- Регрессионный тест с устаревшим экземпляром модели: после изменения записи в БД действие должно перечитать текущее состояние и отклонить уже недопустимый/повторный переход. Это проверяет повторную проверку состояния, но не заменяет полноценный тест двух конкурентных процессов; блокировку и границы транзакции отдельно проверить при review реализации.
- HTTP-проверки чужого ID выполнять также с невалидным payload: приоритет у 404, а не ошибки валидации.
- Узкие тесты, Pint, typecheck и build; полный рабочий цикл создания, ответа, решения и повторного открытия доступен в UI.

### Этап 5. Демонстрационные данные

**Работы**

- Расширить существующий `DatabaseSeeder`, выделив наполнение обращений в `RequestDemoSeeder`; вызывать после создания пользователей и членств в той же транзакционной последовательности.
- Создать ровно три заданных обращения в каждом tenant:
  - `sosnovy`: «Не работает освещение в подъезде» — new; «Протечка трубы в подвале» — in_progress; «Сломан домофон» — resolved.
  - `lesnoy`: «Не работает уличный фонарь» — new; «Повреждено дорожное покрытие» — in_progress; «Не открывается калитка» — resolved.
- Добавить логически последовательные истории и комментарии manager, согласовать created_at/updated_at/resolved_at. Минимум одно обращение `sosnovy` поручить `multi@example.test` как resident; в `lesnoy` он демонстрирует права manager.
- Seed выполняется в CLI, где нет HTTP CurrentCommunity. Не вызывать HTTP-зависимые действия с неинициализированным контекстом; использовать явно найденные demo-community/user и проверенные членства.
- Повторный запуск должен находить существующие demo-записи по стабильным признакам, не дублировать комментарии/историю и не сбрасывать прогресс уже существующего обращения. Не добавлять уникальность заголовка для всех реальных обращений ради seed.

**Новые файлы**

- `database/seeders/RequestDemoSeeder.php`
- `tests/Feature/Database/RequestDemoSeederTest.php`

**Изменяемые файлы**

- `database/seeders/DatabaseSeeder.php`
- `tests/Feature/Database/DemoSeederTest.php` — расширить demo-сценарий пользователя с двумя ролями, сохранив старые проверки.

**Тесты и критерий завершения**

- После seed ровно шесть demo-обращений, правильные title/status/tenant; авторы — участники нужного сообщества, комментаторы имеют соответствующую роль.
- История начинается с new, последовательна и заканчивается текущим статусом; timestamps и resolved_at согласованы.
- Второй seed сохраняет ID и timestamps обращений/событий, не создаёт дубликаты, сохраняет пользовательские комментарии и последующие переходы статусов.
- Существующие тесты пяти пользователей, шести членств, сохранения профилей/паролей и посторонних записей остаются актуальными.
- `multi@example.test` видит только собственные обращения в `sosnovy`, все обращения в `lesnoy` и получает правильные отказы при подмене tenant/ID.
- Выполнить тесты Database и связанные Requests, затем Pint. Не использовать `migrate:fresh` на рабочей БД ради демоданных.

### Этап 6. Итоговая проверка, безопасность и README

**Работы**

- Сверить все 42 сценария EPIC с тестами ниже и все 14 критериев готовности с фактическими результатами.
- Проверить сквозной цикл с реальным session login: resident создаёт → manager видит/комментирует/решает → resident видит ответ → manager повторно открывает. Отдельно проверить обе роли multi-пользователя.
- Проверить CSRF новых POST/PATCH с реально включённой проверкой по примеру существующего `AuthenticationTest`; стандартный testing POST не доказывает CSRF-защиту.
- Проверить отсутствие HTTP-операций редактирования/удаления обращения, комментариев и истории; прямые запросы не меняют данные.
- Проверить мобильный/desktop UI, dark mode, клавиатуру, длинный пользовательский текст, пустые списки, ошибки, disabled/processing, смену tenant, SSR и гидратацию. Не устанавливать новый browser-test framework.
- Обновить README: реализованный модуль, маршруты и права, схема статусов, demo-сценарий, запуск миграций/seed и проверок, принятые ограничения по роли автора и конкурентному тестированию.

**Новые файлы**

- `tests/Feature/Requests/RequestWorkflowTest.php`
- `tests/Feature/Requests/RequestSecurityTest.php`

**Изменяемые файлы**

- `README.md`.
- Файлы Requests и их тесты — только для обнаруженных дефектов; без общего рефакторинга EPIC1.

**Критерий завершения**

- Все целевые тесты и регрессия EPIC1 проходят на PostgreSQL; Pint, typecheck, клиентская и SSR-сборка проходят.
- Миграции применяются без изменения старых таблиц/данных; локальный seed даёт воспроизводимый сценарий без дубликатов.
- В отчёте перечислены реальные изменения, команды и результаты, найденные проблемы и ограничения. Не выполненная проверка явно отмечена как не выполненная.

## 5. Карта обязательного покрытия EPIC2

Все новые тесты БД — Feature с `RefreshDatabase`, существующими factories и выделенной PostgreSQL-БД. Проверять HTTP/Inertia-контракт вместе с сохранёнными значениями и отсутствием побочных записей при отказе. Общие проверки framework не дублировать.

| Требования §13 EPIC2 | Основные тесты | Этап |
| --- | --- | --- |
| 1–10: создание, validation, подмена полей, история, атомарность | `StoreRequestTest`, `ServiceRequestPolicyTest` | 2 |
| 11–18: чтение, роли, tenant, фильтр, pagination | `RequestIndexTest`, `RequestShowTest`, `RequestTenantIsolationTest` | 2 |
| 19–25: комментарии, автор, resolved без смены статуса | `StoreRequestMessageTest`, `RequestShowTest` | 3 |
| 26–36: переходы, отказы, история, resolved_at | `ChangeRequestStatusTest`, `ServiceRequestPolicyTest` | 4 |
| 37–42: подмена URL/ID/payload, два членства, удаление членства | `RequestTenantIsolationTest` с расширением по мере появления endpoints | 2–4 |
| Seed, идемпотентность и demo-права | `RequestDemoSeederTest`, обновлённый `DemoSeederTest` | 5 |
| Сквозной workflow, CSRF, недоступные операции | `RequestWorkflowTest`, `RequestSecurityTest` | 6 |
| Навигация и partial reload | Обновлённый `SharedPropsTest` | 2 и регрессия 6 |
| Сериализация DTO: точные поля, вложенные данные, enum/даты/null, pagination, отсутствие утечек и N+1 | `RequestIndexTest`, `RequestShowTest` с расширением для комментариев и статусов | 2–4 |

Существующие `tests/Feature/Identity/*`, `tests/Feature/Communities/*`, `tests/Feature/Inertia/*`, `tests/Feature/Database/*` сохранить и прогнать в общей регрессии. Не переписывать работающие проверки ради нового модуля.

## 6. Команды и порядок проверки

Перед реализацией каждого этапа повторно проверить `git status`, `git diff`, историю и применимые правила. Если `.ai/rules` появится, прочитать index, соответствующие правила и выполнить поиск по ключевым словам Requests/tenant/status. До новых API-зависимых изменений использовать Boost `search-docs`, перед миграциями сверить актуальную схему через `database-schema`.

Генераторы запускать через `./x artisan make:* --no-interaction`; тесты создавать с `make:test --phpunit`, затем размещать согласно существующей структуре Feature. Все проектные команды выполняются через `./x`.

После соответствующего изменения запускать минимальный затронутый файл/набор, например:

```bash
./x artisan test --compact tests/Feature/Requests/RequestPersistenceTest.php
./x artisan test --compact tests/Feature/Requests/StoreRequestTest.php
./x artisan test --compact tests/Feature/Requests/StoreRequestMessageTest.php
./x artisan test --compact tests/Feature/Requests/ChangeRequestStatusTest.php
./x artisan test --compact tests/Feature/Inertia/SharedPropsTest.php
./x artisan test --compact tests/Feature/Database
./x composer exec -- pint --dirty --format agent
./x npm run typecheck
./x npm run build
```

В конце EPIC:

```bash
./x artisan route:list --path=communities --except-vendor
./x artisan test --compact
./x composer exec -- pint --dirty --format agent
./x npm run typecheck
./x npm run build
```

Применение миграций/демоданных при реализации — обычными `./x artisan migrate --no-interaction` и `./x artisan db:seed --no-interaction` в проверенном локальном окружении; подготовка плана их не выполняет. Для тестов действуют `phpunit.xml` и guard в `tests/TestCase.php`. Не менять зависимости, настройки тестовой БД или wrapper без необходимости.

## 7. Запрос на следующий шаг

> Реализуй этап 1 задачи `ai/EPIC2.md` по плану `ai/2/resident_requests_plan.md`: создай миграции, enum, модели, связи и factories модуля Requests, добавь `RequestPersistenceTest`. Соблюдай `AGENTS.md`, сохрани текущие изменения пользователя, используй `./x`, выполни узкие тесты и Pint. Не переходи к этапу 2. В конце перечисли изменённые файлы, результаты проверок и принятые допущения.
