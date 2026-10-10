# Princess Collections

*Your Style, Your Crown.* — a women's clothing e-commerce store with a customer storefront and an admin dashboard. Cash on Delivery, Philippine pesos.

## Stack

- **Backend:** Laravel 12 REST API under `/api/v1`, Sanctum cookie-based SPA auth, MySQL
- **Frontend:** React 18 + Vite + Tailwind CSS + React Router + Axios + Lucide (in `resources/js`)
- Laravel serves the SPA shell (`resources/views/spa.blade.php`); React Router handles page URLs.

## Local setup (XAMPP)

1. Start **MySQL** in the XAMPP control panel.
2. Create two databases: `princess_collections` and `princess_collections_test` (the test suite uses the second and wipes it on every run).
3. Install and configure:

   ```bash
   composer install
   npm install
   cp .env.example .env          # then set ADMIN_EMAIL and ADMIN_PASSWORD
   php artisan key:generate
   php artisan migrate
   php artisan storage:link
   php artisan db:seed
   ```

4. Run the app (API + Vite dev server together):

   ```bash
   composer dev
   ```

   Open <http://localhost:8000>. The admin dashboard is at <http://localhost:8000/admin>.

Use the `localhost:8000` address rather than `localhost/Princess-collections/public`; the app expects to sit at the site root.

## Seed data

`php artisan db:seed` is safe to re-run and creates:

- **The admin account** from `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` in `.env`. Re-running it resets that account's password to the `.env` value. Admins can only be created this way; public registration always creates a customer.
- **Demo catalogue (demo data):** 5 categories and 8 sample products with sizes XS–XL, colours and per-variant stock. Their pictures are stock photos from Unsplash (credits in `database/seeders/demo-images/CREDITS.md`), not photos of real stock, and not every colour has its own photo. Replace them from **Admin → Products**; the seeder never overwrites images you upload. No customers, orders or reviews are seeded.

To seed only one part: `php artisan db:seed --class=AdminUserSeeder` or `--class=DemoCatalogSeeder`.

## Tests

```bash
php artisan test
```

Feature tests run against MySQL and cover authentication and roles, permissions on every protected route, catalogue filters, admin CRUD and image upload validation, cart, checkout (totals recalculated on the server, duplicate submission, insufficient stock, rollback), order status rules and cancellation, inventory, reports and settings.

## How the important rules are enforced

| Rule | Where |
| --- | --- |
| Prices, shipping and totals are never taken from the browser | `app/Services/CheckoutService.php`, `CartService.php` |
| Checkout is one transaction with row locks; a repeated submit returns the same order | `CheckoutService.php` (`Idempotency-Key` header, unique index on `orders`) |
| Stock changes only through one service and always leaves a movement record | `app/Services/InventoryService.php` |
| Stock can never go negative | unsigned column + check in `InventoryService` |
| Order status transitions; stock restored once on cancellation | `app/Enums/OrderStatus.php`, `app/Services/OrderService.php` |
| Admin-only access | `admin` middleware on `/api/v1/admin/*` plus policies in `app/Policies` |
| Customers only see their own cart, addresses and orders | scoped queries + `AddressPolicy`, `OrderPolicy` |
| Uploads must be real JPEG/PNG/WebP and get a generated filename | `app/Services/ImageStorage.php` |

Store settings an admin can change (shipping fee, free-shipping threshold, announcement bar) live in the `settings` table; defaults are in `config/store.php`.

## Deployment

1. **Server:** PHP 8.2+ with `pdo_mysql`, `bcmath`, `fileinfo`, `mbstring`, `openssl`; MySQL 8 or MariaDB 10.4+; HTTPS.
2. **Web root:** point the domain at the `public/` folder, not the project root.
3. **Environment:** copy `.env.example` to `.env` and follow the production checklist at its top. In particular `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, `SESSION_SECURE_COOKIE=true`, and set `CORS_ALLOWED_ORIGINS` and `SANCTUM_STATEFUL_DOMAINS` to your domain (the second without `https://`).
4. **Install and build:**

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan key:generate          # first deploy only
   php artisan migrate --force
   php artisan storage:link          # first deploy only
   php artisan db:seed --class=AdminUserSeeder --force
   php artisan optimize
   ```

5. **Permissions:** the web server must be able to write to `storage/` and `bootstrap/cache/`.
6. **Updates:** pull the code, then repeat `composer install`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan optimize`.
7. **Backups:** back up the MySQL database and `storage/app/public` (uploaded images) regularly.

Never commit `.env`; it is in `.gitignore`.

## Not included yet

Online payment gateways, email notifications, discount codes, product reviews and password reset by email. The `payment_records` table exists for a future payment feature but nothing writes to it.
