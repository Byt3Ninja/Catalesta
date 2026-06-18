# Catalesta web — Vite dev server for local development (docs/13).
# Production builds static assets; that image is added when we harden deploys.
FROM node:22-alpine
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
EXPOSE 3000
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "3000"]
