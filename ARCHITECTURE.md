# Architecture

High-level map of the Regolator codebase (Laravel 12 event registration for hash house
harriers). Read this first instead of re-discovering the layout. Keep it under ~300 lines.

## 1. Shape of the app

Regolator is a **two-surface** application over one small domain:

| Surface | Path | Built with |
|---|---|---|
| Public/attendee | `/`, `/dashboard`, `/events/{event}`, `/canihazemail`, `/quicklogin/{key}` | Blade + Livewire components |
| Admin | `/admin` | Filament 3 resources |
| Machine | `/api/user`, `/up` | Sanctum-token route, health check |

There are **no conventional controllers**: all HTTP handling is route closures, Livewire
components, or Filament resources. `app/Http/Controllers/Controller.php` is an empty base class.

## 2. Directory layout

```
app/
  Actions/{Fortify,Jetstream}/    auth & profile actions wired to Fortify/Jetstream providers
  Console/Commands/               ExportOrders, VerifyPendingOrders, SyncGoogleCalendar
  Filament/Resources/             Event, Order, User, Message CRUD (+ Pages/, Widgets/)
  Http/Controllers/Controller.php base class only
  Jobs/SendEventMessage.php       one queued mail per message recipient
  Livewire/                       Paypal, Regos, EventInfo, LocationMap, GpxTrack, Auth/QuickLoginForm
  Mail/                           EventMessage, PaymentConfirmation, QuickLogin, RegoInvite
  Models/                         Event, EventTrack, Order, User, Message, MessageRecipient + 3 enums
  Providers/                      App, Fortify, Jetstream, Filament/AdminPanelProvider
bootstrap/app.php                 route registration, SecureHeaders global middleware
routes/web.php                    all page routes + the quicklogin handler (closures)
routes/api.php                    GET /api/user
routes/console.php                scheduler (2 entries)
database/migrations/              23 migrations; SQLite locally
resources/views/                  dashboard, event, welcome, auth/, profile/, emails/, livewire/
tests/Feature/                    17 Pest feature tests (the real suite; Unit/ is a placeholder)
compose.yaml                      php-fpm + scheduler + nightwatch + (profile: local) caddy
```

## 3. Domain model

```
Kennel 1─n Event n─1 User (creator)
Event 1─n Order n─1 User
Event 1─n Message 1─n MessageRecipient n─1 Order
Event 1─n EventTrack n─1 User        (unique per event_id + user_id)
User 1─n Order
```

| Model | File | Notes |
|---|---|---|
| `User` | `app/Models/User.php` | `Authenticatable` + `FilamentUser` + `PasskeyUser`; `LogsActivity`, `SoftDeletes`, `HasProfilePhoto`, `PasskeyAuthenticatable`. `is_admin` gates Filament. |
| `Event` | `app/Models/Event.php` | `$guarded=[]`; `properties` JSON holds dynamic add-ons + custom info fields (schema-less extension point). `base_price` in **cents**, with `get/setBasePriceInDollarsAttribute`. `isFull()`, `paidRegosCount()`, `regoPaidAt()`. |
| `Order` | `app/Models/Order.php` | A user↔event registration. `event_info` JSON stores answers to the event's custom fields. `LogsActivity`, `SoftDeletes`. Owns the PayPal verification logic. |
| `Message` | `app/Models/Message.php` | Bulk-email campaign: `recipient_filter` (array of `OrderStatus`), markdown body, optional personalization field lists, `sent_count`/`failed_count`. `resolveRecipients()`, `updateDeliveryCounts()`. |
| `MessageRecipient` | `app/Models/MessageRecipient.php` | Per-recipient delivery row (`is_test`, `status`, `error`, `sent_at`). |
| `Kennel` | `app/Models/Kennel.php` | Canonical kennel names. Events are tied through `events.kennel_id`; freeform strings (`Event::create(['kennel' => 'NVHHH'])`, `$event->kennel = '...'`) are intercepted in `Event::setAttribute()` and normalised by `Kennel::resolve()` (case/whitespace-insensitive find-or-create), so one kennel row serves every spelling variant. `$event->kennel` reads the model; `users.kennel` is still freeform. |
| `EventTrack` | `app/Models/EventTrack.php` | One GPX track per attendee per event: `path` on the `public` disk, `size`, and two Ramer–Peucker polylines — `points` (5 m tolerance) and `points_overview` (30 m) — so the map never re-parses the GPX. |

