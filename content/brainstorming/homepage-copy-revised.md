# Homepage Content — Revised

> Redundancy pass on the full homepage copy. Key changes: platform pitch consolidated into "The framework" section, "Accessibility Built In" given unique content, experience tenure claimed once in "About me."

---

## About me

Born and raised in Brooklyn, New York — I've spent the last 11 years building websites for agencies, non-profits, and clients across a range of industries. Today I run an independent practice focused on delivering high-quality WordPress solutions with the speed and precision that come from over a decade of hands-on experience.

Every project is powered by a custom framework I've built on top of Gutenberg and refined over the last six years — purpose-built to move fast without cutting corners.

---

## My approach

### Define
CLARITY, SCOPE & TECHNICAL DIRECTION

Every successful project starts with alignment. I work closely with clients to understand their goals, constraints, and the problems they're actually trying to solve — then translate that into a clear technical plan. From audits and discovery to architecture and timelines, this phase ensures the right solution is defined before any code is written.

### Design
ACCESSIBLE, USER-CENTERED INTERFACES

Good design is usable, inclusive, and intentional. I build clean interfaces that serve business goals while meeting WCAG accessibility standards — not as an afterthought, but as a baseline.

Whether I'm working within an existing design system or shaping layouts from scratch, the aim is the same: remove friction and create experiences people trust.

### Build
SCALABLE WORDPRESS ARCHITECTURE

I build fast, reliable WordPress-powered experiences — from marketing sites to complex, content-driven platforms. Every build runs on the same architectural foundation, which means less time reinventing scaffolding and more time solving the problems that are actually unique to each project.

### Develop
AI-AUGMENTED, SENIOR-LEVEL ENGINEERING

This is where strategy becomes production-ready code. I've trained my AI tools on my own architectural patterns and coding standards — the result is a tenfold increase in throughput without compromising quality. Clean, well-tested code at a pace that used to require a team. Every build is stable, extensible, and ships with confidence.

### Deliver
RELIABLE EXECUTION & LONG-TERM PARTNERSHIP

I deliver with accountability and clear communication at every step. From launch support to ongoing improvements, I operate as a long-term partner — not a contractor who disappears after handoff. Clients get reliable deployments, clean documentation, and a direct line to someone who knows their codebase inside and out.

---

## The framework

Every project is powered by a custom WordPress platform — six years of architecture decisions refined into a reusable foundation that ships faster without compromising on code quality, accessibility, or long-term maintainability.

### Modular Architecture
A parent-child theme model built on the Provider pattern — self-contained domains that own their PHP, assets, blocks, and tests. Composition over inheritance. Autowiring-first dependency injection. No monolithic base classes, no tangled dependencies.

### Modern Stack
PHP 8.4+ with strict types. Timber and Twig for templating. PHP-DI for dependency injection. esbuild and Sass for fast, parallel asset compilation. Custom Gutenberg blocks with React editor components and Twig server-side rendering.

### Three Layers of Testing
430+ PHP tests with PHPUnit and WorDBless. JavaScript unit tests with Vitest and Testing Library covering DOM interactions, keyboard navigation, and focus management. End-to-end browser automation with Playwright — including automated WCAG 2.1 AA accessibility audits via axe-core on every page.

### Accessibility Built In
WCAG 2.1 AA compliance isn't a checklist item — it's enforced at every layer. Semantic markup and accessible patterns are the default in every template and block. Automated audits run on every page as part of the test suite, and keyboard navigation and focus management are tested explicitly — not assumed.

### Convention-Driven DX
One Makefile for every workflow. PSR-4 autoloading that mirrors directory structure. Auto-discovered assets and blocks — no manifests to update. Add a Feature class, drop it in the array, and the container handles the rest. Watch mode for real-time recompilation.
