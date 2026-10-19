# This is the all-in-public_html distribution

Follow DEPLOY-IN-PUBLIC-HTML.md for deployment paths. Its instructions replace the sibling-folder deployment section below. This archive has a different index.php entrypoint and PUBLIC_PATH=. configuration. Keep all supplied .htaccess files in place.

# M’s Charm — Accessories & More

A functioning PHP store with a server-rendered storefront, persistent database, secure sessions, an owner CMS, and cash-on-delivery orders. Designed for Hostinger PHP/MySQL hosting. No Node service, paid framework, Composer package, or build step is required.

## Start locally

Requires PHP 8.2+ with PDO, pdo_sqlite (local) or pdo_mysql (production), mbstring, fileinfo, and sessions. Tests additionally use PHP curl. Packaging uses PHP zip. Enable HTTPS in production.

1. Copy `.env.example` to `.env` in the project root, outside `public/`.
2. For local development, set:

```dotenv
APP_ENV=development
APP_URL=http://127.0.0.1:8765
DB_DRIVER=sqlite
DB_SQLITE_PATH=storage/store.sqlite
SESSION_SECURE=false
PUBLIC_PATH=public
```

3. From the project root, run:

```sh
php bin/install.php
php bin/create-owner.php
php -S 127.0.0.1:8765 -t public public/router.php
```

Open `http://127.0.0.1:8765/`. Sign in at `/login`, then open `/admin`. The first-owner CLI asks for your name, email, and a password of at least 12 characters. It never installs default passwords. Input is visible in the terminal; run it privately. Passwords are hashed before database storage.

The installer is idempotent: it creates missing tables and seeds an editable example catalog only if no website settings exist. It does not overwrite an installed store. All orders, reviews, and statistics start empty. Product photographs and products are sample content, not representations of actual inventory. Replace them before launch.

## Architecture and communication

```text
public/             The only web-accessible directory
  index.php         HTTP router and controller dispatch
  router.php        PHP development-server routing
  assets/           Separate CSS, JavaScript, favicon
  uploads/          Validated raster image uploads; executable files denied
app/
  bootstrap.php     Environment, secure sessions, response headers
  database.php      PDO connection and parameterized persistence helpers
  helpers.php       Validation, CSRF, rendering, output escaping, audit, rate limits
  services/
    auth.php        Login, customer registration, role checks, account updates
    catalog.php     Catalog, filtering, stock-aware session cart, review submission
    orders.php      Transactional checkout, price snapshots, inventory, order status
    cms.php         Authorized CMS writes, settings schemas, image uploads
  views/            Storefront and CMS templates
database/
  schema.php        Portable SQLite/MySQL schema
  schema.mysql.sql  Generated MySQL/InnoDB schema for inspection or phpMyAdmin
  seed.php          Editable starting catalog, content, navigation, settings
bin/                CLI-only installation, owner creation, SQL export, packaging
tests/              HTTP integration checks using a disposable database
storage/            Private logs and session files; local SQLite database
.env.example        Environment reference, without real credentials
```

The browser requests pages from the PHP router. Services retrieve current records through PDO and templates render semantic HTML with escaped values. The small JavaScript client updates the session bag through `/api/cart`. Admin forms make CSRF-protected POST requests; services check the authenticated role, validate inputs, save database records, and redirect. The next public request reads those records. There is no hard-coded catalog in storefront templates and no browser-local database pretending to persist store data.

## Database

Tables cover users, products, categories, orders, order items, reviews, global settings, homepage sections, CMS pages, navigation, promotions, contact messages, audit events, and rate limits. IDs use random 128-bit identifiers. Monetary values are integer minor units, with two decimal places. This version targets currencies using two decimal places. Dates are stored in UTC; admin date labels say UTC.

Production migrations explicitly use InnoDB and utf8mb4. Local SQLite enables foreign keys, WAL, and a busy timeout. Products and customers may be removed/disabled without erasing order price snapshots. Deleting a category uncategorizes its products; review associations become null if a product is deleted. Orders and audit records are retained. Accounts are disabled rather than deleted.

For future schema changes, add a versioned migration rather than changing existing column definitions and assuming `CREATE TABLE IF NOT EXISTS` will alter deployed tables. Back up first.

## Owner CMS

### Welcome discount update — implementation and deployment report

This update adds a default **10% first-order discount valid for exactly 24 hours after successful customer registration**, using the existing PHP/PDO authentication, store, admin roles, and cash-on-delivery order flow. There are no new dependencies, authentication providers, environment variables, or payment gateways.

**Activate on Hostinger:** extract the updated `ms-charm-public-html.zip` directly into your existing `public_html`, overwriting matching application files and preserving `.env`, uploads, and your live database. Sign in as an administrator and open **Welcome offers** (`/admin/welcome`). That authenticated admin visit applies the additive migration automatically and initializes the default configuration. You can instead run `php bin/welcome-migrate.php up` from `public_html`. The existing database user needs CREATE/ALTER privileges. Take a normal hosting database backup before applying a database upgrade. No existing account receives a retroactive offer, and existing orders retain their saved totals.

**Popup:** anonymous visitors see a branded native dialog after a short delay. It has a visible close button, Maybe later, registration/sign-in links, an explicit keyboard focus loop, native Escape handling, responsive sizing, and reduced-motion support. A local-storage preference plus a first-party cookie fallback remembers that the offer was shown/dismissed. A subtle link lets visitors reopen it later, and works as an ordinary signup link if JavaScript is unavailable. Logged-in customers and administrators never see the signup popup. Browser preferences do not grant an offer or establish eligibility.

