# Vincent Ragosta — Build system
# Usage: make [target]   (run `make help` for a list)

# ─── Paths ───────────────────────────────────────────────────────────────────

IX_DIR          := $(CURDIR)/wp-content/themes/ix
CHILD_THEME_DIR := $(CURDIR)/wp-content/themes/vincentragosta
MYTHUS_DIR      := $(CURDIR)/wp-content/mu-plugins/mythus
UPLOADS_DIR     := $(CURDIR)/wp-content/uploads

COMPOSER_DIRS := $(MYTHUS_DIR) $(IX_DIR) $(CHILD_THEME_DIR)
NPM_DIRS      := $(IX_DIR) $(CHILD_THEME_DIR)

# ─── Environments ────────────────────────────────────────────────────────────

LOCAL_URL := https://vincentragosta.io.ddev.site

STAGING_HOST := root@174.138.70.29
STAGING_DIR  := /var/www/staging.vincentragosta.io
STAGING_WP   := $(STAGING_DIR)/wp
STAGING_URL  := https://staging.vincentragosta.io

PRODUCTION_HOST := root@174.138.70.29
PRODUCTION_DIR  := /var/www/vincentragosta.io
PRODUCTION_WP   := $(PRODUCTION_DIR)/wp
PRODUCTION_URL  := https://vincentragosta.io

# itzenzo.tv (Next.js headless storefront) — co-located on the same droplet.
# Used by the post-pull image-cache flush. See akivili/business/itzenzo-tv.md
# → "Runbook: flush Next.js image cache" for context.
ITZENZO_PROD_DIR    := /var/www/itzenzo.tv
ITZENZO_PROD_PM2    := itzenzo-tv
ITZENZO_STAGING_DIR := /var/www/staging.itzenzo.tv
ITZENZO_STAGING_PM2 := staging-itzenzo
ITZENZO_NODE_PATH   := /root/.nvm/versions/node/v20.20.2/bin

# ─── Macros ──────────────────────────────────────────────────────────────────
# All `remote-*` macros take the env prefix in upper-case (STAGING|PRODUCTION)
# as $(1); the macros look up $($(1)_HOST) etc. for the per-env values.

# Run `wp eval-file scripts/<script>` on a remote env.
#   $(call remote-wp-eval, ENV, script)
define remote-wp-eval
ssh $($(1)_HOST) "wp eval-file $($(1)_DIR)/scripts/$(2) --path=$($(1)_WP) --allow-root"
endef

# Run a remote `wp eval-file` gated on flag files in the env's scripts/ dir.
# Flag files are touch-created before the script and removed after, even if
# the script fails (the rm runs via `;`, not `&&`).
#   $(call remote-wp-eval-with-flags, ENV, script, flag-files)
define remote-wp-eval-with-flags
ssh $($(1)_HOST) "cd $($(1)_DIR)/scripts && touch $(3) && wp eval-file $($(1)_DIR)/scripts/$(2) --path=$($(1)_WP) --allow-root; rm -f $(3)"
endef

# Run a remote `wp eval-file` with inline env vars (e.g. `FORCE=1`).
#   $(call remote-wp-eval-with-env, ENV, script, env-vars)
define remote-wp-eval-with-env
ssh $($(1)_HOST) "$(3) wp eval-file $($(1)_DIR)/scripts/$(2) --path=$($(1)_WP) --allow-root"
endef

# Flush itzenzo.tv Next.js image proxy cache + zero-downtime pm2 reload.
# Run this after any sync that mutates WP attachment bytes (cards, products),
# otherwise the proxy will keep serving stale per-`(url, w, q)` AVIF/WebP
# variants for up to 30 days. Cache regenerates lazily after the wipe.
#   $(call flush-itzenzo-cache, ENV, ITZENZO_DIR, PM2_PROCESS)
define flush-itzenzo-cache
ssh $($(1)_HOST) 'export PATH=$(ITZENZO_NODE_PATH):$$PATH; rm -rf $(2)/.next/cache/images/* && pm2 reload $(3)'
endef

