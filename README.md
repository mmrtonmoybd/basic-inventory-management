# StockPilot — Basic Inventory Management

A quantity-only inventory management system for tracking product variants across stock conditions, with a full audit trail. There are no prices, costs, invoices, or accounting anywhere in the system: it answers "how many, where, and in what condition".

Built with Laravel 13, Inertia 3, React 19, TypeScript, and Tailwind CSS 4. The interface defaults to Bangla (`bn`), and business dates follow the `Asia/Dhaka` timezone.

## Features

- **Products and variants:** attributes such as Size, Color, or Fabric are data, not code. Only the attribute combinations you pick become variants, and every variant has a unique SKU and an optional barcode.
- **Stock by condition:** stock is held per variant, warehouse, and condition (`available`, `inspection`, `damaged`, `with_supplier`).
- **Stock entry:** opening stock, Stock IN, and Stock OUT, with multi-product entry.
- **Customer returns:** returned units go to `inspection`, then move to `available` or `damaged`. Partial inspections are allowed.
- **Damage cases:** record damage with image evidence. Damaged units leave `available` straight away.
- **Supplier exchange claims:** a review and approval workflow covering dispatch, partial receipts, and short-close.
- **Stock adjustments:** count corrections that a different user must approve.
- **Dashboard and reports:** reports with CSV, XLSX, and PDF export.
- **Low-stock alerts, in-app notifications, and an activity log**
- **Ledger reconciliation:** checks that balances match the immutable ledger.
- **CSV import and global search**
- **Roles and permissions:** Super Admin, Inventory Manager, Stock Operator, Return/QC Operator, Supplier Coordinator, Exchange Approver, and Report Viewer.
- **Account security:** two-factor authentication and passkeys through Laravel Fortify.

## How stock stays correct

- Balances are never edited directly. Every change is a posted movement that writes insert-only ledger entries.
- All posting goes through one service, `App\Services\Inventory\InventoryPoster`. It runs in a single transaction with row locks, and no condition can go negative.
- Each form submission carries an idempotency key, so a double-click or replayed request never posts twice.
- Approving a supplier exchange never changes stock on its own. Only dispatch and receipt move units.

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the business rules, the posting pipeline, the ER diagram, and the movement table.

## Tech stack

| Layer    | Packages                                                        |
| -------- | --------------------------------------------------------------- |
| Backend  | PHP 8.3+, Laravel 13, Fortify, Spatie Laravel Permission        |
| Frontend | Inertia 3, React 19, TypeScript, Tailwind CSS 4, Wayfinder      |
| Exports  | OpenSpout (CSV/XLSX), mPDF (PDF, Hind Siliguri font for Bangla) |
| Tooling  | Vite+, Pest, Larastan, Pint                                     |

## Getting started

### Requirements

- PHP 8.3+
- Composer
- Node.js and npm
- SQLite (default) or MySQL

### Installation

```bash
git clone https://github.com/mmrtonmoybd/basic-inventory-management.git
cd basic-inventory-management

composer setup        # install deps, create .env, generate key, migrate, build assets
php artisan db:seed   # roles, permissions, and the Super Admin account
```

The seeded Super Admin is `admin@example.com` with password `password`. Change it right away.

### Demo data (optional)

```bash
php artisan inventory:demo           # load sample products, stock, and workflows
php artisan inventory:demo --clear   # remove demo data before real use
```

Super Admins can also load or clear demo data from the admin screen.

### Run locally

```bash
composer dev
```

## Configuration

| Variable                      | Default      | Purpose                                         |
| ----------------------------- | ------------ | ----------------------------------------------- |
| `APP_LOCALE`                  | `bn`         | Interface language (`bn` or `en`)               |
| `INVENTORY_BUSINESS_TIMEZONE` | `Asia/Dhaka` | Timezone for business dates and report ranges   |
| `DB_CONNECTION`               | `sqlite`     | Set to `mysql` and fill the `DB_*` vars for MySQL |
| `QUEUE_CONNECTION`            | `database`   | Queue for notifications                         |

Timestamps are always stored in UTC.

## Testing and code quality

```bash
composer test        # Pint check, PHPStan, and the Pest suite
composer lint        # fix PHP style with Pint
npm run check        # frontend lint and format check
npm run types:check  # TypeScript type check
composer ci:check    # everything above, as run in CI
```

Feature tests cover every stock workflow: stock entry, returns, supplier exchanges, adjustments, reconciliation, report accuracy, CSV import, and security.

## Author

Moshiur Rahman — [rmedha037@gmail.com](mailto:rmedha037@gmail.com)