**Eligibility and expiry:** `welcome_promotions` keeps one record per user ID and per SHA-256 normalized-email key using unique constraints. Each issued record snapshots its percentage, server UTC activation/expiry, status, redemption order/time/amount, and reason. It is created in the same transaction as the new customer; failed signup rolls both back. Existing-account login, profile/password updates, cleared browser storage, and a second device do not create a new record. Accounts/emails with prior orders, including guest orders, do not get a new first-order offer. Expiry is evaluated using server time on account/cart/checkout reads and immediately during redemption; no device clock or cron job is trusted. The account page shows Active, Expired, Redeemed, exact local-zone expiry, and the owning customer's order reference where relevant. Returning to a page refreshes status from the authenticated endpoint.

**Pricing rules:** the welcome saving is computed in integer currency cents from full-priced items only. Sale-priced items do not receive an additional welcome saving. Minimum-order and free-delivery thresholds still use the original merchandise subtotal, delivery charges are unchanged, and the existing tax percentage is applied to the discounted merchandise subtotal. Cart/checkout show original subtotal, welcome percentage/amount, discounted subtotal, delivery, tax, and final total. Orders snapshot the discount amount, entitlement identifier, and percentage in new columns. A first order containing only sale items ends first-order eligibility without savings; the bag/account explain this before submission. Cancellation does not restore an issued welcome redemption. There is no coupon code to copy and no other coupon system to stack with.

**Transactions and concurrency:** MySQL checkouts lock the customer before product/offer reads, then lock the offer, calculate authoritative prices, claim stock, insert the order, and atomically redeem the offer in one transaction. The unique checkout key is rechecked after acquiring the account lock. Identical concurrent keys return the same order. Different concurrent keys cannot claim the same offer; a checkout still quoting the lost/expired discount is rejected with a request to review the updated total. Failing any order operation rolls back stock, order, and redemption together. The existing cash-on-delivery amount is the saved validated total; no online payment provider exists in this project.

**Admin:** Welcome offers lets authorized admins enable/disable grants to future registrants, set an integer percentage from 1–100 and validity from 1–168 hours, review actual status counts, and inspect the latest 200 grants/redemptions with order links. Full history stays in the database. Disabling grants or changing defaults does not revoke or modify already-issued offers. Customers cannot call these actions. `/api/welcome` is authenticated and read-only; it always returns the current user's status and ignores attempts to select another customer.

**Migrations and rollback:** `database/welcome-migration.php` supports repeated upgrades and partial-upgrade recovery. It adds the entitlement table, three default/null order columns, and a single settings group. `php bin/welcome-migrate.php down` reverses an unused migration only when no entitlements or discounted order history exist. Once any offer has been issued, destructive rollback is refused to preserve history; disable future grants in Admin instead. Run a schema rollback during maintenance alongside compatible application source, since new admin requests automatically upgrade missing welcome schema. The welcome migration has no destructive data backfill. Fresh installation/exported schema includes the new table and pricing columns.

**Files added:** `app/services/welcome.php`; `database/welcome-migration.php`; `bin/welcome-migrate.php`; views `welcome-popup.php`, `welcome-account.php`, `welcome-admin.php`, `order-discount.php`; tests `welcome.php`, `welcome-http.php`, `welcome-concurrency.php`, and shared `http-client.php`.

**Files updated:** `app/bootstrap.php`; services `auth.php`, `catalog.php`, `orders.php`, `cms.php`; `public/index.php`; views `layout.php`, `auth.php`, `account.php`, `totals.php`, `admin.php`, `admin-orders.php`, `confirmation.php`, `admin-field.php`; `public/assets/store.js` and `style.css`; `database/schema.php`/`schema.mysql.sql`; `bin/install.php`; existing `tests/integration.php` now reuses its extracted HTTP client. The full Hostinger package and main `public_html` output include all preceding thumbnail, status, category, mobile, and filter updates.

**Verification:** 38 welcome service assertions passed on SQLite and 38 on MySQL/MariaDB, using a CLI-only controlled clock for exact expiry-boundary tests. The existing 74 HTTP integration assertions and 27 new welcome HTTP assertions passed on MySQL/MariaDB. Separate simultaneous-process MySQL tests passed for two different checkout keys and two identical keys: exactly one order, one stock decrement, and one redemption. Existing 25 subcategory checks passed on SQLite. Browser automation checked first-visit display, reload-safe dismissal, rediscovery, popup signup navigation, focus wrapping, 390px popup sizing, and account/admin layouts at 320/390/768/1280px. PHP syntax checks and JavaScript syntax checks passed. Hostinger deployment, its particular server configuration, and real payment capture were not tested; this is not a third-party security audit.

Run service checks with `php tests/welcome.php` (fresh isolated SQLite database is created automatically). For MySQL set `TEST_DB_DRIVER=mysql` plus the connection variables for an empty disposable database. Run the existing integration suite and then `tests/welcome-http.php` against a separate APP_ENV=test HTTP server with identical database environment; do not use production data. For concurrency, initialize a disposable MySQL database, set APP_ENV=test, then use `tests/welcome-concurrency.php setup <fixture-directory> different` (or `same`), launch two `worker <fixture-directory> A/B` processes, wait for their ready files, create `go` in that directory, and run `verify <fixture-directory>`.

