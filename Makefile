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
ITZENZO_PROD_URL    := https://itzenzo.tv
ITZENZO_STAGING_URL := https://staging.itzenzo.tv

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

# Force on-demand ISR revalidation of the catalog pages on the itzenzo.tv
# frontend. /cards, /collection and the homepage are ISR-cached (300s) and only
# refresh per-request after the window OR when the per-checkout revalidate
# webhook fires — a bulk price/stock sync triggers neither, so without this the
# storefront keeps serving stale (sold-out / old-price) cards. The secret is
# read from the frontend's .env on the droplet so it never leaves the server.
#   $(call revalidate-itzenzo, ENV, ITZENZO_DIR, ITZENZO_URL)
define revalidate-itzenzo
ssh $($(1)_HOST) 'SECRET=$$(grep -E "^REVALIDATION_SECRET=" $(2)/.env.production | cut -d= -f2-); curl -s -X POST "$(3)/api/revalidate" -H "Content-Type: application/json" -d "{\"secret\":\"$$SECRET\",\"paths\":[\"/\",\"/cards\",\"/collection\",\"/livestream-shop\"]}" -w "\n"'
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
	sync-cards sync-cards-staging sync-cards-production push-cards-production remove-card update-stock \
	export-new-cards create-cards create-cards-apply \
	create-cards-staging create-cards-staging-apply \
	create-cards-production create-cards-production-apply \
	backfill-card-ids-production \
	export-card-prices update-card-prices update-card-prices-apply \
	update-card-prices-staging update-card-prices-staging-apply \
	update-card-prices-production update-card-prices-production-apply \
	release-stuck-pull-box-slots \
	migrate-card-images migrate-card-images-staging migrate-card-images-production \
	enrich-singles enrich-singles-japanese lint-singles audit-alt-art backup-singles \
	seed-itzenzo-pages seed-itzenzo-pages-force \
	seed-itzenzo-pages-staging seed-itzenzo-pages-production \
	seed-stream-schedule seed-stream-schedule-force \
	seed-stream-schedule-staging seed-stream-schedule-production \
	seed-pull-boxes seed-pull-boxes-staging seed-pull-boxes-production \
	export-inventory-production export-inventory-staging \
	build-whatnot-csv whatnot-csv-production \
	build-whatnot-auction-csv whatnot-auction-csv-production \
	build-whatnot-permanent-bin-csv whatnot-permanent-bin-csv-production \
	build-whatnot-post-stream-bin-csv whatnot-post-stream-bin-csv-production \
	build-whatnot-bin-show-csv whatnot-bin-show-csv-production \
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

# ---------------------------------------------------------------------------
# Stripe retired 2026-06-04 (Whatnot pivot). Targets marked (RETIRED — Stripe)
# push/pull the old Sheets → Stripe → WP chain and mutate live Stripe state.
# They are kept only for the documented reversal path (akivili
# business/whatnot-first-strategy.md) and refuse to run without
# CONFIRM_STRIPE=1. Current card pipeline: "Card Catalog Pipeline" in
# CLAUDE.md — update-card-prices-*, create-cards-from-sheet.php, and the
# Whatnot CSV targets, none of which touch Stripe.
define stripe-retired-guard
	@if [ "$(CONFIRM_STRIPE)" != "1" ]; then \
		echo "✋ '$@' is RETIRED — it touches Stripe (retired 2026-06-04, Whatnot pivot)."; \
		echo "   The card catalog now syncs Sheet → WP directly:"; \
		echo "     make update-card-prices-production[-apply]    price/stock"; \
		echo "     scripts/create-cards-from-sheet.php           new cards"; \
		echo "   Re-run with CONFIRM_STRIPE=1 only if you really mean to touch Stripe."; \
		exit 1; \
	fi
endef

##@ Database & uploads sync

# The pre-retirement Stripe mode-mismatch guard is gone from the push
# targets: with checkout disabled everywhere, stripe_product_id postmeta is
# just a sheet↔WP join key — pushing a DB across key modes can't break a
# checkout that no longer exists. `make check-stripe-modes` remains as a
# read-only diagnostic.
push-staging: ## Push local DB + uploads to staging
	$(call push-db-to-env,STAGING,staging)

pull-staging: ## Pull staging DB + uploads to local
	$(call pull-db-from-env,STAGING,staging)

