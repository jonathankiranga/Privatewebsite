# SmartNow Platform Administration

This is a separate PHP/MySQLi administration application for the SmartNow Data Venture platform. It is independent of the public static marketing site and does not modify any product application's operational database.

## Runtime
- PHP 7.4 or later (PHP 8.x recommended), with MySQLi and the mysqlnd driver.
- MySQL 5.7+ / MariaDB 10.3+.
- HTTPS is mandatory in production.
- Configure the subdomain document root to this directory's `public/` folder. Do not expose `src/`, `database/`, or `bin/` through the web server.

## Installation
1. Create a dedicated empty MySQL database and database user with least-privilege access.
2. Import `database/schema.sql`.
3. Run the one-time migration `database/migrations/002_customer_support_workflows.sql` against that database. It adds customer contact fields, ticket classification and descriptions, assignment, and ticket conversation history.
4. Set environment variables in the hosting control panel:
   - `SMARTNOW_DB_HOST`
   - `SMARTNOW_DB_NAME`
   - `SMARTNOW_DB_USER`
   - `SMARTNOW_DB_PASS`
   - `SMARTNOW_SESSION_SECURE=1`
5. From the project root, create the first administrator using the CLI:
   `php bin/create-admin.php admin@example.com`
   The command prompts for a password; no default credentials are created.
6. Point the subdomain document root to `platform-admin/public`, enable HTTPS, and visit `/login.php`.

## Implemented
- Password-hashed administrator authentication.
- Server-side sessions with HttpOnly, SameSite and configurable Secure cookie.
- CSRF-protected login, logout, product, customer and ticket forms.
- Login throttling by account and IP using the database.
- First-admin creation via CLI only.
- Audit records for authentication and product/customer/support actions.
- Owner-only administrator management (create accounts, activate/deactivate access, and protect the last active owner).
- Administrator status and role are refreshed from the database on each protected request, so deactivated accounts lose access on their next request.
- Role-based page access: owners and administrators manage products and customer records; content administrators manage the product catalogue; support administrators access customer records read-only and manage support tickets.
- Dashboard counts from actual database records.
- Product catalogue administration (create, edit and change publication status).
- Customer directory (create, edit, search and change record status).
- Support queue (filter, classify, prioritise, assign to an active administrator and update tickets).
- Ticket conversation history with administrator replies and internal notes.

## Not yet implemented
Customer self-registration and login, subscription plans and lifecycle, payment collection/M-Pesa, refunds, customer application access provisioning, customer-facing ticket submission and replies, public feature voting, knowledge base/CMS, referral tracking, and visitor analytics are not working features yet. They must be designed, implemented and tested before being advertised or used. The customer directory is an administrator-managed contact register, not a customer account system. No integration with the existing School, ERP, LIMS or POS databases is made here.

## Security / operations
- Keep credentials in hosting environment variables, never in Git.
- Restrict database access to this application.
- Back up the database and test restores.
- Keep PHP errors out of public responses; send them to server logs.
- Restrict access to the hosting control panel and enforce strong unique admin passwords.
- Review access logs and audit events.
- Before production, configure a reverse proxy/web server to deny access to all paths outside `public/`.
- Apply the SQL migration exactly once to the database. Back up the database first.


## Administrator roles
- `owner`: full access to the currently implemented administration areas.
- `administrator`: product, customer and support management.
- `support`: read-only customer directory and support-ticket management; cannot change customer records or manage products.
- `content`: product catalogue management only.

Role checks are enforced server-side; hiding navigation links is not the security boundary. Assign roles only through controlled database administration until a dedicated administrator-management screen is implemented.