**Limits:** this account/email-based system cannot prevent one person using multiple email addresses/accounts. The existing app has no email-verification service; none was silently added. No device fingerprinting or unnecessary personal data is collected. Rates, duration, and eligibility come from the server; direct browser-supplied discounts/expiry values are ignored. Review the stated pricing/eligibility rules for your store before enabling the campaign.

### Mobile website

The same domain automatically adapts to phone, tablet, and desktop screen sizes. No separate mobile app, subdomain, or database is required. The phone layout includes a sticky storefront header, mobile navigation, touch controls, compact checkout, and a collapsible admin menu. Admin tables become labeled cards on small screens when JavaScript is enabled; the scrollable table remains available if JavaScript is unavailable. CSS/JavaScript URLs include content versions to load fresh styles after deployment.

### Optional subcategories

In Collections, create or edit a category and choose **Parent category (optional)**. Leave **None — top-level category** selected for an independent category. For example, choose Jewelry as the parent of Bracelets. Products can be assigned directly to either category; category pickers show the full path. A parent’s shop filter includes products from its subcategories, while child links let shoppers narrow the collection.

The database upgrade is automatic when an authorized administrator first opens Collections or Products after uploading this version. Existing categories remain top-level and existing products/orders are preserved. Public requests do not change the schema. The database user needs ALTER permission for this one-time upgrade. Alternatively, run `php bin/install.php` from your deployment root. The upgrade was checked on both SQLite and MySQL/MariaDB against the original schema.

Nested subcategories are supported. Self-parenting and cycles are rejected server-side. Disabling a parent hides its descendants and prevents their products being ordered. Deleting a parent keeps its direct children as top-level categories. Clear the parent field to turn any subcategory into a standalone category again.

| Section | Operations |
| --- | --- |
| Overview | Real order/product/review counts, recent records, completed sales grouped by currency |
| Products | Create/edit/delete/duplicate, prices and sale prices, slug, category, multiple image uploads/URLs, stock, visibility, featured flag, display order |
| Collections | Create/edit/delete, images, description, slugs, visibility, display order |
| Orders | Search/filter, customer/delivery details, line items, immutable purchase prices, status, payment status, internal notes |
| Reviews | Edit, approve, reject/hide, delete, feature; new customer submissions are pending |
| Homepage | Hero and every section’s title, description, image, CTA, visibility, and display order |
| Website content | About, FAQ, legal pages, custom pages, plain-text content and per-page SEO |
| Navigation | Header/footer links, labels, destinations, visibility and display order |
| Promotions | Create/edit/delete promotional sections, images, CTA and visibility |
| Branding | Name, slogan, logo/favicon images, primary/secondary/accent colors |
| Social / contact / footer | Centralized links, business information, contact introduction/form visibility, footer copy |
| Store settings | Currency, shipping fee/free-shipping threshold, minimum order, tax, open/closed state, announcement, delivery copy |
| SEO | Site defaults and supplied social sharing image; product metadata follows product records |
| Messages | Read contact enquiries, mark new/read/resolved |
| Admin users | Owner/developer-only account creation, role changes, password resets and disabling |
| System & audit | Owner/developer-only environment summary and audit events |

Feature products and choose their order in Products. Feature approved reviews in Reviews. Section order and item order use numeric display positions. Image fields accept safe site-relative paths or HTTPS URLs; uploads accept JPG/PNG/WebP only, up to 4 MB and 8000 pixels per side. Up to 12 product images are supported. Original uploaded files are not recompressed. Removing an image reference does not erase the physical file; periodically review orphan uploads and delete them through your developer’s maintenance workflow.

Core page slugs remain stable so checkout/legal links cannot break. Custom page slugs are editable. Plain-text CMS content is escaped, with paragraph and line-break formatting; unrestricted HTML is deliberately unavailable.

## Authentication and authorization

Customers can browse, register, sign in, maintain their own name/phone/password, order, and see their own account orders. Guest checkout is supported. Guest confirmations are accessible only from the placing session. Guest orders are not automatically linked to an account using an unverified matching email.

`customer` cannot access admin routes or invoke admin writes. `admin` can operate catalog/content/orders/reviews but cannot manage user roles or system records. `owner` and `developer` have full CMS authority including role management. Technical access to files, database backups, deployment credentials, and server configuration is controlled by your hosting account/SSH permissions; application roles do not grant shell access.

Every request reloads the active user and role from the database. Role changes, account disablement, and password changes invalidate older sessions through `auth_version`. The current owner’s profile edit keeps their active session usable. The application prevents self-demotion/self-disablement and retaining zero active owners/developers. Login regenerates the session ID. Sessions expire after two hours of inactivity and use HttpOnly, SameSite=Lax, and production Secure cookies.

All mutations require CSRF tokens. Queries are parameterized; editable URLs and colors are constrained; rendered content is escaped. A restrictive Content Security Policy, nosniff, anti-framing, referrer policy, and HTTPS HSTS are supplied. Login, registration, contact, review, and checkout abuse controls are database-backed per IP. Configure trusted proxy IP handling with your developer if deployment places all requests behind a proxy; never trust arbitrary forwarded headers.

No public password-reset or email-verification flow is included. Owners can reset account passwords through Admin users. Do not add a mail-based reset until a verified email delivery service and single-use expiring tokens are implemented.

## Orders, inventory, payments

Checkout validates customer details, terms acceptance, store state, minimum order, stock, prices, shipping, and tax on the server. It ignores client-supplied totals. Orders store line names, quantities, unit prices, currency, delivery/tax amounts, contact/address information, notes, and timestamps. Tax is a simple configured percentage of the product subtotal; it does not calculate jurisdiction-specific tax.

