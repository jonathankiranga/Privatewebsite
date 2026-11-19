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
4. Run the one-time migration `database/migrations/003_customer_accounts.sql`. It creates the separate customer authentication and login-throttling tables.
5. Run the one-time migration `database/migrations/004_knowledge_base.sql`. It creates the published help-article store.
6. Run the one-time migration `database/migrations/005_subscription_enquiries.sql`. It creates the plan catalogue and customer enquiry workflow; it does not create paid subscriptions.
7. Run the one-time migration `database/migrations/006_analytics_and_public_submissions.sql`. It creates pseudonymous visitor event storage and public-form rate limiting. Back up the database before applying migrations.
8. Run the one-time migration `database/migrations/007_office_location.sql` if it has not already been applied.
9. Run the one-time migration `database/migrations/008_admin_password_recovery.sql`. It adds one-time administrator reset tokens, reset-request throttling and an authentication version used to invalidate existing sessions after a password reset. Back up the database first.
10. Run the one-time migration `database/migrations/009_invoicing.sql`. It creates invoice settings, invoice headers, invoice lines and manually recorded payments. Back up the database first. Configure the legal company details, KRA/VAT PINs and M-Pesa Till number under Billing → Invoice settings before issuing invoices.
11. Create the private configuration directory outside the website's public document root. On this hosting account, use:
   `/home/smartern/private-config/`
12. Create `/home/smartern/private-config/platform-admin.php` with the following PHP array. Replace every placeholder with the actual hosting/database value; do not commit this file to Git:
   ```php
   <?php
   return [
       'SMARTNOW_DB_HOST' => 'localhost',
       'SMARTNOW_DB_NAME' => 'YOUR_DATABASE_NAME',
       'SMARTNOW_DB_USER' => 'YOUR_DATABASE_USERNAME',
       'SMARTNOW_DB_PASS' => 'YOUR_DATABASE_PASSWORD',
       'SMARTNOW_SESSION_SECURE' => '1',
       'SMARTNOW_MAIL_FROM' => 'support@smarternowhosting.co.ke',
       'SMARTNOW_SUPPORT_EMAIL' => 'support@smarternowhosting.co.ke',
       'SMARTNOW_SMTP_HOST' => 'YOUR_HOSTING_SMTP_HOST',
       'SMARTNOW_SMTP_PORT' => '465',
       'SMARTNOW_SMTP_USERNAME' => 'support@smarternowhosting.co.ke',
       'SMARTNOW_SMTP_PASSWORD' => 'YOUR_MAILBOX_PASSWORD',
       'SMARTNOW_SMTP_ENCRYPTION' => 'ssl',
       'SMARTNOW_ANALYTICS_SECRET' => 'REPLACE_WITH_A_RANDOM_SECRET_OF_AT_LEAST_32_CHARACTERS',
       'SMARTNOW_CUSTOMER_PORTAL_URL' => 'https://admin.smarternowhosting.co.ke',
       'SMARTNOW_ADMIN_PORTAL_URL' => 'https://admin.smarternowhosting.co.ke'
   ];
   ```
   The application loads this private file automatically for the admin, customer and public help-centre bootstraps. Hosting environment variables, when set to a non-empty value, take precedence over values in this file. The PHP process must be able to read the file; restrict directory/file permissions as tightly as your hosting account permits (for example directory `0750`, file `0640`).
13. From the project root, create the first administrator using the CLI:
   `php bin/create-admin.php admin@example.com`
   The command prompts for a password; no default credentials are created.
14. PHPMailer 6.9.3 is bundled under `libraries/PHPMailer/src/`. No Composer installation, `vendor/` directory, SSH, Terminal or runtime download is required. Upload the complete `platform-admin/` directory, including `libraries/PHPMailer/`.
15. Point the subdomain document root to `platform-admin/public`, enable HTTPS, and visit `/login.php`.

## Implemented
- Password-hashed administrator authentication with email-based, one-time password recovery links (one-hour expiry), request throttling, and forced invalidation of existing admin sessions after a reset.
- Server-side sessions with HttpOnly, SameSite and configurable Secure cookie.
- CSRF-protected login, logout, product, customer and ticket forms.
- Login throttling by account and IP using the database; only failed attempts count towards the threshold.
- CLI cleanup of authentication-attempt records older than 30 days (`php bin/cleanup-auth-attempts.php`); schedule it daily or weekly.
- CLI cleanup of visitor events older than 180 days and public-form rate-limit records older than 30 days (`php bin/cleanup-visitor-analytics.php`); schedule it daily or weekly.
- First-admin creation via CLI only.
- Audit records for authentication and product/customer/support actions.
- Owner-only administrator management (create accounts, activate/deactivate access, and protect the last active owner).
- Customer registration with password hashing and email verification.
- Customer sign-in, sign-out, verification resend and password reset.
- Customer account dashboard and profile management, including contact details and password changes; product licensing and subscriptions are explicitly shown as not yet active.
- Customer-facing support requests, bug reports, feature suggestions and replies; internal administrator notes are excluded from customer-visible history.
- Knowledge base CMS with draft, published and archived articles, plus a public searchable help centre. Article content is escaped plain text; scripts and HTML are not executed.
- Subscription plan catalogue with administrator-entered pricing and terms, plus customer plan enquiries and an administrator sales pipeline. Enquiries do not create subscriptions or grant access.
- Email notifications to the configured support inbox for new customer requests and replies, and to customers for public administrator replies and status changes.
- Administrator status and role are refreshed from the database on each protected request, so deactivated accounts lose access on their next request.
- Role-based page access: owners and administrators manage products and customer records; content administrators manage the product catalogue, plans and knowledge base; support administrators access customer records read-only and manage support tickets.
- Dashboard counts from actual database records.
- Consent-based first-party website analytics for page views, pseudonymous visitors, referring hosts, device class and browser family.
- Public website submission form for support questions, bug reports and feature suggestions, routed into the administrator support queue with spam controls.
- Product catalogue administration (create, edit and change publication status).
- Customer invoicing in KES with sequential invoice numbers, customer and company detail snapshots, line items, configurable VAT, due dates, print / Save PDF layout, outstanding balances and audit events.
- Company billing profile and M-Pesa Buy Goods Till configuration, printed on invoices.
- Manual payment recording for M-Pesa, bank, cash or other methods, including transaction references, payment history and automatic invoice balance/status updates. Payment recording is not an automatic Safaricom confirmation.
- Customer directory (create, edit, search and change record status, with contact person, phone, country code, organisation and internal notes).
- Support queue (filter, classify, prioritise, assign to an active administrator and update tickets).
- Ticket conversation history with administrator replies and internal notes.

