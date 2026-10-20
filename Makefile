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

# SSH alias for the droplet, defined in ~/.ssh/config (HostName + User root) so this
# public repo carries no address. Override per run: make pull-production DROPLET=other
DROPLET ?= vinrag

# Staging runs deploy-kit LAYOUT=releases (2026-10-16): the docroot is the `current`
# symlink of /srv/staging.vincentragosta.io, code is read-only, and uploads live in shared/.
STAGING_HOST    := $(DROPLET)
STAGING_DIR     := /srv/staging.vincentragosta.io/current
STAGING_WP      := $(STAGING_DIR)/wp
STAGING_UPLOADS := /srv/staging.vincentragosta.io/shared/wp-content/uploads
STAGING_URL     := https://staging.vincentragosta.io

PRODUCTION_HOST := $(DROPLET)
PRODUCTION_DIR  := /var/www/vincentragosta.io
PRODUCTION_WP   := $(PRODUCTION_DIR)/wp
PRODUCTION_UPLOADS := $(PRODUCTION_DIR)/wp-content/uploads
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
rsync -az --delete $(UPLOADS_DIR)/ $($(1)_HOST):$($(1)_UPLOADS)/
ssh $($(1)_HOST) "chown -R www-data:www-data $($(1)_UPLOADS)"
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
rsync -az --delete $($(1)_HOST):$($(1)_UPLOADS)/ $(UPLOADS_DIR)/
@echo "Cleaning up..."
rm -f /tmp/$(2)-export.sql
ssh $($(1)_HOST) "rm -f /tmp/$(2)-export.sql"
@echo "Done — local synced from $(2)"
endef

# ─── Phony targets ───────────────────────────────────────────────────────────

.PHONY: help \
	start \
	stop \
	install \
	install-root \
	install-mythus \
	install-ix \
	install-child \
	build \
	watch \
	clean \
	autoload \
	test \
	test-js \
	update \
	deploy-staging \
	deploy-production \
	rollback-staging \
	release \
	push-staging \
	pull-staging \
	push-production \
	pull-production \
	restore-latest \
	refresh-from-production \
	pull-patterns \
	pull-patterns-staging \
	seed-itzenzo-pages \
	seed-itzenzo-pages-force \
	seed-itzenzo-pages-staging \
	seed-itzenzo-pages-production \
	seed-stream-schedule \
	seed-stream-schedule-force \
	seed-stream-schedule-staging \
	seed-stream-schedule-production \
	nous-import \
	nous-import-production \
	nous-post \
	satis-refresh \
	satis-add \
	satis-remove

.DEFAULT_GOAL := help

# Section headers in `make help` come from `##@ Section Name` comments below;
# target descriptions come from the `## description` text after each target.
help: ## Show available targets, grouped by section
	@awk 'BEGIN {FS = ":.*?## "} \
		/^##@ / {sub(/^##@ */, ""); printf "\n\033[1m%s\033[0m\n", $$0; next} \
		/^[a-zA-Z][a-zA-Z0-9_-]*:.*?## / {printf "  \033[36m%-32s\033[0m %s\n", $$1, $$2}' \
		$(MAKEFILE_LIST)

##@ Setup

setup: ## First-run: DDEV config (if needed), start, then pull production content
	@test -f auth.json || { echo "✗ auth.json missing (needed for ACF Pro)"; exit 1; }
	@test -d .ddev || ddev config --project-type=wordpress --docroot="" --project-name=vincentragosta.io
	@$(MAKE) start
	@$(MAKE) pull-production
	@echo "✓ Setup complete — $(LOCAL_URL)"

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

# Deploys go through GitHub. A push to develop/main runs .github/workflows/deploy.yml:
# tests, then deploy-kit builds on the droplet. Every run, with its full log, is in
# the repo's Actions tab, and these targets follow it in the terminal.
# The -direct targets push straight to the droplet — break-glass for when GitHub or
# Actions is down. Push origin afterwards so GitHub is never behind production.

