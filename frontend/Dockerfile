FROM node:22-bookworm-slim

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .

RUN sed -i 's/\r$//' /app/docker/entrypoint.sh && chmod +x /app/docker/entrypoint.sh

EXPOSE 80

# Use /app path so `docker compose` bind-mount picks up entrypoint changes without rebuild.
ENTRYPOINT ["/bin/sh", "/app/docker/entrypoint.sh"]