# Stripe mode-mismatch guard. When local DDEV's Stripe key mode differs from
# the remote env's Stripe key mode, a DB push will replace remote's catalog
# Stripe IDs with values from a different Stripe environment, silently
# breaking every checkout (Stripe products are mode-specific — live ≠ test).
# Override with ALLOW_STRIPE_MODE_MISMATCH=1 if you really mean it.
#   $(call check-stripe-mode-match, ENV, env-display-name)
define check-stripe-mode-match
@LOCAL_MODE=$$(grep -E "define\s*\(\s*'STRIPE_SECRET_KEY'" wp-config-env.php 2>/dev/null | grep -oE "sk_(test|live)_" | head -1) ; \
REMOTE_MODE=$$(ssh $($(1)_HOST) "grep -E \"define\s*\(\s*'STRIPE_SECRET_KEY'\" $($(1)_DIR)/wp-config-env.php 2>/dev/null" | grep -oE "sk_(test|live)_" | head -1) ; \
if [ -n "$$LOCAL_MODE" ] && [ -n "$$REMOTE_MODE" ] && [ "$$LOCAL_MODE" != "$$REMOTE_MODE" ]; then \
    if [ "$(ALLOW_STRIPE_MODE_MISMATCH)" != "1" ]; then \
        echo "" ; \
        echo "================================================================" ; \
        echo "ABORT: Stripe key mode mismatch detected." ; \
        echo "  Local:  $$LOCAL_MODE" ; \
        echo "  $(2):  $$REMOTE_MODE" ; \
        echo "" ; \
        echo "Pushing this DB would replace $(2)'s Stripe product/price IDs" ; \
        echo "with values from a different Stripe environment, silently" ; \
        echo "breaking every checkout because Stripe products are mode-" ; \
        echo "specific (live ≠ test)." ; \
        echo "" ; \
        echo "If you understand and really mean to do it:" ; \
        echo "  ALLOW_STRIPE_MODE_MISMATCH=1 make push-$(2)" ; \
        echo "" ; \
        echo "More likely you want to refresh content WITHOUT touching the" ; \
        echo "catalog Stripe linkage. See:" ; \
        echo "  make rebuild-staging-catalog  (placeholder)" ; \
        echo "  akivili/business/operations.md → Post-cutover staging refresh" ; \
        echo "================================================================" ; \
        exit 1 ; \
    fi ; \
    echo "⚠ Pushing despite Stripe mode mismatch (ALLOW_STRIPE_MODE_MISMATCH=1)." ; \
fi
endef

# Push local DDEV DB + uploads to a remote env.
#   $(call push-db-to-env, ENV, env-display-name)
define push-db-to-env
@echo "Exporting local database..."
ddev export-db --gzip=false --file=/tmp/ddev-export.sql
@echo "Uploading database to $(2)..."
scp /tmp/ddev-export.sql $($(1)_HOST):/tmp/ddev-export.sql
@echo "Importing database on $(2)..."
ssh $($(1)_HOST) "wp db import /tmp/ddev-export.sql --path=$($(1)_WP) --allow-root"
@echo "Replacing URLs..."
ssh $($(1)_HOST) "wp search-replace '$(LOCAL_URL)' '$($(1)_URL)' --path=$($(1)_WP) --allow-root --precise --all-tables --quiet"
@echo "Flushing caches..."
ssh $($(1)_HOST) "wp cache flush --path=$($(1)_WP) --allow-root --quiet && wp rewrite flush --path=$($(1)_WP) --allow-root --quiet"
@echo "Syncing uploads..."
rsync -az --delete $(UPLOADS_DIR)/ $($(1)_HOST):$($(1)_DIR)/wp-content/uploads/
ssh $($(1)_HOST) "chown -R www-data:www-data $($(1)_DIR)/wp-content/uploads"
@echo "Cleaning up..."
rm -f /tmp/ddev-export.sql
ssh $($(1)_HOST) "rm -f /tmp/ddev-export.sql"
@echo "Done — $(2) synced from local"
endef