# Follow the Deploy run for a commit on a branch (it takes a few seconds to appear).
# $(1) = commit-ish, $(2) = branch the run is for (develop → staging, main → production).
define watch_deploy
	@sha=$$(git rev-parse $(1)); id=; \
	for i in $$(seq 1 20); do \
		id=$$(gh run list --workflow deploy.yml --branch $(2) --commit $$sha --limit 1 --json databaseId -q '.[0].databaseId'); \
		[ -n "$$id" ] && break; sleep 3; \
	done; \
	[ -n "$$id" ] || { echo "No Deploy run found for $$sha — check the Actions tab."; exit 1; }; \
	gh run watch $$id --exit-status --interval 10
endef

deploy-staging: ## Deploy develop to staging (push to GitHub → Actions)
	git push origin develop
	$(call watch_deploy,develop,develop)
	@echo "✓ Staging deployed — verify at $(STAGING_URL)"

deploy-production: ## Deploy develop to production (fast-forward GitHub main → Actions)
	@git fetch -q origin
	@[ "$$(git rev-parse develop)" = "$$(git rev-parse origin/develop)" ] || \
		{ echo "develop isn't on GitHub yet — run make deploy-staging first."; exit 1; }
	git push origin develop:main
	@git fetch -q origin; [ "$$(git branch --show-current)" = main ] || git branch -f main origin/main
	$(call watch_deploy,develop,main)
	@echo "✓ Production deployed — verify at $(PRODUCTION_URL)"

rollback-staging: ## Point staging back at its previous release (code only — not the DB)
	ssh $(STAGING_HOST) "/opt/deploy-kit/bin/rollback.sh /etc/deploy-kit/vincentragosta.conf develop"

deploy-staging-direct: ## Break-glass: push develop straight to the droplet (GitHub down)
	git push production develop
	@echo "⚠ Deployed outside GitHub — run 'git push origin develop' once GitHub is back."

deploy-production-direct: ## Break-glass: push develop straight to production (GitHub down)
	git push production develop:main
	@echo "⚠ Deployed outside GitHub — run 'git push origin develop:main' once GitHub is back."

##@ Database & uploads sync

# `stripe_product_id` postmeta survives as one of the sheet↔WP join-key
# handles (see "Card Catalog Pipeline" in CLAUDE.md). It is an inert
# identifier — nothing reads it back to Stripe, and no Stripe I/O remains
# in this repo.
push-staging: ## Push local DB + uploads to staging
	$(call push-db-to-env,STAGING,staging)

pull-staging: ## Pull staging DB + uploads to local
	$(call pull-db-from-env,STAGING,staging)

push-production: ## Push local DB + uploads to production
	$(call push-db-to-env,PRODUCTION,production)

pull-production: ## Pull production DB + uploads to local
	$(call pull-db-from-env,PRODUCTION,production)

# Restore drill: rebuild local DDEV from the latest OFF-SITE backup (restic → B2),
# never touching the droplet. This is how the backups are proven, not assumed —
# run it after setup and quarterly (RUNBOOK → Backups). Credentials live outside
# the repo in $(RESTIC_ENV); local DB is snapshotted first.
#
# B2's free tier allows 1 GB of downloads a day and uploads are ~1.3 GB, so the
# routine drill restores the DB plus ONE uploads subfolder:
#   make restore-latest UPLOADS_SUBDIR=2026/10
# A full restore (no UPLOADS_SUBDIR) needs B2's download cap raised for the day
# (Caps & Alerts; about a cent per GB).
RESTIC_ENV  ?= $(HOME)/.config/restic/vincentragosta.env
RESTIC_SITE := vincentragosta
RESTIC      := set -a && . $(RESTIC_ENV) && set +a && restic

