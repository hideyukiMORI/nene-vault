# ── Development target ────────────────────────────────────────────────────────
# Build context must be the repo root so locales/ is accessible.
# docker compose sets context: ..  for this service.
FROM node:22-alpine AS dev

WORKDIR /app/frontend

# Install dependencies first (cached layer)
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

# Source is mounted at runtime via volume.
# Vite is started by the dev service's command in docker-compose.yml.

EXPOSE 5173

# ── Build stage ───────────────────────────────────────────────────────────────
FROM node:22-alpine AS build

WORKDIR /app/frontend

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

# Copy the whole repo so locales/ resolves via the @locales alias
COPY . /app/

WORKDIR /app/frontend
RUN npm run build

# ── Production static server ──────────────────────────────────────────────────
FROM nginx:1.27-alpine AS prod

COPY --from=build /app/frontend/dist /usr/share/nginx/html

# SPA routing: send all 404s back to index.html
RUN printf 'server {\n\
    listen 80;\n\
    root /usr/share/nginx/html;\n\
    index index.html;\n\
    location / { try_files $uri $uri/ /index.html; }\n\
}\n' > /etc/nginx/conf.d/default.conf

EXPOSE 80
