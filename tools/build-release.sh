#!/usr/bin/env bash
# NeNe Vault — Release ZIP builder for Tier A shared hosting
#
# Usage:
#   bash tools/build-release.sh [version]
#   bash tools/build-release.sh 1.2.0
#
# Output:
#   dist/nene-vault-{version}.zip
#   dist/nene-vault-{version}.zip.sha256   (integrity sidecar, #159)
#
# Requirements:
#   - Composer installed globally
#   - Node.js + npm
#   - zip (CLI utility)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# The single source of the product version is the repo-root VERSION file (#231).
# Explicit argument wins, then VERSION, then 'dev'. Do not fall back to
# `git describe --tags --always`: this repo carries no tags, so `--always`
# silently degraded to a commit SHA and shipped `nene-vault-<sha>.zip`.
VERSION="${1:-$(cat "$ROOT/VERSION" 2>/dev/null || echo 'dev')}"

DIST="$ROOT/dist"
STAGING="$DIST/nene-vault-$VERSION"
ZIP_FILE="$DIST/nene-vault-$VERSION.zip"

echo "==> Building NeNe Vault $VERSION"

# ── Cleanup ───────────────────────────────────────────────────────────────────
rm -rf "$STAGING" "$ZIP_FILE" "$ZIP_FILE.sha256"
mkdir -p "$STAGING"

# ── Frontend build ────────────────────────────────────────────────────────────
echo "--> Building frontend..."
cd "$ROOT/frontend"
npm ci --silent
npm run build --silent
cd "$ROOT"

echo "--> Frontend built to frontend/dist/"

# ── PHP dependencies (no-dev) ─────────────────────────────────────────────────
echo "--> Installing production Composer dependencies..."
composer install --no-dev --optimize-autoloader --quiet

# ── Stage files ───────────────────────────────────────────────────────────────
echo "--> Staging release files..."

# PHP application source
rsync -a --exclude='*.test.*' \
    "$ROOT/src/"       "$STAGING/src/"
rsync -a \
    "$ROOT/vendor/"    "$STAGING/vendor/"

# Guard against symlinked vendor packages (the historic #122 trap): NENE2 now
# comes from Packagist as real files (#159), so this loop is normally a no-op,
# but a local path-repository override would reintroduce the empty-framework
# zip without it. Dereference any remaining links into real files.
find "$STAGING/vendor" -maxdepth 3 -type l | while read -r link; do
    # Relative link targets only resolve from the ORIGINAL vendor tree.
    src="$ROOT/vendor/${link#"$STAGING/vendor/"}"
    real="$(readlink -f "$src")"
    rm "$link"
    rsync -a --exclude='.git/' --exclude='node_modules/' "$real/" "$link/"
done
rsync -a \
    "$ROOT/locales/"   "$STAGING/locales/"
# CLI tools ship with the release: the demo sweep/reseed crons (#141) and the
# email-inbound entry point live here — a zip without tools/ leaves a Tier A
# install with no cron targets at all (#145).
rsync -a \
    "$ROOT/tools/"     "$STAGING/tools/"
rsync -a \
    "$ROOT/database/"  "$STAGING/database/"
rsync -a \
    "$ROOT/docker/"    "$STAGING/docker/"

# Public HTML (PHP front controller + .htaccess + built frontend)
rsync -a \
    "$ROOT/public_html/"          "$STAGING/public_html/"
rsync -a \
    "$ROOT/frontend/dist/"        "$STAGING/public_html/"

# Config files
cp "$ROOT/phinx.php"        "$STAGING/phinx.php"
cp "$ROOT/.env.example"     "$STAGING/.env.example"
# Ship VERSION inside the zip too, so an installed site can state its own version
# without relying on the zip's filename surviving the download (#231).
cp "$ROOT/VERSION"          "$STAGING/VERSION"
cp "$ROOT/phpstan.neon.dist" "$STAGING/phpstan.neon.dist" 2>/dev/null || true

# Installer
# install.php now lives in public_html/ (web-reachable with the documented docroot, #120)
# and is staged with the rest of public_html below.

# var/ placeholder (empty, writable)
mkdir -p "$STAGING/var"
touch "$STAGING/var/.gitkeep"

# Storage placeholder
mkdir -p "$STAGING/storage/vault"
touch "$STAGING/storage/vault/.gitkeep"

# ── Create ZIP ────────────────────────────────────────────────────────────────
echo "--> Creating ZIP archive..."
cd "$DIST"
# Exclude dot-directories but KEEP required dotfiles: the blanket "*/\.*"
# pattern silently dropped public_html/.htaccess and .env.example from the
# Tier A zip (#118) — without .htaccess Apache serves no routes at all.
zip -r "nene-vault-$VERSION.zip" "nene-vault-$VERSION/" -x "*/.git/*" -x "*/.gitkeep" -q

# ── SHA-256 sidecar (#159, invoice shape) ─────────────────────────────────────
echo "--> Writing SHA-256 sidecar..."
sha256sum "nene-vault-$VERSION.zip" > "nene-vault-$VERSION.zip.sha256"
SHA256="$(cut -d' ' -f1 "nene-vault-$VERSION.zip.sha256")"

echo ""
echo "==> Release ZIP: $ZIP_FILE"
echo "    sha256:  ${SHA256}"
echo "    sidecar: $ZIP_FILE.sha256"
echo "    Contents: $(du -sh "$STAGING" | cut -f1) uncompressed"
echo ""
echo "Tier A installation:"
echo "  1. Upload and extract nene-vault-$VERSION.zip to your web root"
echo "  2. Set document root to public_html/"
echo "  3. Visit install.php to complete setup"

# ── Restore dev dependencies ──────────────────────────────────────────────────
echo "--> Restoring dev Composer dependencies..."
composer install --quiet

echo "==> Done."
