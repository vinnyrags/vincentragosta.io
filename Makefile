# Vincent Ragosta Theme - Build System
# Usage: make [target]

IX_DIR := $(CURDIR)/wp-content/themes/ix
CHILD_THEME_DIR := $(CURDIR)/wp-content/themes/vincentragosta
MYTHUS_DIR := $(CURDIR)/wp-content/mu-plugins/mythus

.PHONY: help start stop install install-root install-mythus install-ix install-child build watch clean autoload test test-js update deploy-staging deploy-production release push-staging pull-staging push-production pull-production pull-patterns pull-patterns-staging pull-products pull-products-publish pull-products-staging push-cards pull-cards pull-cards-publish pull-cards-staging pull-cards-production sync-cards enrich-singles backup-singles seed-itzenzo-pages seed-itzenzo-pages-force seed-itzenzo-pages-staging seed-itzenzo-pages-production nous-import satis-refresh satis-add satis-remove

# Server config
STAGING_HOST := root@174.138.70.29
STAGING_DIR := /var/www/staging.vincentragosta.io
STAGING_WP := $(STAGING_DIR)/wp
STAGING_URL := https://staging.vincentragosta.io
PRODUCTION_HOST := root@174.138.70.29
PRODUCTION_DIR := /var/www/vincentragosta.io
PRODUCTION_WP := $(PRODUCTION_DIR)/wp
PRODUCTION_URL := https://vincentragosta.io
LOCAL_URL := https://vincentragosta.io.ddev.site
UPLOADS_DIR := $(CURDIR)/wp-content/uploads

# Default target
help:
	@echo "Available targets:"
	@echo "  make start         - Start DDEV, restore DB snapshot, install deps, and build"
	@echo "  make stop          - Snapshot database and stop DDEV"
	@echo "  make install            - Install all dependencies (root, Mythus, IX, child)"
	@echo "  make build              - Build child theme assets"
	@echo "  make test               - Run test suite (Mythus + both themes)"
	@echo "  make watch              - Start watch mode for development"
	@echo "  make clean              - Remove all generated files"
	@echo "  make update             - Update composer dependencies (root + all packages)"
	@echo "  make autoload           - Regenerate composer autoloaders"
	@echo "  make deploy-staging     - Deploy develop branch to staging"
	@echo "  make deploy-production  - Merge develop into main and deploy to production"
	@echo "  make release            - Merge develop into main and push both to origin"
	@echo "  make push-staging       - Push local DB + uploads to staging"
	@echo "  make pull-staging       - Pull staging DB + uploads to local"
	@echo "  make push-production    - Push local DB + uploads to production"
	@echo "  make pull-production    - Pull production DB + uploads to local"
	@echo "  make pull-patterns      - Export block patterns from production to PHP files"
	@echo "  make pull-patterns-staging - Export block patterns from staging to PHP files"
	@echo "  make pull-products      - Sync Stripe products to local WordPress (as drafts)"
	@echo "  make pull-products-publish - Sync Stripe products to local WordPress (auto-publish)"
	@echo "  make pull-products-staging - Sync Stripe products to staging (clean + publish)"
	@echo "  make backup-singles     - Duplicate the Singles tab as Singles_Backup_YYYY-MM-DD"
	@echo "  make enrich-singles     - Populate set/rarity/image data via Pokemon TCG API"
	@echo "  make push-cards         - Push card singles from Google Sheets to Stripe"
	@echo "  make pull-cards         - Sync Stripe card singles to local WordPress (as drafts)"
	@echo "  make pull-cards-publish - Sync Stripe card singles to local WordPress (auto-publish)"
	@echo "  make pull-cards-staging - Sync Stripe card singles to staging (clean + publish)"
	@echo "  make pull-cards-production - Sync Stripe card singles to production (publish, idempotent)"
	@echo "  make sync-cards         - Full card pipeline: push-cards + pull-cards-publish"
	@echo "  make seed-itzenzo-pages - Seed the itzenzo.tv Pages ACF repeater (refuses to overwrite)"
	@echo "  make seed-itzenzo-pages-force - Force-overwrite the Pages repeater"
	@echo "  make seed-itzenzo-pages-staging - Force-overwrite staging Pages repeater"
	@echo "  make seed-itzenzo-pages-production - Force-overwrite production Pages repeater"
	@echo "  make nous-import FILE=... TITLE=... EXCERPT=... DATE=... TAGS=... - Import a Nous Signal post"
	@echo "  make satis-refresh      - Rebuild Satis package repository on server"
	@echo "  make satis-add URL=...  - Add a repository to Satis (rebuilds by default)"
	@echo "  make satis-remove URL=... - Remove a repository from Satis and rebuild"

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
install: install-root install-mythus install-ix install-child
	@echo "✓ All dependencies installed"

