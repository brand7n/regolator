# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Regolator is a Laravel 12 event registration system for hash house events. It handles user sign-up, event management, PayPal payment processing, and admin via Filament. The stack is PHP 8.5, Livewire 3, Filament 3, Tailwind CSS, and Vite.

## Common Commands

```bash
# Development
npm run dev                    # Vite dev server (frontend hot reload)
npm run build                  # Production frontend build
php artisan serve              # Local PHP dev server (or use Herd/Docker)

# Testing
./vendor/bin/pest              # Run all tests
./vendor/bin/pest tests/Unit   # Run unit tests only
./vendor/bin/pest tests/Feature # Run feature tests only
./vendor/bin/pest --filter=TestName  # Run a single test by name

# Linting & Static Analysis
./vendor/bin/pint              # Fix PHP code style (Laravel Pint)
./vendor/bin/phpstan           # Static analysis (level 5, Larastan)

# Database
php artisan migrate            # Run migrations
php artisan migrate:fresh --seed  # Reset and seed database

# Docker (production-like)
docker compose up              # Starts php-fpm, scheduler, caddy
```

## Architecture

**Core domain models:** `User`, `Event`, `Order`, `Message`, `MessageRecipient`. Models use Spatie Activity Log for audit trails.

```
Users (name, email, password, phone, kennel, nerd_name, shirt_size, short_bus, comment, is_admin)
Events (properties JSON column, starts_at, ends_at, base_price in cents, creator_id)
Orders (status via OrderStatus enum, event_id, user_id, paypal_order_id, verified_at, event_info JSON)
Messages (event_id, created_by, subject, body_html, recipient_filter, status via MessageStatus, sent/failed counts)
MessageRecipients (message_id, user_id, order_id, status via MessageRecipientStatus, is_test)
```

**Order workflow:** `WAITLISTED → INVITED → ACCEPTED → PAYPAL_PENDING → PAYMENT_VERIFIED` (or `CANCELLED`). The `OrderStatus` enum (`app/Models/OrderStatus.php`) defines these states.

**Message system:** Admin-created messages targeted at orders filtered by status (`recipient_filter`). Supports test sends, delivery tracking per recipient, and aggregate sent/failed counts. Status workflow: `DRAFT → SENDING → SENT/FAILED`. Filament resource in `app/Filament/Resources/MessageResource.php`.

**Payment:** PayPal integration lives in `Order::verify()` — authenticates via OAuth2, confirms order completion, then calls `handle_payment_success()`. Config in `services.paypal`.

**Quick Login:** Passwordless magic links via `User::getQuickLogin()` / `User::fromQuickLogin()` — encrypted time-limited tokens. Route: `/quicklogin/{key}`. Supports `?action=unsubscribe` query param. Also auto-sets `email_verified_at` on login.

**Admin panel:** Filament at `/admin`, restricted to users with `is_admin` flag in `User::canAccessPanel()`. Resources: Events, Users (with `ListUsersByShirtSize` custom page), Orders, Messages.

**Livewire components** in `app/Livewire/` handle interactive UI (event info forms, PayPal buttons, registration lists, maps via Leaflet.js). Auth component: `Auth/QuickLoginForm`.

**Custom artisan commands:**
- `app:export-orders {eventId}` — CSV export of event orders (`--paid-only` for verified payments only, `--pdf` for a PDF check-in sheet via barryvdh/laravel-dompdf, optional columns `--comment`/`--order-id`/`--short-bus`)
- `app:verify-pending-orders` — schedule-friendly command to verify PayPal pending orders; reverts stale ones (>1hr) back to `Accepted`

**Flexible data:** `Event.properties` and `Order.event_info` are JSON columns for schema-less data (cabin assignments, etc.).

## Key Conventions

- Prices stored in cents (`base_price`), converted via `getBasePriceInDollarsAttribute()` accessor
- Database: SQLite locally, configurable via `.env`
- PHPStan level 5 with Larastan — run before committing
- Tests use Pest PHP (PHPUnit-compatible)