# Pull a remote env's DB + uploads to local DDEV.
#   $(call pull-db-from-env, ENV, env-display-name)
define pull-db-from-env
@echo "Exporting $(2) database..."
ssh $($(1)_HOST) "wp db export /tmp/$(2)-export.sql --path=$($(1)_WP) --allow-root"
@echo "Downloading database..."
scp $($(1)_HOST):/tmp/$(2)-export.sql /tmp/$(2)-export.sql
@echo "Importing into DDEV..."
ddev import-db --file=/tmp/$(2)-export.sql
@echo "Replacing URLs..."
ddev wp search-replace '$($(1)_URL)' '$(LOCAL_URL)' --precise --all-tables --quiet
@echo "Flushing caches..."
ddev wp cache flush --quiet
ddev wp rewrite flush --quiet
@echo "Syncing uploads..."
rsync -az --delete $($(1)_HOST):$($(1)_DIR)/wp-content/uploads/ $(UPLOADS_DIR)/
@echo "Cleaning up..."
rm -f /tmp/$(2)-export.sql
ssh $($(1)_HOST) "rm -f /tmp/$(2)-export.sql"
@echo "Done — local synced from $(2)"
endef

# ─── Phony targets ───────────────────────────────────────────────────────────

.PHONY: help \
	start stop \
	install install-root install-mythus install-ix install-child \
	build watch clean autoload test test-js update \
	deploy-staging deploy-production release \
	push-staging pull-staging push-production pull-production \
	check-stripe-modes rebuild-local-catalog rebuild-staging-catalog refresh-from-production \
	pull-patterns pull-patterns-staging \
	pull-products pull-products-publish pull-products-staging \
	push-cards pull-cards pull-cards-publish \
	pull-cards-staging pull-cards-production \
	sync-cards sync-cards-staging sync-cards-production remove-card update-stock \
	release-stuck-pull-box-slots \
	migrate-card-images migrate-card-images-staging migrate-card-images-production \
	enrich-singles lint-singles audit-alt-art backup-singles \
	seed-itzenzo-pages seed-itzenzo-pages-force \
	seed-itzenzo-pages-staging seed-itzenzo-pages-production \
	seed-stream-schedule seed-stream-schedule-force \
	seed-stream-schedule-staging seed-stream-schedule-production \
	seed-pull-boxes seed-pull-boxes-staging seed-pull-boxes-production \
	nous-import \
	satis-refresh satis-add satis-remove

.DEFAULT_GOAL := help

# Section headers in `make help` come from `##@ Section Name` comments below;
# target descriptions come from the `## description` text after each target.
help: ## Show available targets, grouped by section
	@awk 'BEGIN {FS = ":.*?## "} \
		/^##@ / {sub(/^##@ */, ""); printf "\n\033[1m%s\033[0m\n", $$0; next} \
		/^[a-zA-Z][a-zA-Z0-9_-]*:.*?## / {printf "  \033[36m%-32s\033[0m %s\n", $$1, $$2}' \
		$(MAKEFILE_LIST)

##@ Local development

start: ## Start DDEV, restore latest DB snapshot, install deps, build assets
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
	@echo "✓ Project is running at $(LOCAL_URL)"

stop: ## Snapshot database and stop DDEV
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

install: install-root install-mythus install-ix install-child ## Install all dependencies (root, Mythus, IX, child)
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

build: ## Build assets (parent theme first, then child)
	@echo "Building IX parent theme assets..."
	cd $(IX_DIR) && npm run build
	@echo "Building child theme assets..."
	cd $(CHILD_THEME_DIR) && npm run build
	@echo "✓ Build complete"

watch: ## Start watch mode for development
	@echo "Starting watch mode..."
	cd $(CHILD_THEME_DIR) && npm run start

clean: ## Remove vendor, node_modules, and dist from all packages
	@echo "Cleaning generated files..."
	rm -rf $(MYTHUS_DIR)/vendor
	@for dir in $(NPM_DIRS); do \
		rm -rf $$dir/vendor $$dir/node_modules $$dir/dist; \
	done
	@echo "✓ Clean complete"

autoload: ## Regenerate composer autoloaders for Mythus and both themes
	@echo "Regenerating autoloaders..."
	@for dir in $(COMPOSER_DIRS); do \
		(cd $$dir && composer dump-autoload) || exit $$?; \
	done
	@echo "✓ Autoloaders regenerated"

