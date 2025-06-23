default: help

################# ALIAS #################

DC := $(shell docker --help | grep -q "^\s*compose" && echo "docker compose" || echo "docker-compose")
INTERACTIVE := $(shell [ -t 0 ] && echo 1)
ifdef INTERACTIVE
	DC_PHP := $(DC) exec php # Docker container php executable
else
	DC_PHP := $(DC) exec -T php # Docker container php executable
endif
SYM := $(DC_PHP) php bin/console # Symfony executable

#################  COMMANDS #################

##@ Helpers
.PHONY: help
help:  ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

KNOWN_TARGETS := $(shell awk '/^[a-zA-Z_-]+:/ { print $$1 }' $(MAKEFILE_LIST) | sed 's/://')
ARGS := $(filter-out $(KNOWN_TARGETS),$(MAKECMDGOALS))
.DEFAULT: ;: do nothing
.SUFFIXES:
.PHONY: dc-images
.PHONY: sf
sf: ## Run Symfony console commands inside the PHP container
	@$(DC_PHP) php bin/console $(filter-out $@,$(MAKECMDGOALS))
%:
	@:

##@ Docker
.PHONY: dc-images
dc-images: ## Show containers
	$(DC) images

.PHONY: dc-network
dc-network: ## Inspect network
	docker network inspect symfony_network

.PHONY: dc-php
dc-php: ## Connect to the php container
	$(DC_PHP) sh

##@ Project
.PHONY: pj-install
pj-install: ## Install the project
	$(MAKE) pj-start
	$(DC_PHP) composer install --no-scripts
	$(DC_PHP) composer --working-dir=tools/php-cs-fixer install
	$(DC_PHP) composer --working-dir=tools/phpstan install
	$(SYM) doctrine:database:create
	$(SYM) doctrine:migrations:migrate -n
	$(SYM) doctrine:fixtures:load -n
	$(SYM) lexik:jwt:generate-keypair --skip-if-exists
	$(SYM) assets:install
	@echo "\033[1;32mInstallation completed successfully\033[0m"

.PHONY: pj-start
pj-start: ## Start the project's containers
    ifeq ($(DC),docker-compose)
		$(DC) up -d
    else
		docker compose up -d
    endif

.PHONY: pj-stop
pj-stop: ## Stop the project's containers
    ifeq ($(DC),docker-compose)
		$(DC) down
    else
		docker compose down
    endif

.PHONY: pj-restart
pj-restart: pj-stop pj-start ## Restart the project's containers

.PHONY: pj-update
pj-update: ## Update the project (composer dependencies)
	$(DC_PHP) composer install

.PHONY: pj-reset
pj-reset: ## Reset the project (remove database, and re-install the project)
	$(MAKE) pj-start
	$(DC_PHP) composer install --no-scripts
	$(SYM) doctrine:database:drop --if-exists --force
	$(SYM) doctrine:database:create
	$(SYM) cache:clear
	$(SYM) doctrine:migrations:migrate -n
## $(SYM) doctrine:fixtures:load -n

.PHONY: pj-cc
pj-cc: ## Clear the Symfony cache in the container
	$(SYM) cache:clear

##@ Database
.PHONY: db-create
db-create: ## Create database
	$(SYM) doctrine:database:create

.PHONY: db-update
db-update: ## Play migrations
	$(SYM) doctrine:migrations:migrate -n

.PHONY: db-drop
db-drop: ## Drop database
	$(SYM) doctrine:database:drop --if-exists --force

.PHONY: db-status
db-status: ## Show status of migrations
	$(SYM) doctrine:migrations:status

.PHONY: db-validate
db-validate: ## Show schema validate
	$(SYM) doctrine:schema:validate

.PHONY: db-fixtures
db-fixtures: ## Reload fixtures in the database
	make db-drop
	make db-create
	make db-update
	$(SYM) doctrine:fixtures:load -q

.PHONY: dbt-tests
db-tests: ## Create database for tests (drop if already exist)
	$(SYM) doctrine:database:drop --env=test --if-exists --force
	$(SYM) doctrine:database:create --env=test
	$(SYM) doctrine:schema:create --env=test