install-root:
	@echo "Installing root dependencies (WordPress, plugins, Mythus, IX)..."
	composer install --no-interaction

install-mythus:
	@echo "Installing Mythus dependencies..."
	cd $(MYTHUS_DIR) && composer install --no-interaction

install-ix:
	@echo "Installing IX dependencies..."
	cd $(IX_DIR) && composer install --no-interaction
	cd $(IX_DIR) && npm install

install-child:
	@echo "Installing child theme dependencies..."
	cd $(CHILD_THEME_DIR) && composer install --no-interaction
	cd $(CHILD_THEME_DIR) && npm install

# Build assets (IX parent theme first, then child theme)
build:
	@echo "Building IX parent theme assets..."
	cd $(IX_DIR) && npm run build
	@echo "Building child theme assets..."
	cd $(CHILD_THEME_DIR) && npm run build
	@echo "✓ Build complete"

# Watch mode
watch:
	@echo "Starting watch mode..."
	cd $(CHILD_THEME_DIR) && npm run start

# Deploy develop branch to staging
deploy-staging:
	@echo "Deploying develop to staging..."
	git push production develop
	@echo "✓ Staging deployed — verify at $(STAGING_URL)"

# Merge develop into main and deploy to production
deploy-production:
	@echo "Merging develop into main..."
	git checkout main
	git merge develop --ff-only
	@echo "Deploying main to production..."
	git push production main
	git checkout develop
	@echo "✓ Production deployed — verify at $(PRODUCTION_URL)"

# Merge develop into main and push both branches to origin
release:
	@echo "Merging develop into main..."
	git checkout main
	git merge develop --ff-only
	@echo "Pushing both branches to origin..."
	git push origin main
	git push origin develop
	git checkout develop
	@echo "✓ Both branches pushed to origin"

# Clean generated files
clean:
	@echo "Cleaning generated files..."
	rm -rf $(MYTHUS_DIR)/vendor
	rm -rf $(IX_DIR)/vendor
	rm -rf $(IX_DIR)/node_modules
	rm -rf $(IX_DIR)/dist
	rm -rf $(CHILD_THEME_DIR)/vendor
	rm -rf $(CHILD_THEME_DIR)/node_modules
	rm -rf $(CHILD_THEME_DIR)/dist
	@echo "✓ Clean complete"

# Update composer dependencies
update:
	@echo "Updating root dependencies..."
	composer update --no-interaction
	@echo ""
	@echo "Updating Mythus dependencies..."
	cd $(MYTHUS_DIR) && composer update --no-interaction
	@echo ""
	@echo "Updating IX dependencies..."
	cd $(IX_DIR) && composer update --no-interaction
	@echo ""
	@echo "Updating child theme dependencies..."
	cd $(CHILD_THEME_DIR) && composer update --no-interaction
	@echo ""
	@echo "✓ All dependencies updated"

# Composer dump-autoload for Mythus and both themes
autoload:
	@echo "Regenerating autoloaders..."
	cd $(MYTHUS_DIR) && composer dump-autoload
	cd $(IX_DIR) && composer dump-autoload
	cd $(CHILD_THEME_DIR) && composer dump-autoload
	@echo "✓ Autoloaders regenerated"