update: ## Update composer dependencies (root + Mythus + both themes)
	@echo "Updating root dependencies..."
	composer update --no-interaction
	@for dir in $(COMPOSER_DIRS); do \
		echo ""; \
		echo "Updating composer in $$dir..."; \
		(cd $$dir && composer update --no-interaction) || exit $$?; \
	done
	@echo "✓ All dependencies updated"

test: ## Run PHP and JS test suites for Mythus and both themes
	@for dir in $(COMPOSER_DIRS); do \
		echo ""; \
		echo "Running PHP tests in $$dir..."; \
		(cd $$dir && composer test) || exit $$?; \
	done
	@for dir in $(NPM_DIRS); do \
		echo ""; \
		echo "Running JS tests in $$dir..."; \
		(cd $$dir && npm run test:js) || exit $$?; \
	done
	@echo ""
	@echo "✓ All tests complete"

test-js: ## Run JS test suites only
	@for dir in $(NPM_DIRS); do \
		(cd $$dir && npm run test:js) || exit $$?; \
	done

##@ Deploy

deploy-staging: ## Deploy develop branch to staging
	@echo "Deploying develop to staging..."
	git push production develop
	@echo "✓ Staging deployed — verify at $(STAGING_URL)"

deploy-production: ## Merge develop into main and deploy to production
	@echo "Merging develop into main..."
	git checkout main
	git merge develop --ff-only
	@echo "Deploying main to production..."
	git push production main
	git checkout develop
	@echo "✓ Production deployed — verify at $(PRODUCTION_URL)"

release: ## Merge develop into main and push both branches to origin
	@echo "Merging develop into main..."
	git checkout main
	git merge develop --ff-only
	@echo "Pushing both branches to origin..."
	git push origin main
	git push origin develop
	git checkout develop
	@echo "✓ Both branches pushed to origin"

##@ Database & uploads sync

push-staging: ## Push local DB + uploads to staging (aborts on Stripe mode mismatch)
	$(call check-stripe-mode-match,STAGING,staging)
	$(call push-db-to-env,STAGING,staging)

pull-staging: ## Pull staging DB + uploads to local
	$(call pull-db-from-env,STAGING,staging)

push-production: ## Push local DB + uploads to production (aborts on Stripe mode mismatch)
	$(call check-stripe-mode-match,PRODUCTION,production)
	$(call push-db-to-env,PRODUCTION,production)

pull-production: ## Pull production DB + uploads to local
	$(call pull-db-from-env,PRODUCTION,production)

check-stripe-modes: ## Verify local + staging + production Stripe key modes match (non-destructive)
	@echo "Checking staging..."
	$(call check-stripe-mode-match,STAGING,staging)
	@echo "Checking production..."
	$(call check-stripe-mode-match,PRODUCTION,production)
	@echo "✓ All Stripe key modes consistent."

rebuild-local-catalog: ## Delete + repopulate LOCAL catalog from test-mode Stripe (post-cross-mode pull)
	@echo ">> Hard-deleting all card + product posts from local..."
	@# pull-cards/pull-products match by stripe_product_id meta. After a
	@# pull-production overwrites everything with live-mode IDs, just
	@# wiping the meta makes pull-cards CREATE duplicates instead of
	@# updating in place. Hard-delete every card + product post first
	@# so the next pull starts from a clean slate. Stdin redirected to
	@# /dev/null on each `wp post delete` so the outer `while read`
	@# loop doesn't get its stdin slurped after the first iteration.
	@for type in card product; do \
		ddev wp post list --post_type=$$type --post_status=any --field=ID 2>/dev/null \
			| /usr/bin/grep -E "^[0-9]+$$" \
			| while read -r id; do \
				ddev wp post delete "$$id" --force </dev/null >/dev/null 2>&1 || true; \
			done; \
	done
	@echo ">> Re-pulling products from test-mode Stripe → local..."
	@$(MAKE) --no-print-directory pull-products-publish
	@echo ">> Re-pulling card singles from test-mode Stripe → local..."
	@$(MAKE) --no-print-directory pull-cards-publish
	@echo "✓ Local catalog rebuilt with fresh test-mode Stripe IDs."

