# Задача №1 — план реализации основы мультитенантной платформы

Источник: [ai/EPIC.md](../EPIC.md). Дата анализа: 2026-11-13.

Это план, а не отчёт о реализации. На данном шаге создаётся только этот файл. Номер каталога `1` взят из заголовка EPIC; slug — `multitenant_foundation`.

## 1. Проверенное состояние проекта

Прочитаны `AGENTS.md`, EPIC, прикладные файлы, конфигурация окружения и релевантные навыки Laravel, тестирования, Inertia/Vue и Tailwind. Каталога `.ai/rules` сейчас нет. Проверены `git status`, рабочий и индексированный diff, последние коммиты. Исходные незакоммиченные изменения относятся только к `ai/EPIC.md` (`AM`); их необходимо сохранить.

| Область | Уже реализовано | Недостающее |
| --- | --- | --- |
| Окружение | Wrapper `./x`, Docker-конфигурация PHP 8.4, PostgreSQL 17, Redis 7.4; рабочая БД действительно PostgreSQL | Проверяемые инструкции запуска приложения и отдельная PostgreSQL-БД для тестов |
| Backend | Установлены Laravel 13.42.0, Inertia Laravel 3.7.0, PHPUnit 12.5.44, Pint 1.32.3 | Модули Identity и Communities, прикладные контроллеры, политики и действия |
| Пользователи | `app/Models/User.php`, стандартные таблицы `users`, `sessions`, `password_reset_tokens`; hashed cast пароля, скрытие password/remember_token; `UserFactory` | Перенос модели в Identity, связи с сообществами |
| Вход | Session guard `web` и стандартная web middleware-группа Laravel | Маршруты и обработчики login/logout, форма, валидация, управление сессией и тесты |
| Сообщества | Ничего | Таблицы, модели, enum ролей, контекст, проверка членства и изоляция |
| Маршруты | В `routes/web.php` только `/`, который показывает Welcome; полный `route:list` также показывает служебные маршруты | Все маршруты EPIC, кроме изменения уже существующего `/` |
| Frontend | Vue 3, Inertia 3, Vite 8 и Tailwind 4; `app.js`, `Welcome.vue`, Blade shell; скрипт build собирает клиент и SSR | TypeScript, типы props, Login, три страницы Communities, общий layout |
| Inertia | `HandleInertiaRequests` подключён, но не содержит своей логики | Безопасные shared props `auth.user` и `community` |
| Данные | `DatabaseSeeder` создаёт одного `test@example.com` через factory | Два сообщества, пять пользователей, шесть членств; повторяемость seeder |
| Тесты | Два шаблонных ExampleTest; PHPUnit настроен на SQLite `:memory:` | Все 15 сценариев EPIC, дополнительные проверки границ доступа; проверки схемы на PostgreSQL |
| Документация | README содержит продуктовый бриф; `_dev/README.md` описывает wrapper | Инструкции именно этого приложения, демодоступ, модульная архитектура и правила tenant-доступа |

Версии PHP-пакетов подтверждены `./x composer show --direct`. Frontend-ветки подтверждены `package.json` и lock-файлом: `@inertiajs/vue3`/`@inertiajs/vite` — 3.x, Vue — 3.5.x, Tailwind — 4.x, Vite — 8.x. `typescript`, `vue-tsc`, tsconfig, PHPStan и конфигурации frontend lint/typecheck отсутствуют.

Boost `database-schema` подтвердил стандартные Laravel-таблицы и отсутствие `communities`/`community_members`. Прочитана версия документации Laravel/Inertia через Boost: scoped bindings, route binding, порядок middleware, сессии и shared props.

## 2. Решения и границы реализации

