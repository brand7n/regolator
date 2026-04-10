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

**Core domain models:** `User`, `Event`, `Order` — all use Spatie Activity Log for audit trails.

**Order workflow:** `WAITLISTED → INVITED → ACCEPTED → PAYPAL_PENDING → PAYMENT_VERIFIED` (or `CANCELLED`). The `OrderStatus` enum (`app/Models/OrderStatus.php`) defines these states.

**Payment:** PayPal integration lives in `Order::verify()` — authenticates via OAuth2, confirms order completion, then calls `handle_payment_success()`. Config in `services.paypal`.

**Quick Login:** Passwordless magic links via `User::getQuickLogin()` / `User::fromQuickLogin()` — encrypted time-limited tokens. Route: `/quicklogin/{key}`.

**Admin panel:** Filament at `/admin`, restricted to user ID 9 in `User::canAccessPanel()`. Resources in `app/Filament/Resources/` for Events, Users, Orders.

**Livewire components** in `app/Livewire/` handle interactive UI (event info forms, PayPal buttons, registration lists, maps via Leaflet.js).

**Custom artisan commands:**
- `app:send-emails` — bulk email for an event (reminders, invites, confirmations)
- `app:export-orders {eventId}` — CSV export of event orders

**Flexible data:** `Event.properties` and `Order.event_info` are JSON columns for schema-less data (cabin assignments, etc.).

## Key Conventions

- Prices stored in cents (`base_price`), converted via `getBasePriceInDollarsAttribute()` accessor
- Database: SQLite locally, configurable via `.env`
- PHPStan level 5 with Larastan — run before committing
- Tests use Pest PHP (PHPUnit-compatible)