Status enums (string-backed, cast on the models):

- `OrderStatus` (`app/Models/OrderStatus.php`): `WAITLISTED → INVITED → ACCEPTED → PAYPAL_PENDING → PAYMENT_VERIFIED`, plus `CANCELLED`, `BLOCKED`.
- `MessageStatus` (`app/Models/MessageStatus.php`): `DRAFT → SENDING → SENT | FAILED`.
- `MessageRecipientStatus` (`app/Models/MessageRecipientStatus.php`): `PENDING → QUEUED → SENT | FAILED`.

Cross-cutting traits: Spatie `LogsActivity` on User/Event/Order/Message (audit trail, surfaced
in `app/Filament/Widgets/ActivityLogWidget.php`); `SoftDeletes` on User and Order.
No model uses `HasFactory` — tests build state directly.

**Order status transitions are the spine of the app.** Admin/registrant actions move an order
forward; only `PAYMENT_VERIFIED` counts toward capacity and the public roster.

## 4. Control flow

### Page requests
1. `bootstrap/app.php` registers `routes/web.php` and appends `SecureHeadersMiddleware` to the web group.
2. `GET /dashboard` (closure, `routes/web.php`) → `Event::upcoming()` by default, `Event::past()`
   with `?show=past` (scopes on `app/Models/Event.php`) → `resources/views/dashboard.blade.php`,
   which renders the Upcoming/Past switch.
3. `GET /events/{event}` → route-model-bound `Event` → `resources/views/event.blade.php`, which
   mounts five Livewire components: `location-map`, `event-info`, `paypal`, `regos`, `gpx-track`.
4. Each Livewire component re-queries its own state on every round trip; there is no
   controller layer coordinating them.

### Registration + payment flow
1. `App\Livewire\Paypal::accept_terms()` — rejects if `Event::isFull()`; else creates an `Order`
   (`ACCEPTED`) or short-circuits to `PAYMENT_VERIFIED` when `base_price <= 0`.
2. PayPal Buttons JS (`resources/views/livewire/paypal.blade.php`) creates the PayPal order and
   calls `Paypal::setOrderID()`, which stamps `order_id` and moves status to `PAYPAL_PENDING`.
3. `Paypal::approve()` → **`Order::verify()`** (server-side authority):
   - OAuth2 client-credentials token from `api-m.{sandbox.}paypal.com`.
   - `GET /v2/checkout/orders/{id}`; if `APPROVED`, re-check `Event::isFull()` then `POST /capture`.
   - `finalizeIfAmountOk()` compares captured cents against `event->base_price`.
   - `handle_payment_success()` sets `verified_at` + `PAYMENT_VERIFIED` and mails `PaymentConfirmation`.
4. Backstop: `app:verify-pending-orders` (`app/Console/Commands/VerifyPendingOrders.php`) every
   5 min re-verifies pending orders, reverting ones stale for >1h to `ACCEPTED`.

### Auth flow (passwordless magic links)
- `/canihazemail` → `App\Livewire\Auth\QuickLoginForm` finds or creates the `User`
  (phone normalized via libphonenumber) and mails `QuickLogin` with `/quicklogin/{key}`.
- `User::getQuickLogin()` encrypts `{hash, id, expires}` with `Crypt::encryptString`;
  `User::fromQuickLogin()` decrypts, checks expiry + password hash, returns `{user, expires_at}`.
- `GET /quicklogin/{key}` closure logs out any current session, logs in, marks
  `email_verified_at`, then redirects to `?action=` (default `dashboard`; `action=unsubscribe`
  logs an unsubscribe request). The same URL pattern is reused inside every outbound email.
- `/admin` is a separate Filament panel (`app/Providers/Filament/AdminPanelProvider.php`);
  `User::canAccessPanel()` returns `is_admin`.

### Admin flows
- `EventResource` / `UserResource` — plain CRUD (`UserResource::Pages/ListUsersByShirtSize.php` is
  a custom roster page).
- `OrderResource::Pages/EditOrder` — header actions `block`/`unblock`, `resend_invite`,
  `resend_payment`; saving with status `INVITED` mails `RegoInvite`. `ListOrders` has a bulk
  `send_invites` action.
