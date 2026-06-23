# MatchFrame Admin (Laravel 11 + Filament 3)

A **complete, runnable** admin panel for MatchFrame. Manage end users, analyses
(jobs), payments, AI errors, and the **delayed-reveal window** — all from one
dashboard.

For now it runs entirely on **MySQL** and is self-contained: its own migrations
create every table, so you don't need Supabase to run it. (When you're ready,
switching to Supabase/Postgres is just an `.env` change — see the end.)

---

## Why you were getting 404 at `/admin`

Filament's panel routes only exist when its panel provider is **registered**.
In the earlier drop-in version you had the provider file but it wasn't wired
into the app, so `/admin` resolved to nothing → 404.

This complete project fixes that: `bootstrap/providers.php` registers
`App\Providers\Filament\AdminPanelProvider::class`, which creates the `/admin`
routes. After `composer install` + `migrate`, `/admin` works.

---

## Requirements

- PHP **8.2+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `bcmath`,
  `ctype`, `fileinfo`, `tokenizer`, `xml`, `curl`, `intl`, `gd` (or `zip`).
- **Composer**
- A **MySQL** 8+ database

---

## Already installed an earlier version?

This update adds the API, Stripe management and user CRUD. Pull the new files,
then run:

```bash
composer require laravel/sanctum:"^4.0" stripe/stripe-php:"^15.0"
php artisan migrate          # adds Stripe columns, user auth fields, tokens table
php artisan filament:assets
```

Fresh install? Just follow the steps below.

---

## Setup (about 2 minutes)

```bash
# 1. Install PHP dependencies (downloads Laravel + Filament)
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Create the database, then put the credentials in .env
#    e.g.  mysql -u root -e "CREATE DATABASE matchframe CHARACTER SET utf8mb4;"
#    edit DB_DATABASE / DB_USERNAME / DB_PASSWORD in .env

# 4. Build the schema + create the admin + seed sample data
php artisan migrate --seed

# 5. Publish Filament's assets (icons/css/js)
php artisan filament:assets

# 6. Run
php artisan serve
```

Open **http://127.0.0.1:8000/admin** and log in:

```
email:    admin@matchframe.app
password: password
```

(Both come from `ADMIN_*` in `.env`. Change them before deploying.)

You'll immediately see sample users, analyses (one revealed, one held back, one
failed) and payments, because `DatabaseSeeder` runs `SampleDataSeeder`.

> Don't want sample data? Run `php artisan migrate` then
> `php artisan db:seed --class=AdminSeeder` instead of `--seed`.

---

## What you get

**Dashboard widgets**
- Stats: users, analyses (revealed / in progress), revenue, AI errors, current
  reveal window.
- Doughnut chart of analyses by status.
- Latest activity table.

**Operations**
- **Analyses** — every job with its status. Filter by status (incl. "Failed (AI
  error)" and "Held: AI done, not yet revealed"). Per-row actions:
  - **Reveal now** — release a report early, skipping the remaining delay.
  - **Re-queue** — send a failed analysis back to the worker.
  - **View** — details + the AI error + the full generated report JSON.
- **Payments** — payment statuses, amounts, revenue, Stripe session ids.

**People**
- **Users** — list end users, sign-in activity, analyses count, and delete
  (cascades to their analyses/photos/payments).

**Settings**
- **Reveal delay** — edit `reveal_min_hours` / `reveal_max_hours` live.
- **Stripe & pricing** — enter your Stripe publishable/secret/webhook keys and
  set the price. Secrets are encrypted at rest. The API uses these for checkout
  and webhook verification; the frontend reads the publishable key + price via
  `GET /api/config`. With Stripe disabled, payments fall back to dev mode.

**People**
- **Users** — full CRUD: list, **create new users**, **edit** (name, email,
  email-verified, set/reset password), and delete (cascades).

---

## Backend API (for the frontend)

