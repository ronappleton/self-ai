# Docker Development Environment

This repository now includes a Docker Compose setup that provisions the Laravel API, Redis, Horizon queue workers, and the Vite development server. It is intended for local development so you can run the project without installing PHP, Composer, or Node.js on the host machine.

## Prerequisites

- Docker Engine 24+
- Docker Compose v2 (bundled with Docker Desktop or the Docker CLI)

## First-time bootstrap

```bash
# From the repository root
cp app/.env.example app/.env

# Ensure the SQLite database file exists so migrations can run
: > app/database/database.sqlite

# Build images and start the stack
docker compose up --build
```

The first boot may take a few minutes while Composer and npm dependencies are installed. Subsequent startups reuse cached volumes.

## Services

| Service  | Description                                   | Exposed Ports |
|----------|-----------------------------------------------|---------------|
| `app`    | Laravel HTTP server (`php artisan serve`)     | 8000          |
| `horizon`| Queue processing via Laravel Horizon          | —             |
| `vite`   | Vite dev server for frontend assets           | 5173          |
| `redis`  | Redis cache/queue backend                     | 6379          |

Access the Laravel application at [http://localhost:8000](http://localhost:8000) and the Vite dev server at [http://localhost:5173](http://localhost:5173).

## Useful commands

```bash
# Run an Artisan command
docker compose run --rm app php artisan migrate

# Install a new Composer dependency
docker compose run --rm app composer require vendor/package

# Run the PHP test suite
docker compose run --rm app php artisan test

# Install new npm packages
docker compose run --rm vite npm install <package>
```

All application code is bind-mounted into the containers, so changes made on the host are picked up automatically.

## Stopping and cleanup

Press `Ctrl+C` in the terminal where `docker compose up` is running to stop the stack. To remove containers, networks, and anonymous volumes, run:

```bash
docker compose down -v
```
