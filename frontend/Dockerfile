# Catalesta web — Vite dev server for local development (docs/13).
# Production builds static assets; that image is added when we harden deploys.
FROM node:22-alpine
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
EXPOSE 3000
# `npm install` on start reconciles the persisted /app/node_modules volume with
# package-lock.json — self-heals after a dependency is added (a stale volume
# otherwise breaks Vite's import resolution). No-op (~1s) when already in sync.
CMD ["sh", "-c", "npm install && npm run dev -- --host 0.0.0.0 --port 3000"]