The panel doubles as the backend API the Next.js frontend talks to. Auth is via
**Laravel Sanctum** bearer tokens.

```
GET    /api/config                     public: price, reveal window, publishable key
POST   /api/register                   { email, password, name? } -> { user, token }
POST   /api/login                      { email, password } -> { user, token }
GET    /api/me                         (auth)
POST   /api/logout                     (auth)
GET    /api/analyses                   (auth) list the user's analyses
POST   /api/analyses                   (auth) multipart: audience, photos[], name?
GET    /api/analyses/{id}              (auth) report is null until reveal_at passes
POST   /api/analyses/{id}/checkout     (auth) -> { url } (Stripe) or dev-paid
GET    /api/payments                   (auth)
DELETE /api/account                    (auth)
POST   /api/stripe/webhook             Stripe -> starts the reveal timer on payment
```

The report is **gated**: `GET /api/analyses/{id}` returns `report: null` plus a
`seconds_until_reveal` countdown until the reveal time, so results can't be seen
early. See `INTEGRATION.md` in the frontend project for page-by-page wiring.

CORS is preconfigured for the frontend origins in `FRONTEND_URLS`.

---

## The worker (processing + reveal)

A scheduled command runs the AI pipeline and reveals due reports:

```bash
# Pass 1: queued -> ready (report generated, held). Pass 2: ready -> revealed.
php artisan analyses:process

# In dev, keep it running:
php artisan schedule:work

# In production, one cron entry drives everything:
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

The report generator (`app/Support/ReportGenerator.php`) is a deterministic mock
so the flow works with no AI keys. Swap its `generate()` for your real vision
model (per the proposal's 4-step pipeline), keeping the same output shape.

---

## Project layout

```
app/
  Models/                 Analysis, Photo, Payment, AppSetting, AuthUser (users), Admin
  Providers/
    AppServiceProvider.php
    Filament/AdminPanelProvider.php   ← registered in bootstrap/providers.php
  Filament/
    Resources/            AnalysisResource, PaymentResource, UserResource (+ Pages)
    Pages/                RevealSettings.php
    Widgets/              StatsOverview, AnalysesStatusChart, LatestAnalyses
config/                   database (MySQL), auth (admin guard), + standard configs
database/
  migrations/             framework tables + users/analyses/photos/payments/app_settings
  seeders/                AdminSeeder, SampleDataSeeder, DatabaseSeeder
resources/views/filament/pages/reveal-settings.blade.php
routes/  bootstrap/  public/  artisan          (full Laravel skeleton)
```

---

## Admin login is separate from app users

Panel admins live in their own `admins` table with a dedicated `admin` auth
guard (`config/auth.php`). The MatchFrame end users live in `users`
(`AuthUser` model). They never mix — an end user can't log into the panel.

Add another admin any time:

```bash
php artisan tinker
>>> \App\Models\Admin::create(['name'=>'Sara','email'=>'sara@matchframe.app','password'=>'secret']);
```

---

## Later: switching to Supabase / Postgres

When the app moves to Supabase, point this panel at the same database:

1. In `.env`: `DB_CONNECTION=pgsql`, then set `DB_HOST/PORT/DATABASE/USERNAME/
   PASSWORD` to your Supabase **Session pooler** values (port 5432),
   `DB_SSLMODE=require`.
2. The data tables already exist in Supabase (created by the Next.js project's
   `supabase/schema.sql`), so **don't** run the data migrations there. Either run
   only the admin/framework migrations, or keep those on a separate local
   connection.
3. In `app/Models/AuthUser.php`, set `protected $table = 'auth.users';` so the
   Users screen reads Supabase's managed users. The Postgres role bypasses RLS,
   so the panel sees all rows.

No other code changes are needed — every data model uses the default connection.

---

## Deploying

Host on any PHP 8.2+ server (Laravel Forge, a VPS, Railway, Fly.io, etc.):

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force
php artisan filament:assets
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Point the web server's document root at `public/`.
