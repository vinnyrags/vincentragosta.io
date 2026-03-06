# Digital Ocean Server Setup

## Server Details

- **Provider:** Digital Ocean
- **Droplet IP:** 174.138.70.29
- **OS:** Ubuntu 24.04.3 LTS (Noble Numbat)
- **Specs:** 1 vCPU, ~1GB RAM, 25GB SSD
- **Stack:** LEMP (Linux, Nginx, MariaDB, PHP 8.4)

## Domains

| Domain | Environment | Branch | Purpose |
|--------|-------------|--------|---------|
| vincentragosta.io | Production | main | Live site |
| staging.vincentragosta.io | Staging | develop | Testing/preview |

DNS managed in Digital Ocean (nameservers delegated from GoDaddy). Both environments hosted on the same droplet using Nginx server blocks. Staging uses a subdomain rather than a separate domain.

## Architecture

- Two separate WordPress installations (one per domain/environment)
- One MariaDB server, two databases (isolated per environment)
- Nginx server blocks route each domain to its WordPress root
- Git push deployment via bare repo + post-receive hook
- SSL via Let's Encrypt (Certbot)

---

## Stage 1: Server Provisioning (LEMP Stack) — COMPLETED

### Installed Versions

| Software | Version |
|----------|---------|
| Ubuntu | 24.04.3 LTS |
| Nginx | (system default from apt) |
| MariaDB | (system default from apt) |
| PHP | 8.4.18 (FPM + CLI) |
| Composer | 2.9.5 |
| Node.js | 24.14.0 (via nvm) |
| npm | 11.9.0 |
| Swap | 2GB (swappiness=10) |
| Firewall | UFW (SSH + Nginx Full) |

### 1.1 Add Swap Space (2GB)

The droplet has only 1GB RAM. Adding swap prevents out-of-memory issues during builds and traffic spikes.

```bash
# Create 2GB swap file
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile

# Make persistent across reboots
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Tune swappiness (low value = prefer RAM, only swap when necessary)
sysctl vm.swappiness=10
echo 'vm.swappiness=10' >> /etc/sysctl.conf
```

### 1.2 Update System Packages

```bash
apt update && apt upgrade -y
```

### 1.3 Install Nginx

```bash
apt install nginx -y
systemctl enable nginx
systemctl start nginx

# Verify
systemctl status nginx
```

### 1.4 Install MariaDB

```bash
apt install mariadb-server -y
systemctl enable mariadb

# Secure the installation (non-interactive)
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '<YOUR_PASSWORD>';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\_%';
FLUSH PRIVILEGES;"
```

> **Note:** MariaDB root password was set during provisioning. Change it before production.

### 1.5 Install PHP 8.4 + Extensions

```bash
# Add PHP repository
apt install software-properties-common -y
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP-FPM and WordPress-required extensions
apt install php8.4-fpm php8.4-mysql php8.4-xml php8.4-mbstring \
    php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath \
    php8.4-imagick php8.4-redis php8.4-cli -y

# Verify
php -v
systemctl status php8.4-fpm
```

### 1.6 Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
composer --version
```

### 1.7 Install Node.js (LTS via nvm)

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.bashrc
nvm install --lts
node -v && npm -v
```

### 1.8 Configure Firewall (UFW)

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
ufw status
```

---

## Stage 2: WordPress + Database — COMPLETED

### 2.1 Install WP-CLI

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
mv wp-cli.phar /usr/local/bin/wp
```

### 2.2 Create Databases and Users

```bash
mysql -u root -p'<ROOT_PASSWORD>' -e "
CREATE DATABASE wp_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE wp_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'wp_prod_user'@'localhost' IDENTIFIED BY '<GENERATED>';
CREATE USER 'wp_stage_user'@'localhost' IDENTIFIED BY '<GENERATED>';
GRANT ALL PRIVILEGES ON wp_production.* TO 'wp_prod_user'@'localhost';
GRANT ALL PRIVILEGES ON wp_staging.* TO 'wp_stage_user'@'localhost';
FLUSH PRIVILEGES;"
```

### 2.3 Download WordPress

```bash
mkdir -p /var/www/vincentragosta.io /var/www/vincentragosta.dev
wp core download --path=/var/www/vincentragosta.io --allow-root
wp core download --path=/var/www/vincentragosta.dev --allow-root
chown -R www-data:www-data /var/www/vincentragosta.io /var/www/vincentragosta.dev
```

WordPress 6.9.1 installed to both directories.

