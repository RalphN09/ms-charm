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
