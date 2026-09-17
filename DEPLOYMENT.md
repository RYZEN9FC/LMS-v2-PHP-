# PEGWISE prototype: complete VM deployment runbook

This runbook deploys the current PEGWISE Laravel prototype and migrates the complete Sip Society MySQL database. It assumes an Ubuntu 24.04 LTS VM, Nginx, PHP-FPM, MySQL, and a real domain with HTTPS. Adjust package names and service paths for another Linux distribution.

## 1. What must be handed to the developer

Provide these items:

1. The complete application source repository.
2. The database dump, transferred separately over an encrypted channel:
   `storage/app/private/backups/sip-society-full-20260917-141643.sql`
3. Its checksum file:
   `storage/app/private/backups/sip-society-full-20260917-141643.sql.sha256`
4. The domain name and access to its DNS records.
5. VM SSH access using an SSH key.
6. SMTP credentials if password-reset emails must work.

The SQL dump is intentionally ignored by Git. It contains business data and hashed user credentials. Never email it unencrypted, commit it, place it under `public/`, or leave it on the VM after a verified import and backup.

The current dump SHA-256 is:

```text
95A6304E6E1DD1688A4E0F982A4267CC56A9C5E9C1EF485F00AC5BE79B666699
```

The dump is a point-in-time copy. If data changes locally before deployment, create and validate a fresh dump immediately before the final migration.

## 2. Application requirements

### Runtime

- 64-bit PHP 8.3 or newer within the Composer constraint `^8.3`
- Composer 2
- MySQL 8.x
- Nginx or Apache; this guide uses Nginx
- HTTPS certificate
- At least 1 GB RAM; 2 GB is recommended for PDF/Excel parsing and exports
- Enough disk space for the app, logs, temporary uploads, database, and backups

### Required PHP extensions

Install these explicitly even where PHP provides some by default:

- CLI and FPM
- MySQL/PDO MySQL
- ctype
- DOM/XML, SimpleXML, XMLReader, and XMLWriter
- fileinfo
- filter
- GD
- hash
- iconv
- JSON
- libxml
- mbstring
- OpenSSL
- PCRE
- session
- tokenizer
- ZIP and zlib
- cURL, required by Composer and normal HTTP integrations

Composer installs the application libraries from `composer.lock`, including Laravel, DOMPDF, PhpSpreadsheet, and the PDF parser. Do not manually copy `vendor/` from Windows.

### Frontend build tools

- Node.js 22 LTS recommended
- npm

The repository includes a tested `package-lock.json`. Commit it with the source and use `npm ci` for reproducible production builds.

### Current background-process requirements

The present prototype defines no scheduled application tasks and no queued jobs that require a permanent worker. A scheduler cron and queue worker are therefore not required for this version. Add them when background jobs or scheduled work are introduced.

## 3. Prepare the repository before transfer

The repository must have at least one Git commit before a developer can clone a reliable version. Confirm that `.env`, `vendor/`, `node_modules/`, `public/build/`, and database dumps remain ignored.

Run locally or in CI:

```bash
composer validate --strict
composer install
composer check-platform-reqs
php artisan test
npm ci
npm run build
```

Expected automated test result for this handoff: 46 tests and 533 assertions passing.

Tag or record the exact commit deployed so it can be rolled back later.

## 4. Prepare the VM

Update the server and install the required packages:

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y \
  nginx mysql-server git unzip curl ca-certificates \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-curl \
  php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip
```

Install Composer 2 with the official installer and verify its installer checksum:

```bash
cd /tmp
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
if [ "$EXPECTED_CHECKSUM" = "$ACTUAL_CHECKSUM" ]; then
  sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
else
  echo 'Composer installer checksum mismatch; installation stopped.'
  false
