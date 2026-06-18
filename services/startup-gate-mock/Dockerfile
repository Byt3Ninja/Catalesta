FROM node:22-alpine
WORKDIR /app
COPY package.json ./
COPY server.mjs ./
EXPOSE 8080
HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
  CMD wget -qO- http://localhost:8080/health || exit 1
CMD ["node", "server.mjs"]