restore-latest: ## Restore drill: latest off-site backup into local DDEV
	@test -r $(RESTIC_ENV) || { echo "Missing $(RESTIC_ENV) — see RUNBOOK → Backups"; exit 1; }
	@command -v restic >/dev/null || { echo "restic not installed — brew install restic"; exit 1; }
	ddev snapshot --name pre-restore-$$(date +%Y%m%d-%H%M%S)
	@echo "Restoring latest database snapshot..."
	$(RESTIC) dump latest --host $(RESTIC_SITE) --tag db /$(RESTIC_SITE)-db.sql > /tmp/restore-db.sql
	ddev import-db --file=/tmp/restore-db.sql
	ddev wp search-replace '$(PRODUCTION_URL)' '$(LOCAL_URL)' --precise --all-tables --quiet
	ddev wp cache flush --quiet
	ddev wp rewrite flush --quiet
	@echo "Restoring latest uploads snapshot$(if $(UPLOADS_SUBDIR), (only $(UPLOADS_SUBDIR)),)..."
	rm -rf /tmp/restore-uploads
	$(RESTIC) restore latest --host $(RESTIC_SITE) --tag uploads --target /tmp/restore-uploads \
		$(if $(UPLOADS_SUBDIR),--include '*/uploads/$(UPLOADS_SUBDIR)',)
	src="$$(find /tmp/restore-uploads -type d -name uploads -prune | head -1)"; \
	test -n "$$src" -a -d "$$src" || { echo "No uploads dir in the restored snapshot"; exit 1; }; \
	if [ -n "$(UPLOADS_SUBDIR)" ]; then \
		test -d "$$src/$(UPLOADS_SUBDIR)" || { echo "$(UPLOADS_SUBDIR) not in the snapshot"; exit 1; }; \
		mkdir -p "$(UPLOADS_DIR)/$(UPLOADS_SUBDIR)"; \
		rsync -a --delete "$$src/$(UPLOADS_SUBDIR)/" "$(UPLOADS_DIR)/$(UPLOADS_SUBDIR)/"; \
	else \
		rsync -a --delete "$$src/" $(UPLOADS_DIR)/; \
	fi
	rm -rf /tmp/restore-db.sql /tmp/restore-uploads
	@echo "Done — local restored from the latest off-site backup. Check $(LOCAL_URL)"

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

##@ Nous import

nous-import: ## Import a Nous Signal post (FILE=, TITLE=, EXCERPT=, DATE=, TAGS=)
	$(if $(FILE),,$(error Usage: make nous-import FILE=path/to/post.php TITLE="Post Title" EXCERPT="..." DATE="YYYY-MM-DD" TAGS="tag1,tag2"))
	$(if $(TITLE),,$(error TITLE is required))
	$(if $(DATE),,$(error DATE is required (YYYY-MM-DD)))
	@bash scripts/nous-import.sh "$(FILE)" "$(TITLE)" "$(EXCERPT)" "$(DATE)" "$(TAGS)"

nous-import-production: ## Publish a Nous Signal post LIVE to production (FILE=, TITLE=, EXCERPT=, DATE=, TAGS=) — additive, non-destructive
	$(if $(FILE),,$(error Usage: make nous-import-production FILE=path/to/post.php TITLE="Post Title" EXCERPT="..." DATE="YYYY-MM-DD" TAGS="tag1,tag2"))
	$(if $(TITLE),,$(error TITLE is required))
	$(if $(DATE),,$(error DATE is required (YYYY-MM-DD)))
	@PROD_HOST='$(PRODUCTION_HOST)' PROD_WP='$(PRODUCTION_WP)' bash scripts/nous-import-prod.sh "$(FILE)" "$(TITLE)" "$(EXCERPT)" "$(DATE)" "$(TAGS)"

nous-post: ## Auto-generate + publish Nous Signal post(s): DATE=YYYY-MM-DD (one day) or FROM= TO= (range). DRY_RUN=1 to preview only.
	$(if $(or $(DATE),$(FROM)),,$(error Usage: make nous-post DATE=YYYY-MM-DD   |   make nous-post FROM=YYYY-MM-DD TO=YYYY-MM-DD   (DRY_RUN=1 to preview)))
	@bash scripts/nous-post.sh "$(or $(DATE),$(FROM))" "$(or $(TO),$(DATE),$(FROM))"

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