.PHONY: db-restore
db-restore: ## Restore the anonymized database
	make db-drop
	make db-create
	$(SYM) restore:database:backup

.PHONY: db-show-table
db-show-table: ## Show the CREATE TABLE statement for a specific table or all tables (usage: make db-show-table TABLE=<table_name> or TABLE=ALL)
	@TABLE=$(TABLE) && \
	if [ -z "$$TABLE" ]; then \
		echo "Error: TABLE argument is required. Usage: make db-show-table TABLE=<table_name> or TABLE=ALL"; \
		exit 1; \
	fi; \
	USER=$(shell grep -oP 'DATABASE_URL=mysql://\K[^:]+' .env.local) && \
	PASSWORD=$(shell grep -oP 'DATABASE_URL=mysql://[^:]+:\K[^@]+' .env.local) && \
	DATABASE=$(shell grep -oP 'DATABASE_URL=mysql://[^/]+/\K[^?]+' .env.local) && \
	if [ "$$TABLE" = "ALL" ]; then \
		SCHEMA_FILE=".database.schema"; \
		echo "Generating schema file: $$SCHEMA_FILE"; \
		echo "" > $$SCHEMA_FILE; \
		TABLES=$$(docker compose exec database mysql -uroot -proot -Nse "SHOW TABLES IN \`$$DATABASE\`"); \
		for t in $$TABLES; do \
			echo "Processing table: $$t"; \
			echo "CREATE TABLE for $$t:" >> $$SCHEMA_FILE; \
			docker compose exec database mysql -uroot -proot -e "SHOW CREATE TABLE \`$$DATABASE\`.\`$$t\`\G" >> $$SCHEMA_FILE; \
			echo "" >> $$SCHEMA_FILE; \
		done; \
		echo "Schema file generated: $$SCHEMA_FILE"; \
	else \
		docker compose exec database mysql -uroot -proot -e "SHOW CREATE TABLE \`$$DATABASE\`.\`$$TABLE\`\G"; \
	fi

##@ Git

LAST_COMMIT_SHA := $(shell git rev-parse HEAD)
ORIGIN_BRANCH = $(if $(ARGS),\
	$(ARGS),\
	"origin/develop")

.PHONY: git-advance
git-advance: ## Advance the branch to the last commit - only for a single commit
	git fetch origin
	git reset --hard $(ORIGIN_BRANCH)
	git cherry-pick $(LAST_COMMIT_SHA)

##@ Production

.PHONY: prod-stop
prod-stop: ## Stop the production containers
	sudo docker compose -f docker-compose.prod.yml down

.PHONY: prod-start
prod-start: ## Start the production containers
	sudo docker compose -f docker-compose.prod.yml up -d

.PHONY: prod-build
prod-build: ## Build the production containers
	sudo docker compose -f docker-compose.prod.yml build

.PHONY: prod-restart
prod-restart: ## Restart the production containers
	sudo docker compose -f docker-compose.prod.yml down
	sudo docker compose -f docker-compose.prod.yml up -d

.PHONY: prod-composer-install
prod-composer-install: ## Install the production composer dependencies
	sudo docker compose -f docker-compose.prod.yml exec frankenphp composer install

.PHONY: prod-migrate
prod-migrate: ## Run the production migrations
	sudo docker compose -f docker-compose.prod.yml exec frankenphp php bin/console doctrine:migrations:migrate --no-interaction --env=prod

.PHONY: prod-cache-clear
prod-cache-clear: ## Clear the production cache
	sudo docker compose -f docker-compose.prod.yml exec frankenphp php bin/console cache:clear --env=prod

.PHONY: prod-reset
prod-reset: ## Reset the production containers
	make prod-stop
	make prod-build
	make prod-start
	make prod-composer-install
	make prod-migrate
	make prod-cache-clear

.PHONY: prod-list-users
prod-list-users: ## List all users in the production database
	sudo docker compose -f docker-compose.prod.yml exec frankenphp php bin/console app:list-users

.PHONY: prod-delete-user
prod-delete-user: ## Delete a user in the production database
	sudo docker compose -f docker-compose.prod.yml exec frankenphp php bin/console app:delete-user $(USERNAME)