rebuild-staging-catalog: ## Wipe + repopulate STAGING catalog Stripe IDs from test-mode Stripe (post-cross-mode push)
	@echo ">> Wiping stale Stripe IDs from staging postmeta..."
	@ssh $(STAGING_HOST) "wp --allow-root --path=$(STAGING_WP) db query \"DELETE FROM wp_postmeta WHERE meta_key IN ('stripe_product_id','stripe_price_id','sale_price_id')\"" >/dev/null
	@echo ">> Re-pulling products from test-mode Stripe → staging..."
	@$(MAKE) --no-print-directory pull-products-staging
	@echo ">> Re-pulling card singles from test-mode Stripe → staging..."
	@$(MAKE) --no-print-directory pull-cards-staging
	@echo "✓ Staging catalog rebuilt with fresh test-mode Stripe IDs."

refresh-from-production: ## Refresh local + staging from production content (auto-rebuilds catalogs in test-mode)
	@echo "==================================================================="
	@echo "  refresh-from-production — propagating live prod content to test"
	@echo "==================================================================="
	@echo ""
	@echo "[1/4] Pulling production → local DDEV..."
	@$(MAKE) --no-print-directory pull-production
	@echo ""
	@echo "[2/4] Rebuilding LOCAL catalog (live-mode Stripe IDs → test-mode)..."
	@$(MAKE) --no-print-directory rebuild-local-catalog
	@echo ""
	@echo "[3/4] Pushing now-test-mode local → staging..."
	@$(MAKE) --no-print-directory push-staging
	@echo ""
	@echo "[4/4] Verifying staging catalog (defensive rebuild — should be a no-op)..."
	@$(MAKE) --no-print-directory rebuild-staging-catalog
	@echo ""
	@echo "==================================================================="
	@echo "  ✓ Local + staging now reflect production content."
	@echo "  ✓ Both end up with fresh test-mode Stripe IDs (checkout works)."
	@echo "==================================================================="

##@ Block patterns

pull-patterns: ## Export block patterns from production to PHP files
	@REMOTE_HOST="$(PRODUCTION_HOST)" \
	REMOTE_WP="$(PRODUCTION_WP)" \
	REMOTE_URL="$(PRODUCTION_URL)" \
	$(CHILD_THEME_DIR)/scripts/export-patterns.sh

pull-patterns-staging: ## Export block patterns from staging to PHP files
	@REMOTE_HOST="$(STAGING_HOST)" \
	REMOTE_WP="$(STAGING_WP)" \
	REMOTE_URL="$(STAGING_URL)" \
	$(CHILD_THEME_DIR)/scripts/export-patterns.sh

##@ Stripe products

pull-products: ## Sync Stripe products to local WordPress (as drafts)
	@echo "Syncing Stripe products to WordPress..."
	ddev wp eval-file scripts/pull-products.php

pull-products-publish: ## Sync Stripe products to local WordPress (auto-publish)
	@echo "Syncing Stripe products to WordPress (auto-publish)..."
	@touch scripts/.publish
	@ddev wp eval-file scripts/pull-products.php; rm -f scripts/.publish

pull-products-staging: ## Sync Stripe products to staging (clean + publish)
	@echo "Syncing Stripe products to staging WordPress..."
	$(call remote-wp-eval-with-flags,STAGING,pull-products.php,.publish .clean)
	@echo "Flushing staging itzenzo.tv image proxy cache..."
	$(call flush-itzenzo-cache,STAGING,$(ITZENZO_STAGING_DIR),$(ITZENZO_STAGING_PM2))

##@ Card singles

backup-singles: ## Duplicate the Singles tab as Singles_Backup_YYYY-MM-DD
	@echo "Backing up Singles tab..."
	cd ../Nous/scripts/shop && node backup-singles.js

enrich-singles: ## Populate set/rarity/image data via Pokemon TCG API
	@echo "Enriching Singles tab via Pokemon TCG API..."
	cd ../Nous/scripts/shop && node enrich-singles.js

lint-singles: ## Lint the Singles sheet for data-entry issues before pushing
	@echo "Linting Singles tab..."
	cd ../Nous/scripts/shop && node lint-singles.js $(ARGS)

audit-alt-art: ## Audit alt-art rows for misrouted Pokemon TCG API IDs
	@echo "Auditing alt-art rows in Singles tab..."
	cd ../Nous/scripts/shop && node audit-alt-art-ids.js $(ARGS)

