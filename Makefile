# Vincent Ragosta Theme - Build System
# Usage: make [target]

PARENT_THEME_DIR := $(CURDIR)/wp-content/themes/parent-theme
CHILD_THEME_DIR := $(CURDIR)/wp-content/themes/child-theme

.PHONY: help start stop install install-parent install-child build watch clean autoload test test-js update push-staging pull-staging

# Server config
STAGING_HOST := root@174.138.70.29
STAGING_DIR := /var/www/vincentragosta.dev
STAGING_WP := $(STAGING_DIR)/wp
STAGING_URL := https://staging.vincentragosta.io
LOCAL_URL := https://vincentragosta.io.ddev.site
UPLOADS_DIR := $(CURDIR)/wp-content/uploads

# Default target
help:
	@echo "Available targets:"
	@echo "  make start         - Start DDEV, restore DB snapshot, install deps, and build"
	@echo "  make stop          - Snapshot database and stop DDEV"
	@echo "  make install       - Install all dependencies (both themes)"
	@echo "  make build         - Build child theme assets"
	@echo "  make test          - Run test suite (both themes)"
	@echo "  make watch         - Start watch mode for development"
	@echo "  make clean         - Remove all generated files"
	@echo "  make update        - Update composer dependencies (root + both themes)"
	@echo "  make autoload      - Regenerate composer autoloaders"
	@echo "  make push-staging  - Push local DB + uploads to staging"
	@echo "  make pull-staging  - Pull staging DB + uploads to local"

# Start DDEV environment, install dependencies, and build assets
start:
	@echo "Starting DDEV environment..."
	ddev start
	@if ddev snapshot restore --latest 2>/dev/null; then \
		echo "✓ Database restored from latest snapshot"; \
	else \
		echo "No snapshot found, using existing database"; \
	fi
	@$(MAKE) install
	@$(MAKE) build
	@echo ""
	@echo "✓ Project is running at https://vincentragosta.io.ddev.site"

# Snapshot database and stop DDEV environment
stop:
	@echo "Snapshotting database..."
	ddev snapshot --name=pre-stop-$$(date +%Y%m%d-%H%M%S)
	@echo "Pruning old snapshots (keeping 3 most recent)..."
	@cd .ddev/db_snapshots && ls -t *.gz 2>/dev/null | tail -n +4 | while read f; do \
		echo "  Removed: $$f"; \
		rm -f "$$f"; \
	done
	@echo "Stopping DDEV environment..."
	ddev stop
	@echo "✓ Database snapshotted and DDEV stopped"

# Install all dependencies
install: install-parent install-child
	@echo "✓ All dependencies installed"

install-parent:
	@echo "Installing parent theme dependencies..."
	cd $(PARENT_THEME_DIR) && composer install --no-interaction
	cd $(PARENT_THEME_DIR) && npm install

install-child:
	@echo "Installing child theme dependencies..."
	cd $(CHILD_THEME_DIR) && composer install --no-interaction
	cd $(CHILD_THEME_DIR) && npm install

# Build assets (child theme only - parent is PHP infrastructure)
build:
	@echo "Building child theme assets..."
	cd $(CHILD_THEME_DIR) && npm run build
	@echo "✓ Build complete"

# Watch mode
watch:
	@echo "Starting watch mode..."
	cd $(CHILD_THEME_DIR) && npm run start

# Clean generated files
clean:
	@echo "Cleaning generated files..."
	rm -rf $(PARENT_THEME_DIR)/vendor
	rm -rf $(PARENT_THEME_DIR)/node_modules
	rm -rf $(PARENT_THEME_DIR)/dist
	rm -rf $(CHILD_THEME_DIR)/vendor
	rm -rf $(CHILD_THEME_DIR)/node_modules
	rm -rf $(CHILD_THEME_DIR)/dist
	@echo "✓ Clean complete"

# Update composer dependencies
update:
	@echo "Updating root dependencies..."
	composer update --no-interaction
	@echo ""
	@echo "Updating parent theme dependencies..."
	cd $(PARENT_THEME_DIR) && composer update --no-interaction
	@echo ""
	@echo "Updating child theme dependencies..."
	cd $(CHILD_THEME_DIR) && composer update --no-interaction
	@echo ""
	@echo "✓ All dependencies updated"