# Run test suite for Mythus and both themes
test:
	@echo "Running Mythus tests..."
	cd $(MYTHUS_DIR) && composer test
	@echo ""
	@echo "Running IX tests..."
	cd $(IX_DIR) && composer test
	@echo ""
	@echo "Running child theme tests..."
	cd $(CHILD_THEME_DIR) && composer test
	@echo ""
	@echo "Running IX JS tests..."
	cd $(IX_DIR) && npm run test:js
	@echo ""
	@echo "Running child theme JS tests..."
	cd $(CHILD_THEME_DIR) && npm run test:js
	@echo ""
	@echo "✓ All tests complete"

# Run JS tests only
test-js:
	cd $(IX_DIR) && npm run test:js
	cd $(CHILD_THEME_DIR) && npm run test:js

# Push local DDEV database and uploads to staging
push-staging:
	@echo "Exporting local database..."
	ddev export-db --gzip=false --file=/tmp/ddev-export.sql
	@echo "Uploading database to staging..."
	scp /tmp/ddev-export.sql $(STAGING_HOST):/tmp/ddev-export.sql
	@echo "Importing database on staging..."
	ssh $(STAGING_HOST) "wp db import /tmp/ddev-export.sql --path=$(STAGING_WP) --allow-root"
	@echo "Replacing URLs..."
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
	@echo "Replacing URLs..."
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

# Push local DDEV database and uploads to production
push-production:
	@echo "Exporting local database..."
	ddev export-db --gzip=false --file=/tmp/ddev-export.sql
	@echo "Uploading database to production..."
	scp /tmp/ddev-export.sql $(PRODUCTION_HOST):/tmp/ddev-export.sql
	@echo "Importing database on production..."
	ssh $(PRODUCTION_HOST) "wp db import /tmp/ddev-export.sql --path=$(PRODUCTION_WP) --allow-root"
	@echo "Replacing URLs..."
	ssh $(PRODUCTION_HOST) "wp search-replace '$(LOCAL_URL)' '$(PRODUCTION_URL)' --path=$(PRODUCTION_WP) --allow-root --precise --all-tables --quiet"
	@echo "Flushing caches..."
	ssh $(PRODUCTION_HOST) "wp cache flush --path=$(PRODUCTION_WP) --allow-root --quiet && wp rewrite flush --path=$(PRODUCTION_WP) --allow-root --quiet"
	@echo "Syncing uploads..."
	rsync -az --delete $(UPLOADS_DIR)/ $(PRODUCTION_HOST):$(PRODUCTION_DIR)/wp-content/uploads/
	ssh $(PRODUCTION_HOST) "chown -R www-data:www-data $(PRODUCTION_DIR)/wp-content/uploads"
	@echo "Cleaning up..."
	rm -f /tmp/ddev-export.sql
	ssh $(PRODUCTION_HOST) "rm -f /tmp/ddev-export.sql"
	@echo "Done — production synced from local"

# Pull production database and uploads to local DDEV
pull-production:
	@echo "Exporting production database..."
	ssh $(PRODUCTION_HOST) "wp db export /tmp/production-export.sql --path=$(PRODUCTION_WP) --allow-root"
	@echo "Downloading database..."
	scp $(PRODUCTION_HOST):/tmp/production-export.sql /tmp/production-export.sql
	@echo "Importing into DDEV..."
	ddev import-db --file=/tmp/production-export.sql
	@echo "Replacing URLs..."
	ddev wp search-replace '$(PRODUCTION_URL)' '$(LOCAL_URL)' --precise --all-tables --quiet
	@echo "Flushing caches..."
	ddev wp cache flush --quiet
	ddev wp rewrite flush --quiet
	@echo "Syncing uploads..."
	rsync -az --delete $(PRODUCTION_HOST):$(PRODUCTION_DIR)/wp-content/uploads/ $(UPLOADS_DIR)/
	@echo "Cleaning up..."
	rm -f /tmp/production-export.sql
	ssh $(PRODUCTION_HOST) "rm -f /tmp/production-export.sql"
	@echo "Done — local synced from production"