- Использовать `app/Modules/Identity` и `app/Modules/Communities`, сохранив общие `app/Http`, `app/Providers`, `routes`, `database`, `resources/js/pages`. Создание `app/Modules` прямо предусмотрено EPIC; отдельный загрузчик модулей не нужен.
- Перенести единственную модель User в Identity; не оставлять две конкурирующие модели. Таблицу `users` и существующие миграции не переписывать, дополнительные стандартные поля не удалять. Обновить все ссылки и явно связать модульные модели с factories.
- Использовать обычную модель `CommunityMember` с enum cast `CommunityRole`; таблицу `community_members` явно указать в обеих belongsToMany-связях. Роль брать из членства, а не из пользователя или произвольного pivot без согласованного cast.
- Объявлять strict types в новых/затронутых PHP-файлах. Использовать final/readonly там, где это совместимо с наследованием и изменяемым состоянием: Eloquent-модели и устанавливаемый контекст не делать readonly механически.
- Все маршруты приложения этого EPIC — session-based web/Inertia. REST API, JWT, токены, публичную регистрацию и отдельное редактирование профиля не добавлять.
- Идентификатор текущего сообщества приходит из `{community:slug}`. На каждом запросе проверять актуальное членство из БД; не кешировать права между запросами и не доверять `community_id`, `user_id`, `role` из клиента.
- `CurrentCommunity` — scoped binding с Community и CommunityMember, понятным исключением при обращении до установки. Ни static-состояния, ни singleton. Политика проверяет права именно для переданного сообщества, а не только факт роли manager где-либо.
- Чужой/несуществующий tenant возвращает 404; resident на members своего tenant — 403. Скрытие ссылки в интерфейсе не заменяет серверную проверку.
- Shared props собирать по явному списку полей. `community` вычислять после проверки членства; при ранней регистрации shared props использовать отложенное вычисление. На login и списке сообществ — `community: null`.
- Для `/` выбрать простой разрешённый EPIC вариант: гостю login, авторизованному `/communities`, в том числе при единственном членстве. У пользователя без членств — понятное пустое состояние списка.
- Ссылки генерировать на backend через именованные маршруты и передавать нужные URL в props. Новая библиотека маршрутизации frontend не требуется.
- Новые Vue-компоненты писать на TypeScript с одним корневым элементом. Использовать существующие Inertia/Vue/Tailwind, поддержать мобильный экран и имеющийся подход к dark mode. Не добавлять UI-kit.
- Для полноценной проверки TypeScript запланированы только devDependencies `typescript` и `vue-tsc`. По `AGENTS.md` изменение зависимостей требует согласования: разрешение на этап 2 должно явно включать эти пакеты. До такого разрешения package-файлы не менять.
- Будущий Requests описать в README. Пустой каталог без отслеживаемых файлов не даёт результата в Git, поэтому сейчас не создавать фиктивные классы или `.gitkeep` только ради модуля.
- Вне объёма: обращения, комментарии, уведомления, файлы, подписки, оплаты, приглашения, WebSocket, административная панель и управление ролями через HTTP.

## 3. Этапы реализации

Этапы выполняются последовательно. Тесты добавляются вместе с соответствующим поведением, а не откладываются целиком на завершение EPIC. Имена новых файлов ниже — целевые; timestamp миграций выдаёт Artisan.

### Этап 1. Модульная модель данных и тестовая БД

**Работы**

1. Подготовить отдельную PostgreSQL-БД для тестов и конфигурацию её подключения; исключить запуск `RefreshDatabase` на рабочей БД. Использовать существующий PostgreSQL-сервис, без добавления контейнеров или PHP-пакетов.
2. Перенести User в Identity, обновить auth provider, UserFactory и текущий DatabaseSeeder. Сохранить существующее поведение аутентифицируемой модели, hashing и hidden attributes.
3. Добавить `communities`: id, name, уникальный slug, nullable description, timestamps.
4. Добавить `community_members`: id, внешние ключи community_id/user_id, role, timestamps, unique(community_id, user_id), отдельный индекс user_id. Предусмотреть удаление зависимых членств при удалении пользователя/сообщества; лишний индекс community_id не добавлять поверх ведущего столбца составного индекса.
5. Реализовать Community, CommunityMember, CommunityRole с Resident/Manager и все связи из EPIC. Создать factories со состояниями resident/manager.

**Файлы**