The checkout transaction locks product rows on MySQL, in stable ID order, and atomically decrements available stock. Any failure rolls back the order and inventory together. A session-issued unique checkout key prevents duplicate submission. Cancelled orders restore stock once. Completed/cancelled orders cannot be reopened. Payment status is manually managed for cash-on-delivery. Cancelling does not initiate a refund.

Payment-provider integration can extend the existing `payment_method` / `payment_status` fields and order service with signed webhooks and provider idempotency. Never trust a browser redirect as proof of payment. No card gateway, automated email/SMS notifications, discount codes, variants, wishlist, or advanced analytics are wired in this version; the brief treats them as future extensions. Contact enquiries are stored in the admin inbox, not sent by email.

The bag is session-backed and survives navigation; changing prices is reflected on the next bag/checkout read. It is not synchronized between devices and expires with the session. Sold-out and inactive products cannot be ordered.

## Hostinger deployment

This project uses PHP and PDO MySQL, which Hostinger documents as supported on Web/Cloud plans: https://www.hostinger.com/support/which-databases-and-data-tools-are-supported-at-hostinger/ . Your account, plan, domain, SSL, SSH, and database access still need to be configured. This package has not been uploaded to a Hostinger account.

1. In hPanel, configure PHP 8.2 or later and create a MySQL database/user. Use the database’s full assigned names. Enable SSL for the domain.
2. Keep private application folders outside the web document root. Recommended sibling layout:

```text
/domains/your-domain/
  .env
  app/
  bin/
  database/
  storage/
  public_html/     Contents of this package’s public/ directory
    index.php
    .htaccess
    assets/
    uploads/
```

   Copy `app/`, `bin/`, `database/`, and `storage/` beside `public_html/`. Copy the contents of `public/` into `public_html/`, including both `.htaccess` files. The `index.php` bootstrap already expects `app/` in its parent directory. Do not copy the complete project into `public_html/`.

3. Place `.env` beside `app/`, not inside `public_html/`. Set `APP_ENV=production`, `APP_URL=https://your-domain`, `DB_DRIVER=mysql`, database host/port/name/user/password, `SESSION_SECURE=true`, and `PUBLIC_PATH=public_html`. Do not leave local development cookie settings on a live store. Configuration values are literal strings; do not surround passwords with shell interpolation.
4. Give the PHP process write permission to `storage/` and `public_html/uploads/`. Use private permissions for `.env` and storage appropriate to your host; never make the entire application world writable. Ensure InnoDB is used.
5. From the domain directory over SSH run `php bin/install.php`, then `php bin/create-owner.php`. CLI PHP must have the required extensions. If SSH is unavailable, have your developer import `database/schema.mysql.sql` using phpMyAdmin and initialize settings/owner through a secure controlled deployment session. No publicly exposed setup or default-password endpoint is shipped.
6. Sign in on your HTTPS domain, complete the store content, inspect uploads, and perform a test order/cancellation. Verify that requesting private files such as `/.env` or `/storage/` does not disclose anything. Verify rewrite behavior on your hosting plan. Adjust deployment paths only if your host uses a different layout.
7. Replace sample photos/descriptions, verify all prices/materials/stock, configure contact/social links, delivery/tax/currency settings, and publish reviewed delivery, return, privacy, and terms content. Seeded legal text explicitly identifies itself as a starter. This is business setup work, not a source-code edit.

Back up the MySQL database and uploads regularly using the hosting backup tools. Keep backups outside `public_html`. Rotate hosting credentials and restrict database users to this store. Keep PHP patched. Review logs without exposing them publicly. On existing installations, preserve uploaded images, `.env`, session/storage data, and the live database when replacing application source.

## Tests and verification

`tests/integration.php` runs HTTP and database assertions, including server permissions, CSRF, role injection, live CMS edits, pricing, stock rejection, order idempotency, confirmation ownership, cancellation, review moderation/XSS escaping, contact enquiries, product duplication, category CRUD, closed-store rejection, and owner-session continuity.

Tests refuse to run unless `APP_ENV=test` and the selected database has no installed settings. **Use a separate disposable database.** Both the test PHP server and test CLI must have identical environment variables. Example POSIX shell (PowerShell users can set the same values with `$env:KEY='value'`):

```sh
export APP_ENV=test APP_URL=http://127.0.0.1:8766 SESSION_SECURE=false
export DB_DRIVER=sqlite DB_SQLITE_PATH=/absolute/path/to/new-test.sqlite
php -S 127.0.0.1:8766 -t public public/router.php
# In a second terminal with the SAME environment:
php tests/integration.php
```

For MySQL use a fresh separate test database and `DB_DRIVER=mysql` with its credentials. Do not point the suite at production. Stop the test server after completion. SQLite passed 69 core integration checks and MySQL/MariaDB passed 74 checks, including uploads, during delivery verification; mobile/desktop storefront and image loading were inspected. This is not a third-party security audit or a load test.

There is no transpilation step. Check source with `php -l` and JavaScript with `node --check public/assets/store.js` when Node is available. Generate the SQL reference with `php bin/export-schema.php > database/schema.mysql.sql`. Make a source-only deployment zip with `php bin/package.php /absolute/path/ms-charm-hostinger.zip`.

## Assets

Seed photographs are linked to Unsplash/Pexels as editable external URLs. They require network availability; upload your own catalog photography for independent production delivery. Credits and verified source URLs are in `ASSET-CREDITS.md`. No fabricated customer reviews or orders are seeded.



## Offers & Discounts update — implementation and deployment report (16 October 2026)