fi
rm composer-setup.php
```

Verify the server tools:

```bash
php -v
composer --version
mysql --version
nginx -v
```

Install Node.js 22 LTS and npm. When Ubuntu's default repository does not supply Node 22, use the NodeSource setup package after reviewing the downloaded setup script:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x -o /tmp/nodesource_setup.sh
less /tmp/nodesource_setup.sh
sudo -E bash /tmp/nodesource_setup.sh
sudo apt install -y nodejs
rm /tmp/nodesource_setup.sh
```

Then verify:

```bash
node --version
npm --version
```

Run MySQL's security setup and disable remote root access unless explicitly required:

```bash
sudo mysql_secure_installation
```

Use a non-root deployment account with SSH-key authentication. Restrict inbound firewall access to SSH, HTTP, and HTTPS. Do not expose MySQL port 3306 publicly when the database is on the same VM.

For Ubuntu's firewall:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

## 5. Create the application directory

This guide uses `/var/www/pegwise`:

```bash
sudo mkdir -p /var/www/pegwise
sudo chown -R "$USER":www-data /var/www/pegwise
```

Clone the repository or securely copy the source into that directory:

```bash
git clone YOUR_PRIVATE_REPOSITORY_URL /var/www/pegwise
cd /var/www/pegwise
git checkout THE_APPROVED_COMMIT_OR_TAG
```

Do not copy the local `.env`, `vendor/`, `node_modules/`, caches, logs, or temporary files to the VM.

## 6. Install application dependencies

From `/var/www/pegwise`:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer check-platform-reqs --no-dev
```

Install the locked frontend dependencies and build the production assets:

```bash
npm ci --ignore-scripts
npm run build
```

## 7. Configure PHP for uploads and exports

The app accepts PDF and Excel uploads up to 15 MB. Create `/etc/php/8.3/fpm/conf.d/99-pegwise.ini`:

```ini
upload_max_filesize = 16M
post_max_size = 17M
memory_limit = 512M
max_execution_time = 120
max_input_time = 120
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl enable php8.3-fpm
```

## 8. Create the production database

Choose unique values for the database name, user, and password. Do not reuse the MySQL root account.

```bash
sudo mysql
```

Run the following SQL after replacing the placeholders:

```sql
CREATE DATABASE pegwise_production
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'pegwise_app'@'127.0.0.1'
  IDENTIFIED BY 'USE_A_LONG_RANDOM_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP,
      REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES,
      CREATE VIEW, SHOW VIEW, TRIGGER, EVENT, EXECUTE
  ON pegwise_production.* TO 'pegwise_app'@'127.0.0.1';

FLUSH PRIVILEGES;
EXIT;
```

Store the actual password only in the VM's protected `.env` or secret manager.

## 9. Transfer and verify the database dump

Transfer the `.sql` and `.sha256` files using SCP, SFTP, or another encrypted channel into a private directory such as `/home/DEPLOY_USER/pegwise-import/`. Do not put them under `/var/www/pegwise/public`.

On the VM:

```bash
cd /home/DEPLOY_USER/pegwise-import
sha256sum -c sip-society-full-20260917-141643.sql.sha256
```

The result must say `OK`. Stop if the checksum differs.

If the target database is not empty, back it up before proceeding. The supplied prototype dump should normally be imported into a new empty database.

Import the database:

```bash
mysql \
  --host=127.0.0.1 \
  --user=pegwise_app \
  --password \
  pegwise_production \
  < sip-society-full-20260917-141643.sql
