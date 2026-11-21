.DEFAULT_GOAL := help
.PHONY: help up down reset logs seed db demo events test qa ci build-prod

COMPOSE := docker compose
# Run a one-off command in a fresh app container, without starting the whole stack.
RUN_APP := $(COMPOSE) run --rm --no-deps app

help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

# Random local database passwords. The secrets/ folder is git-ignored, so no password is ever committed.
secrets/%.txt:
	@mkdir -p secrets
	@openssl rand -hex 24 > $@
	@chmod 644 $@
	@echo "Generated $@"

SECRETS := secrets/db_password.txt secrets/db_root_password.txt

up: $(SECRETS) ## Build and start everything. API on http://localhost:8080
	$(COMPOSE) up -d --build --wait
	@echo ""
	@echo "Ready: http://localhost:8080/health   Next: make demo"

down: ## Stop all containers (database volume is kept)
	$(COMPOSE) down

reset: ## Stop all containers and delete the database
	$(COMPOSE) down -v --remove-orphans

logs: ## Follow the API and outbox relay logs
	$(COMPOSE) logs -f app outbox-relay

seed: ## Put the demo data back to its starting state
	$(COMPOSE) exec app php bin/seed.php

db: ## Open a MariaDB shell on the demo database
	$(COMPOSE) exec db sh -c 'mariadb -u club_billing -p"$$(cat /run/secrets/db_password)" club_billing'

demo: ## Walk through every scenario with curl (resets demo data first)
	$(COMPOSE) exec app sh scripts/demo.sh

events: ## Print the events the relay published to Kafka
	@echo "Topic billing.payment-events (message key = invoice id). Waits 5s for more, then exits."
	@# The consumer reports its normal idle exit as a TimeoutException on stderr, so stderr is hidden.
	@$(COMPOSE) exec kafka /opt/kafka/bin/kafka-console-consumer.sh \
		--bootstrap-server kafka:9092 --topic billing.payment-events \
		--from-beginning --property print.key=true --property key.separator=" | " \
		--timeout-ms 5000 2>/dev/null || true

test: $(SECRETS) ## Run the test suite against a real MariaDB
	$(COMPOSE) build app
	$(COMPOSE) up -d --wait db
	$(RUN_APP) composer test

qa: $(SECRETS) ## Code style, static analysis, tests and dependency audit
	$(COMPOSE) build app
	$(COMPOSE) up -d --wait db
	$(RUN_APP) composer validate --strict
	$(RUN_APP) composer qa
	$(RUN_APP) composer audit

build-prod: ## Build the production image (no dev tools, no tests, no seed data)
	docker build --target prod -t club-billing-app:prod .

ci: qa build-prod ## Everything CI runs
