# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Inventory management system for a motorcycle parts shop (refaccionaria). Laravel 12 API backend + React 19 + TypeScript SPA frontend, communicating via Axios with Sanctum token auth. **Not** an Inertia.js app despite the dependency (`@inertiajs/react` and `inertia-laravel` are unused leftovers) — the frontend is a standalone SPA served from `resources/views/app.blade.php`. Code, tables, routes and enums are in Spanish; keep that convention. Route prefixes are kebab-case (`venta-producto`, `reporte-movimientos`). The `@/` alias maps to `resources/js`.

## Commands

### Development

```bash
# Run everything (Laravel server + queue + pail logger + Vite)
# Note: the script uses `npx`/`npm run dev` internally (exception to the pnpm rule)
composer dev

# Frontend only
pnpm run dev

# Build frontend
pnpm run build
```

### Testing

```bash
# Run all tests (uses SQLite in /tmp/testing.sqlite)
php artisan test --env=testing

# Run a single test file
php artisan test --env=testing tests/Feature/Venta/VentaTest.php

# Run a specific test method
php artisan test --env=testing --filter=test_store_venta
```

Tests are Pest/PHPUnit under `tests/Feature/{Module}` (no Feature tests yet for Devoluciones, Ubicacion or Users). Tests use `DatabaseTransactions` (not `RefreshDatabase`) and run `migrate:fresh && db:seed` once per test run via a static `$migrated` flag in `TestCase`. The `loginAdmin()` / `createUser(RoleEnum $role)` helpers in `TestCase` authenticate via `Sanctum::actingAs`.

### Code Formatting

```bash
# Format everything (frontend + PHP)
composer format

# Frontend only
pnpm run format

# PHP only
./vendor/bin/pint

# Type check frontend
pnpm run types

# Lint frontend
pnpm run lint
```

### Database

```bash
# Manual DB backup (stored in storage/app/backups/)
php artisan db:backup
```

### Docker

```bash
# Start dev environment
docker compose up --build -d

# Run tests in Docker
docker compose -f docker-compose.test.yml run --rm php_test

# Stop containers (add --volumes to wipe DB data)
docker compose down
```

When using Docker, set these env vars:
```
VITE_APP_URL=http://localhost:8000
DB_HOST=mysql
DB_PASSWORD=root
```

**Database connection: Docker vs. host.** The database is the `laravel_mysql` container (`mysql:8.0`), data in the `mysql_data` volume. The same `.env` serves only one mode at a time:

| Where the command runs | `DB_HOST` | `DB_PORT` |
|---|---|---|
| Inside Docker (php/node containers) | `mysql` | `3306` |
| On the host (local `php artisan`, TablePlus, DBeaver) | `127.0.0.1` | `3307` (published port) |

After changing `.env`, recreate the containers (`docker compose up -d --force-recreate php web`); `docker restart` does not re-read it.

## Destructive Operations — Explicit Permission Required

Never run any of the following unless the user explicitly orders that specific action in the current conversation. A general request (e.g. "fix the error", "set up the project", "run the tests") is NOT permission:

- Refreshing/wiping the database: `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `db:seed` against a database with data, dropping tables, `TRUNCATE`, or bulk `DELETE`/`UPDATE` SQL.
- `docker compose down --volumes` / `docker volume rm` (destroys `mysql_data`), or `docker system prune`.
- Deleting or overwriting backups in `storage/app/backups/` or `.env` files.
- Git history rewrites or discards: `git reset --hard`, `git checkout -- .`, `git clean`, force push.

Caveats: `php artisan test` runs `migrate:fresh && db:seed` once per run (see Testing). It must only run with `--env=testing` (SQLite in `/tmp/testing.sqlite`), never against the dev MySQL; check `.env.testing` first. If an operation is needed to solve a problem, propose it and wait for confirmation. Before any approved destructive step, offer `php artisan db:backup` first.

## Architecture

### Backend (Laravel 12)

**API structure** — all routes are under `/api` via `routes/api.php`, split into per-module files in `routes/modules/`. All routes except `/api/auth/*` require `auth:sanctum`. The web route (`routes/web.php`) catches every non-API path and returns `app.blade.php` to support client-side routing.

**Response macros** — custom macros registered in `AppServiceProvider` via `ResponseMacros::register()`. Use these everywhere instead of raw `response()->json()`:
- `Response::success($data)` → 200 `{status: "OK", data: ...}`
- `Response::successDataTable($paginator, $headers)` → 206 with paginated data + column definitions for the frontend datatable
- `Response::error($message)` → 422
- `Response::unauthenticated()` / `Response::unauthorized()` → 401/403

**Logic layer** — business logic lives in `app/Logic/{Module}/` not in controllers. Controllers are thin: they validate via `FormRequest`, delegate to a `Logic` class, and return the response. The `IndexLogic` base class in `app/Core/Logic/IndexLogic.php` handles pagination, filtering, searching, and ordering for list endpoints. Extend it and override `tableHeaders()`, `customFilters()`, `withRelations()`, and `getColumnSearch()`.

**Actions** — `app/Actions/` contains single-action classes for complex operations (e.g. sale processing, product adjustments, returns).

**Movimientos trait** — `app/Traits/Movimientos.php` must be used whenever stock changes. Call `$this->nuevoMovimiento([...])` with exactly these 7 keys: `producto_id`, `tipo_movimiento_id`, `motivo`, `cantidad`, `cantidad_anterior`, `cantidad_actual`, `user_id`. It only checks the key *count* and returns `false` silently on mismatch, so check the return value.

**Middleware** — API routes run through `setHeaders`, `api`, `transaction` and `errorReporting` (see `bootstrap/app.php`). `TransactionMiddleware` wraps every non-GET request in a DB transaction (skipped in unit tests) and rolls back on an exception, a 500 response, or any JSON body with `status: "error"` (i.e. `Response::error()`), so a failed request leaves no partial writes in production. Don't open your own outer transaction in controllers. Because the middleware is skipped in tests, tests do NOT see that rollback. `ErrorReporting` persists errors to the `error_reportings` table (module `Logic/ErrorReporting`); the frontend also reports to Sentry via `instrument.js` (disabled when environment is `local`).

**Roles & authorization** — `RoleEnum`: Admin=1, User=2 (employee), SuperAdmin=3. Gates are in `AuthServiceProvider`: `can:admin` passes for Admin and SuperAdmin (e.g. `adeudos` routes); `can:user` compares `role_id` against the enum object rather than `->value`, so it likely never passes — verify before relying on it.

**Domain model** — `Producto` (with `ImagenProducto`, `Categoria`/`Subcategoria`, `Marca`, `Proveedor`, `Ubicacion`) → `Venta` / `VentaProducto` (sales and line items) → `Devoluciones` / `DetalleDevolucion` (returns). `Cliente` has credit balances tracked in `HistorialAdeudo` (settled via `/api/adeudos`). Every stock change is logged in `ReporteMovimiento` with a `TipoMovimiento`.

**Imports & downloads** — `app/Imports/ImportProducto.php` (Excel product import) and `ImageProductImport.php` (bulk images); routes `imports`, `images`, `descargables`, `pdf`, `barcode`.

**Adding a module** — (1) `routes/modules/{x}.php` + register a prefix in `routes/api.php`, (2) Controller + FormRequest(s), (3) `app/Logic/{X}/` extending `IndexLogic`/`ShowLogic` (from `app/Core/Logic`), (4) Model/migration/seeder, (5) feature test in `tests/Feature/{X}`, (6) frontend: `Services/{x}/`, `router/modules/`, `pages/{X}/`, enums in `resources/js/enums` mirroring the PHP ones. Add a request to `bruno/inventario` (API collection) as well.

**Printer module** — `app/Printer/` is a self-contained ESC/POS ticket printing subsystem with interfaces for connector (CUPS/network/OS) and formatter. Configured via `PRINTER_NAME`, `PRINTER_DRIVER`, and `PRINTER_HOST` env vars.

**Enums** — domain enums in `app/Enums/`: `RoleEnum`, `StatusVentaEnum`, `TipoCompraEnum`, `TipoMovimientoEnum`, `TipoProductoEnum`, `ProductoUnidadEnum`, `StatusDevolucionEnum`. Use these instead of raw strings.

### Frontend (React 19 + TypeScript)

**Entry** — `resources/js/main.tsx` → `App.tsx`. Provider order matters: `AxiosProvider` → `MantineProvider` → `ThemeProvider` → `QueryClientProvider` → `RouterProvider`.

**Auth** — token stored in `localStorage` as `authToken`. `AxiosContext` injects it into all Axios requests and handles 401 by calling `logout()` (clears storage + redirects to `/login`). The logged-in user object is also persisted in `localStorage`.

**Routing** — React Router v7 via `resources/js/router/routes.routes.tsx`. Routes are defined per module in `resources/js/router/modules/` and composed into `authRoutes`, `adminRoutes`, and `errorRoutes`. The `PrivateRoute` component wraps protected pages; `AppLayout` wraps all non-blank pages.

**Data fetching** — TanStack Query (`@tanstack/react-query`) with `refetchOnWindowFocus: false`. Per-module service functions in `resources/js/Services/{module}/` wrap `axiosApi` calls.

**State** — Zustand stores in `resources/js/store/`: `useSelectOptionsStore` (shared dropdown options by key) and `useSelectedItemStore` (selected row/item for modals).

**Tables** — `mantine-datatable` with the `useDatatable` hook which talks to `Response::successDataTable` endpoints (HTTP 206). Column definitions come from the backend response.

**Forms** — Formik + Yup validation. The `useOnSubmit` hook standardizes form submission.

**UI** — Tailwind CSS v4 + shadcn/ui (Radix UI primitives) + Mantine components. Icons from `@solar-icons/react` and `lucide-react`. Notifications via `react-toastify` and `sweetalert2` for confirmations.

**Package manager** — `pnpm` (v10). Do not use `npm` or `yarn`.

## CI

GitHub Actions on push/PR to `main`: `code_style.yml` runs `pnpm run format:check` and `./vendor/bin/pint --test`; `inventario_test.yml` runs `php artisan test --env=testing` (PHP 8.4, SQLite). Run `composer format` before pushing or CI will fail.

## Environment

Besides the usual Laravel vars (`.env.example`): `DB_CONNECTION=mysql`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `APP_DUMP_PATH` (path to `mysqldump` for `db:backup`, `/usr/bin` in Docker), `WHATSSAPP_CONTACTO` (sic — spelled this way in code), `APP_FULL_NAME`, plus the `PRINTER_*` vars. Other folders: `bruno/` (API collection), `docker/` (mysql, nginx, php configs), `TODO-inventario.txt` (informal product notes/backlog).

## Default Users (dev/seeded)

| Role       | Email                      | Password   |
|------------|----------------------------|------------|
| superadmin | superadmin@repamotos.com   | password   |
| admin      | admin@repamotos.com        | password   |
| employee   | empleado@repamotos.com     | password   |

Dev/seed credentials only — never use them in production.

## Printer Setup (CUPS)

```bash
# List devices
lpinfo -v

# Enable remote access
sudo cupsctl --remote-any --remote-admin --share-printers

# Add POS80 printer
lpadmin -p POS80_Series_POS80_Printer_USB -E -v usb://POS80_Series/POS80_Printer_USB -m raw
```