```

Enter the database password at the prompt; do not place it directly in shell history.

Do not run `php artisan migrate:fresh`, `php artisan db:wipe`, or the demo `DatabaseSeeder` against this database.

## 10. Verify the imported data

Open MySQL:

```bash
mysql --host=127.0.0.1 --user=pegwise_app --password pegwise_production
```

Run:

```sql
SELECT COUNT(*) AS products FROM products;
SELECT COUNT(*) AS recipes FROM recipes;
SELECT COUNT(*) AS source_mappings FROM source_mappings;
SELECT COUNT(*) AS imports FROM imports;
SELECT COUNT(*) AS stock_movements FROM stock_movements;
SELECT COUNT(*) AS upload_documents FROM upload_documents;
SELECT COUNT(*) AS users FROM users;
```

Expected counts for the supplied dump:

| Table | Expected rows |
|---|---:|
| `products` | 55 |
| `recipes` | 27 |
| `source_mappings` | 57 |
| `imports` | 10 |
| `stock_movements` | 105 |
| `upload_documents` | 12 |
| `users` | 1 |

The database also contains one organisation and one outlet. If a fresh dump is produced later, record and use its new expected counts instead.

## 11. Create the production environment file

Create `/var/www/pegwise/.env`; do not copy the local development `.env`:

```dotenv
APP_NAME="PEGWISE"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN
APP_TIMEZONE=Asia/Kolkata
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pegwise_production
DB_USERNAME=pegwise_app
DB_PASSWORD=USE_THE_DATABASE_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=YOUR_SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=YOUR_SMTP_USERNAME
MAIL_PASSWORD=YOUR_SMTP_PASSWORD
MAIL_FROM_ADDRESS=no-reply@YOUR_DOMAIN
MAIL_FROM_NAME="PEGWISE"
```

Protect the file:

```bash
sudo chown "$USER":www-data /var/www/pegwise/.env
sudo chmod 640 /var/www/pegwise/.env
```

Generate a new application key exactly once for this deployment:

```bash
cd /var/www/pegwise
php artisan key:generate
```

Generating a new key invalidates old browser sessions but does not invalidate the imported user password hashes. Do not regenerate the key after the application has gone live.

## 12. Apply migrations and optimize Laravel

The dump includes its existing migration history. The following applies only migrations created after the dump:

```bash
cd /var/www/pegwise
php artisan migrate --force
php artisan optimize
php artisan about
```

Confirm that `php artisan about` reports:

- Environment: `production`
- Debug mode: disabled
- Correct application URL
- MySQL database connection
- Cached configuration, routes, events, and views

Do not run `php artisan config:clear` during normal production operation unless troubleshooting and recaching immediately afterward.

## 13. Set filesystem ownership and permissions

```bash
cd /var/www/pegwise
sudo chown -R "$USER":www-data .
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;
sudo chmod -R ug+rwx storage bootstrap/cache
sudo chmod 640 .env
```

Never use `chmod -R 777`.

The current application does not require `php artisan storage:link` for its reviewed-import data. Run it only if future features deliberately store public files on Laravel's `public` disk.

## 14. Configure Nginx

Create `/etc/nginx/sites-available/pegwise`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name YOUR_DOMAIN;

    root /var/www/pegwise/public;
    index index.php;

    client_max_body_size 17M;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(env|log|sql|bak|ini)$ {
        deny all;
    }
}
```

Enable and validate the site:

```bash
sudo ln -s /etc/nginx/sites-available/pegwise /etc/nginx/sites-enabled/pegwise
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl enable nginx
```

Remove the default Nginx site if it conflicts with the domain.

## 15. Configure DNS and HTTPS