push-production: ## Push local DB + uploads to production
	$(call push-db-to-env,PRODUCTION,production)

pull-production: ## Pull production DB + uploads to local
	$(call pull-db-from-env,PRODUCTION,production)

check-stripe-modes: ## Verify local + staging + production Stripe key modes match (non-destructive)
	@echo "Checking staging..."
	$(call check-stripe-mode-match,STAGING,staging)
	@echo "Checking production..."
	$(call check-stripe-mode-match,PRODUCTION,production)
	@echo "✓ All Stripe key modes consistent."

rebuild-local-catalog: ## (RETIRED — Stripe) Delete + repopulate LOCAL catalog from test-mode Stripe
	$(stripe-retired-guard)
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

rebuild-staging-catalog: ## (RETIRED — Stripe) Wipe + repopulate STAGING catalog Stripe IDs from test-mode Stripe
	$(stripe-retired-guard)
	@echo ">> Wiping stale Stripe IDs from staging postmeta..."
	@ssh $(STAGING_HOST) "wp --allow-root --path=$(STAGING_WP) db query \"DELETE FROM wp_postmeta WHERE meta_key IN ('stripe_product_id','stripe_price_id','sale_price_id')\"" >/dev/null
	@echo ">> Re-pulling products from test-mode Stripe → staging..."
	@$(MAKE) --no-print-directory pull-products-staging
	@echo ">> Re-pulling card singles from test-mode Stripe → staging..."
	@$(MAKE) --no-print-directory pull-cards-staging
	@echo "✓ Staging catalog rebuilt with fresh test-mode Stripe IDs."

# Pre-retirement this had two extra steps that rebuilt the local + staging
# catalogs from test-mode Stripe (live↔test product IDs are mode-specific and
# cross-mode IDs used to break checkout). Checkout is gone, so a straight
# content copy is all that's left.
refresh-from-production: ## Refresh local + staging from production content (Stripe-free)
	@echo "==================================================================="
	@echo "  refresh-from-production — propagating live prod content to test"
	@echo "==================================================================="
	@echo ""
	@echo "[1/2] Pulling production → local DDEV..."
	@$(MAKE) --no-print-directory pull-production
	@echo ""
	@echo "[2/2] Pushing local → staging..."
	@$(MAKE) --no-print-directory push-staging
	@echo ""
	@echo "==================================================================="
	@echo "  ✓ Local + staging now reflect production content."
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

##@ Stripe products (RETIRED)

pull-products: ## (RETIRED — Stripe) Sync Stripe products to local WordPress (as drafts)
	$(stripe-retired-guard)
	@echo "Syncing Stripe products to WordPress..."
	ddev wp eval-file scripts/pull-products.php

pull-products-publish: ## (RETIRED — Stripe) Sync Stripe products to local WordPress (auto-publish)
	$(stripe-retired-guard)
	@echo "Syncing Stripe products to WordPress (auto-publish)..."
	@touch scripts/.publish
	@ddev wp eval-file scripts/pull-products.php; rm -f scripts/.publish

pull-products-staging: ## (RETIRED — Stripe) Sync Stripe products to staging (clean + publish)
	$(stripe-retired-guard)
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

enrich-singles-japanese: ## Enrich Japanese cards via TCGplayer (Pokemon TCG API is English-only)
	@echo "Enriching Japanese Singles rows via TCGplayer pokemon-japan catalog..."
	cd ../Nous/scripts/shop && node enrich-singles-japanese.mjs $(ARGS)

lint-singles: ## Lint the Singles sheet for data-entry issues before pushing
	@echo "Linting Singles tab..."
	cd ../Nous/scripts/shop && node lint-singles.js $(ARGS)

audit-alt-art: ## Audit alt-art rows for misrouted Pokemon TCG API IDs
	@echo "Auditing alt-art rows in Singles tab..."
	cd ../Nous/scripts/shop && node audit-alt-art-ids.js $(ARGS)