# Export block patterns from production to PHP files
pull-patterns:
	@REMOTE_HOST="$(PRODUCTION_HOST)" \
	REMOTE_WP="$(PRODUCTION_WP)" \
	REMOTE_URL="$(PRODUCTION_URL)" \
	$(CHILD_THEME_DIR)/scripts/export-patterns.sh

# Export block patterns from staging to PHP files
pull-patterns-staging:
	@REMOTE_HOST="$(STAGING_HOST)" \
	REMOTE_WP="$(STAGING_WP)" \
	REMOTE_URL="$(STAGING_URL)" \
	$(CHILD_THEME_DIR)/scripts/export-patterns.sh

# Sync Stripe products to local WordPress (as drafts)
pull-products:
	@echo "Syncing Stripe products to WordPress..."
	ddev wp eval-file scripts/pull-products.php

# Sync Stripe products to local WordPress (auto-publish)
pull-products-publish:
	@echo "Syncing Stripe products to WordPress (auto-publish)..."
	@touch scripts/.publish
	@ddev wp eval-file scripts/pull-products.php; rm -f scripts/.publish

# Sync Stripe products to staging WordPress (clean rebuild, auto-publish)
pull-products-staging:
	@echo "Syncing Stripe products to staging WordPress..."
	ssh $(STAGING_HOST) "touch $(STAGING_DIR)/scripts/.publish $(STAGING_DIR)/scripts/.clean && wp eval-file $(STAGING_DIR)/scripts/pull-products.php --path=$(STAGING_WP) --allow-root; rm -f $(STAGING_DIR)/scripts/.publish $(STAGING_DIR)/scripts/.clean"

# Duplicate the Singles tab in Google Sheets as a backup
backup-singles:
	@echo "Backing up Singles tab..."
	cd ../Nous/scripts/shop && node backup-singles.js

# Enrich Singles rows with set/rarity/image data from the Pokemon TCG API
enrich-singles:
	@echo "Enriching Singles tab via Pokemon TCG API..."
	cd ../Nous/scripts/shop && node enrich-singles.js

# Push card singles from Google Sheets Singles tab to Stripe.
# Pulls STRIPE_SECRET_KEY from local wp-config via DDEV so the target is
# self-sufficient — no manual env var needed.
push-cards:
	@echo "Pushing cards from Google Sheets to Stripe..."
	@STRIPE_SECRET_KEY=$$(ddev wp eval "echo STRIPE_SECRET_KEY;" 2>/dev/null | tail -1); \
	if [ -z "$$STRIPE_SECRET_KEY" ]; then \
		echo "Error: could not read STRIPE_SECRET_KEY from DDEV. Is DDEV running?"; exit 1; \
	fi; \
	cd ../Nous/scripts/shop && STRIPE_SECRET_KEY="$$STRIPE_SECRET_KEY" node push-cards.js $(ARGS)

# Sync Stripe card singles to local WordPress (as drafts)
pull-cards:
	@echo "Syncing Stripe card singles to WordPress..."
	ddev wp eval-file scripts/pull-cards.php

# Sync Stripe card singles to local WordPress (auto-publish)
pull-cards-publish:
	@echo "Syncing Stripe card singles to WordPress (auto-publish)..."
	@touch scripts/.publish
	@ddev wp eval-file scripts/pull-cards.php; rm -f scripts/.publish

# Sync Stripe card singles to staging WordPress (clean rebuild, auto-publish)
pull-cards-staging:
	@echo "Syncing Stripe card singles to staging WordPress..."
	ssh $(STAGING_HOST) "touch $(STAGING_DIR)/scripts/.publish $(STAGING_DIR)/scripts/.clean && wp eval-file $(STAGING_DIR)/scripts/pull-cards.php --path=$(STAGING_WP) --allow-root; rm -f $(STAGING_DIR)/scripts/.publish $(STAGING_DIR)/scripts/.clean"