Point the domain's DNS `A` record to the VM's public IP and wait for it to resolve. Install Certbot:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d YOUR_DOMAIN
sudo certbot renew --dry-run
```

After HTTPS is working, verify that HTTP redirects to HTTPS. Keep `SESSION_SECURE_COOKIE=true`.

## 16. Configure password-reset email

The local app uses the log mailer, which does not send real email. Production must use valid SMTP settings if “Forgot password” is shown to users.

After editing mail settings:

```bash
cd /var/www/pegwise
php artisan optimize:clear
php artisan optimize
```

Send one password-reset request to a controlled test account and confirm delivery. Do not test by changing the client's password without authorization.

## 17. Final security actions before client access

1. Change the imported prototype owner password through **Settings → Account security**.
2. Confirm `APP_DEBUG=false` by intentionally visiting a nonexistent URL; no stack trace should appear.
3. Confirm the `.env`, SQL dump, logs, and Git metadata are not downloadable.
4. Delete the transferred SQL dump from the VM after the import is verified and a protected backup exists.
5. Restrict SSH to keys and disable password/root login where operationally possible.
6. Enable the VM/provider firewall and automatic security updates.
7. Do not share the owner login with multiple people; create individual team accounts.

## 18. Production smoke-test checklist

Test on both desktop and a real mobile browser:

- `https://YOUR_DOMAIN/up` returns a healthy response.
- Login and logout work.
- Dashboard displays **Sip Society** and expected-stock totals.
- Current stock opens, filters work, and horizontal table controls work on mobile.
- Interval reports open for short and long date ranges.
- Excel and PDF exports download and open correctly.
- POS upload accepts a valid `.xlsx` preview without changing stock before submission.
- Excise upload accepts a valid PDF preview without changing stock before submission.
- Duplicate uploads and insufficient stock are rejected safely.
- Brands, cocktails, mappings, Settings, Team access, and Upload history open according to role.
- Password reset email arrives if SMTP is enabled.
- No browser console errors or Laravel errors appear in `storage/logs/`.

Do not submit a test upload against the production database unless its stock effect is intended or the test database will be restored afterward.

## 19. Backups

At minimum configure:

- Automated daily MySQL backups
- Encryption at rest and during transfer
- Retention appropriate for the client
- A separate off-VM backup location
- Periodic restore tests
- A backup immediately before every deployment or database migration

Back up both the database and the production `.env`/application key in a secure secret store. Never commit either one.

## 20. Normal application updates

Use a tested release commit or tag:

```bash
cd /var/www/pegwise
php artisan down --render="errors::503"
git fetch --all --tags
git checkout THE_APPROVED_COMMIT_OR_TAG
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci --ignore-scripts
npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

## 21. Rollback outline

Before each release, retain the prior code revision and a pre-release database backup. If a deployment fails:

1. Put the app in maintenance mode.
2. Restore the prior tested code revision.
3. Run `composer install --no-dev --optimize-autoloader` for that revision.
4. Rebuild its frontend assets.
5. Restore the pre-release database backup if the failed release changed data or schema.
6. Run `php artisan optimize`.
7. Complete the smoke tests before running `php artisan up`.

Do not blindly use `migrate:rollback` in production; a database restore is safer when a migration transformed or removed data.

## 22. Common deployment failures

### 500 error immediately after deployment

Check `.env`, `APP_KEY`, database access, PHP extensions, `storage/` permissions, and `storage/logs/laravel.log`.

### CSS or JavaScript missing

Run `npm run build`, confirm `public/build/` exists, and then run `php artisan optimize`.

### Upload rejected before parsing

Confirm the Nginx `client_max_body_size`, PHP `upload_max_filesize`, and PHP `post_max_size` settings, then restart PHP-FPM and reload Nginx.

### Password reset says it succeeded but no email arrives

Confirm the SMTP values, provider credentials, sender-domain verification, outbound firewall access, and Laravel logs. Recache configuration after changing `.env`.

### Database appears empty

Confirm the production `.env` references the imported database, verify the expected record counts, and ensure configuration was recached after editing `.env`.

### `npm run build` cannot find Vite

Run `npm ci` first and confirm that the committed `package-lock.json` is present and matches `package.json`.

### Changes to `.env` have no effect

Run:

```bash
php artisan optimize:clear
php artisan optimize
```

## 23. Commands that must not be run on the migrated production database

Unless a verified destructive reset is explicitly intended, never run:

```text
php artisan migrate:fresh
php artisan db:wipe
php artisan migrate:reset
php artisan db:seed
php artisan migrate:fresh --seed
```

These commands can remove or replace the imported Sip Society stock history and catalogue.
