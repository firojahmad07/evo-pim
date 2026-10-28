#
# This file is a template Makefile. Some targets are presented here as examples.
# Feel free to customize it to your needs!
#
export HOST_UID ?= $(shell id -u)
export HOST_GID ?= $(shell id -g)

-include docker/.runtime-user.mk

# prepare-runtime-user.sh sets these. On Docker Desktop they are the image's
# PHP-FPM uid (1000), which is not the same number as the host user.
PHP_RUNTIME_UID ?= $(HOST_UID)
PHP_RUNTIME_GID ?= $(HOST_GID)
export PHP_RUNTIME_UID
export PHP_RUNTIME_GID

CMD_ON_PROJECT = docker compose run --rm --user $(PHP_RUNTIME_UID):$(PHP_RUNTIME_GID) php
ROOT_CMD_ON_PROJECT = docker compose run --rm --user root php
PHP_RUN = $(CMD_ON_PROJECT) php
YARN_RUN = docker compose run -u $(PHP_RUNTIME_UID):$(PHP_RUNTIME_GID) --rm -e YARN_REGISTRY -e PUPPETEER_SKIP_CHROMIUM_DOWNLOAD node yarn

ifdef NO_DOCKER
  CMD_ON_PROJECT =
  ROOT_CMD_ON_PROJECT =
  YARN_RUN = yarnpkg
  PHP_RUN = php
endif

.DEFAULT_GOAL := dev

.PHONY: prepare-runtime
prepare-runtime:
	./docker/prepare-runtime-user.sh

yarn.lock: package.json
	PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=1 $(YARN_RUN) install

node_modules: yarn.lock
	PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=1 $(YARN_RUN) install

.PHONY: javascript-extensions
javascript-extensions:
	$(YARN_RUN) run update-extensions

.PHONY: front-packages
front-packages:
	PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=1 $(YARN_RUN) packages:build

.PHONY: assets
assets:
	$(ROOT_CMD_ON_PROJECT) rm -rf public/bundles public/js
	$(PHP_RUN) bin/console pim:installer:assets --symlink --clean
	$(MAKE) javascript-extensions

.PHONY: css
css:
	$(ROOT_CMD_ON_PROJECT) rm -rf public/css
	$(YARN_RUN) run less

.PHONY: javascript-prod
javascript-prod:
	$(ROOT_CMD_ON_PROJECT) rm -rf public/dist
	$(YARN_RUN) run webpack

.PHONY: javascript-dev
javascript-dev:
	$(ROOT_CMD_ON_PROJECT) rm -rf public/dist
	$(YARN_RUN) run webpack-dev

.PHONY: front
front: assets css front-packages javascript-dev

.PHONY: database
database:
	$(PHP_RUN) bin/console doctrine:database:drop --force --if-exists
	$(PHP_RUN) bin/console doctrine:database:create --if-not-exists
	$(PHP_RUN) bin/console pim:installer:db ${O}

.PHONY: database-if-needed
database-if-needed:
ifndef NO_DOCKER
	@tables=$$(docker compose exec -T mysql mysql -N -uroot -proot -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='akeneo_pim'" 2>/dev/null | tail -n 1); \
	if [ "$$tables" = "0" ] || [ -z "$$tables" ]; then \
		echo "Database is empty. Loading the demo catalog."; \
		$(MAKE) database O="$(O)"; \
	else \
		echo "Database already has $$tables tables. Skipping demo catalog load."; \
	fi
else
	$(MAKE) database O="$(O)"
endif

.PHONY: cache
cache:
	$(ROOT_CMD_ON_PROJECT) sh -c 'mkdir -p var/cache var/logs var/file_storage && chmod 777 var var/cache var/logs var/file_storage && rm -rf var/cache/*'
	$(PHP_RUN) bin/console cache:warmup
	$(ROOT_CMD_ON_PROJECT) chmod -R a+rwX var/cache var/logs || true

composer.lock: composer.json
	$(PHP_RUN) -d memory_limit=4G /usr/local/bin/composer update

vendor: composer.lock
	$(PHP_RUN) -d memory_limit=4G /usr/local/bin/composer install

.PHONY: dependencies
dependencies: vendor node_modules

.PHONY: dev
dev: prepare-runtime
	$(MAKE) dev-after-prepare

.PHONY: dev-after-prepare
dev-after-prepare: dependencies pim-after-prepare

.PHONY: prod
prod: prepare-runtime
	$(MAKE) prod-after-prepare

.PHONY: prod-after-prepare
prod-after-prepare: dependencies pim-prod-after-prepare

.PHONY: pim-prod
pim-prod: prepare-runtime
	$(MAKE) pim-prod-after-prepare

.PHONY: pim-prod-after-prepare
pim-prod-after-prepare:
ifndef NO_DOCKER
	APP_ENV=prod $(MAKE) up
	docker/wait_docker_up.sh
endif
	$(MAKE) cache
	$(MAKE) assets
	$(MAKE) front-packages
	$(MAKE) javascript-prod
	$(MAKE) css
	$(MAKE) javascript-extensions
	APP_ENV=prod $(MAKE) database-if-needed O="--catalog src/Akeneo/Platform/Installer/back/src/Infrastructure/Symfony/Resources/fixtures/minimal"

.PHONY: pim-dev
pim-dev: prepare-runtime
	$(MAKE) pim-after-prepare

.PHONY: pim-after-prepare
pim-after-prepare:
ifndef NO_DOCKER
	APP_ENV=dev $(MAKE) up
	docker/wait_docker_up.sh
endif
	$(MAKE) cache
	$(MAKE) assets
	$(MAKE) front-packages
	$(MAKE) javascript-dev
	$(MAKE) css
	$(MAKE) javascript-extensions
	APP_ENV=dev $(MAKE) database-if-needed O="--catalog src/Akeneo/Platform/Installer/back/src/Infrastructure/Symfony/Resources/fixtures/icecat_demo_dev"

.PHONY: up
up:
	docker compose up -d --remove-orphans

.PHONY: down
down:
	docker compose down -v

.PHONY: upgrade-front
upgrade-front:
	$(MAKE) node_modules
	$(MAKE) cache
	$(MAKE) assets
	$(MAKE) front-packages
	$(MAKE) javascript-prod
	$(MAKE) css
	$(MAKE) javascript-extensions