This release is integrated into the existing PHP/PDO application, existing products and collections, admin roles, CSRF protection, image uploads, integer-cent pricing, mobile styles, and cash-on-delivery order transactions. It includes all earlier thumbnail, red cancellation status, optional subcategory, mobile, filter dropdown, and welcome-discount updates. Promotional banners at `/admin/promotions` remain available as a separate content feature.

### Upload to your existing Hostinger site

1. Back up your current files and database. Extract `ms-charm-public-html.zip` locally, then upload its **contents directly into your existing public_html**, replacing matching application files. There is no extra parent folder. The updated ready-to-upload main folder is also supplied as `outputs/public_html`.
2. Keep your live `.env`, database, uploaded images, and storage files. The ZIP excludes live credentials, uploaded customer/product images, sessions, logs, and database files. Keep all supplied `.htaccess` files. No `.env` changes are needed.
3. Sign in and open **Admin → Offers & Discounts** (`/admin/offers`). This authorized page performs the additive migration automatically. Alternatively, from the actual public_html directory run `php bin/offers-migrate.php`. The existing database account needs CREATE, ALTER, and INDEX permissions for this one-time upgrade.
4. Create a draft offer, preview it, then enable it and test the storefront, bag, checkout, and saved order. Changes after installation require no upload or code edit. Use your existing live store credentials; no new administrator or production offer is shipped.

The migration adds `offers`, `offer_products`, `offer_categories`, `offer_redemptions`, and `offer_mutex`, plus `orders.offer_discount` and `orders.offer_snapshot`. It is repeatable on SQLite and MySQL/MariaDB, preserves existing records, and does not rewrite product prices. `database/schema.mysql.sql` includes the fresh-install schema; **do not reimport a full schema into your existing live store**. Deletion in the admin disables and archives an offer while retaining its assignments and redemption history. There is deliberately no destructive down migration for historical pricing records.

### Create and manage offers

Open **Offers & Discounts → Create New Offer**. Enter the internal name, customer headline/description, optional image, button label, discount type/value, scope, dates, restrictions, and popup settings. The customer headline/description/image/button are shared between the offer and its popup. This keeps what customers see aligned with the promotion. Preview uses the current unsaved form in the actual popup component, without submitting or publishing it. Preview links cannot navigate away. Saving Draft does not affect live prices.

Choose **Selected product(s)** for one or many actual catalog products, **Selected collection(s)** for collections and their descendants, **All products**, or **All products except exclusions**. The product picker supports name search, collection filtering, persistent checkboxes, thumbnails, original prices, visibility and stock information. Selected names remain listed when hidden by search. Storewide saves require a confirmation checkbox, and activation from the overview asks for confirmation. Missing targets are reported in the overview and never broaden an offer automatically. Disabled products and hidden collection branches remain unavailable.

Set Publication to **Enabled** to activate immediately, or enter a future start to schedule it. A blank start means now; a blank end means indefinite. Dates entered in the admin use `APP_TIMEZONE` (Asia/Beirut by default) and are stored as UTC. Status is calculated from server time: Draft, Scheduled, Active, Expired, Disabled. No cron job is required. The overview lists actual targets, type/value, start/end, creation date, popup settings, priority, redemptions, and edit/preview, duplicate, activate/deactivate and delete actions. Filter by status, type and local start-date range. A duplicate always starts as Draft, with no copied redemptions.

Restrictions default to zero/unlimited, with all customers eligible. Minimum spend refers to the eligible merchandise assigned to that offer, excluding delivery/tax. Maximum savings applies once per offer per order. Total limits count placed orders. Per-account limits require sign-in and use the account's normalized email hash; guests cannot bypass an account limit by entering an email at checkout. Cancellation does not restore a redemption. Customer identity cannot be reliably deduplicated across different email addresses without a separate identity-verification system.

### Authoritative pricing and welcome compatibility

Percentage discounts are whole numbers from 1–100. Fixed discounts use the store currency and apply **per piece**, capped at that piece's regular price; they cannot produce negative prices. Regular product prices remain unchanged. Existing sale prices compete with offers: the lower resulting price wins, without applying another percentage to an already reduced sale price.

For overlapping offers, higher priority wins on each item, then older creation timestamp and ID break ties. Each line receives at most one offer. Distinct lines can receive distinct offers. Caps allocate savings in stable product-ID order. A higher-priority conditional offer is disclosed on product pages and cards; conditional/capped offers are calculated in the bag rather than shown as an unconditional card price. This avoids promising a price before minimum-spend or order-cap conditions are known. Card prices, product detail and structured data, shop sorting and price filters use the same effective-price function for unconditional offers.

The existing welcome entitlement, signup activation, expiry, one-use rules, and account status remain. The bag compares the entire promotional saving with the welcome saving and uses the **larger single result**; a tie favors promotional offers. It never silently combines both. A first order using a better promotion ends unused first-order welcome eligibility, with an explicit explanation in the bag/account. This compatibility rule supersedes the earlier release's welcome-only cart description.

The bag and checkout show original line totals, promotional line savings, named discounts, discounted subtotal and final total. Shipping thresholds and minimum-order rules keep their existing original-subtotal behavior; tax applies to the discounted merchandise subtotal. Checkout rereads trusted database prices and eligibility inside its transaction. Posted prices, offer IDs, amounts and expiry dates cannot grant discounts. A previously quoted checkout whose saving falls or total increases must be reviewed again. Offer expiry is checked again at redemption. Orders snapshot offer IDs, headline, type/value, original line totals, quantities and allocated savings. Saved order summaries use these snapshots, so later offer edits, expiry, cancellation or deletion do not rewrite historical totals. Order item unit prices remain original pre-offer prices, with the separate immutable discount breakdown explaining the final amount.

