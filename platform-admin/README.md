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
7. Set environment variables in the hosting control panel:
   - `SMARTNOW_DB_HOST`
   - `SMARTNOW_DB_NAME`
   - `SMARTNOW_DB_USER`
   - `SMARTNOW_DB_PASS`
   - `SMARTNOW_SESSION_SECURE=1`
   - `SMARTNOW_MAIL_FROM` (a valid sender address configured on the hosting account)
   - `SMARTNOW_SUPPORT_EMAIL` (the support-team inbox for new customer requests and replies)
   - `SMARTNOW_ANALYTICS_SECRET` (a random secret of at least 32 characters used to HMAC pseudonymous visitor and rate-limit identifiers; keep it private and consistent)
   - `SMARTNOW_CUSTOMER_PORTAL_URL` (for example `https://cpanel.smarternowhosting.co.ke`; must be HTTPS and point to this app's public directory)
8. From the project root, create the first administrator using the CLI:
   `php bin/create-admin.php admin@example.com`
   The command prompts for a password; no default credentials are created.
9. Point the subdomain document root to `platform-admin/public`, enable HTTPS, and visit `/login.php`.

## Implemented
- Password-hashed administrator authentication.
- Server-side sessions with HttpOnly, SameSite and configurable Secure cookie.
- CSRF-protected login, logout, product, customer and ticket forms.
- Login throttling by account and IP using the database; only failed attempts count towards the threshold.
- CLI cleanup of authentication-attempt records older than 30 days (`php bin/cleanup-auth-attempts.php`); schedule it daily or weekly.
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
- Customer directory (create, edit, search and change record status, with contact person, phone, country code, organisation and internal notes).
- Support queue (filter, classify, prioritise, assign to an active administrator and update tickets).
- Ticket conversation history with administrator replies and internal notes.

## Not yet implemented
Paid subscription lifecycle, payment collection/M-Pesa, refunds, customer application access provisioning, public feature voting and referral tracking are not working features yet. Visitor analytics and the public submission form require migration 006 and the tracking script; analytics is consent-dependent. Customer email delivery depends on the hosting account's PHP mail configuration; test verification and password-reset delivery before opening registration publicly. They must be designed, implemented and tested before being advertised or used. Customer contact profiles are stored in `platform_customers`; login credentials and verification state are stored separately in `platform_customer_accounts`. No integration with the existing School, ERP, LIMS or POS databases is made here.

## Security / operations
- Keep credentials in hosting environment variables, never in Git.
- Restrict database access to this application.
- Back up the database and test restores.
- Keep PHP errors out of public responses; send them to server logs.
- Restrict access to the hosting control panel and enforce strong unique admin passwords.
- Review access logs and audit events.
- Before production, configure a reverse proxy/web server to deny access to all paths outside `public/`.
- Apply the SQL migrations exactly once and in numeric order to the database. Back up the database first.
- Schedule `php bin/cleanup-auth-attempts.php` through the hosting cron facility.


## Administrator roles
- `owner`: full access to the currently implemented administration areas.
- `administrator`: product, customer and support management.
- `support`: read-only customer directory and support-ticket management; cannot change customer records or manage products.
- `content`: product catalogue, subscription plan and knowledge base management.

Role checks are enforced server-side; hiding navigation links is not the security boundary. New administrator roles can be selected when creating an account; changing an existing administrator's role is not yet supported in the interface.


## Customer account mail configuration
Customer verification and password-reset messages use PHP `mail()`. The hosting account must be configured to send mail for the address in `SMARTNOW_MAIL_FROM`; the sender address must pass SPF/DKIM/DMARC alignment for reliable delivery. `SMARTNOW_CUSTOMER_PORTAL_URL` must be the HTTPS base URL of the deployed customer portal. Do not enable public registration until a real mailbox has successfully received and completed verification and password-reset tests.