- `MessageResource::Pages/EditMessage` — `preview`, `send_test` (synchronous, `is_test=true`),
  `send` (queues one job per recipient), `resend_failed`.

## 5. Data flow: bulk message delivery

```
Filament EditMessage
  → Message::resolveRecipients()   Orders for event whose status ∈ recipient_filter,
                                   minus users already sent a non-test message
  → Message.status = SENDING; one MessageRecipient(QUEUED) per order
  → SendEventMessage::dispatch()   (database queue)
  → Job: build quick-login URL → Mail::to(user)->send(EventMessage)
         → recipient SENT/FAILED (+error) → Message::updateDeliveryCounts()
  → when all recipients processed: Message SENT|FAILED, last_sent_at
```

`resources/views/emails/event_message.blade.php` renders the markdown body, an optional
"Your Info" table (from `include_profile_fields` + `include_event_fields` resolved against
`event.properties.fields`) and an unsubscribe footer. `EventMessage` also builds a plain-text
alternative and a `List-Unsubscribe` header.

Queue execution is **cron-driven, not a daemon**: `routes/console.php` runs
`queue:work --stop-when-empty --tries=3` every minute (and the same scheduler is a container in
`compose.yaml` via `php artisan schedule:work`).

## 6. Data flow: exports

`app:export-orders {eventId} [--paid-only --pdf --comment --order-id --short-bus]`
(`app/Console/Commands/ExportOrders.php`) joins orders + users, appends dynamic event-field
columns, and writes `storage/app/exports/orders_{id}.csv` (league/csv) or
`signup_sheet_{id}.pdf` via dompdf + `resources/views/exports/signup-sheet.blade.php`.

## 6b. Data flow: Google Calendar import

`app:sync-google-calendar [--from= --kennel= --user=]` (`app/Console/Commands/SyncGoogleCalendar.php`)
is run by hand, periodically. It reads a **public** calendar with a plain API key (no OAuth):
`GET https://www.googleapis.com/calendar/v3/calendars/{id}/events?key=…&singleEvents=true`
(`config/services.php` → `google.calendar_api_key` / `calendar_id` / `kennel`), follows
`nextPageToken`, skips `status=cancelled`, and upserts on `events.google_event_id` (unique).
Only `name/description/location/starts_at/ends_at` are refreshed on re-sync; `created_by`,
`kennel_id`, `event_tag`, `base_price`, `max_regos`, `private` are set once on insert — so imported
past events become normal `Event` rows that the admin can edit or attach regos to.

## 6c. Data flow: GPX tracks

```
resources/views/event.blade.php → <livewire:gpx-track>  (app/Livewire/GpxTrack.php)
  upload (≤10 MB, .gpx) → validate extensions:gpx → EventTrack::pointsFrom() (SimpleXML, LIBXML_NONET)
  → deny unless paid (`PAYMENT_VERIFIED`) / host / admin  (HTTP 403)
  → Storage::disk('public')->put gpx/{event_id}/… ; firstOrNew(event_id,user_id) so re-upload
    replaces and deletes the old file
  → `points` (5 m) + `points_overview` (30 m) JSON on the EventTrack row
  → the map draws the coarse tier and calls `LocationMap::detail(ids)` (`#[Renderless]`)
    for tracks in view once zoom ≥ 14, so big maps stay small but zoom in sharp
LocationMap (app/Livewire/LocationMap.php) reads event->tracks() and renders one Leaflet polyline
per track, then fitBounds() — the map stays `wire:ignore` and is built once on mount.

