# Vincent Ragosta Theme - Build System
# Usage: make [target]

PARENT_THEME_DIR := $(shell dirname $(CURDIR))/parent-theme
CHILD_THEME_DIR := $(CURDIR)

.PHONY: help install install-parent install-child build watch clean autoload

# Default target
help:
	@echo "Available targets:"
	@echo "  make install   - Install all dependencies (both themes)"
	@echo "  make build     - Build child theme assets"
	@echo "  make watch     - Start watch mode for development"
	@echo "  make clean     - Remove all generated files"
	@echo "  make autoload  - Regenerate composer autoloaders"

# Install all dependencies
install: install-parent install-child
	@echo "✓ All dependencies installed"

install-parent:
	@echo "Installing parent theme dependencies (composer only)..."
	cd $(PARENT_THEME_DIR) && composer install --no-interaction

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
	rm -rf $(CHILD_THEME_DIR)/vendor
	rm -rf $(CHILD_THEME_DIR)/node_modules
	rm -rf $(CHILD_THEME_DIR)/dist
	@echo "✓ Clean complete"

# Composer dump-autoload for both themes
autoload:
	@echo "Regenerating autoloaders..."
	cd $(PARENT_THEME_DIR) && composer dump-autoload
	cd $(CHILD_THEME_DIR) && composer dump-autoload
	@echo "✓ Autoloaders regenerated"