Cash on delivery is the existing payment method; the saved order total is its amount due. This project has no variant pricing model or external card-payment gateway, so none is duplicated or introduced. Buy-more tiers, coupon codes and stacking controls are intentionally omitted; unsupported combinations cannot be enabled.

### Popups and discovery

The customer endpoint `/api/offers` returns only safe active offer information, with server-validated time, customer restrictions, remaining redemption availability and available product targets. Missing internal destinations fall back to the shop; `/shop` automatically chooses a single selected product or first selected category where appropriate. External CTA URLs must use HTTPS. Both the landing page and endpoint omit offers with no available, eligible stock. With no eligible offer, no empty popup is shown.

Only one popup opens automatically per page: highest priority among eligible unseen popup-enabled offers. It shares the welcome popup's visual system, responsive layout and native accessible dialog behavior, and never overlaps the welcome dialog. If no offer popup is selected, the existing welcome popup can run. Other active offers remain discoverable through **Explore current offers** and `/offers`. The request is deferred until after initial render, network failures leave the storefront usable, and missing images hide cleanly.

Frequency choices are **once per browser tab session** (sessionStorage), **once per rolling 24 hours**, or **once per offer** (localStorage). Preference keys are independent per offer; a duplicate has a new key. A popup is remembered as soon as it is displayed, avoiding repeat displays on navigation. Clearing storage can change popup visibility, never eligibility, dates or price. Blocked storage suppresses offer auto-popups instead of repeatedly displaying them. Visible close/Maybe later buttons, Escape, Tab/Shift+Tab wrapping, focus restoration, touch sizing and reduced-motion styling use the existing responsive design. No third-party frontend library was added.

Public requests perform no schema changes. Pricing lists are reused within the current page render; no cross-request price cache can bypass expiry. Checkout and offer edits share an InnoDB mutex row before reading eligibility, with current locking reads for redemption limits and product stock claims. This deliberately serializes short checkout transactions for this small store, including guests and offers created while another checkout runs. A high-volume deployment should replace the global mutex with carefully ordered per-offer reservation locks and load-test the change. Current offer counts use database indexes; transactional current reads avoid stale MySQL repeatable-read snapshots. This implementation is designed for the current catalog size, not an unbounded bulk product selector.

### Files added / changed

Added:
- `app/services/offers.php`
- `app/views/offers-admin.php`, `offers.php`, `offers-popup.php`, `offer-dialog.php`
- `database/offers-migration.php`
- `bin/offers-migrate.php`
- `tests/offers.php`, `offers-http.php`, `offers-concurrency.php`, `offers-popup.js`

Modified:
- `app/bootstrap.php`, `app/services/catalog.php`, `app/services/orders.php`
- `app/views/admin.php`, `layout.php`, `product-card.php`, `product.php`, `cart.php`, `checkout.php`, `totals.php`, `order-discount.php`, `welcome-popup.php`, `welcome-account.php`
- `public/index.php`, `public/assets/store.js`, `public/assets/style.css`
- `database/schema.php`, generated `database/schema.mysql.sql`, `bin/install.php`
- `tests/welcome-http.php` (updated expected stale-checkout wording), `README.md`

Matching deployed files are synchronized in the flattened main `public_html` folder. Both the Hostinger and developer-source ZIPs are regenerated. Build scripts keep their existing dependency-free packaging process.

### Verification and limitations

Passed: **60 offer service assertions on SQLite and 60 on MySQL/MariaDB**, **20 offer HTTP assertions**, **9 popup preference assertions**, **2 simultaneous-process MySQL scenarios**, **74 existing checkout/CMS HTTP assertions**, **27 welcome HTTP assertions**, **38 welcome service assertions**, and **25 subcategory assertions**. Total: **315 automated checks**, plus PHP/JavaScript syntax checks and repeatable fresh-install verification. Scheduling tests use a CLI-only controlled clock; HTTP always uses server time. Both fresh setup and upgrade from an orders schema without offer columns were exercised. No frontend build/type-check step exists in this dependency-free PHP/JavaScript project.

Browser checks verified real popup content, dismissal across navigation, preserved product selection while filtering, unpublished preview, focus wrapping, one open dialog, mobile popup fit, and admin layouts at 320/390/768/1280px without horizontal overflow. A mobile screenshot is supplied as `ms-charm-offer-mobile.png`. Concurrent different keys produced one discounted order plus one stale-quote rejection; concurrent same keys produced one identical order for both callers, one stock claim and one redemption.

Hostinger deployment and its production database permissions have not been exercised; the package is ready for your upload. No live customer data or production offers were changed. Automated verification used disposable local SQLite and MariaDB databases, not the live store. There is no external payment provider or real payment capture to test in the current project.

To reproduce: `php tests/offers.php` creates a disposable SQLite database. For MySQL set `TEST_DB_DRIVER=mysql` and connection variables pointing to an empty disposable database. HTTP checks require an APP_ENV=test server with the same test database; run `tests/integration.php`, `tests/welcome-http.php`, then `tests/offers-http.php`. Run popup checks with `node tests/offers-popup.js`. For concurrency run `php tests/offers-concurrency.php setup <new-fixture-directory> different` (or `same`), launch two `worker <directory> A/B` processes, wait for their ready files, create `go`, then run `verify <directory>`. Never point these test suites at production data.