# Sync Stripe card singles to production WordPress (auto-publish, idempotent)
# No --clean — we don't nuke existing card posts/attachments on production.
pull-cards-production:
	@echo "Syncing Stripe card singles to production WordPress..."
	ssh $(PRODUCTION_HOST) "touch $(PRODUCTION_DIR)/scripts/.publish && wp eval-file $(PRODUCTION_DIR)/scripts/pull-cards.php --path=$(PRODUCTION_WP) --allow-root; rm -f $(PRODUCTION_DIR)/scripts/.publish"

# Full card pipeline: Sheets -> Stripe -> local WordPress (published)
sync-cards: push-cards pull-cards-publish
	@echo "✓ Card pipeline complete"

# Seed the itzenzo.tv Pages ACF repeater with canonical content
seed-itzenzo-pages:
	@echo "Seeding itzenzo.tv Pages (refuses to overwrite existing data)..."
	ddev wp eval-file scripts/seed-itzenzo-pages.php

# Force-overwrite the itzenzo.tv Pages ACF repeater
seed-itzenzo-pages-force:
	@echo "Seeding itzenzo.tv Pages (overwriting)..."
	ddev exec "FORCE=1 wp eval-file scripts/seed-itzenzo-pages.php"

# Seed the staging Pages ACF repeater (force-overwrite)
seed-itzenzo-pages-staging:
	@echo "Seeding staging itzenzo.tv Pages..."
	ssh $(STAGING_HOST) "FORCE=1 wp eval-file $(STAGING_DIR)/scripts/seed-itzenzo-pages.php --path=$(STAGING_WP) --allow-root"

# Seed the production Pages ACF repeater (force-overwrite)
seed-itzenzo-pages-production:
	@echo "Seeding production itzenzo.tv Pages..."
	ssh $(PRODUCTION_HOST) "FORCE=1 wp eval-file $(PRODUCTION_DIR)/scripts/seed-itzenzo-pages.php --path=$(PRODUCTION_WP) --allow-root"

# Import a Nous Signal post from a PHP block markup file
nous-import:
ifndef FILE
	$(error Usage: make nous-import FILE=path/to/post.php TITLE="Post Title" EXCERPT="..." DATE="YYYY-MM-DD" TAGS="tag1,tag2")
endif
ifndef TITLE
	$(error TITLE is required)
endif
ifndef DATE
	$(error DATE is required (YYYY-MM-DD))
endif
	@bash scripts/nous-import.sh "$(FILE)" "$(TITLE)" "$(EXCERPT)" "$(DATE)" "$(TAGS)"

# Rebuild Satis package repository on server
satis-refresh:
	@echo "Rebuilding Satis repository..."
	ssh $(PRODUCTION_HOST) "/var/satis/rebuild.sh"
	@echo "Done — packages.vincentragosta.io updated"

# Add a repository to Satis (URL required, REBUILD=true by default)
REBUILD ?= true
satis-add:
ifndef URL
	$(error Usage: make satis-add URL=https://github.com/vinnyrags/repo.git)
endif
	@echo "Adding $(URL) to Satis..."
	@ssh $(PRODUCTION_HOST) "jq '.repositories += [{\"type\": \"vcs\", \"url\": \"$(URL)\"}] | .repositories |= unique_by(.url)' /var/satis/satis.json > /tmp/satis.json && mv /tmp/satis.json /var/satis/satis.json"
	@echo "Repository added."
ifeq ($(REBUILD),true)
	@$(MAKE) satis-refresh
endif

# Remove a repository from Satis and rebuild
satis-remove:
ifndef URL
	$(error Usage: make satis-remove URL=https://github.com/vinnyrags/repo.git)
endif
	@echo "Removing $(URL) from Satis..."
	@ssh $(PRODUCTION_HOST) "jq '.repositories |= map(select(.url != \"$(URL)\"))' /var/satis/satis.json > /tmp/satis.json && mv /tmp/satis.json /var/satis/satis.json"
	@echo "Repository removed."
	@$(MAKE) satis-refresh
