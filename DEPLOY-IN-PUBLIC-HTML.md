# Deploy entirely inside public_html

Use **ms-charm-public-html.zip**, whose entrypoint and access rules are adapted for this layout. Extract its contents directly inside `public_html`; do not create an extra nested project folder.

```text
public_html/
  .env
  .htaccess
  index.php
  assets/
  uploads/       contains its own .htaccess
  app/           contains its own .htaccess
  bin/           contains its own .htaccess
  database/      contains its own .htaccess
  storage/       contains its own .htaccess
```

If updating your earlier upload, replace application files and the root `.htaccess` and `index.php` from this archive. Preserve an existing database, real uploads, and `.env`; the archive does not contain those. Copy the archive’s `.env.example` to `.env` only for a new configuration. Existing extra nested `public/` and `tests/` folders are unnecessary; the root access rules block tests. Do not merge old rewrite rules blindly.

Set `.env` to:

```dotenv
APP_ENV=production
APP_URL=https://mscharms.com
APP_TIMEZONE=Asia/Beirut
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=YOUR_FULL_HOSTINGER_DATABASE_NAME
DB_USER=YOUR_FULL_HOSTINGER_DATABASE_USERNAME
DB_PASSWORD=YOUR_DATABASE_PASSWORD
SESSION_SECURE=true
PUBLIC_PATH=.
```

Use the full database/user names from hPanel, including any assigned prefix. Keep the password private. Set the database host to the host listed by hPanel if it differs from localhost. Ensure HTTPS is active.

All supplied `.htaccess` files must be uploaded, including hidden files. The root rules forbid HTTP requests to `.env`, private folders, and deployment documents. Folder-level rules add protection for private folders. PHP filesystem includes and CLI scripts can still read these files. This layout depends on the hosting web server honoring Apache-compatible `.htaccess` rules; PHP’s built-in development server does not enforce them.

In Hostinger SSH, enter your domain’s `public_html` directory and run:

```sh
php bin/install.php
php bin/create-owner.php
```

The installer initializes tables and editable starter content. If SSH is unavailable, a developer needs to initialize the schema, seed content, and owner through a controlled deployment process; this package does not expose an unauthenticated web installer.

Before using real customer data, check on your HTTPS domain that these URLs return **403 or 404**, never file contents:

- `/.env`
- `/app/bootstrap.php`
- `/database/schema.mysql.sql`
- `/storage/`
- `/bin/install.php`

Check the homepage, sign-in, admin saves, and an image upload as well. Keep `storage/` and `uploads/` writable by PHP without making the whole site world writable. Configure the store and replace sample content using the admin dashboard. For architecture and operation details, read README.md locally.

## Latest delivery upgrade: governorates

After uploading this release, sign in and open **Delivery & Shipping Settings** before accepting new orders. The page safely initializes the eight-governorate pricing system. CLI alternative, from this public_html directory: `php bin/delivery-migrate.php`.

Review all eight fees and coverage. Conflicting or missing old rates remain unavailable until you explicitly set and confirm them. Unrecognized old governorate labels also require review. A previously active global flat rate is preserved across the eight governorates; uniform city rates can carry over. The old Other area fallback is not assigned automatically. Original orders and city-based records are retained. Do not import a fresh-install schema into an existing live database.

Customers then select only their governorate and type their city, street and building details. No new .env settings or external mapping service are needed. The README's latest governorate report lists the exact migration rules and tests.

## Product variants upgrade

After merging this release into public_html, open **Admin → Products** while signed in as an administrator. The page applies the safe additive migration. CLI alternative from this directory: `php bin/variants-migrate.php`. Keep the live .env, storage and uploads; no new environment keys or database reset are needed. Existing products retain their current no-variant behavior.

Edit a product and find **Product Variants**. Choose sizes, colors or both, add custom options and color images, then add the actual combinations with stock and optional SKU/price. Blank overrides use the product price/sale price. Save before testing on the storefront. Used configurations are archived when removed; their order snapshots remain intact. Read the README's latest Product variants release report for pricing rules, inventory behavior, changed files and validation.

## Welcome and campaign popups

This release fixes first-visit popup ordering without a schema or .env change. Enable email claims in Admin → Welcome offers. First-time browsers see that email form automatically, and successful submission displays their private welcome code inside the popup. Campaign ads follow after dismissal, one at a time. Activate under Offers & Discounts also enables the campaign popup; its configured frequency still controls repeat visits. Use a fresh private browser session to test the welcome popup if your normal browser has already seen it. Clearing browser display preferences does not reset an email's claim or redemption eligibility.

## Welcome email verification — sender mailbox required

Welcome codes now require inbox verification. The email popup first sends a single-use, 15-minute verification link. A promo code appears only after the recipient opens that link and clicks Verify my email. Missing or failed email delivery does not issue a code.

Create a sender mailbox (for example hello@mscharms.com), then add MAIL_TRANSPORT=smtp, MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION, MAIL_USER, MAIL_PASSWORD, MAIL_FROM_ADDRESS and MAIL_FROM_NAME to your existing private .env. Keep APP_URL=https://mscharms.com. The README's latest Inbox verification report contains the full Hostinger example and official setup links. The SMTP password must be that mailbox's password; keep it private.

After uploading, open Admin → Welcome offers to initialize the additive migration and inspect delivery configuration status. CLI alternative: php bin/email-verification-migrate.php. Do not import a fresh schema over the live database. Enable claims and test with your own real inbox before launch. No real sender mailbox was configured or external email delivered during development. Existing products/orders and previous updates are preserved.
