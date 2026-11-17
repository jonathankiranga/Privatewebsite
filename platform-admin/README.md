# SmartNow Platform Administration

This is a separate PHP/MySQLi administration application intended for a dedicated subdomain (for example, `admin.smarternowapps.co.ke`). It is independent of the public static marketing site and does not modify any product application's operational database.

## Runtime
- PHP 7.4 or later (PHP 8.x recommended), with MySQLi and the mysqlnd driver.
- MySQL 5.7+ / MariaDB 10.3+.
- HTTPS is mandatory in production.
- Configure the subdomain document root to this directory's `public/` folder. Do not expose `src/`, `database/`, or `bin/` through the web server.

## Installation
1. Create a dedicated empty MySQL database and database user with least-privilege access.
2. Import `database/schema.sql`.
3. Set environment variables in the hosting control panel:
   - `SMARTNOW_DB_HOST`
   - `SMARTNOW_DB_NAME`
   - `SMARTNOW_DB_USER`
   - `SMARTNOW_DB_PASS`
   - `SMARTNOW_BASE_URL` (for example, `https://admin.smarternowapps.co.ke`)
   - `SMARTNOW_SESSION_SECURE=1`
4. From the project root, create the first administrator using the CLI:
   `php bin/create-admin.php admin@example.com`
   The command prompts for a password; no default credentials are created.
5. Point the subdomain document root to `platform-admin/public`, enable HTTPS, and visit `/login.php`.

## Implemented in this foundation
- Password-hashed administrator authentication.
- Server-side sessions with HttpOnly, SameSite and configurable Secure cookie.
- CSRF-protected login, logout and product forms.
- Login throttling by account and IP using the database.
- First-admin creation via CLI only.
- Audit records for authentication and product changes.
- Dashboard counts from actual database records.
- Product catalogue administration (create, edit, publish/unpublish).

## Not yet implemented
Customer self-registration, subscriptions, payment collection/M-Pesa, refunds, customer access provisioning, support ticket workflows, public feature voting, CMS, and visitor analytics are not represented as working features. Their tables and workflows must be designed and implemented before being advertised or used. No integration with the existing School, ERP, LIMS or POS databases is made here.

## Security / operations
- Keep credentials in hosting environment variables, never in Git.
- Restrict database access to this application.
- Back up the database and test restores.
- Keep PHP errors out of public responses; send them to server logs.
- Restrict access to the hosting control panel and enforce strong unique admin passwords.
- Review access logs and audit events.
- Before production, configure a reverse proxy/web server to deny access to all paths outside `public/`.
