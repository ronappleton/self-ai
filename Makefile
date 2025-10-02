SHELL := /bin/bash
DOCKER_COMPOSE ?= docker compose

.PHONY: run up down stop logs build bootstrap

run: up

up: bootstrap
	$(DOCKER_COMPOSE) up --build

build:
	$(DOCKER_COMPOSE) build

stop down:
	$(DOCKER_COMPOSE) down

logs:
	$(DOCKER_COMPOSE) logs -f

bootstrap:
	@if [ ! -f app/.env ]; then \
		cp app/.env.example app/.env; \
		printf 'Created app/.env from example.\n'; \
	fi
	@mkdir -p app/database
	@if [ ! -f app/database/database.sqlite ]; then \
		touch app/database/database.sqlite; \
		printf 'Initialized SQLite database file at app/database/database.sqlite.\n'; \
	fi
	@mkdir -p app/storage/app/vector-store