# Composer dump-autoload for both themes
autoload:
	@echo "Regenerating autoloaders..."
	cd $(PARENT_THEME_DIR) && composer dump-autoload
	cd $(CHILD_THEME_DIR) && composer dump-autoload
	@echo "✓ Autoloaders regenerated"

# Run test suite for both themes
test:
	@echo "Running parent theme tests..."
	cd $(PARENT_THEME_DIR) && composer test
	@echo ""
	@echo "Running child theme tests..."
	cd $(CHILD_THEME_DIR) && composer test
	@echo ""
	@echo "Running parent theme JS tests..."
	cd $(PARENT_THEME_DIR) && npm run test:js
	@echo ""
	@echo "Running child theme JS tests..."
	cd $(CHILD_THEME_DIR) && npm run test:js
	@echo ""
	@echo "✓ All tests complete"

# Run JS tests only
test-js:
	cd $(PARENT_THEME_DIR) && npm run test:js
	cd $(CHILD_THEME_DIR) && npm run test:js

# Push local DDEV database and uploads to staging
push-staging:
	@echo "Exporting local database..."
	ddev export-db --gzip=false --file=/tmp/ddev-export.sql
	@echo "Uploading database to staging..."
	scp /tmp/ddev-export.sql $(STAGING_HOST):/tmp/ddev-export.sql
	@echo "Importing database on staging..."
	ssh $(STAGING_HOST) "wp db import /tmp/ddev-export.sql --path=$(STAGING_WP) --allow-root"
	@echo "Replacing URLs (siteurl)..."
	ssh $(STAGING_HOST) "wp search-replace '$(LOCAL_URL)/wp' '$(STAGING_URL)/wp' --path=$(STAGING_WP) --allow-root --precise --all-tables --quiet"
	@echo "Replacing URLs (home)..."
	ssh $(STAGING_HOST) "wp search-replace '$(LOCAL_URL)' '$(STAGING_URL)' --path=$(STAGING_WP) --allow-root --precise --all-tables --quiet"
	@echo "Flushing caches..."
	ssh $(STAGING_HOST) "wp cache flush --path=$(STAGING_WP) --allow-root --quiet && wp rewrite flush --path=$(STAGING_WP) --allow-root --quiet"
	@echo "Syncing uploads..."
	rsync -az --delete $(UPLOADS_DIR)/ $(STAGING_HOST):$(STAGING_DIR)/wp-content/uploads/
	ssh $(STAGING_HOST) "chown -R www-data:www-data $(STAGING_DIR)/wp-content/uploads"
	@echo "Cleaning up..."
	rm -f /tmp/ddev-export.sql
	ssh $(STAGING_HOST) "rm -f /tmp/ddev-export.sql"
	@echo "Done — staging synced from local"

# Pull staging database and uploads to local DDEV
pull-staging:
	@echo "Exporting staging database..."
	ssh $(STAGING_HOST) "wp db export /tmp/staging-export.sql --path=$(STAGING_WP) --allow-root"
	@echo "Downloading database..."
	scp $(STAGING_HOST):/tmp/staging-export.sql /tmp/staging-export.sql
	@echo "Importing into DDEV..."
	ddev import-db --file=/tmp/staging-export.sql
	@echo "Replacing URLs (siteurl)..."
	ddev wp search-replace '$(STAGING_URL)/wp' '$(LOCAL_URL)/wp' --precise --all-tables --quiet
	@echo "Replacing URLs (home)..."
	ddev wp search-replace '$(STAGING_URL)' '$(LOCAL_URL)' --precise --all-tables --quiet
	@echo "Flushing caches..."
	ddev wp cache flush --quiet
	ddev wp rewrite flush --quiet
	@echo "Syncing uploads..."
	rsync -az --delete $(STAGING_HOST):$(STAGING_DIR)/wp-content/uploads/ $(UPLOADS_DIR)/
	@echo "Cleaning up..."
	rm -f /tmp/staging-export.sql
	ssh $(STAGING_HOST) "rm -f /tmp/staging-export.sql"
	@echo "Done — local synced from staging"