- Перенос: `app/Models/User.php` → `app/Modules/Identity/Models/User.php`.
- Изменение: `config/auth.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `phpunit.xml`.
- Создание: `.env.testing.example` с безопасными настройками отдельной тестовой PostgreSQL-БД; реальные локальные значения — в некоммитящемся `.env.testing`.
- Создание: `database/migrations/<timestamp>_create_communities_table.php`, `database/migrations/<timestamp>_create_community_members_table.php`.
- Создание: `app/Modules/Communities/Models/Community.php`, `app/Modules/Communities/Models/CommunityMember.php`, `app/Modules/Communities/Enums/CommunityRole.php`.
- Создание: `database/factories/CommunityFactory.php`, `database/factories/CommunityMemberFactory.php`.
- Создание: `tests/Feature/Communities/CommunityMembershipTest.php`.

**Тесты и критерий завершения**

- Factories создают модульные модели, auth provider загружает перенесённый User.
- Один пользователь состоит в двух сообществах с разными enum-ролями; обе стороны связей возвращают правильные записи.
- Повторный slug/членство отклоняются, некорректные внешние ключи не создают осиротевших записей; удаление владельца связи не оставляет членства.
- Миграции проходят на отдельной PostgreSQL-БД, тесты используют `RefreshDatabase` согласно EPIC. Не подменять его рекомендацией навыка про `LazilyRefreshDatabase`.
- Прогнать файл тестов этапа и существующие тесты после переноса User; выполнить Pint. Пользовательский интерфейс на этом этапе остаётся стартовым.

### Этап 2. Сессионный вход/выход и TypeScript-основа

**Работы**

1. После согласования двух devDependencies настроить TS, переименовать entrypoint в `app.ts`, согласовать Vite и Blade shell. Сохранить существующую сборку клиента и SSR; не вводить второй SSR entrypoint без необходимости.
2. Реализовать GET/POST `/login`, POST `/logout`; использовать `web`, `guest`, `auth` и стандартный session guard.
3. Валидировать только email/password. Проверку credentials и завершение сессии оформить небольшими действиями; контроллер отвечает за HTTP-ответы.
4. После входа регенерировать сессию, после выхода инвалидировать сессию и регенерировать CSRF-токен. Сохранить CSRF-защиту Laravel без исключений для login/logout.
5. Добавить ограничение попыток входа по комбинации email/IP. Ошибка неверных credentials не раскрывает существование аккаунта.
6. Создать Login с понятными русскими подписями, ошибками и состоянием отправки. Добавить безопасный `auth.user` и `community: null` в shared props.
7. Пока страницы сообществ не созданы, успешный вход возвращает на существующий `home`; итоговое перенаправление `/` на список вводится в этапе 4. Не создавать фиктивную страницу сообществ ради промежуточного шага.

**Файлы**

- Создание: `app/Modules/Identity/Http/Controllers/AuthenticatedSessionController.php`, `app/Modules/Identity/Http/Requests/LoginRequest.php`.
- Создание: `app/Modules/Identity/Actions/AuthenticateUser.php`, `app/Modules/Identity/Actions/LogoutUser.php`.
- Изменение: `routes/web.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php` при необходимости настройки перенаправлений, `app/Http/Middleware/HandleInertiaRequests.php`.
- Перенос: `resources/js/app.js` → `resources/js/app.ts`.
- Изменение: `vite.config.js`, `resources/views/app.blade.php`, `package.json`, `package-lock.json`.
- Создание: `tsconfig.json`, `resources/js/types/index.ts`, `resources/js/pages/Auth/Login.vue`.
- Создание: `tests/Feature/Identity/AuthenticationTest.php`, `tests/Feature/Inertia/SharedPropsTest.php`.

**Тесты и критерий завершения**

- Login отображается гостю, корректный пароль даёт аутентифицированную сессию, неверный пароль и некорректные/пустые поля возвращают ошибки.
- Logout завершает вход; проверяется последующий доступ, смена session ID и очистка прежних данных сессии/CSRF-токена.
- Проверить поведение limiter и отсутствие повышения роли при добавлении неожиданных полей к login.
- Отдельно проверить CSRF на изменяющих запросах с действительно включённой проверкой: обычный HTTP-тест Laravel в testing-режиме её обходит. Не считать стандартный успешный POST доказательством CSRF.
- Props пользователя содержат только id/name/email; гостю передаётся null, пароль и remember_token не попадают в ответ.
- Выполнить узкие тесты, Pint, TS typecheck и сборку клиента/SSR после смены entrypoint.

### Этап 3. Контекст сообщества и серверные права

**Работы**

1. Создать CurrentCommunity с установкой сообщества/членства и методами получения сообщества, членства, роли; до установки выбрасывать понятное исключение.
2. Зарегистрировать его через scoped binding в существующем AppServiceProvider.
3. Создать ResolveCommunity: получить связанную по slug модель, проверить пользователя и актуальное членство, вернуть 404 при отсутствии членства, установить контекст.
4. Согласовать порядок `auth`, `SubstituteBindings`, ResolveCommunity и policy, не перезаписывая без необходимости весь стандартный middleware stack. Гостевой доступ к защищённым страницам должен вести на login.
5. Зарегистрировать CommunityPolicy явно: dashboard для обоих типов участников, members только для manager этого сообщества. Проверка policy вне HTTP не должна предоставлять доступ постороннему пользователю.
6. Обеспечить завершение/очистку контекста между запросами, включая отказ в доступе и переход на нетенантную страницу. Не сохранять scoped-объект внутри singleton-сервисов.

**Файлы**

- Создание: `app/Modules/Communities/CurrentCommunity.php`, `app/Modules/Communities/Http/Middleware/ResolveCommunity.php`, `app/Modules/Communities/Policies/CommunityPolicy.php`.
- Изменение: `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`.
- Создание: `tests/Feature/Communities/CurrentCommunityTest.php`, `tests/Feature/Communities/CommunityPolicyTest.php`, `tests/Feature/Communities/ResolveCommunityTest.php`.

**Тесты и критерий завершения**

- Матрица policy: resident/manager/посторонний для dashboard/members, включая пользователя с различающимися ролями в двух сообществах.
- Ошибка при незаданном контексте; корректная выдача Community, CommunityMember и роли после установки.
- Middleware-тесты через маршрут, зарегистрированный только в тесте: гость, неизвестный slug, отсутствие членства, разрешённый доступ, недоверенные client-параметры.
- Следующий запрос после удаления членства запрещён; последовательность A → B → нетенантный маршрут не сохраняет A или роль из него. Проверить lifecycle scoped-контекста отдельно от простого повторного использования одного объекта в тестовом приложении.
- Выполнить тесты этапа и Pint. Проверки реальных пользовательских маршрутов добавляются на этапе 4.

### Этап 4. Сквозной сценарий выбора сообщества и Inertia-интерфейс

**Работы**

1. Изменить GET `/`: гостю login, авторизованному именованный маршрут списка сообществ. Обновить соответствующий существующий Feature ExampleTest.
2. Добавить GET `/communities`, GET `/communities/{community:slug}/dashboard`, GET `/communities/{community:slug}/members`.
3. Список выбирать только через членства текущего пользователя, участников — только через разрешённое текущее сообщество. Загрузить нужные связи без N+1; задать устойчивый порядок. Не отдавать полные модели пользователей.
4. Контроллеры держать тонкими: выборки/формирование данных для двух списков вынести в конкретные действия, без repository-интерфейсов и универсальных сервисов.
5. Подключить middleware ко всем tenant-маршрутам и реально вызвать policy. Не принимать tenant или роль из query/body как основание доступа.
6. Завершить shared props `community: {id, name, slug, role} | null`; регистрировать их так, чтобы данные вычислялись после ResolveCommunity. Дополнительные URL передавать отдельно от фиксированного контракта auth/community.
7. Реализовать общий авторизованный layout, список сообществ с ролью/описанием, dashboard с пользователем и заглушкой обращений, таблицу участников. Навигация на members видна manager, выход — POST-кнопкой.
8. Добавить пустое состояние при отсутствии членств, обработать nullable description; обеспечить доступность полей/кнопок, мобильную компоновку и таблицу без разлома страницы. Текст интерфейса — для жителя, без деталей реализации.

**Файлы**

- Изменение: `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/index.ts`.
- Создание: `app/Modules/Communities/Http/Controllers/CommunityController.php`, `app/Modules/Communities/Http/Controllers/CommunityDashboardController.php`, `app/Modules/Communities/Http/Controllers/CommunityMemberController.php`.
- Создание: `app/Modules/Communities/Actions/ListUserCommunities.php`, `app/Modules/Communities/Actions/ListCommunityMembers.php`.
- Создание: `resources/js/layouts/AuthenticatedLayout.vue`, `resources/js/pages/Communities/Index.vue`, `resources/js/pages/Communities/Dashboard.vue`, `resources/js/pages/Communities/Members.vue`.
- По необходимости общих стилей: `resources/css/app.css`. Не создавать набор компонентов до появления реального повторения.
- Удаление больше не используемого стартового экрана: `resources/js/pages/Welcome.vue` после замены home.
- Изменение: `tests/Feature/ExampleTest.php`, `tests/Feature/Identity/AuthenticationTest.php`, `tests/Feature/Inertia/SharedPropsTest.php`.
- Создание: `tests/Feature/Communities/CommunityIndexTest.php`, `tests/Feature/Communities/CommunityDashboardTest.php`, `tests/Feature/Communities/CommunityMembersTest.php`.

**Тесты и критерий завершения**

- Все обязательные HTTP-сценарии EPIC из матрицы ниже проходят на настоящих маршрутах, с реальными БД, middleware и policies.
- Проверять не только 200/403/404, но и Inertia component, точный состав доступных записей, tenant/role в props и отсутствие чужих имён/email/slug в payload.
- При переходе dashboard → список community становится null; при переходе A → B меняются tenant и роль. Повторить проверку для Inertia-запросов/частичных обновлений props, чтобы не сохранить старый tenant на клиенте.
- Пользователь без членств получает пустой список; несуществующий slug и чужой slug не раскрывают данные.
- Добавленные `community_id`, `user_id`, `role=manager` в запросах не расширяют доступ и не меняют членство. Нет маршрута самоназначения роли.
- Прогнать связанные Feature-тесты, Pint, typecheck и build; вручную проверить desktop/mobile, вход, переходы между сообществами, 403/404 и выход.

### Этап 5. Демоданные и документация запуска

**Работы**

1. Заменить стартовое наполнение на повторяемый DatabaseSeeder: два сообщества по slug, пять пользователей по email, шесть уникальных членств.
2. Создать `sosnovy` / «ЖК Сосновый»: manager1@example.test — manager, resident1@example.test — resident, multi@example.test — resident.
3. Создать `lesnoy` / «Посёлок Лесной»: manager2@example.test — manager, resident2@example.test — resident, multi@example.test — manager.
4. Указать единый демонстрационный пароль `DemoPassword123!`, хранить его в БД через hashing и документировать в README как локальный демодоступ. Повторный запуск не создаёт дубликаты и не удаляет посторонних пользователей/членства; не сбрасывать существующие пользовательские пароли без необходимости.
5. Дополнить README конкретным стеком, локальным запуском через `./x`, .env/APP_KEY, миграциями, seeders, frontend/SSR, тестовой PostgreSQL-БД, typecheck и аккаунтами. Указать корректный адрес, предварительно получив его через Boost get-absolute-url.
6. Описать границы модулей, 404/403, получение CurrentCommunity через DI и будущее ограничение выборок Requests текущим сообществом с проверкой policy.
7. Согласовать примеры окружения: корневая `.env.example` сейчас предлагает SQLite/phpredis, а wrapper использует `_dev/.env.example.development` с PostgreSQL/Predis. Устранить двусмысленность инструкции и примеров, не менять рабочий `.env` и не переписывать несвязанные разделы `_dev/README.md`.

**Файлы**

- Изменение: `database/seeders/DatabaseSeeder.php`, `README.md`, `.env.example`; `_dev/.env.example.development` — только если нужны недостающие параметры запуска приложения.
- Создание: `tests/Feature/Database/DemoSeederTest.php`.

**Тесты и критерий завершения**

- На чистой тестовой БД появляются ровно 2 сообщества, 5 пользователей и 6 членств с нужными ролями.
- Второй запуск seeder сохраняет количества и идентичность записей; посторонние данные не удаляются.
- Демопароль проверяется hashing-сервисом, а HTTP-вход и выбор сообществ соответствуют ролям; проверить различающиеся права multi@example.test.
- Выполнить тест seeder и затронутые тесты аутентификации, Pint. Проверить воспроизводимость инструкции без `migrate:fresh` на рабочей БД.

### Этап 6. Итоговая проверка и приёмка

**Работы**

1. На изолированном окружении проверить миграции с нуля, повторный seeder и весь путь login → communities → dashboard → members → logout.
2. Выполнить всю PHPUnit suite на отдельной PostgreSQL-БД, typecheck и клиентскую/SSR-сборку. Проверить фактический SSR-рендер и отсутствие hydration-ошибок, а не только наличие build-скрипта.
3. Прочитать свежие browser logs через Boost, проверить поведение resident/manager/multi и пустого списка, отсутствие раскрытия внутренних полей.
4. Сверить маршруты и все критерии EPIC, проверить diff на отсутствие бизнес-функциональности Requests и несвязанных изменений.
5. Представить фактический отчёт: файлы, команды, результаты, оставшиеся ограничения. Если проверка недоступна — назвать причину, не считать её пройденной.

**Файлы**

Новые прикладные файлы не планируются. По результатам проверки исправляются только относящиеся к EPIC файлы предыдущих этапов; `README.md` уточняется по фактическому запуску.

**Команды проверки при реализации**

```bash
./x artisan test --compact tests/Feature/Communities/CommunityMembershipTest.php
./x artisan test --compact tests/Feature/Identity/AuthenticationTest.php
./x artisan test --compact tests/Feature/Communities
./x artisan test --compact tests/Feature/Inertia/SharedPropsTest.php
./x artisan test --compact tests/Feature/Database/DemoSeederTest.php
./x composer exec -- pint --dirty --format agent
./x npm run typecheck
./x npm run build
./x artisan route:list
./x artisan test --compact
```

Команда `typecheck` появится на этапе 2 и будет запускать `vue-tsc --noEmit`. Pint запускается через Composer wrapper; `./x run` поддерживает только dev/build и не подходит для произвольного PHP executable. Не устанавливать отдельный PHP-анализатор в рамках EPIC. Полный suite запускается после завершения этапов; после каждого изменения теста повторяется минимальная соответствующая проверка.

## 4. Покрытие обязательных сценариев EPIC

Все перечисленные тестовые файлы находятся в `tests/Feature`; DB-зависимые тесты используют `RefreshDatabase` и factories, без глобального запуска демонстрационного seeder.

| № EPIC | Проверка | Файл | Этап |
| --- | --- | --- | --- |
| 1–2 | Гость не открывает dashboard и перенаправляется на login; также защищены список и members | `Communities/CommunityDashboardTest.php`, `CommunityIndexTest.php`, `CommunityMembersTest.php` | 4 |
| 3 | Корректный login | `Identity/AuthenticationTest.php` | 2 |
| 4 | Неверный пароль отклоняется | `Identity/AuthenticationTest.php` | 2 |
| 5 | Logout и невозможность повторного доступа без входа | `Identity/AuthenticationTest.php` | 2, 4 |
| 6 | Только собственные сообщества, без чужих данных | `Communities/CommunityIndexTest.php` | 4 |
| 7 | Resident открывает свой dashboard | `Communities/CommunityDashboardTest.php` | 4 |
| 8–9 | Resident и manager не открывают чужой dashboard: 404 | `Communities/CommunityDashboardTest.php` | 4 |
| 10 | Resident не открывает members своего сообщества: 403 | `Communities/CommunityMembersTest.php` | 4 |
| 11 | Manager видит участников только своего сообщества | `Communities/CommunityMembersTest.php` | 4 |
| 12 | Manager не открывает чужой members: 404 | `Communities/CommunityMembersTest.php` | 4 |
| 13 | Разные роли одного пользователя в двух сообществах; соответствующие права и props | `Communities/CommunityDashboardTest.php`, `CommunityMembersTest.php`, `Inertia/SharedPropsTest.php` | 4 |
| 14 | Подмена slug не раскрывает данные; дополнительно игнорируются client tenant/role | `Communities/CommunityDashboardTest.php`, `CommunityMembersTest.php` | 4 |
| 15 | Удалённое членство запрещает следующий запрос и исчезает из списка | `Communities/CommunityDashboardTest.php`, `CommunityMembersTest.php`, `CommunityIndexTest.php` | 4 |

Дополнительные проверки: lifecycle контекста, неизвестный slug, пустой список, отсутствие чувствительных props, CSRF, session rotation, limiter, повторяемость seeder и ограничения схемы. Template Unit ExampleTest не требует изменений и не считается покрытием требований.

## 5. Результат текущего шага и следующий запрос

Создан только `ai/1/multitenant_foundation_plan.md`. Код, миграции, зависимости и рабочая БД не менялись. Во время анализа запускались `./x composer show --direct`, `./x artisan route:list --except-vendor`, `./x artisan route:list`, read-only Boost schema/docs и команды чтения Git/файлов. Фильтр `--except-vendor` скрыл текущий маршрут с контроллером Inertia, поэтому результат уточнён полным `route:list`.

Тесты, Pint и frontend build на шаге планирования не запускались: исполняемый код не менялся. Наличие конфигурации не является подтверждением успешного прохождения проверок.

Запрос для начала реализации:

> Реализуй этап 1 из `ai/1/multitenant_foundation_plan.md`: модульную модель данных и отдельную тестовую PostgreSQL-БД. Соблюдай `AGENTS.md`, сохрани мои изменения в `ai/EPIC.md`, не переходи к следующим этапам. Запусти соответствующие тесты и Pint, затем отчитайся об изменённых файлах, выполненных командах, результатах и допущениях.