# Pulls STRIPE_SECRET_KEY from local wp-config via DDEV so the target is
# self-sufficient — no manual env var needed.
push-cards: ## (RETIRED — Stripe) Push card singles from Google Sheets to Stripe
	$(stripe-retired-guard)
	@echo "Pushing cards from Google Sheets to Stripe..."
	@STRIPE_SECRET_KEY=$$(ddev wp eval "echo STRIPE_SECRET_KEY;" 2>/dev/null | tail -1); \
	if [ -z "$$STRIPE_SECRET_KEY" ]; then \
		echo "Error: could not read STRIPE_SECRET_KEY from DDEV. Is DDEV running?"; exit 1; \
	fi; \
	cd ../Nous/scripts/shop && STRIPE_SECRET_KEY="$$STRIPE_SECRET_KEY" node push-cards.js $(ARGS)

pull-cards: ## (RETIRED — Stripe) Sync Stripe card singles to local WordPress (as drafts)
	$(stripe-retired-guard)
	@echo "Syncing Stripe card singles to WordPress..."
	ddev wp eval-file scripts/pull-cards.php

pull-cards-publish: ## (RETIRED — Stripe) Sync Stripe card singles to local WordPress (auto-publish)
	$(stripe-retired-guard)
	@echo "Syncing Stripe card singles to WordPress (auto-publish)..."
	@touch scripts/.publish
	@ddev wp eval-file scripts/pull-cards.php; rm -f scripts/.publish

pull-cards-staging: ## (RETIRED — Stripe) Sync Stripe card singles to staging (clean + publish)
	$(stripe-retired-guard)
	@echo "Syncing Stripe card singles to staging WordPress..."
	$(call remote-wp-eval-with-flags,STAGING,pull-cards.php,.publish .clean)
	@echo "Flushing staging itzenzo.tv image proxy cache..."
	$(call flush-itzenzo-cache,STAGING,$(ITZENZO_STAGING_DIR),$(ITZENZO_STAGING_PM2))

# No --clean on production — we don't nuke existing card posts/attachments.
pull-cards-production: ## (RETIRED — Stripe) Sync Stripe card singles to production (publish, idempotent)
	$(stripe-retired-guard)
	@echo "Syncing Stripe card singles to production WordPress..."
	$(call remote-wp-eval-with-flags,PRODUCTION,pull-cards.php,.publish)
	@echo "Flushing production itzenzo.tv image proxy cache..."
	$(call flush-itzenzo-cache,PRODUCTION,$(ITZENZO_PROD_DIR),$(ITZENZO_PROD_PM2))

# Production-targeted variant of `push-cards`. Unlike `push-cards` —
# which reads STRIPE_SECRET_KEY from local DDEV's wp-config — this
# fetches the live key from production's /opt/nous-bot/.env via SSH,
# so the operator doesn't have to swap local DDEV modes just to push
# the Sheet to live Stripe. Production is ALWAYS in live mode by
# definition, so the mode-match guard isn't applicable here.
push-cards-production: ## (RETIRED — Stripe) Push Singles Sheet → live-mode Stripe
	$(stripe-retired-guard)
	@echo "Pulling live Stripe key from production droplet..."
	@LIVE_KEY=$$(ssh $(PRODUCTION_HOST) "grep '^STRIPE_SECRET_KEY=' /opt/nous-bot/.env | cut -d= -f2-") ; \
	if [ "$${LIVE_KEY:0:8}" != "sk_live_" ]; then \
		echo "Error: expected sk_live_* from production, got: $${LIVE_KEY:0:12}..."; exit 1; \
	fi ; \
	echo "  ✓ Live key acquired" ; \
	cd ../Nous/scripts/shop && STRIPE_SECRET_KEY="$$LIVE_KEY" node push-cards.js $(ARGS)

##@ Card sync (Sheets → WP, Stripe-free)

# The Stripe-free card pipeline: the Singles sheet is the source of truth and
# syncs straight into WordPress. `sync-cards*` is the one-shot operator entry
# point — it creates any brand-new sheet rows as WP card posts, then refreshes
# price/stock on everything else. The old Sheets → Stripe → WP targets of the
# same names are retired (see the guard above).
#
# Sold cards are NOT handled here: move stock-0 rows to the Sold tab
# (move-zero-stock-to-sold.mjs --apply) and `make remove-card WP_ID=…` each.

sync-cards: create-cards-apply update-card-prices-apply ## Sheet → local WP: create new cards + refresh price/stock
	@echo "✓ Card sync complete (local, Stripe-free)"

sync-cards-staging: create-cards-staging-apply update-card-prices-staging-apply ## Sheet → staging WP: create new cards + refresh price/stock
	@echo "✓ Card sync complete (staging, Stripe-free)"

