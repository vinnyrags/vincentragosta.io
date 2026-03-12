# Clever — Senior WordPress Developer Interview Prep

---

## 1. Please share your experience building custom WordPress plugins from scratch.

My experience with WordPress plugin architecture spans both agency-level proprietary platforms and independent framework development.

At Situation Interactive, we built and maintained a proprietary platform where reusable backend and frontend logic lived in the `mu-plugins` and `plugins` directories — shared components, base classes, and integration layers that powered 50+ client sites across the Broadway and performing arts space. Supporting plugins extended this foundation, including a Visual Composer framework adaptation that standardized how content teams built pages. Managing that ecosystem meant understanding plugin lifecycle deeply — activation hooks, update paths, and dependency chains between mu-plugins and standard plugins.

A significant part of that work involved **Composer-based dependency management for premium plugins**. Plugins like Gravity Forms and ACF Pro don't live in the public WordPress plugin repository — they require authenticated access. I set up private Composer repositories to pull licensed plugins into our build pipeline, keeping `composer.json` as the single source of truth for dependencies. This meant managing the distinction between public Packagist/WPackagist repositories and private Satispress or token-authenticated endpoints, ensuring `composer install` in CI or on a fresh environment would resolve everything without manual plugin uploads. This is a challenge any team using paid WordPress plugins at scale has to solve, and getting it right eliminates an entire class of "works on my machine" problems.