# Pulls STRIPE_SECRET_KEY from local wp-config via DDEV so the target is
# self-sufficient — no manual env var needed.
push-cards: ## Push card singles from Google Sheets to Stripe
	@echo "Pushing cards from Google Sheets to Stripe..."
	@STRIPE_SECRET_KEY=$$(ddev wp eval "echo STRIPE_SECRET_KEY;" 2>/dev/null | tail -1); \
	if [ -z "$$STRIPE_SECRET_KEY" ]; then \
		echo "Error: could not read STRIPE_SECRET_KEY from DDEV. Is DDEV running?"; exit 1; \
	fi; \
	cd ../Nous/scripts/shop && STRIPE_SECRET_KEY="$$STRIPE_SECRET_KEY" node push-cards.js $(ARGS)

pull-cards: ## Sync Stripe card singles to local WordPress (as drafts)
	@echo "Syncing Stripe card singles to WordPress..."
	ddev wp eval-file scripts/pull-cards.php

pull-cards-publish: ## Sync Stripe card singles to local WordPress (auto-publish)
	@echo "Syncing Stripe card singles to WordPress (auto-publish)..."
	@touch scripts/.publish
	@ddev wp eval-file scripts/pull-cards.php; rm -f scripts/.publish

pull-cards-staging: ## Sync Stripe card singles to staging (clean + publish)
	@echo "Syncing Stripe card singles to staging WordPress..."
	$(call remote-wp-eval-with-flags,STAGING,pull-cards.php,.publish .clean)
	@echo "Flushing staging itzenzo.tv image proxy cache..."
	$(call flush-itzenzo-cache,STAGING,$(ITZENZO_STAGING_DIR),$(ITZENZO_STAGING_PM2))

# No --clean on production — we don't nuke existing card posts/attachments.
pull-cards-production: ## Sync Stripe card singles to production (publish, idempotent)
	@echo "Syncing Stripe card singles to production WordPress..."
	$(call remote-wp-eval-with-flags,PRODUCTION,pull-cards.php,.publish)
	@echo "Flushing production itzenzo.tv image proxy cache..."
	$(call flush-itzenzo-cache,PRODUCTION,$(ITZENZO_PROD_DIR),$(ITZENZO_PROD_PM2))

sync-cards: push-cards pull-cards-publish ## Full card pipeline → local WP (Sheets → Stripe → local DDEV)
	@echo "✓ Card pipeline complete (local)"

# `push-cards` reads STRIPE_SECRET_KEY from local DDEV's wp-config, so the
# Stripe push targets whatever mode local is in. If local is in test mode
# but the destination env is in live mode (or vice versa), the push and
# pull would hit different Stripe environments and the catalog would land
# with stale / missing product IDs. The mode-match guard fails fast.
sync-cards-staging: ## Full card pipeline → staging WP (Sheets → Stripe → staging)
	@echo "Pre-flight: checking local + staging Stripe key modes match..."
	$(call check-stripe-mode-match,STAGING,staging)
	@$(MAKE) --no-print-directory push-cards
	@$(MAKE) --no-print-directory pull-cards-staging
	@echo "✓ Card pipeline complete (staging)"

sync-cards-production: ## Full card pipeline → production WP (Sheets → Stripe → production)
	@echo "Pre-flight: checking local + production Stripe key modes match..."
	$(call check-stripe-mode-match,PRODUCTION,production)
	@$(MAKE) --no-print-directory push-cards
	@$(MAKE) --no-print-directory pull-cards-production
	@echo "✓ Card pipeline complete (production)"

