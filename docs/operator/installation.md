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

Tier A support (release ZIP + web installer) is planned for Phase 3. Until then,
the Docker Compose setup above is the supported deployment path.

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