sync-cards-production: create-cards-production-apply backfill-card-ids-production update-card-prices-production-apply ## Sheet → production WP: create new cards + stamp col-S IDs + refresh price/stock
	@echo "✓ Card sync complete (production, Stripe-free)"

# Stamp WP post IDs into blank Singles col S cells so every row has an exact
# join key (legacy rows keep their inert prod_… handles). Runs from PRODUCTION
# inventory only — production post IDs are canonical; staging/local IDs
# diverge and must never be written to the sheet.
backfill-card-ids-production: export-inventory-production ## Backfill Singles col S with WP post IDs (blank rows only)
	@cd ../Nous/scripts/shop && node backfill-card-postids.mjs --apply --inventory=$(INVENTORY_JSON)

##@ Card create (Sheets → WP, Stripe-free)

# Brand-new sheet rows (name + set + image present, col S Stripe ID blank) →
# WP card posts with full metadata + sideloaded featured image + taxonomy.
# Dedupe is by card_name + card_number ONLY (not set) — a re-added card that
# ever existed in WP is skipped; backfill its col S from the WP card's meta
# so update-card-prices picks it up instead. Dry-run by default.

NEW_CARDS_JSON := /tmp/new-cards.json

export-new-cards: ## Read Singles sheet → $(NEW_CARDS_JSON) (enriched rows with no Stripe ID)
	@echo "Exporting new card rows from Singles sheet → $(NEW_CARDS_JSON)..."
	@cd ../Nous/scripts/shop && node export-new-cards.mjs > $(NEW_CARDS_JSON)
	@echo "✓ Wrote $(NEW_CARDS_JSON) ($$(python3 -c 'import json,sys; print(len(json.load(open(sys.argv[1]))))' $(NEW_CARDS_JSON)) rows)"

create-cards: export-new-cards ## DRY-RUN: new sheet rows → local WP card posts
	@cp $(NEW_CARDS_JSON) scripts/.new-cards.json
	@ddev exec "NEW_CARDS_JSON=/var/www/html/scripts/.new-cards.json wp eval-file scripts/create-cards-from-sheet.php"; rm -f scripts/.new-cards.json

create-cards-apply: export-new-cards ## APPLY: new sheet rows → local WP card posts
	@cp $(NEW_CARDS_JSON) scripts/.new-cards.json
	@ddev exec "NEW_CARDS_JSON=/var/www/html/scripts/.new-cards.json APPLY=1 wp eval-file scripts/create-cards-from-sheet.php"; rm -f scripts/.new-cards.json

create-cards-staging: export-new-cards ## DRY-RUN: new sheet rows → staging WP card posts
	@scp -q $(NEW_CARDS_JSON) $(STAGING_HOST):$(NEW_CARDS_JSON)
	$(call remote-wp-eval-with-env,STAGING,create-cards-from-sheet.php,NEW_CARDS_JSON=$(NEW_CARDS_JSON))

create-cards-staging-apply: export-new-cards ## APPLY: new sheet rows → staging WP (+ revalidate itzenzo)
	@scp -q $(NEW_CARDS_JSON) $(STAGING_HOST):$(NEW_CARDS_JSON)
	$(call remote-wp-eval-with-env,STAGING,create-cards-from-sheet.php,NEW_CARDS_JSON=$(NEW_CARDS_JSON) APPLY=1)
	@echo "Revalidating staging itzenzo.tv catalog pages so new cards show..."
	$(call revalidate-itzenzo,STAGING,$(ITZENZO_STAGING_DIR),$(ITZENZO_STAGING_URL))

create-cards-production: export-new-cards ## DRY-RUN: new sheet rows → production WP card posts
	@scp -q $(NEW_CARDS_JSON) $(PRODUCTION_HOST):$(NEW_CARDS_JSON)
	$(call remote-wp-eval-with-env,PRODUCTION,create-cards-from-sheet.php,NEW_CARDS_JSON=$(NEW_CARDS_JSON))