# Atomic orphan-cleanup for a removed Sheet row: archives the Stripe
# product (idempotent — already-archived returns 200) AND deletes the
# WP post in one shot. Either STRIPE_ID or WP_ID is sufficient; the
# other is auto-resolved from postmeta (key=stripe_product_id).
# Stripe is archived first — a failed Stripe call leaves the WP post
# intact so the operator can retry instead of dealing with a half-
# cleaned state.
remove-card: ## Remove an orphan card from production (archive Stripe + delete WP post atomically)
	@if [ -z "$(STRIPE_ID)" ] && [ -z "$(WP_ID)" ]; then \
		echo "Usage: make remove-card STRIPE_ID=prod_xxx [WP_ID=123]"; \
		echo "   or: make remove-card WP_ID=123 [STRIPE_ID=prod_xxx]"; \
		echo ""; \
		echo "Either input is sufficient — the other is auto-resolved"; \
		echo "from WP postmeta on production (key: stripe_product_id)."; \
		exit 1; \
	fi
	@ssh $(PRODUCTION_HOST) "STRIPE_ID='$(STRIPE_ID)' WP_ID='$(WP_ID)' WP_PATH='$(PRODUCTION_WP)' bash" < scripts/remove-card.sh

# Atomic stock adjustment for a single card or product. Updates
# Stripe metadata.stock + WP stock_quantity + triggers Next.js ISR
# revalidation. Either STRIPE_ID or WP_ID is sufficient; the other
# is auto-resolved from postmeta. Does NOT touch the Google Sheet
# — update that manually as source of truth so the next sync-cards-*
# / sync-products doesn't revert this change.
update-stock: ## Set stock for a card or product (Stripe + WP + Next.js revalidate)
	@if [ -z "$(STOCK)" ]; then \
		echo "Usage: make update-stock STRIPE_ID=prod_xxx STOCK=N"; \
		echo "   or: make update-stock WP_ID=123 STOCK=N"; \
		echo ""; \
		echo "STOCK must be a non-negative integer."; \
		echo "Either STRIPE_ID or WP_ID is sufficient — the other is auto-resolved."; \
		echo "Does NOT touch the Google Sheet — update that manually as source of truth."; \
		exit 1; \
	fi
	@if [ -z "$(STRIPE_ID)" ] && [ -z "$(WP_ID)" ]; then \
		echo "Usage: make update-stock STRIPE_ID=prod_xxx STOCK=N"; \
		echo "   or: make update-stock WP_ID=123 STOCK=N"; \
		exit 1; \
	fi
	@ssh $(PRODUCTION_HOST) "STRIPE_ID='$(STRIPE_ID)' WP_ID='$(WP_ID)' STOCK='$(STOCK)' WP_PATH='$(PRODUCTION_WP)' bash" < scripts/update-stock.sh

# Manual safety valve for wp-pending-* slot rows that get stuck when a
# pull-box checkout's Stripe call throws after the slot claim. The
# endpoint now releases on failure automatically, so this should rarely
# need running — keep it around for the older drift period and any
# future races that escape the catch-block release.
release-stuck-pull-box-slots: ## Release any wp-pending-* pull-box slot claims (idempotent; flushes Redis)
	@ssh $(PRODUCTION_HOST) "WP_PATH='$(PRODUCTION_WP)' bash" < scripts/release-stuck-pull-box-slots.sh

##@ Card image migration

migrate-card-images: ## Regenerate local card sub-sizes as JPEG
	@echo "Migrating local card images to JPEG sub-sizes..."
	ddev wp eval-file scripts/migrate-card-images-to-jpeg.php

migrate-card-images-staging: ## Regenerate staging card sub-sizes as JPEG
	@echo "Migrating staging card images to JPEG sub-sizes..."
	$(call remote-wp-eval,STAGING,migrate-card-images-to-jpeg.php)

migrate-card-images-production: ## Regenerate production card sub-sizes as JPEG
	@echo "Migrating production card images to JPEG sub-sizes..."
	$(call remote-wp-eval,PRODUCTION,migrate-card-images-to-jpeg.php)

##@ itzenzo.tv Pages seed

seed-itzenzo-pages: ## Seed Pages ACF repeater (refuses to overwrite)
	@echo "Seeding itzenzo.tv Pages (refuses to overwrite existing data)..."
	ddev wp eval-file scripts/seed-itzenzo-pages.php

seed-itzenzo-pages-force: ## Force-overwrite local Pages repeater
	@echo "Seeding itzenzo.tv Pages (overwriting)..."
	ddev exec "FORCE=1 wp eval-file scripts/seed-itzenzo-pages.php"

seed-itzenzo-pages-staging: ## Force-overwrite staging Pages repeater
	@echo "Seeding staging itzenzo.tv Pages..."
	$(call remote-wp-eval-with-env,STAGING,seed-itzenzo-pages.php,FORCE=1)

