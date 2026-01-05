# SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
# SPDX-License-Identifier: AGPL-3.0-or-later

app_name=federatedtalklink
build_directory=$(CURDIR)/build
source_directory=$(CURDIR)
sign_directory=$(build_directory)/sign
cert_directory=$(HOME)/.nextcloud/certificates

all: dev

# Install dependencies
.PHONY: composer
composer:
	composer install --prefer-dist --no-dev

.PHONY: composer-dev
composer-dev:
	composer install --prefer-dist

.PHONY: npm
npm:
	npm ci

.PHONY: npm-update
npm-update:
	npm update

# Development
.PHONY: dev
dev: npm
	npm run dev

.PHONY: watch
watch: npm
	npm run watch

# Production build
.PHONY: build
build: npm
	npm run build

# Linting
.PHONY: lint
lint:
	npm run lint

.PHONY: lint-fix
lint-fix:
	npm run lint:fix

.PHONY: stylelint
stylelint:
	npm run stylelint

.PHONY: stylelint-fix
stylelint-fix:
	npm run stylelint:fix

.PHONY: php-lint
php-lint:
	composer run lint

# Testing
.PHONY: test
test: composer-dev
	composer run test

# Clean
.PHONY: clean
clean:
	rm -rf $(build_directory)
	rm -rf node_modules
	rm -rf vendor
	rm -rf js/*.js js/*.js.map js/*.js.LICENSE.txt

# App store package
.PHONY: appstore
appstore: build composer
	mkdir -p $(sign_directory)
	rsync -a \
		--exclude=/.git \
		--exclude=/.github \
		--exclude=/build \
		--exclude=/node_modules \
		--exclude=/tests \
		--exclude=/.eslintrc.js \
		--exclude=/.gitignore \
		--exclude=/.stylelintrc.js \
		--exclude=/babel.config.js \
		--exclude=/composer.json \
		--exclude=/composer.lock \
		--exclude=/Makefile \
		--exclude=/package.json \
		--exclude=/package-lock.json \
		--exclude=/phpunit.xml \
		--exclude=/webpack.config.js \
		--exclude=/src \
		$(source_directory)/ $(sign_directory)/$(app_name)
	tar -czf $(build_directory)/$(app_name).tar.gz \
		-C $(sign_directory) $(app_name)
	@echo "Package created at $(build_directory)/$(app_name).tar.gz"

# Help
.PHONY: help
help:
	@echo "Federated Talk Link - Makefile targets"
	@echo ""
	@echo "  dev          - Build for development (default)"
	@echo "  watch        - Watch files and rebuild on changes"
	@echo "  build        - Build for production"
	@echo "  lint         - Run JavaScript/Vue linter"
	@echo "  lint-fix     - Fix linting issues"
	@echo "  stylelint    - Run style linter"
	@echo "  php-lint     - Run PHP linter"
	@echo "  test         - Run PHP tests"
	@echo "  clean        - Remove build artifacts"
	@echo "  appstore     - Create app store package"
	@echo "  help         - Show this help message"
