# Installation Guide

## Prerequisites

- Docker and Docker Compose (for Tier B / VPS)
- PHP 8.4 + Composer (for Tier A / shared hosting)
- `ext-zip` enabled (required for ZIP export — `docker-php-ext-install zip` or
  `apt install php8.4-zip`)

---

## Tier B — Docker Compose (recommended)

### 1. Clone and configure

```sh
git clone https://github.com/hideyukiMORI/nene-vault.git
cd nene-vault
cp .env.example .env
```

Edit `.env` to set at minimum:

| Variable | Purpose | Default |
|---|---|---|
| `NENE2_LOCAL_JWT_SECRET` | JWT signing secret — **change this** | `nene-vault-dev-secret-change-in-production` |
| `ADMIN_EMAIL` | First admin login | `admin@example.com` |
| `ADMIN_PASSWORD` | First admin password — **change this** | `changeme123` |
| `NENE_VAULT_STORAGE_PATH` | Where uploaded files are stored | `storage/vault` |
| `ORG_NAME` | Name of the default organization | `NeNe Vault` |
| `ORG_SLUG` | URL slug for the organization | `default` |

### 2. Install Composer dependencies (host side)

```sh
composer install --no-dev --optimize-autoloader
```

The `../NENE2` path dependency is resolved at this step. NENE2 must be a sibling
directory or the `repositories` path in `composer.json` must be updated.

### 3. Start services

```sh
docker compose up -d
```

Services:

| Service | Default URL | Notes |
|---|---|---|
| API (Apache + PHP 8.4) | http://localhost:8080 | |
| Admin UI (Vite) | http://localhost:5173 | Dev server; proxies `/admin`, `/health` to the API |

On first start, `init.sh` bootstraps the SQLite schema and seeds the default
organization and admin user.

### 4. (Optional) MySQL backend

```sh
docker compose --profile mysql \
  -f docker-compose.yml \
  -f docker-compose.mysql.yml up -d
```

And set in `.env`:

```env
DB_ADAPTER=mysql
DB_HOST=mysql
DB_PORT=3306
DB_NAME=nene_vault
DB_USER=nene_vault
DB_PASSWORD=nene_vault
MYSQL_ROOT_PASSWORD=nene_vault_root
```

### 5. Port conflicts

If the default ports are in use (e.g. by a sibling product):

```sh
NENE_VAULT_PORT=8090 NENE_VAULT_FRONTEND_PORT=5180 docker compose up -d
```

---

## Tier A — Shared hosting

For operators on shared hosting without Docker access.

### 1. Build the release ZIP

On a machine with Docker/Node.js/Composer:

```sh
git clone https://github.com/hideyukiMORI/nene-vault.git
cd nene-vault
bash tools/build-release.sh 1.0.0
# → dist/nene-vault-1.0.0.zip
```

Or download a pre-built release ZIP from the GitHub Releases page.

### 2. Upload and extract

Upload `nene-vault-1.0.0.zip` to your server and extract it:

```sh
unzip nene-vault-1.0.0.zip
```

### 3. Set document root

Configure your web server's document root (or virtual host) to point to
`public_html/` inside the extracted directory:

```
DocumentRoot /path/to/nene-vault-1.0.0/public_html
```

If you cannot change the document root, copy the contents of `public_html/`
to your `public_html/` or `htdocs/` directory and update the paths in
`public_html/index.php` accordingly.

### 4. Run the web installer

Visit `http://your-domain.example.com/install.php` in a browser. The installer
walks you through:

1. **Requirements check** — PHP 8.4, ext-zip, writable directories
2. **Database** — SQLite (default) or MySQL credentials
3. **Application settings** — JWT secret, storage path, admin email/password
4. **Setup** — writes `.env`, runs migrations, seeds initial data
5. **Done** — deletes `install.php`

### 5. Fix permissions

```sh
chmod 755 var/
chmod 755 storage/
```

The PHP process must be able to write to `var/` (SQLite DB) and
`storage/vault/` (uploaded files).

---

## First login

Open http://localhost:5173 and log in with `ADMIN_EMAIL` / `ADMIN_PASSWORD` from
`.env`. Change the password immediately after first login.

---

## Environment variable reference

| Variable | Required | Default | Notes |
|---|---|---|---|
| `NENE2_LOCAL_JWT_SECRET` | Yes (prod) | `nene-vault-dev-secret…` | Min 32 chars; random in prod |
| `NENE_VAULT_STORAGE_PATH` | No | `storage/vault` | Absolute or relative to project root |
| `NENE_VAULT_MAX_FILE_SIZE_MB` | No | `20` | Per-file upload limit |
| `TENANT_RESOLUTION` | No | `single` | `single` \| `subdomain` \| `custom_domain` |
| `ORG_SLUG` | When `single` | `default` | Must match an org slug in the DB |
| `BASE_DOMAIN` | When `subdomain` | — | e.g. `nene-vault.example.com` |
| `DB_ADAPTER` | No | `sqlite` | `sqlite` \| `mysql` |
| `DB_HOST` | When `mysql` | `127.0.0.1` | |
| `DB_PORT` | No | `3306` | |
| `DB_NAME` | No | `var/nene_vault.sqlite` | SQLite: file path; MySQL: database name |
| `DB_USER` | When `mysql` | — | |
| `DB_PASSWORD` | When `mysql` | — | |
| `ADMIN_EMAIL` | First run | `admin@example.com` | Seeder only; change in `.env` |
| `ADMIN_PASSWORD` | First run | `changeme123` | Seeder only; change after first login |
| `APP_DEBUG` | No | `false` | Never `true` in production |
| `PROBLEM_DETAILS_BASE_URL` | No | `https://nene-vault.dev/problems/` | RFC 9457 type prefix |