create-cards-production-apply: export-new-cards ## APPLY: new sheet rows → production WP (+ revalidate itzenzo)
	@scp -q $(NEW_CARDS_JSON) $(PRODUCTION_HOST):$(NEW_CARDS_JSON)
	$(call remote-wp-eval-with-env,PRODUCTION,create-cards-from-sheet.php,NEW_CARDS_JSON=$(NEW_CARDS_JSON) APPLY=1)
	@echo "Revalidating production itzenzo.tv catalog pages so new cards show..."
	$(call revalidate-itzenzo,PRODUCTION,$(ITZENZO_PROD_DIR),$(ITZENZO_PROD_URL))

##@ Card price sync (Sheets → WP, Stripe-free)

# Direct Singles-sheet → WordPress price/stock refresh that bypasses Stripe
# (parked under the Whatnot pivot, so the old Sheet → Stripe → WP chain no
# longer refreshes prices). export-card-prices reads the sheet into JSON;
# update-card-prices.php joins by stripe_product_id and writes price (col D) +
# stock (col F), clears retired sale fields, and drafts red "do not sell" rows.
# Dry-run by default; the *-apply targets write AND flush the itzenzo.tv cache
# so the storefront serves the new prices. Production/staging targets need the
# PHP script deployed (git push) since they run it via the remote scripts/ dir.

CARD_PRICES_JSON := /tmp/card-prices.json

export-card-prices: ## Read Singles sheet → $(CARD_PRICES_JSON) (price col D, stock col F, red flag)
	@echo "Exporting card prices from Singles sheet → $(CARD_PRICES_JSON)..."
	@cd ../Nous/scripts/shop && node export-card-prices.mjs > $(CARD_PRICES_JSON)
	@echo "✓ Wrote $(CARD_PRICES_JSON) ($$(python3 -c 'import json,sys; print(len(json.load(open(sys.argv[1]))))' $(CARD_PRICES_JSON)) rows)"

update-card-prices: export-card-prices ## DRY-RUN: Singles prices → local WP (no writes)
	@cp $(CARD_PRICES_JSON) scripts/.card-prices.json
	@ddev exec "CARD_PRICES_JSON=/var/www/html/scripts/.card-prices.json wp eval-file scripts/update-card-prices.php"; rm -f scripts/.card-prices.json

update-card-prices-apply: export-card-prices ## APPLY: Singles prices → local WP
	@cp $(CARD_PRICES_JSON) scripts/.card-prices.json
	@ddev exec "CARD_PRICES_JSON=/var/www/html/scripts/.card-prices.json APPLY=1 wp eval-file scripts/update-card-prices.php"; rm -f scripts/.card-prices.json

update-card-prices-staging: export-card-prices ## DRY-RUN: Singles prices → staging WP
	@scp -q $(CARD_PRICES_JSON) $(STAGING_HOST):$(CARD_PRICES_JSON)
	$(call remote-wp-eval-with-env,STAGING,update-card-prices.php,CARD_PRICES_JSON=$(CARD_PRICES_JSON))

update-card-prices-staging-apply: export-card-prices ## APPLY: Singles prices → staging WP (+ revalidate itzenzo)
	@scp -q $(CARD_PRICES_JSON) $(STAGING_HOST):$(CARD_PRICES_JSON)
	$(call remote-wp-eval-with-env,STAGING,update-card-prices.php,CARD_PRICES_JSON=$(CARD_PRICES_JSON) APPLY=1)
	@echo "Revalidating staging itzenzo.tv catalog pages so new prices/stock show..."
	$(call revalidate-itzenzo,STAGING,$(ITZENZO_STAGING_DIR),$(ITZENZO_STAGING_URL))

update-card-prices-production: export-card-prices ## DRY-RUN: Singles prices → production WP
	@scp -q $(CARD_PRICES_JSON) $(PRODUCTION_HOST):$(CARD_PRICES_JSON)
	$(call remote-wp-eval-with-env,PRODUCTION,update-card-prices.php,CARD_PRICES_JSON=$(CARD_PRICES_JSON))

update-card-prices-production-apply: export-card-prices ## APPLY: Singles prices → production WP (+ revalidate itzenzo)
	@scp -q $(CARD_PRICES_JSON) $(PRODUCTION_HOST):$(CARD_PRICES_JSON)
	$(call remote-wp-eval-with-env,PRODUCTION,update-card-prices.php,CARD_PRICES_JSON=$(CARD_PRICES_JSON) APPLY=1)
	@echo "Revalidating production itzenzo.tv catalog pages so new prices/stock show..."
	$(call revalidate-itzenzo,PRODUCTION,$(ITZENZO_PROD_DIR),$(ITZENZO_PROD_URL))

