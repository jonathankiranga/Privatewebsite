# SmartNow Data Venture website

A lightweight, dependency-free static website foundation for https://smarternowapps.co.ke/.

## Current implementation

- Responsive company homepage and product catalogue.
- Product detail sections for School Management Suite, Smart ERP, Smart LIMS, Smart POS and Bulk Messaging.
- Search-engine description, crawler instructions and XML sitemap.
- Product statements are limited to capabilities evidenced by the currently reviewed source code and documentation.
- No fabricated pricing, testimonials, customer counts, service-level claims or unsupported integrations.

## Important limitations

This commit is a public marketing-site foundation only. It does not implement customer registration, authentication, checkout, subscriptions, M-Pesa collection, support tickets, community discussions, knowledge-base management or an administrative back office. Do not advertise those workflows as available until they are implemented and tested.

The product descriptions are scoped summaries, not a certification that every feature is enabled in every customer deployment. Confirm the exact release, hosting arrangement, dependencies, supported workflows and commercial terms before onboarding a customer.

## Deployment

The repository currently contains static HTML and crawler files. It can be deployed as a static site by a compatible host. No build command or package installation is required. Configure the host's custom domain for `smarternowapps.co.ke` and verify HTTPS, canonical host redirects and sitemap availability after deployment.

## Next engineering stages

1. Create product-specific pages from verified application workflows and genuine screenshots.
2. Implement a real enquiry/demo request flow with durable storage, spam protection and delivery monitoring.
3. Design the central customer identity and organisation model.
4. Implement subscription plans, entitlements, billing records and payment reconciliation only after commercial rules and payment credentials are confirmed.
5. Build authenticated support, knowledge base and customer community workflows.
6. Build the administration console, role permissions, audit trail and consent-aware analytics.
7. Integrate each application through verified authentication and entitlement boundaries without disrupting its existing operational database.

## Data protection

Traffic analytics must minimise personal data. IP-based geography is approximate. Do not infer age or gender from IP addresses or browser fingerprints. Collect optional demographic information only when there is a clear purpose, appropriate notice and lawful basis.