More recently, for an industrial supply e-commerce site ([theabsupplies.com](https://theabsupplies.com)), I built a custom mu-plugin that handles the entire product data layer — custom post type registration, taxonomy definitions, Fieldmanager-based admin metaboxes for product specs and SKUs, WP-CLI commands for CSV import and batch processing, and a custom REST API endpoint for product search. The plugin follows a module auto-discovery pattern where classes implementing a `ModuleInterface` are automatically registered — no manual wiring needed. The theme stays purely presentational while the plugin owns all data concerns.

In my independent practice, I've also built a reusable parent-child theme framework for my portfolio site ([vincentragosta.io](https://vincentragosta.io)) that is architecturally equivalent to a plugin ecosystem. The parent theme provides shared infrastructure — a **provider pattern** inspired by Laravel service providers, **PHP-DI for dependency injection** with autowiring, **PSR-4 autoloading**, and a suite of managers (Asset, Block, Feature, Pattern) that handle registration lifecycles. Each provider is self-contained: it owns its hooks, features, blocks, templates, assets, config, and tests. Features are toggleable via the DI container with opt-out inheritance. Hooks follow additive-only inheritance. The child theme composes site-specific functionality on top without modifying the parent — the same separation of concerns you'd see between a plugin and a theme.

This architecture directly maps to plugin development patterns:

- **Custom Gutenberg blocks** with server-side rendering, React editor UIs, and Twig templates — registered dynamically through a BlockManager that auto-discovers block directories
- **Custom post types and taxonomies** driven by JSON config, with dedicated repository classes encapsulating query logic (find, filter by taxonomy, sort by custom meta, related-post algorithms)
- **WordPress hook integration** throughout — `save_post` actions for automated meta extraction, `term_link` filters for URL rewriting, custom REST endpoints
- **430+ automated tests** across PHPUnit (unit + integration), Vitest (JavaScript), and Playwright (E2E with WCAG 2.1 AA accessibility enforcement)

The packaging differs — theme vs. plugin — but the engineering is the same: dependency injection, autoloading, hook lifecycle management, and a test suite that catches regressions before they ship.

---

## 2. Please share a link to a project you built that includes a dynamic or data-driven component.

Here are two of my most recent projects, each taking a different technical approach to the same core challenge — making large sets of structured content filterable, sortable, and fast:

### A&B Supplies — Product Catalog with Data Pipeline
**URL:** https://theabsupplies.com/products/

A full-stack WordPress e-commerce catalog for an industrial supply company, handling thousands of product SKUs with structured data, dynamic filtering, and a client-side shopping cart.

**Data pipeline:** Product data originates from an external inventory system. I built a Puppeteer-based Node.js scraper that extracts product listings (handling pagination and resume capability for large runs), outputs normalized CSV files, and feeds them into a WP-CLI import pipeline. The import command (`wp absupplies import-csv`) pre-loads existing SKUs into memory for O(1) duplicate detection, creates posts with raw meta, then a separate `wp absupplies process-products` command batch-processes them — parsing specifications into structured fields, assigning categories from content analysis, downloading and optimizing images via EWWW, and setting featured images. A retry command handles failed image downloads separately. This three-stage pipeline (scrape, import, process) means each step is independently re-runnable without data loss.

**Dynamic filtering:** The product archive features a two-column layout — a recursive category tree sidebar with live post counts (including descendant terms) and a sortable product grid. Filtering is URL-parameter driven (`?product_category=slug&orderby=title&order=ASC`), which means every filtered view is a unique, crawlable URL. The `pre_get_posts` hook builds `tax_query` arguments dynamically, including child category inheritance so selecting a parent category surfaces all descendant products. Sort controls use a fully accessible custom dropdown component I built from scratch — complete with ARIA attributes (`aria-expanded`, `aria-selected`, `aria-activedescendant`), full keyboard navigation (arrow keys, Home, End, Enter, Escape), and focus management.

**Client-side cart:** Since this is a catalog site (not transactional e-commerce), I built a lightweight localStorage-based cart system instead of WooCommerce. The `Cart` class manages add/remove/clear operations, quantity validation (1–15,000 units with input sanitization), and a slide-out cart panel. Checkout generates a `mailto:` link with a formatted product list. The cart count badge formats dynamically (999 → "999", 1,500 → "1.5K"). All cart interactions are keyboard-accessible with ARIA live regions announcing state changes.

**Search:** A custom REST API endpoint (`/wp-json/absupplies/v1/products/search`) queries across both `post_title` and `absupplies_product_sku` meta using a custom `WHERE` clause with `LIKE` pattern matching — so searching "AB-1234" finds products by SKU, not just title. On the frontend, Relevanssi handles the public search with enhanced indexing across all product fields.

**SEO considerations:** Clean permalink structure (`/products/product-name/`), breadcrumb navigation reflecting the category hierarchy, proper heading hierarchy, and every filtered archive view being a unique indexable URL rather than JavaScript-rendered content.

### vincentragosta.io — Projects Archive
**URL:** https://vincentragosta.io/projects/

My portfolio site features a composable client-side filtering system on the projects archive. Projects are rendered server-side via a custom `ProjectRepository` class (resolved through a PHP-DI container) that queries by a `project_year` custom meta field rather than WordPress publish date. Each project card carries data attributes (`data-title`, `data-date`, `data-category`) that power real-time filtering — a search input and category dropdown filter independently, each managing its own `data-*-hidden` attribute, with a shared `applyVisibility()` function deriving the final visibility state from both filters combined. Category links on individual project detail pages deep-link back to the archive with `?category=slug`, and the JavaScript reads `URLSearchParams` on init to pre-select the dropdown and apply the filter.

The CSS grid uses `nth-child(An+B of .project-card:not(.is-hidden))` to recalculate the alternating 35%/65% column layout as cards are filtered — so the visual rhythm holds regardless of which subset is visible. The sort dropdown reorders DOM nodes directly and strips `is-visible` classes so the scroll-reveal `IntersectionObserver` re-triggers entrance animations on the newly sorted cards.

**Technical decisions:** No JavaScript framework — vanilla JS with progressive enhancement. The filtering is instant because there's no network round-trip. Server-side rendering means the full content is crawlable by search engines. The project year meta is extracted automatically via a `save_post_project` hook that parses the year from block content markup, with a one-time backfill for existing posts on `admin_init`. Accessibility: single tab stop per card (image link uses `tabindex="-1"` and `aria-hidden="true"`), `aria-live="polite"` on the empty state message for screen reader announcements, and keyboard-navigable custom dropdowns.

---

## 3. How are you currently using AI tools in your day-to-day work?

AI is embedded in my daily workflow — not as a replacement for architectural thinking, but as a force multiplier for execution.

**Claude Code (CLI)** is my primary tool. I use it directly in the terminal alongside my editor for:

- **Implementation acceleration** — once I've made an architectural decision, I use Claude to generate the initial implementation, then review and refine. For example, building out a new feature with its PHP classes, WordPress hooks, Twig templates, SCSS, JavaScript interactions, and test coverage might take a full day manually. With Claude handling the boilerplate-heavy parts while I direct the architecture, I focus on design decisions and edge cases.
- **Test authoring** — I describe the behavior I want to verify and Claude generates comprehensive test suites. One of my projects has 430+ tests across PHP, JavaScript, and E2E layers. That coverage exists partly because the cost of writing tests dropped dramatically.
- **Debugging and refactoring** — when a CSS layout breaks or a WordPress hook fires in the wrong order, I can describe the symptom and get targeted diagnostic steps rather than trial-and-error.
- **Code review as conversation** — I'll ask Claude to evaluate an approach before committing to it. "Is this the right abstraction?" or "What are the trade-offs of this pattern?" This catches over-engineering early.

Lately I've been working on something new — an agent swarm, a familiar concept in the AI space today but one I've been tailoring to my own workflow. The idea is to spin up multiple Claude agents in parallel, each independently analyzing the same problem. Every agent produces its own assessment and proposed solution, then the outputs are synthesized into a single consolidated report or implementation plan. This works well for code reviews — each agent catches different things depending on how it reads the context, and the combined output is more thorough than any single pass. I use the same pattern for discrete programming tasks: multiple agents propose architecturally distinct solutions, and the synthesis step pulls the best ideas from each into one coherent plan.

**What I don't delegate:** Architecture decisions, UX judgment, client communication, and anything that requires understanding the *why* behind a business requirement. AI is excellent at the *how* once the *what* is clear.

**Project-level context:** I maintain a project-level markdown file that documents architecture, conventions, and patterns for AI tools to reference. This means every AI session starts with full project context — it understands the codebase structure, the design patterns in use, the testing strategy, and the naming conventions. The result is AI-generated code that actually fits the codebase rather than generic solutions that need heavy adaptation.

---

## 4. What excites you most about the opportunity to join Clever?

Three things stand out:

**The algorithmic content engineering problem.** My career has been about building systems where content teams operate independently — structured content models, block-based editors, convention-driven architectures that reduce the need for developer intervention on every content change. Clever's focus on algorithmic content delivery is the next evolution of that same principle: systematizing content creation at scale. The idea of building composable web components and data-driven templates that generate high-quality, SEO-performing content across real estate markets is a compelling technical challenge — it combines the structured content architecture I've built for performing arts (where every show has similar data shapes but unique content) with the data workflow automation I've done at scale (50+ market-specific sites at Broadway Across America, thousands of product SKUs at A&B Supplies).

**The intersection of development and design sensibility.** The job description specifically calls out "instinct for what looks polished and professional" — that resonates with how I work. I've spent years building platforms where visual precision matters: pixel-accurate layouts for Broadway productions, responsive design systems for cultural institutions, and interactive components where spacing, typography, and motion all serve the user experience. I care about the craft of frontend implementation, not just making it functional.

**The scrappiness balance.** "Knows when to build durable systems and when to rely on quick, high-impact solutions" — this is exactly how I operate. I've shipped a holiday microsite for Afterpay on a compressed deadline with no scope cuts alongside building a multi-year platform for Roundabout Theatre Company with a full test suite and documented architecture. Knowing which situation calls for which approach is a skill I've developed across 11 years of balancing agency deadlines with long-term maintainability. The 30/60/365-day roadmap in the description tells me Clever values both shipping quickly and building lasting systems, and that's the environment where I do my best work.

---

## 5. What are your base salary expectations for your next role?

I'm targeting **$130,000**, which reflects my 11 years of experience, the depth of my WordPress specialization, and the seniority of this role. I bring full-stack ownership, design sensibility, SEO awareness, and the ability to lead technical strategy while still shipping code daily. I'm open to discussing how the full compensation package factors in.
