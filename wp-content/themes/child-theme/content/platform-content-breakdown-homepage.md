# Platform — Homepage Section Content

> Reference copy for the condensed platform section on the homepage. This section introduces the platform and links to the full `/platform` page for the deep dive.

---

## Section Headline
The framework behind the work.

## Section Subheadline
Every project is powered by a custom WordPress platform — six years of architecture decisions refined into a reusable foundation that ships faster without compromising on code quality, accessibility, or long-term maintainability.

---

## Pillar 1: Architecture

### Label
Modular Architecture

### Body
A parent-child theme model built on the Provider pattern — self-contained domains that own their PHP, assets, blocks, and tests. Composition over inheritance. Autowiring-first dependency injection. No monolithic base classes, no tangled dependencies.

---

## Pillar 2: Tech Stack

### Label
Modern Stack

### Body
PHP 8.4+ with strict types. Timber and Twig for templating. PHP-DI for dependency injection. esbuild and Sass for fast, parallel asset compilation. Custom Gutenberg blocks with React editor components and Twig server-side rendering.

---

## Pillar 3: Testing

### Label
Three Layers of Testing

### Body
430+ PHP tests with PHPUnit and WorDBless. JavaScript unit tests with Vitest and Testing Library covering DOM interactions, keyboard navigation, and focus management. End-to-end browser automation with Playwright — including automated WCAG 2.1 AA accessibility audits via axe-core on every page.

---

## Pillar 4: Accessibility

### Label
Accessibility Built In

### Body
WCAG utility mixins for screen readers, high contrast, and reduced motion. Semantic HTML with proper landmark elements and ARIA attributes. Automated axe-core audits that fail the build on any violation. Accessibility isn't a cleanup pass — it's enforced at every layer.

---

## Pillar 5: Developer Experience

### Label
Convention-Driven DX

### Body
One Makefile for every workflow. PSR-4 autoloading that mirrors directory structure. Auto-discovered assets and blocks — no manifests to update. Add a Feature class, drop it in the array, and the container handles the rest. Watch mode for real-time recompilation.

---

## CTA
Explore the platform (links to `/platform`)