### 2.4 Nginx Server Blocks

Two server block configs created:

- `/etc/nginx/sites-available/vincentragosta.io` — production
- `/etc/nginx/sites-available/vincentragosta.dev` — staging

Both configs include:
- PHP-FPM via `unix:/run/php/php8.4-fpm.sock`
- WordPress permalink support (`try_files $uri $uri/ /index.php?$args`)
- Static asset caching (30 days)
- `.htaccess` denial
- `client_max_body_size 64M`

Symlinked to `sites-enabled/`, default site removed.

### 2.5 WordPress Configuration

Both sites configured via `wp config create` + `wp core install`:

| Setting | Production (.io) | Staging (.dev) |
|---------|-------------------|----------------|
| WP_DEBUG | false | true |
| WP_DEBUG_LOG | false | true |
| WP_DEBUG_DISPLAY | — | true |
| DISALLOW_FILE_EDIT | true | true |
| Admin user | vragosta | vragosta |

### Credentials

All credentials stored securely on the server at `/root/.wp-credentials` (chmod 600, root-only).

Contains: MariaDB root password, DB user/pass for both environments, WP admin password.

---

## Stage 3: Git Push Deployment — COMPLETED

### Architecture Change: Bedrock-Style Layout

The project uses a Bedrock-inspired structure where WordPress core is installed via Composer into a `wp/` subdirectory. The repo root IS the web root. This required:

1. **Clearing the standard WordPress installs** from the server
2. **Environment-specific config via `wp-config-env.php`** — loaded by `wp-config.php` before defaults, not tracked in git
3. **Updated Nginx configs** — handle `wp/` subdirectory routing, block sensitive files (`.git`, `auth.json`, `composer.*`, `wp-config-env.php`)
4. **ACF Pro auth** — `/root/.composer-auth.json` copied into deploy dir as `auth.json` during deployment

### 3.1 Bare Git Repository

```bash
git init --bare /var/repo/vincentragosta.git
```

### 3.2 Post-Receive Hook

Located at `/var/repo/vincentragosta.git/hooks/post-receive`. On push:

| Branch | Deploys to | Environment |
|--------|-----------|-------------|
| main | /var/www/vincentragosta.io | Production |
| develop | /var/www/vincentragosta.dev | Staging |

The hook:
1. Backs up `wp-config-env.php` (not in repo)
2. Checks out the pushed branch to the deploy directory
3. Restores `wp-config-env.php` and `auth.json`
4. Runs `composer install --no-dev --optimize-autoloader`
5. Runs `npm ci && npm run build` for both parent and child themes
6. Fixes file ownership to `www-data`

### 3.3 Local Git Remote

```bash
git remote add production root@174.138.70.29:/var/repo/vincentragosta.git
```

### Deployment Commands

```bash
# Deploy to production
git push production main

# Deploy to staging
git push production develop
```

### Key Files on Server

| File | Purpose |
|------|---------|
| `/var/repo/vincentragosta.git/` | Bare git repository |
| `/var/www/vincentragosta.io/wp-config-env.php` | Production DB/URL/debug config |
| `/var/www/vincentragosta.dev/wp-config-env.php` | Staging DB/URL/debug config |
| `/root/.composer-auth.json` | ACF Pro license for Composer |
| `/root/.wp-credentials` | All passwords (root-only readable) |

---

## Stage 4: SSL + Domain Configuration — COMPLETED

### 4.1 DNS

All DNS managed in Digital Ocean (nameservers delegated from GoDaddy).

| Record | Value |
|--------|-------|
| A `@` → 174.138.70.29 | vincentragosta.io |
| A `www` → 174.138.70.29 | www.vincentragosta.io |
| A `staging` → 174.138.70.29 | staging.vincentragosta.io |

### 4.2 SSL Certificates (Let's Encrypt)

```bash
apt install certbot python3-certbot-nginx -y
certbot --nginx -d vincentragosta.io -d www.vincentragosta.io --redirect
certbot --nginx -d staging.vincentragosta.io --redirect
```

- Certificates auto-renew via `certbot.timer`
- HTTP → HTTPS redirect enabled automatically
- Cert paths: `/etc/letsencrypt/live/{domain}/`
- Certbot auto-modified the Nginx configs to add SSL blocks

### Behavior

- `http://vincentragosta.io` → 301 → `https://vincentragosta.io`
- `http://staging.vincentragosta.io` → 301 → `https://staging.vincentragosta.io`
- Certs expire every 90 days, auto-renewed by Certbot timer
