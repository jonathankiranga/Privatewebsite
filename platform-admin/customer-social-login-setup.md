# Customer social sign-in deployment

The customer portal supports Google and Yahoo OAuth sign-in. Provider credentials must remain in the hosting account's private configuration; never commit client secrets.

## 1. Apply the database migration

Run `platform-admin/database/migrations/011_customer_social_and_remember_sessions.sql` against the same database configured by the `SMARTNOW_DB_*` variables. This creates the hashed remember-token store and the external-identity mapping table.

## 2. Configure Google

Create an OAuth 2.0 client of type **Web application** in Google Cloud Console. Add this exact authorised redirect URI:

```
<SMARTNOW_CUSTOMER_PORTAL_URL>/customer-oauth.php?provider=google
```

Enable the OpenID Connect scopes `openid`, `email`, and `profile`.

## 3. Configure Yahoo

Create an OAuth application in the Yahoo Developer Network and add this exact redirect URI:

```
<SMARTNOW_CUSTOMER_PORTAL_URL>/customer-oauth.php?provider=yahoo
```

Enable OpenID Connect scopes `openid`, `email`, and `profile`.

## 4. Add private configuration values

Add these keys to the private `platform-admin.php` configuration array (or set them as hosting environment variables):

- `SMARTNOW_GOOGLE_CLIENT_ID`
- `SMARTNOW_GOOGLE_CLIENT_SECRET`
- `SMARTNOW_YAHOO_CLIENT_ID`
- `SMARTNOW_YAHOO_CLIENT_SECRET`

`SMARTNOW_CUSTOMER_PORTAL_URL` must be the HTTPS base URL of the customer public directory, with no trailing slash. For example, use the actual deployed URL ending in `/platform-admin/public` if that is where the customer PHP pages are published. Do not use a guessed or shortened callback URL.

The PHP cURL extension must be enabled. TLS certificate verification is enforced by the OAuth HTTP client.

## Account linking and session behaviour

Social sign-in only links to an existing customer account whose email has already been verified in the customer portal. The provider must also return a verified email claim. It does not create new customer accounts or link unverified addresses.

The **Remember me** option stores a random 256-bit token in an HttpOnly, Secure, SameSite=Lax cookie and stores only its SHA-256 hash in MySQL. Tokens expire after 30 days and are rotated when restored. Signing out revokes that device's token; changing the account password revokes all remembered-device tokens. The existing 15-minute failed-password-attempt protection remains enabled.

After deploying the code, apply the migration and configure the provider credentials before enabling the buttons for customers.
