# set all to phony
SHELL=bash

.PHONY: *

COMPOSER_CACHE_DIR=$(shell composer config --global cache-dir -q || echo ${HOME}/.composer-php/cache)
PHP_VERSION:=$(shell docker run --rm -v "`pwd`:`pwd`" jess/jq jq -r -c '.require.php' "`pwd`/composer.json" | php -r "echo str_replace(['|', '^'], ['.', ''], explode('.', implode('|', explode('.', stream_get_contents(STDIN), 2)), 2)[0]);")
COMPOSER_CONTAINER_CACHE_DIR=$(shell docker run --rm -it "ghcr.io/wyrihaximusnet/php:${PHP_VERSION}-nts-alpine${SLIM_DOCKER_IMAGE}-dev" composer config --global cache-dir -q || echo ${HOME}/.composer-php/cache)

ifneq ("$(wildcard /.you-are-in-a-wyrihaximus.net-php-docker-image)","")
    IN_DOCKER=TRUE
else
    IN_DOCKER=FALSE
endif

ifeq ("$(IN_DOCKER)","TRUE")
	DOCKER_RUN:=
else
	DOCKER_RUN:=docker run --rm -it \
		-v "`pwd`:`pwd`" \
		-v "${COMPOSER_CACHE_DIR}:${COMPOSER_CONTAINER_CACHE_DIR}" \
		-w "`pwd`" \
		"ghcr.io/wyrihaximusnet/php:${PHP_VERSION}-nts-alpine-dev"
endif

all: ## Runs everything ###
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -v "###" | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | xargs --open-tty $(MAKE)

generate: ## Generate code based on spec
	$(DOCKER_RUN) php spec/generate.php

cs-fix: ## Fix any automatically fixable code style issues
	$(DOCKER_RUN) vendor/bin/phpcbf --parallel=1 --cache=./var/.phpcs.cache.json --standard=./phpcs.xml || $(DOCKER_RUN) vendor/bin/phpcbf --parallel=1 --cache=./var/.phpcs.cache.json --standard=./phpcs.xml || $(DOCKER_RUN) vendor/bin/phpcbf --parallel=1 --cache=./var/.phpcs.cache.json --standard=./phpcs.xml -vvvv

cs: ## Check the code for code style issues
	$(DOCKER_RUN) vendor/bin/phpcs --parallel=1 --cache=./var/.phpcs.cache.json --standard=./phpcs.xml

stan: ## Run static analysis (PHPStan)
	$(DOCKER_RUN) vendor/bin/phpstan analyse src test --ansi -c ./phpstan.neon

unit-testing: ## Run tests
ifeq ("$(wildcard test/tls/ca.pem)","")
	make -C test/tls all
endif
	docker compose up -d
	docker exec -it bunny-bunny-1 php examples/wait-for-connection.php
	(docker exec -it bunny-bunny-1 vendor/bin/phpunit --colors=always -c /opt/bunny/phpunit.xml) || docker compose down
	docker compose down

composer-install: ## Install dependencies
	$(DOCKER_RUN) composer install --no-progress --ansi --no-interaction --prefer-dist -o

shell: ## Provides Shell access in the expected environment ###
	$(DOCKER_RUN) ash

help: ## Show this help ###
	@printf "\033[33mUsage:\033[0m\n  make [target]\n\n\033[33mTargets:\033[0m\n"
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[32m%-32s\033[0m %s\n", $$1, $$2}' | tr -d '#'