## Not yet implemented
Paid subscription lifecycle, automated M-Pesa STK push/validation and callback reconciliation, refunds, customer application access provisioning, public feature voting and referral tracking are not implemented. Invoicing and manual payment recording are separate functions; an administrator must verify an M-Pesa payment independently before recording its receipt reference. Visitor analytics and the public submission form require migration 006 and the tracking script; analytics is consent-dependent. Customer email delivery uses authenticated SMTP through PHPMailer; test verification and password-reset delivery before opening registration publicly. Customer contact profiles are stored in `platform_customers`; login credentials and verification state are stored separately in `platform_customer_accounts`. No integration with the existing School, ERP, LIMS or POS databases is made here.

## Analytics and public submissions setup
- Apply migration 006 after migrations 002–005.
- Configure `SMARTNOW_ANALYTICS_SECRET` in the PHP environment on the customer-platform subdomain. Do not put it in browser JavaScript or commit it to Git.
- The collector accepts browser events only from `https://smarternowhosting.co.ke`; if the public site is later served from another origin, update the exact-origin allow-list in `public/collect.php` deliberately.
- The public website loads `/analytics.js`; the script asks for consent before creating its first-party visitor identifier or sending page-view events.
- Public form submissions are stored as unlinked support tickets with the supplied reply email; administrators can review and respond in Support tickets. Notification delivery depends on `SMARTNOW_MAIL_FROM` and `SMARTNOW_SUPPORT_EMAIL`.
- Current analytics does not resolve visitor country/region and does not collect age or gender. Do not infer those attributes from IP addresses or browsing behaviour.

## Security / operations
- Keep database credentials and secrets in `/home/smartern/private-config/platform-admin.php` or hosting environment variables; never put the private config file in `public_html` or commit it to Git.
- Restrict database access to this application.
- Back up the database and test restores.
- Keep PHP errors out of public responses; send them to server logs.
- Restrict access to the hosting control panel and enforce strong unique admin passwords.
- Review access logs and audit events.
- Before production, configure a reverse proxy/web server to deny access to all paths outside `public/`.
- Apply the SQL migrations exactly once and in numeric order to the database. Back up the database first.
- Schedule `php bin/cleanup-auth-attempts.php` and `php bin/cleanup-visitor-analytics.php` through the hosting cron facility.


## Administrator roles
- `owner`: full access to the currently implemented administration areas, including invoices and invoice settings.
- `administrator`: product, customer, support and invoicing management.
- `support`: read-only customer directory and support-ticket management; cannot change customer records or manage products.
- `content`: product catalogue, subscription plan and knowledge base management.

Role checks are enforced server-side; hiding navigation links is not the security boundary. New administrator roles can be selected when creating an account; changing an existing administrator's role is not yet supported in the interface.


## Administrator password recovery mail configuration
All platform email is sent through PHPMailer using authenticated SMTP; PHP `mail()` is not used. Configure `SMARTNOW_SMTP_HOST`, `SMARTNOW_SMTP_PORT`, `SMARTNOW_SMTP_USERNAME`, `SMARTNOW_SMTP_PASSWORD` and `SMARTNOW_SMTP_ENCRYPTION` in the private configuration file. Use the SMTP hostname, port and encryption supplied by your hosting provider (commonly port 465 with `ssl`, or port 587 with `tls`). The SMTP username is normally the full mailbox address. `SMARTNOW_MAIL_FROM` must be an address permitted by that authenticated mailbox. Administrator password recovery also requires `SMARTNOW_ADMIN_PORTAL_URL` to be set to `https://admin.smarternowhosting.co.ke`. Apply migration 008 before using the recovery pages. Test delivery to an active administrator mailbox before relying on recovery.

## Customer account mail configuration
Customer verification and password-reset messages use the same PHPMailer SMTP configuration. The sender address must be authorised for the authenticated mailbox and pass SPF/DKIM/DMARC alignment for reliable delivery. `SMARTNOW_CUSTOMER_PORTAL_URL` must be the HTTPS base URL of the deployed customer portal. Do not enable public registration until a real mailbox has successfully received and completed verification and password-reset tests.