seed-itzenzo-pages-production: ## Force-overwrite production Pages repeater
	@echo "Seeding production itzenzo.tv Pages..."
	$(call remote-wp-eval-with-env,PRODUCTION,seed-itzenzo-pages.php,FORCE=1)

seed-stream-schedule: ## Seed Stream Schedule ACF repeater (refuses to overwrite)
	@echo "Seeding itzenzo.tv stream schedule (refuses to overwrite existing data)..."
	ddev wp eval-file scripts/seed-stream-schedule.php

seed-stream-schedule-force: ## Force-overwrite local Stream Schedule repeater
	@echo "Seeding itzenzo.tv stream schedule (overwriting)..."
	ddev exec "FORCE=1 wp eval-file scripts/seed-stream-schedule.php"

seed-stream-schedule-staging: ## Force-overwrite staging Stream Schedule repeater
	@echo "Seeding staging itzenzo.tv stream schedule..."
	$(call remote-wp-eval-with-env,STAGING,seed-stream-schedule.php,FORCE=1)

seed-stream-schedule-production: ## Force-overwrite production Stream Schedule repeater
	@echo "Seeding production itzenzo.tv stream schedule..."
	$(call remote-wp-eval-with-env,PRODUCTION,seed-stream-schedule.php,FORCE=1)

seed-pull-boxes: ## Create/find Pull Box Entry Stripe product + V/VMAX prices, write IDs to local WP
	@echo "Seeding Pull Box Entry product + prices on Stripe..."
	ddev wp eval-file scripts/seed-pull-boxes.php

seed-pull-boxes-staging: ## Same on staging WordPress
	@echo "Seeding Pull Box Entry on staging..."
	$(call remote-wp-eval,STAGING,seed-pull-boxes.php)

seed-pull-boxes-production: ## Same on production WordPress
	@echo "Seeding Pull Box Entry on production..."
	$(call remote-wp-eval,PRODUCTION,seed-pull-boxes.php)

##@ Nous import

nous-import: ## Import a Nous Signal post (FILE=, TITLE=, EXCERPT=, DATE=, TAGS=)
	$(if $(FILE),,$(error Usage: make nous-import FILE=path/to/post.php TITLE="Post Title" EXCERPT="..." DATE="YYYY-MM-DD" TAGS="tag1,tag2"))
	$(if $(TITLE),,$(error TITLE is required))
	$(if $(DATE),,$(error DATE is required (YYYY-MM-DD)))
	@bash scripts/nous-import.sh "$(FILE)" "$(TITLE)" "$(EXCERPT)" "$(DATE)" "$(TAGS)"

##@ Satis package repository

REBUILD ?= true

satis-refresh: ## Rebuild Satis package repository on server
	@echo "Rebuilding Satis repository..."
	ssh $(PRODUCTION_HOST) "/var/satis/rebuild.sh"
	@echo "Done — packages.vincentragosta.io updated"

satis-add: ## Add a repository to Satis (URL=..., REBUILD=true|false)
	$(if $(URL),,$(error Usage: make satis-add URL=https://github.com/vinnyrags/repo.git))
	@echo "Adding $(URL) to Satis..."
	@ssh $(PRODUCTION_HOST) "jq '.repositories += [{\"type\": \"vcs\", \"url\": \"$(URL)\"}] | .repositories |= unique_by(.url)' /var/satis/satis.json > /tmp/satis.json && mv /tmp/satis.json /var/satis/satis.json"
	@echo "Repository added."
ifeq ($(REBUILD),true)
	@$(MAKE) satis-refresh
endif

satis-remove: ## Remove a repository from Satis and rebuild
	$(if $(URL),,$(error Usage: make satis-remove URL=https://github.com/vinnyrags/repo.git))
	@echo "Removing $(URL) from Satis..."
	@ssh $(PRODUCTION_HOST) "jq '.repositories |= map(select(.url != \"$(URL)\"))' /var/satis/satis.json > /tmp/satis.json && mv /tmp/satis.json /var/satis/satis.json"
	@echo "Repository removed."
	@$(MAKE) satis-refresh