# Collection-tab price sync (Stripe-free) — feeds the /collection vault display.
# Price = AP Override (col G) else Auction Price (col E). Joins by WP Post ID
# (col O) else card name+number among personal-collection cards.
COLLECTION_PRICES_JSON := /tmp/collection-prices.json

export-collection-prices: ## Read Collection tab → $(COLLECTION_PRICES_JSON) (override-else-auction price)
	@echo "Exporting collection prices from Collection tab → $(COLLECTION_PRICES_JSON)..."
	@cd ../Nous/scripts/shop && node export-collection-prices.mjs > $(COLLECTION_PRICES_JSON)
	@echo "✓ Wrote $(COLLECTION_PRICES_JSON) ($$(python3 -c 'import json,sys; print(len(json.load(open(sys.argv[1]))))' $(COLLECTION_PRICES_JSON)) rows)"

update-collection-prices: export-collection-prices ## DRY-RUN: Collection prices → local WP
	@cp $(COLLECTION_PRICES_JSON) scripts/.collection-prices.json
	@ddev exec "COLLECTION_PRICES_JSON=/var/www/html/scripts/.collection-prices.json wp eval-file scripts/update-collection-prices.php"; rm -f scripts/.collection-prices.json scripts/.col-postids.json

update-collection-prices-apply: export-collection-prices ## APPLY: Collection prices → local WP
	@cp $(COLLECTION_PRICES_JSON) scripts/.collection-prices.json
	@ddev exec "COLLECTION_PRICES_JSON=/var/www/html/scripts/.collection-prices.json APPLY=1 wp eval-file scripts/update-collection-prices.php"; rm -f scripts/.collection-prices.json scripts/.col-postids.json

update-collection-prices-production: export-collection-prices ## DRY-RUN: Collection prices → production WP
	@scp -q $(COLLECTION_PRICES_JSON) $(PRODUCTION_HOST):$(COLLECTION_PRICES_JSON)
	$(call remote-wp-eval-with-env,PRODUCTION,update-collection-prices.php,COLLECTION_PRICES_JSON=$(COLLECTION_PRICES_JSON))

update-collection-prices-production-apply: export-collection-prices ## APPLY: Collection prices → production WP (+ revalidate itzenzo)
	@scp -q $(COLLECTION_PRICES_JSON) $(PRODUCTION_HOST):$(COLLECTION_PRICES_JSON)
	$(call remote-wp-eval-with-env,PRODUCTION,update-collection-prices.php,COLLECTION_PRICES_JSON=$(COLLECTION_PRICES_JSON) APPLY=1)
	@echo "Revalidating production itzenzo.tv so collection prices show..."
	$(call revalidate-itzenzo,PRODUCTION,$(ITZENZO_PROD_DIR),$(ITZENZO_PROD_URL))

# Atomic orphan-cleanup for a removed Sheet row: archives the Stripe
# product (idempotent — already-archived returns 200) AND deletes the
# WP post in one shot. Either STRIPE_ID or WP_ID is sufficient; the
# other is auto-resolved from postmeta (key=stripe_product_id).
# Stripe-free since 2026-06-06: deletes the WP post, flushes Redis, and
# revalidates itzenzo.tv /cards. STRIPE_ID is accepted purely as a lookup
# key (stripe_product_id postmeta = sheet col S join handle).
remove-card: ## Remove a card from production (delete WP post + flush + revalidate, Stripe-free)
	@if [ -z "$(STRIPE_ID)" ] && [ -z "$(WP_ID)" ]; then \
		echo "Usage: make remove-card WP_ID=123"; \
		echo "   or: make remove-card STRIPE_ID=prod_xxx   (lookup key only — no Stripe call)"; \
		exit 1; \
	fi
	@ssh $(PRODUCTION_HOST) "STRIPE_ID='$(STRIPE_ID)' WP_ID='$(WP_ID)' WP_PATH='$(PRODUCTION_WP)' bash" < scripts/remove-card.sh

# Stock adjustment for a single card or product: WP stock_quantity +
# Next.js ISR revalidation (Stripe-free since 2026-06-06; STRIPE_ID is a
# lookup key only). Does NOT touch the Google Sheet — update that
# manually as source of truth so the next update-card-prices-*-apply
# doesn't revert this change.
update-stock: ## Set stock for a card or product (WP + Next.js revalidate, Stripe-free)
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

