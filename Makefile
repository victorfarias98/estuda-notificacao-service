SHELL := /bin/bash

DC ?= docker compose
APP_CONTAINER ?= notification-service-app
ARTISAN ?= $(DC) exec -T app php artisan
COMPOSER ?= $(DC) exec -T app composer
PHP ?= $(DC) exec -T app php

.DEFAULT_GOAL := help

.PHONY: help setup build up down restart logs ps shell mysql install \
        env key migrate migrate-fresh seed queue queue-restart \
        test test-unit test-feature test-filter pint pint-test \
        postman-validate

help: ## Mostra esta ajuda
	@awk 'BEGIN {FS = ":.*?## "; printf "\nUso: make \033[36m<target>\033[0m\n\nTargets:\n"} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

setup: env build up install key migrate seed ## Configura ambiente do zero (env, build, deps, migrate, seed)
	@echo "Setup concluído. API em http://localhost:8000/api  |  Mailhog em http://localhost:8025"

env: ## Cria .env a partir de .env.example se não existir
	@if [ ! -f .env ]; then cp .env.example .env && echo ".env criado a partir de .env.example"; else echo ".env já existe"; fi

build: ## Builda as imagens Docker
	$(DC) build

up: ## Sobe os containers em background
	$(DC) up -d

down: ## Derruba os containers
	$(DC) down

restart: ## Reinicia os containers
	$(DC) restart

logs: ## Acompanha logs (app + queue)
	$(DC) logs -f app queue

ps: ## Lista containers
	$(DC) ps

shell: ## Abre bash no container app
	$(DC) exec app bash

mysql: ## Abre cliente MySQL
	$(DC) exec mysql mysql -unotification -psecret notification_service

install: ## Instala dependências composer dentro do container
	$(COMPOSER) install --no-interaction --prefer-dist

key: ## Gera APP_KEY
	$(ARTISAN) key:generate --ansi

migrate: ## Executa migrations
	$(ARTISAN) migrate --force

migrate-fresh: ## Drop + migrate + seed
	$(ARTISAN) migrate:fresh --seed --force

seed: ## Executa seeders
	$(ARTISAN) db:seed --force

queue: ## Executa worker da fila em foreground
	$(ARTISAN) queue:work --tries=3 --timeout=60

queue-restart: ## Sinaliza workers para reiniciar
	$(ARTISAN) queue:restart

test: ## Roda toda a suíte de testes (compact)
	$(ARTISAN) test --compact

test-unit: ## Roda apenas testes unitários
	$(ARTISAN) test --testsuite=Unit --compact

test-feature: ## Roda apenas testes de feature
	$(ARTISAN) test --testsuite=Feature --compact

test-filter: ## Roda teste filtrado: make test-filter FILTER=NomeDoTeste
	$(ARTISAN) test --compact --filter=$(FILTER)

pint: ## Formata código alterado
	$(DC) exec -T app vendor/bin/pint --dirty --format agent

pint-test: ## Checa formatação sem alterar
	$(DC) exec -T app vendor/bin/pint --test --format agent

postman-validate: ## Valida JSON da collection do Postman
	$(PHP) -r "json_decode(file_get_contents('docs/postman/notification-service.postman_collection.json'), false, 512, JSON_THROW_ON_ERROR); echo 'collection ok\n';"
	$(PHP) -r "json_decode(file_get_contents('docs/postman/notification-service.postman_environment.json'), false, 512, JSON_THROW_ON_ERROR); echo 'environment ok\n';"