## Location-based delivery update — implementation and deployment report (16 October 2026)

This release adds delivery areas to the existing PHP/PDO storefront, admin roles, checkout, offers, welcome discounts, and cash-on-delivery order flow. It preserves the existing single-currency, integer-minor-unit pricing and merchandise-only tax calculation. No duplicate checkout, product catalog, customer table, payment provider, or frontend dependency was added.

### Hostinger installation and first setup

1. Back up your site files and database. Extract the latest `ms-charm-public-html.zip` and upload its contents directly into your existing `public_html`, replacing matching application files. The synchronized main folder is `outputs/public_html`; all previous updates are included. Keep the supplied `.htaccess` protection files.
2. Keep your live `.env`, uploaded images, and storage files. The packages exclude credentials, runtime databases, sessions, logs and uploaded images. No `.env` changes are required.
3. Sign in and open **Admin → Delivery & Shipping Settings** (`/admin/delivery`). This authorized page performs the repeatable additive migration. Alternatively, from your actual public_html directory run `php bin/delivery-migrate.php`. The database account needs CREATE, ALTER and INDEX privileges for the upgrade.
4. Click **Add delivery area** and enter your actual coverage and prices. No sample locations or example rates are installed in production. Add all supported governorates/districts/towns/neighborhoods you need.
5. Choose the unsupported-area policy, review the existing free-delivery-threshold checkbox, check **Enable location-based delivery**, then **Save delivery policy**. Use the admin preview and a test checkout to confirm your rates.

For a safe upgrade, location-based delivery starts **off**. Until you enable it, your existing flat fee and free-delivery threshold remain in use. Once enabled, a valid location is required for every new order. If no locations are configured and unsupported areas are blocked, orders cannot be placed until you add coverage. The old Store settings field is now labeled **Legacy flat delivery fee** to make its role clear.

### Administrator controls

Each area has a stable generated ID, area/neighborhood name, governorate, optional district, optional city/town/village, optional postal or internal area code, fee, currency, minimum merchandise amount, active status, customer-facing notes, delivery estimate, and timestamps. Names/locations/codes are normalized for case, punctuation and whitespace and protected by a unique hash, preventing duplicate identical configurations. Different neighborhood names allow different rates within the same district. This is a merchant-managed area directory, not an exhaustive Lebanese geographic registry or geocoding service.

The overview supports text search, active/inactive filtering and governorate filtering. It shows the current currency/rate/minimum, notes, estimate, placed order count and completed order count. Edit and activate/deactivate actions take effect for new quotes immediately. An unused area can be deleted; an area referenced by an order must be deactivated instead. Deactivation does not remove old delivery information.

The default unsupported policy is **Do not accept orders outside listed areas**. Alternatively, explicitly enable **Other area** and enter its fallback fee. This adds a clearly priced catch-all destination; only this explicit choice can use the fallback. Arbitrary unknown identifiers do not silently receive a default rate. Customer address text is not geocoded or independently verified against their selected area.

The **Apply existing Store settings free-delivery threshold** checkbox starts checked to preserve your existing configured rule. It only waives area delivery if the current positive threshold is reached. If the store threshold is zero, no threshold-based waiver occurs. Uncheck it to always charge an area's configured fee. A fee of zero is an explicit free-delivery rate and displays **Free Delivery**. No product offer or welcome discount automatically discounts delivery.

The admin preview accepts an area and original merchandise subtotal and uses the same delivery calculation as checkout. It also works before area mode is enabled, creates no order, and does not change the administrator's bag or delivery selection.

### Customer checkout and saved addresses

Checkout has a **Delivery Address** section with existing name, phone and email fields, plus supported-area selection, street address, building, optional floor/apartment, city/town and optional delivery instructions. Area selection is searchable and can be filtered by configured governorate. Option labels include district/town where available. Only active rates in the store's current currency are selectable. Search preserves the current selection.

If the chosen area specifies a town, checkout fills that canonical town and makes it read-only. A district-wide area with no listed town requires the customer to enter their actual town; Other area also requires town text. Building information is required in location-based mode. The selected area and address draft survive refresh in the customer's own PHP session. Signed-in customers can explicitly choose **Remember this delivery address**; the address is saved only after a successful order and prefills future checkout, including after signing in again. Saved addresses are always revalidated and repriced, so a disabled or outdated area cannot bypass current coverage rules.

Changing an area fetches a fresh server-rendered delivery/discount/total summary without reloading the page. Updating address fields preserves the draft. A loading status temporarily disables Place my order; errors or unsupported/minimum-unmet destinations keep it disabled and explain the problem. With JavaScript unavailable, **Review delivery fee** posts the selected area and draft, returns to checkout with a new quote, and requires the customer to review before placing the order. Nothing is silently ordered by the review action.

### Pricing, currency and checkout validation

The final calculation remains **original merchandise subtotal − valid product discounts + delivery + existing merchandise tax**. Welcome/offer priority and non-stacking rules are unchanged. Delivery is calculated independently of product discounts. Both the area's minimum and the optional existing free-delivery threshold use merchandise subtotal **before discounts**, excluding delivery and tax. Existing global minimum-order rules keep their original definition. Tax continues to apply to discounted merchandise, not delivery; no new tax rule is introduced.

Money stays in integer minor units using the store's existing two-decimal convention. Every area and the explicit fallback record its currency. A later store-currency change does not silently relabel old rates: mismatched rates are unavailable, and the admin must re-enter and confirm values in the new currency. There is no automatic USD/LBP conversion service.