##@ Whatnot CSV pipeline

# Two-step flow:
#   1. `make export-inventory-production`  → dumps /tmp/inventory.json from prod
#   2. `make build-whatnot-csv`            → reads /tmp/inventory.json,
#                                            writes tmp/whatnot-full-import-<date>.csv
# `make whatnot-csv-production` runs both. The operator then deletes existing
# Whatnot listings via the Whatnot UI and uploads the resulting CSV.

INVENTORY_JSON := /tmp/inventory.json
NOUS_DIR       := $(realpath ../Nous)

export-inventory-production: ## Export production WP inventory to /tmp/inventory.json
	@echo "Exporting production inventory → $(INVENTORY_JSON)..."
	@ssh $(PRODUCTION_HOST) "wp eval-file $(PRODUCTION_DIR)/scripts/export-inventory-json.php --path=$(PRODUCTION_WP) --allow-root" > $(INVENTORY_JSON)
	@echo "✓ Wrote $(INVENTORY_JSON) ($$(wc -c < $(INVENTORY_JSON) | tr -d ' ') bytes, $$(python3 -c 'import json,sys; print(len(json.load(open(sys.argv[1]))))' $(INVENTORY_JSON)) items)"

export-inventory-staging: ## Export staging WP inventory to /tmp/inventory.json
	@echo "Exporting staging inventory → $(INVENTORY_JSON)..."
	@ssh $(STAGING_HOST) "wp eval-file $(STAGING_DIR)/scripts/export-inventory-json.php --path=$(STAGING_WP) --allow-root" > $(INVENTORY_JSON)
	@echo "✓ Wrote $(INVENTORY_JSON) ($$(wc -c < $(INVENTORY_JSON) | tr -d ' ') bytes)"

build-whatnot-csv: ## Build Whatnot bulk-import CSV from /tmp/inventory.json
	@test -f $(INVENTORY_JSON) || { echo "Missing $(INVENTORY_JSON) — run 'make export-inventory-production' first"; exit 1; }
	@test -d $(NOUS_DIR) || { echo "Nous repo not found at $(NOUS_DIR)"; exit 1; }
	@cd $(NOUS_DIR) && node scripts/shop/build-whatnot-full-import.mjs $(ARGS)

build-whatnot-auction-csv: ## Build Whatnot AUCTION CSV (Type=Auction, sorted ascending by Price, excludes permanent-BIN)
	@$(MAKE) --no-print-directory build-whatnot-csv ARGS="--auction"

build-whatnot-permanent-bin-csv: ## Build Whatnot BIN CSV with only the permanent-BIN product list (always-on shop)
	@$(MAKE) --no-print-directory build-whatnot-csv ARGS="--permanent-bin-only"

build-whatnot-post-stream-bin-csv: ## Build Whatnot BIN CSV for unsold auction items (tiered BIN markup on cards)
	@$(MAKE) --no-print-directory build-whatnot-csv ARGS="--post-stream-bin"

build-whatnot-bin-show-csv: ## Build Whatnot BIN-SHOW CSV (cards as BIN @ auction price, quick-picks stay Auction)
	@$(MAKE) --no-print-directory build-whatnot-csv ARGS="--bin-show"

whatnot-csv-production: export-inventory-production build-whatnot-csv ## One-shot: refresh from prod + build BIN CSV (everything)

whatnot-auction-csv-production: export-inventory-production build-whatnot-auction-csv ## One-shot: refresh from prod + build AUCTION CSV (for live shows)

whatnot-permanent-bin-csv-production: export-inventory-production build-whatnot-permanent-bin-csv ## One-shot: refresh from prod + build PERMANENT-BIN CSV (post-show always-on shop)

whatnot-post-stream-bin-csv-production: export-inventory-production build-whatnot-post-stream-bin-csv ## One-shot: refresh from prod + build POST-STREAM-BIN CSV (relist unsold auctions at BIN markup)

whatnot-bin-show-csv-production: export-inventory-production build-whatnot-bin-show-csv ## One-shot: refresh from prod + build BIN-SHOW CSV (BIN @ auction price + quick-pick auctions)

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