`GET /tracks` (`routes/web.php`, view `resources/views/tracks.blade.php`) browses trails by kennel:
past events that have tracks group by `kennel_id`; `?kennel={id}` opens one kennel and passes
`kennel-id` to the same `LocationMap` component (no marker, all of that kennel's tracks on one map).
```

## 7. Entry points

| Mode | Entry point |
|---|---|
| Web | `public/index.php` → `bootstrap/app.php` → `routes/web.php` |
| API | `routes/api.php` (`auth:sanctum`) |
| Admin | Filament panel at `/admin` (`AdminPanelProvider`) |
| Scheduled | `routes/console.php` + `php artisan schedule:work` (compose `scheduler` service) |
| Queue | `queue:work --stop-when-empty`, triggered by the scheduler |
| CLI | `artisan` → `app/Console/Commands/*` |
| Tests | `./vendor/bin/pest`; `tests/Pest.php` applies `RefreshDatabase` to `Feature/` |

## 8. External dependencies and where they are used

| Dependency | Used for |
|---|---|
| `laravel/framework` 12 | Everything; the `Http::` facade drives PayPal calls in `Order::verify()` |
| `livewire/livewire` 3 | `app/Livewire/*` interactive UI, mounted from Blade views |
| `filament/filament` 3 | Entire `/admin` panel: `app/Filament/Resources/*` |
| `laravel/jetstream` + `fortify` | Auth scaffolding: `app/Actions/*`, `app/Providers/FortifyServiceProvider.php`, `resources/views/auth/*` |
| `laravel/passkeys` | WebAuthn login (`config/passkeys.php`, `@laravel/passkeys` npm) |
| `laravel/sanctum` | `routes/api.php`, API token views |
| `spatie/laravel-activitylog` | `LogsActivity` on 4 models; `ActivityLogWidget`; explicit activity calls in `routes/web.php`, `app/Livewire/Paypal.php`, `app/Models/Order.php` |
| `barryvdh/laravel-dompdf` | PDF check-in sheet in `ExportOrders` |
| `league/csv` | CSV export in `ExportOrders` |
| `giggsey/libphonenumber-for-php` | Phone normalization in `QuickLoginForm` and `UserResource` |
| `bepsvpt/secure-headers` | Global `SecureHeadersMiddleware`; policy in `config/secure-headers.php` |
| `laravel/nightwatch` | Observability agent (compose `nightwatch` service) |
| `leaflet` (npm) | `app/Livewire/LocationMap.php` |
| `tailwindcss` + `vite` | `resources/css`, `resources/js`, `vite.config.js`, `npm run build` |
| PayPal REST API | Called directly with `Http::` — no SDK; credentials in `config/services.php` → `paypal.client_id/client_secret/sandbox` |
| Google Calendar API v3 | Read-only import in `SyncGoogleCalendar` with an API key (`services.google.*`); no SDK, no OAuth |
| `simplexml` (PHP ext) | GPX parsing in `EventTrack::pointsFrom()`, RDP simplification in `EventTrack::simplify()` |
| Pest + Larastan + Pint (dev) | `./vendor/bin/pest`, `./vendor/bin/phpstan` (level 5, `phpstan.neon`), `./vendor/bin/pint` |

## 9. Design decisions and rationale

- **Closures + Livewire instead of controllers.** The app has few distinct pages and each page is
  one interactive island; a controller layer would only forward to a view.
- **Server-side PayPal verification.** `Order::verify()` re-fetches and captures the PayPal order
  with credentials rather than trusting the browser callback; the client is only a trigger
  (`Paypal::approve()`). Amount is checked against `event->base_price` before `PAYMENT_VERIFIED`.
- **Encrypted stateless magic links, not sessions or passwords.** A `Crypt::encryptString` payload
  with an embedded expiry is self-validating and revocable (change the password hash) with no new
  table. Reused for invites, payment confirmations and bulk-mail "view event" buttons; `?action=`
  picks the post-login destination.
- **Schema-less JSON columns for per-event variability.** `events.properties` (add-ons + custom
  field definitions) and `orders.event_info` (answers) let each event define its own extra
  questions without migrations; everything else about an event is fixed columns.
- **Behavior-carrying enums.** Status is cast on the models, so it is a typed value everywhere
  instead of a magic string.
- **Per-recipient delivery rows.** `MessageRecipient` makes bulk sending idempotent, resumable
  (`resend_failed`), deduplicable (`resolveRecipients`) and inspectable per person — at the cost
  of one row + one job per recipient.
- **Cron-driven queue worker.** `queue:work --stop-when-empty` every minute keeps the UI
  non-blocking without a long-lived daemon; the tradeoff is up to a minute of send latency.
- **Activity log as the audit trail** for payments, invites and logins, exposed to admins on the
  Filament dashboard.
- **Google import by API key, not OAuth.** The calendar is public, so a key + manual command keeps
  the credential surface tiny (no refresh tokens, no consent screen); `google_event_id` is the
  idempotency key so re-running never duplicates.
- **GPX as parsed points, not rendered files.** Storing a downsampled polyline means the map needs
  no GPX parser in the browser and the upload stays a single small JSON column; the original file
  is kept on the `public` disk for download.