`POST /api/delivery` requires the current checkout key and existing CSRF protection, rate limits requests, validates the selected identifier, ignores posted amounts, and returns only the current session's safe totals fragment and public delivery details. GET does not expose saved addresses. Monotonic quote revisions prevent an older AJAX request from replacing a newer selection. The public bag endpoint contains no saved private address.

Before creating an order, the backend captures and validates delivery fields, locks delivery configuration and the relevant rate, recalculates products, discounts, stock, shipping and tax, and compares a server-held delivery quote fingerprint. A changed destination, rate, coverage, currency or other relevant quote data requires a review; a reduced discount or increased final amount also triggers the existing stale-price check. Browser fees, totals, location names and forged currency values are never used as pricing authority. For configured town-level areas, posted city text cannot replace the area's canonical town.

Delivery management and checkout share a mutex before reading current configuration; MySQL current locking reads prevent stale repeatable-read snapshots from accepting an old rate. Offers, user and stock locks follow a consistent order. Order idempotency prevents retries from adding delivery twice. As with the existing offer engine, these short global locks suit this store's current scale; a high-volume installation should load-test and redesign lock granularity before scaling.

### Order history and data preservation

The additive migration creates `delivery_areas` and `delivery_mutex`; adds `orders.delivery_area_id` with a foreign key and `orders.delivery_snapshot`; adds nullable `users.delivery_profile`; and creates a `shipping` settings record plus lookup indexes. Existing orders are left untouched and remain readable without a backfill or fabricated area. Fresh-install schemas and the generated MySQL schema are updated. Do not reimport a full fresh-install schema into the live store to perform this upgrade.

Every new migrated order retains its existing `delivery_fee`, currency, subtotal, discount, tax and total, together with a snapshot of the selected area metadata, delivery notes/estimate, street, building, floor and instructions. Order address text includes the complete street/building/floor information. Fallback and legacy orders are labeled in their snapshots without inventing a configured area relationship.

Admin order detail, customer order confirmation and customer order history show saved delivery information and the charged fee. Historical displays use the saved order fee/snapshot, never today's area rate. Editing or disabling an area leaves previous totals unchanged. Full order addresses remain protected by the existing owner/session/admin access checks. Fulfillment and payment statuses remain separate; changing fulfillment does not mark an order paid. This release deliberately keeps placed-order addresses and fees read-only; it does not introduce an adjustment/refund workflow that could overwrite historical accounting. The existing project has no order-email, invoice generator or external card gateway, so no separate email/invoice/payment integration was invented. Cash on delivery uses the validated saved order total.

### Files added and modified

Added:
- `app/services/delivery.php`
- `app/views/delivery-admin.php`, `app/views/delivery-order.php`
- `database/delivery-migration.php`, `bin/delivery-migrate.php`
- `tests/delivery.php`, `tests/delivery-http.php`, `tests/delivery-concurrency.php`

Modified:
- `app/bootstrap.php`, `app/services/catalog.php`, `app/services/orders.php`, `app/services/cms.php`
- `public/index.php`, `public/assets/store.js`, `public/assets/style.css`
- `app/views/admin.php`, `checkout.php`, `totals.php`, `admin-orders.php`, `confirmation.php`, `account.php`
- `database/schema.php`, generated `database/schema.mysql.sql`, `bin/install.php`, `README.md`

Both `outputs/ms-charm` source and the flattened `outputs/public_html` main folder are updated. `ms-charm-public-html.zip` and `ms-charm-source.zip` are regenerated with all prior changes. The upload archive has no extra enclosing folder.

### Verification

Passed **59 delivery service assertions on SQLite and 59 on MySQL/MariaDB**, **34 delivery HTTP assertions**, and **3 simultaneous-process delivery scenarios**. Regression checks passed: **74 existing checkout/CMS HTTP assertions**, **27 welcome HTTP assertions**, **20 offer HTTP assertions**, **60 offer service assertions**, **38 welcome service assertions**, **25 subcategory assertions**, and **9 popup-preference assertions**. Total: **408 automated checks/scenarios**, plus PHP/JavaScript syntax checks, a fresh installation, repeated installation and repeated migration checks. Both legacy-schema upgrades and fresh schemas were exercised.

Concurrency verified identical checkout keys produce one order, one stock deduction and one delivery charge; a rate edit committed while checkout is queued causes a review refusal; disabling a queued destination prevents order creation without reducing stock. Browser checks verified actual fee/total changes without navigation, town correction and selection persistence after refresh, search/filter behavior, and checkout/admin layouts at 320/390/768/1280px with no horizontal overflow. The screenshot `ms-charm-delivery-checkout.png` shows a real disposable-store checkout, with test rates only.

Run service checks with `php tests/delivery.php` for an automatically isolated SQLite database. For MySQL set `TEST_DB_DRIVER=mysql` and connection variables for an empty disposable database. HTTP tests require an APP_ENV=test server using the same disposable database; run `tests/integration.php`, `tests/welcome-http.php`, `tests/offers-http.php`, then `tests/delivery-http.php`. These tests create test users/areas/orders and must never target production. For concurrency use `tests/delivery-concurrency.php setup <new-directory> same|rate|disable`, then worker/editor barriers as implemented in that script. This dependency-free project has no separate type-check or production frontend build step.

Hostinger deployment and its database permissions have not been exercised. No production delivery rates, customer addresses or orders were changed. You must upload the package, enter your real coverage/rates, enable the new policy, and check a real-domain test order before relying on the new rates.
