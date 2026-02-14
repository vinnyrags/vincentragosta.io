# Vincent Ragosta Theme - Build System
# Usage: make [target]

PARENT_THEME_DIR := $(CURDIR)/wp-content/themes/parent-theme
CHILD_THEME_DIR := $(CURDIR)/wp-content/themes/child-theme

.PHONY: help start stop install install-parent install-child build watch clean autoload test update

# Default target
help:
	@echo "Available targets:"
	@echo "  make start     - Start DDEV, restore DB snapshot, install deps, and build"
	@echo "  make stop      - Snapshot database and stop DDEV"
	@echo "  make install   - Install all dependencies (both themes)"
	@echo "  make build     - Build child theme assets"
	@echo "  make test      - Run test suite (both themes)"
	@echo "  make watch     - Start watch mode for development"
	@echo "  make clean     - Remove all generated files"
	@echo "  make update    - Update composer dependencies (root + both themes)"
	@echo "  make autoload  - Regenerate composer autoloaders"

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
	ddev snapshot --name=pre-stop
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
	@echo "✓ All tests complete"
