# vincentragosta.io — 2026 Redesign

Living doc for the 2026 redesign. One doc for the whole initiative — fold sub-plans in here rather than
starting new files.

**Status (2026-10-14):** Phases 0–5b done — every page signed off and on staging (Meet me, Contact, Privacy, The Framework, Nous Signal, Nous post, 404). Remaining: Phase 6. **New agent? Start at [Handoff](#handoff--continuing-the-build).**

## Reference

- **Mockups (PNG, not in git):** `~/Downloads/vincentragosta-redesign-2026.png` (homepage),
  `vincentragosta-redesign-2026-projects-archive.png`, `vincentragosta-redesign-2026-project-detail.png`,
  and (Phase 5b) `-meet-me`, `-framework`, `-contact`, `-nous-signal`, `-nous-post`, `-privacy-policy`, `-404`.
- **Mockup source (HTML/CSS):** [`redesign-2026/mockups/`](redesign-2026/mockups/) — open in a browser;
  images load from production uploads. Use as the pixel reference for spacing, type, and color.

## Decisions

| Decision | Choice |
|---|---|
| Fonts | Inter Tight (sans), Instrument Serif (serif italic accents), JetBrains Mono (mono) — self-hosted woff2. Replaces Roobert, Reckless Neue, Fira Code. |
| Light mode | Dropped. Dark only. |
| Rollout | Big-bang: build on `develop`, preview on staging, cut over once. |
| Scope of code | Child theme only. IX stays pinned (v1.7.2) — it is shared with the ARTHOUSE client sites. |
| Project detail | **ACF only, no block editor** (decided 2026-10-13). All 68 projects share one structure — intro + Background / Implementation / Results — so the page renders entirely from fields + `single-project.twig`. Reverses the 2026-03 ACF→blocks move (`b23ee3e`) for projects only; pages stay CMS-first. |
| Content delivery | Scripted cutover (`scripts/redesign/`). **Never `make push-production`** — the same DB holds itzenzo.tv's products and unified queue. |

## Branching during the build

- `develop` is the redesign integration branch; staging previews it. `main`/production keeps the
  current design until launch.
- **Hotfixes** (e.g. Shop provider / itzenzo backend): branch off `main` → merge to `main` →
  `git push production main` (verify the `old..new main -> main` line) → merge `main` back into `develop`.
  `make deploy-production` will not work while `develop` has diverged — that is expected.

## Phase 0 findings

**Staging refreshed from production 2026-10-13** (`make refresh-from-production`): 68 projects,
20 primary-menu items, latest content 2026-08-17 — identical to production. The mega-menu came
across intact (production has it), so `setup-mega-menu.sh` did not need re-running.

Preset classes used in existing content (local DB, content current through 2026-08-13; re-check on
production during the cutover rehearsal):

- **Font-family classes:** none — Roobert / Reckless Neue / Fira Code slugs can be removed outright.
- **Color classes:** only `has-nous-red-color` (2) — keep the `nous-red` slug.
- **Font sizes:** `30` (449), `10` (272), `70` (174), `40` (26), `60` (1) — keep slugs 10–70, re-value.
- **Style variants:** `is-style-muted` (1638), `subheading` (18), `pills` (12), `animated` (8),
  `numbered-list` (2), `nous-accent` (2), `dark` (2), `focal-top` (53, IX) — all must keep working.

## Phase 1 notes

- **Dim grey re-valued for WCAG AA.** The mockup's `#5d6066` is 3.16:1 on the page background;
  `grey-5` is now `#7d8086` (≥ 4.6:1 on every surface) and `grey-3` (muted) `#9a9ca3`.
- **Header and footer are IX content partials** (Appearance → Partials), fully block-authored like
  celebrityautobiography. `views/header.twig` / `footer.twig` are thin wrappers; ContentPartial is
  no longer opted out. Re-enabled core blocks: navigation(-link/-submenu), site-title, search.
  - **Header partial:** site title (CSS draws the V mark) + version chip, `core/navigation`
    (`wp_navigation` "Primary Navigation"; core overlay below 992px), `core/search` (⌘K focuses it),
    CTA button.
  - **Footer partial:** CTA panel (Panel style), link columns, outline wordmark (html block,
    `aria-hidden`), bar (copyright, `core/social-links`, colophon). Static © year — update in the CMS
    each January.
  - **Footer — Compact:** same minus the CTA panel; `/contact/` uses it via the page's
    Header & Footer override (its form already does the CTA's job).
  - **Seeded by** `scripts/redesign/seed-content-partials.php` from `scripts/redesign/content/partials/*.html`
    (idempotent; FORCE=1 overwrites, DRY_RUN=1 previews). Safe to run before the code deploy.
  - **Dropped:** the classic-menu mega-menu overlay and its dynamic latest-projects/posts feeds, and
    the custom expanding search. Classic `primary`/`footer` menu locations stay registered but render
    nowhere; `scripts/setup-mega-menu.sh` and the RUNBOOK's mega-menu re-apply step become obsolete
    at launch. Site Settings `footer_description`, `contact_page`, `social_icons` no longer feed the
    chrome (content now lives in the partials).
- **Fonts:** Inter Tight 400/500/600, Instrument Serif 400 + italic, JetBrains Mono 400/500 —
  latin-subset woff2 from Fontsource (OFL, licenses alongside), ~150 KB total; Inter Tight 400/500
  preloaded. Google Fonts removed.
- **E2E accessibility audits run with reduced motion** so axe checks the settled render; the
  pre-redesign suite already flaked on mid-animation contrast.

- **Block asset cache-busting.** `block.json` assets (e.g. `projects-view.js`) were versioned with the
  WordPress release (`?ver=6.9.7`), so Cloudflare kept serving the pre-redesign script on staging
  after deploy. `DistAssetVersioning` rewrites `ver` to filemtime for theme `dist/` assets still on
  the core version. Same bug exists fleet-wide (IX-level fix pending).

## Phases

- [x] **0 — Setup:** this doc, mockup source, preset-class audit.
- [x] **1 — Foundation:** tokens (palette re-valued under existing slugs, new font families + display
  sizes), remove light mode, self-host fonts, style vocabulary (`eyebrow`, `chips`, `panel`,
  `stat-strip`, `terminal`, serif-italic accent format), new header + footer.
- [x] **2 — Project data model:** ACF group "Project" (`group_project_fields`) in three tabs —
  Overview (`project_lead`, `project_lead_note`), Sections (`project_sections` repeater: label,
  heading, body, note; new projects pre-filled with Background / Implementation / Results), Details
  (`project_client`, `project_role`, `project_year`, `project_live_url`, `project_technologies`,
  `project_stack_summary`, `project_case_study`, `project_status_label`). Block editor removed
  from the post type (`default-content.html` + `project-details` patterns deleted).
  `ProjectYearExtractor` retired. Relevanssi indexes the project text fields
  (`ProjectSearchIndex`). `scripts/redesign/migrate-project-fields.php` parses all 68 projects via
  `parse_blocks` (dry-run CSV; all 68 parse cleanly). Fields are `project_`-prefixed because four
  projects carry stale `client`/`role`/`year`/`technologies`/`external_url` meta from a deleted
  group — content wins where they disagree.
  **Wicked split into two projects** (`split-wicked.php`): the existing post/URL becomes
  *Wicked The Musical (2025)* (live site, 2025 key art) and a new *Wicked The Musical (2018)*
  (`/projects/wicked-the-musical-2018/`, classic key art, no live URL). Copy is the original
  write-up divided by era — review the seams. 69 projects total.
- [x] **3 — Projects archive:** the projects block in "all" mode renders the archive from
  `ProjectArchive` (stats: projects / case studies / distinct clients / earliest year; category
  pills for categories with ≥ 2 projects; case studies; year groups). Sticky toolbar: pills,
  search, Index/Grid toggle; index collapses after 20 rows ("Show N more"); `?category=` and
  `?view=` deep-link. Featured cards hide while filtering. "latest"/"curated" modes (homepage)
  keep a card grid. Page hero (eyebrow, H1, intro) is page content shipped as
  `scripts/redesign/content/pages/projects.html`, applied by `apply-page-content.php` (backs up
  current content first). Sort dropdown dropped — the index is newest-first by design.
- [x] **4 — Project detail:** `single-project.twig` renders only from fields — breadcrumb, split
  title (`ProjectPost::titleParts()`: last word in serif italic; a trailing "(YYYY)" becomes a
  suffix), live pill, category chips, lead + note, meta strip (client / role / year / stack / live
  site), browser-frame hero, sticky "In this case study" contents card (`project-toc.js`: active
  section + progress; replaces the section-navigator pills on detail pages), sections with
  eyebrow + heading + body + note callout, next project (index order, wraps), up to three related
  projects with images. JSON-LD gains `sourceOrganization` + `keywords`. Dropped from the mockup:
  per-project diagram / terminal / results cards (fields-only decision).
- [x] **5 — Homepage + remaining pages:** homepage rebuilt per the mockup from core blocks +
  style variants (`status`, `stat-strip`, `pipeline`, `layers`, `index`, `testimonials`,
  `terminal`, `panel`) using the existing copy (about, five approach stages, framework intro,
  Nous Signal, five testimonials, FAQ accordion — lifted verbatim). "Selected projects" uses a new
  projects-block mode, **Case studies** (`featured`), sharing the archive's featured layout.
  Meet me / The Framework / Contact / Privacy get the new hero (eyebrow + H1 with a serif-accent
  word + their existing intro); these pages had no H1 before. Nous Signal keeps its own hero.
  Privacy policy: "Google Fonts" section → "Self-hosted fonts", light/dark cookie example
  replaced, "Last updated" → 2026-10-13 (**review the wording**). Display font sizes 80/90/100
  get smaller fluid minimums so H1s fit on phones.
  **Content freeze:** `scripts/redesign/content/pages/*.html` were generated from the
  production copy of 2026-10-13. Any production edit to home, projects, meet-me, framework,
  contact, or privacy-policy before launch must be folded into these files, or it is lost at
  cutover (backups are kept, but the edit is overwritten).
- [ ] **5b — Remaining pages to the new mockups** (one page at a time; local review → sign-off →
  commit → staging). New CMS vocabulary: table `spec`, group `window` / `timeline`, column
  `sticky`, list `rows`, image `paper`, panel `is-accent`, restyled `core/pullquote`, core grid
  layout for card grids. `CoverBlockStyles` → `MediaBlockStyles` (adds image `paper`).
  - [x] Meet me — signed off 2026-10-14 (AI section reworked to an "accelerates / hands-on" contrast and the toolbox to core tiles + a full-stack Spec table, per `mockups/meet-me-v2.html`). Adds list `arrows` / `checks`; Pullquote citation renders as a trailing caption; panel `is-accent` no longer colors headings (use the Accent format). Résumé is the October 2026 v2 PDF; the preview
    image covers the contact line. Instagram section restored (`[instagram-feed feed=1]`, 4 local-image
    tiles via `update-instagram-feed.php`); works on staging, production still needs the reconnect
    (cutover 4c).
  - [x] Contact — signed off, on staging. Floating labels opted out (static mono labels,
    bordered fields, margin-top spacing that neutralizes IX's field margin-bottom). Form copy
    (placeholders, "Send message") is WPForms data → `update-contact-form.php`.
  - [x] Privacy — signed off, on staging. New **Contents** block (`vincentragosta/toc`):
    lists every H2 with an HTML anchor, labeled by the eyebrow above it; shared `.toc` component +
    `toc.js` (the project detail's card now uses it too). New group style `section` (ruled
    chapters). Compact footer via `seed-content-partials.php` (now also sets Privacy). Copy: adds an
    "Instagram feed" paragraph; "Last updated" → October 14, 2026. **Open:** Cloudflare Web
    Analytics is injected on prod + staging — disable it or name it in the policy.
  - **Design-system sweep (2026-10-14):** small mono text now comes from two theme.json font-size
    presets — `label` (11.5px) and `meta` (12.5px) — via `common/_typography.scss`
    (`label-text`, `meta-text`); the 44 raw 10–12.5px sizes are gone. `custom.measure` (47.5rem)
    is the prose width; `common/_surface.scss` (`panel-surface`) backs Panel, Window, and the
    Contents card. New SCSS should use these, never raw label sizes.
  - **Spacing (2026-10-14):** `spacing-70` is `clamp(6rem, 13.5vw, 20rem)` (194px at 1440). The
    footer owns `--footer-gap` (above + below the contact panel); `.site-content` has no bottom
    padding. Homepage stat strip uses a block margin of `spacing-65`.
  - [x] The Framework — signed off 2026-10-14. Contents block beside 11 `section` groups; hero
    `layers` + stat strip; everything from existing vocabulary. Stale copy corrected: 430+ → 700+
    PHP tests (721 across Mythus/IX/child), light/dark claims removed, `make push-production` →
    deploy targets, "no runbooks" dropped, the block-allowlist claim made true (`core/html` and
    YouTube embeds are allowed), real Feature/Hook class names, the AssetManager test path. List
    style `rows` is no longer a grid (inline `<code>` became grid cells); the label/number hang as
    floats, label column 14rem. **Open:** Mythus `composer test` fails (phpunit.xml lists a missing
    `tests/Integration`), so `make test` stops at step one — fix in the mythus repo.
  - [x] Nous Signal — signed off 2026-10-14. Hero is page content (`content/pages/nous-signal.html`;
    no "live" claims — the feed stopped 2026-08-17). The blog block's "all" mode renders
    `NousArchive` (stats, latest + a `signal.parse()` terminal from real fields, month pills with
    "Earlier", month-grouped index, 8 rows then "Show N more"); `view.js` filters client-side
    (`?month=`, `?tag=` chip; tag/sort dropdowns dropped). `NousPost` is the post class (read time
    = words ÷ 230). Archive components shared with Projects via `common/_archive.scss`. New image
    style **Screen**. **Accent is now derived everywhere:** `--color-accent-glow/-line` and 18
    hardcoded lime tints use `accent-1`, so Nous pages go fully red (footer V is a mask). The old
    card grid + decrypt CSS stays until the Nous post template replaces "Related posts".
  - [x] Nous post — signed off 2026-10-14. `single-post.twig` renders the header (breadcrumb,
    "← All transmissions", split title via `src/Support/TitleSplitter.php`, signal № from
    `NousSequence`, excerpt as lead, meta strip with Copy link), a Contents card ("In this signal" +
    tag links into the archive's `?tag=` filter), the body, a sign-off, and prev/next (the ends
    link back to the archive). Header/meta/body styles shared with the project detail via
    `common/_detail.scss`. **Migration:** `scripts/redesign/migrate-nous-posts.php` — exact
    signature match (all 168 matched, 0 skipped; text verified word-for-word), strips the legacy
    header group, turns each section into a `section` group with an anchored, un-underlined H2, and
    the last ("What This Means") into an accent Panel; JSON backup first, `wp_slash` on write,
    re-runs skip. The old card grid, decrypt effect and their templates/styles are removed. The
    Contents card's link styles now target only its `ol`. **Open:** 166 posts mark Nous's "I" with
    the Accent format — reads as a red bar; keep, restyle, or strip.
  - [x] 404 — signed off 2026-10-14. Child `404.php` + `views/404.twig` render the "Not Found"
    content partial (slug `not-found`, no partial type; seeded by `seed-content-partials.php`) with
    the compact footer; falls back to a plain message if the partial is missing. Real 404 status.
    Copy from the mockup (no live-feed claim, no hard-coded project count). New Panel behavior:
    a heading that is a single link makes the whole panel clickable (stretched link; the heading
    skips the scroll-reveal transform so it doesn't trap the hit area).
- [ ] **6 — QA + cutover:** axe, contrast, mobile, font payload; staging rehearsal from a fresh
  production copy; production cutover.

## Cutover checklist (production)

1. `mysqldump` the production DB.
2. `migrate-project-fields.php` and `seed-content-partials.php` on production — both additive; the
   old theme ignores the fields and the partials, and seeding first means the new header/footer
   exist the moment the new code goes live.
3. `make deploy-production` — confirm the ref-update line, not the banner.
4. `split-wicked.php` — **after** the deploy: it publishes the new Wicked (2018) post, which the
   old theme would list with an empty page. It downloads the 2025 key art from the live Wicked
   site's OG image (outbound fetch from the droplet). A later `FORCE=1` migration re-run overwrites
   Wicked (2025) from its legacy content — re-run `split-wicked.php` after it.
4a. **Contact form copy:** `update-contact-form.php` (WPForms #60 placeholders + submit text; dry-run first).
4c. **Instagram:** reconnect the vincent.ragosta source by hand (**Instagram Feed → Settings → Manage
   Sources** — delete the broken source, then **Add Source**; the feed *builder's* Add Source freezes
   on a broken source). Production's token stopped decrypting after the 2026-08-13 salt rotation.
   Then `update-instagram-feed.php` (4 tiles, no captions/likes, GDPR mode **on** so images are
   served from uploads, never Instagram's CDN — keeps the privacy policy accurate).
4d. **Nous posts:** `migrate-nous-posts.php` — **after** the deploy (the old theme still renders
   the legacy header). `DRY_RUN=1` first: expect 168 × migrate, 0 skips; then the real run
   (JSON backup of every post to `BACKUP_DIR`).
4b. **Media:** import the résumé PDF and its preview so the content files' URLs resolve —
   `wp media import VincentRagostaResume.pdf vincent-ragosta-resume-preview.jpg` (from
   `~/Downloads/VincentRagostaResume-2026-10-v2.pdf`, renamed). Content links
   `/wp-content/uploads/2026/10/…`; an import in a later month lands in a different folder —
   update the two URLs in `meet-me.html` to match before applying.
5. Clear legacy project `post_content` (after a JSON backup — the fields now hold it) +
   `apply-page-content.php` (page content files; backs up each page first) + `cutover.php` on production; reindex Relevanssi. Free Relevanssi has no WP-CLI command — reindex
   projects with `wp eval` looping `relevanssi_index_doc($id, true, relevanssi_get_custom_fields(), true)`
   over published project IDs (proven on staging 2026-10-13: 69 reindexed, the content-less
   Wicked (2018) post matches on its fields).
6. `wp cache flush`; FastCGI check — compare `<title>` across `/`, `/projects/`, `/contact/`, twice;
   check `X-FastCGI-Cache`.
7. Smoke-test itzenzo.tv (GraphQL/REST).
8. **Rollback:** push the previous `main` sha to production and restore the dump; per-project JSON
   backups from step 5 allow a partial content restore.

## Handoff — continuing the build

For an agent picking this up cold. Read, in order: `CLAUDE.md` (architecture), `RUNBOOK.md` (hosts,
deploy, traps), then this doc. The mockups are the spec: `docs/redesign-2026/mockups/*.html` (open
in a browser) and the PNGs in `~/Downloads/vincentragosta-redesign-2026-*.png`.

### Working agreement (non-negotiable)

- **One page at a time:** build locally → hand Vincent the local + editor URLs → he reviews and
  signs off → propose a commit message **and** file list → he approves → commit → push `develop` →
  `make deploy-staging` (confirm the `old..new develop -> develop` line, not the banner) → run the
  page's scripts on staging (dry run first) → smoke-check through Cloudflare.
- **Never** commit, push, or deploy without that explicit approval. No co-author trailer. Never
  stage `RUNBOOK.md` or `CLAUDE.md` unless he says so — he edits them himself (and never auto-edit
  `CLAUDE.md`). Never `make push-production`. Never edit IX (shared with client sites).
- **CMS-first.** Pages are core blocks + registered style variants, authored as
  `scripts/redesign/content/pages/{slug}.html` and applied with `apply-page-content.php`
  (backs up first). The editor must open every page with **zero invalid blocks**. Templates only
  for data the post owns (Nous post header, 404). Push back on one-off CSS — extend the system.
- **Margin-top only.** No non-zero `margin-bottom` / `margin-block-end` (negative included). A blanket
  `margin: 0` kills the layout's block gap — zero inline + end only. Spacing from presets.
- **Design tokens:** font sizes `label` (11.5px) / `meta` (12.5px) via `@include label-text` /
  `meta-text` (`common/_typography.scss`); `panel-surface` (`common/_surface.scss`); prose width
  `var(--wp--custom--measure)`; spacing presets `20–70`. Any `.site-content` layout rule needs an
  `.is-root-container` twin (editor parity — see `layout/_site-main.scss`). American spelling, always.

### Vocabulary (registered, reuse before inventing)

- **Paragraph:** `eyebrow`, `status`, `muted` · **Heading:** `subheading`
- **List:** `chips`, `index`, `rows` (numbered key points), `arrows` / `checks` (`<strong>Lead</strong> — detail`), `pills`, `muted`
- **Group:** `panel` (+ class `is-accent`; link its heading to make the whole panel a link), `window` (first paragraph = title bar), `section`
  (ruled chapters), `timeline`, `stat-strip`, `pipeline`, `layers`, `testimonials`,
  `transmission`, `nous-accent` · **Column:** `sticky` · **Table:** `spec` (label | value | count)
- **Code / preformatted:** `terminal` · **Image:** `paper`, `screen` · **Cover:** `animated`
- Core `pullquote` (citation = trailing caption); core **grid** layout (`minimumColumnWidth`) for
  card grids; rich-text formats **Accent** (`<mark class="accent-highlight">`) and **Serif accent**
  (`<em class="serif-accent">`); utilities `has-grid-backdrop`, `has-v-lines`.
- **Contents block** `vincentragosta/toc` (Theme provider, ACF block): lists every H2 that has an HTML
  anchor (`<h2 id="…">`), labeled by the eyebrow paragraph directly above it ("02 / Cookies" →
  "Cookies"). Put it in a `sticky` column beside a column of `section` groups. Logic:
  `TableOfContents::fromBlocks()` (unit-tested); script `assets/js/toc.js` (`[data-toc]`), also used
  by the project detail card.

### QA loop per page

- `make build` · `composer test` (child PHPUnit) · `npx vitest run` (in the child theme).
- E2E against DDEV with system Chrome (the bundled Chromium download is broken) — temp config,
  delete after: `.pw-local.config.js` = `const base = require('./playwright.config.js');
  module.exports = { ...base, projects: [{ name: 'chrome', use: { ...base.projects[0].use, channel: 'chrome' } }] };`
  then `npx playwright test -c .pw-local.config.js`. Add each new page to `tests/e2e/smoke.test.js`
  and `accessibility.test.js`.
- Screenshots at 1440 and 390 (no horizontal overflow), side by side with the mockup.
- Editor validity: in Vincent's own Chrome (Claude in Chrome extension, his existing local WP
  login — never generate auth cookies or change a password), open any editor screen
  (`/wp/wp-admin/post.php?post=ID&action=edit`) and run WordPress's parser over every item in one
  pass: fetch raw content with `wp.apiFetch({ path: '/wp/v2/{pages|posts|content-partial|wp_navigation}?context=edit&per_page=100&_fields=id,slug,content' })`
  (page through `X-WP-TotalPages`), `wp.blocks.parse(item.content.raw)`, and walk blocks +
  `innerBlocks` for `!isValid`. Expect zero. Last run 2026-10-14: 7 pages / 168 posts / 3 partials /
  1 navigation, 3,596 blocks, 0 invalid.
- Local WP admin is at `/wp/wp-admin/` (core lives in `wp/`).

### Remaining work

1. ~~**The Framework (page 68)**~~ — done 2026-10-14. — mockup `framework.html`. Hero (H1 + `layers` diagram) + stat strip,
   then `sticky` column with the Contents block beside 11 `section` groups (Architecture … Developer
   experience). Fix stale copy while rebuilding: drop light/dark-mode claims; replace
   `make push-production` with `make deploy-staging` / `deploy-production`; use the real Feature /
   Hook class names from `ThemeProvider` (e.g. `ContentPartial`, `AccordionIconEnhancer`); **verify
   the "430+ tests" claim** before reusing it (child PHPUnit alone is 316). Mockup copy is a
   proposal — the existing page copy (`content/pages/framework.html`) is the source.
2. ~~**Nous Signal archive (page 431)**~~ — done 2026-10-14. — mockup `nous-signal.html`. Hero is page content (red accent
   comes from `body.nous-signal-page` → accent-1 = nous-red; create `content/pages/nous-signal.html`).
   Rework the child `ix/blog` override (`src/Providers/Blog/blocks/blog/*`): "latest" feature,
   month pills with counts (replace the month dropdown), month-grouped text index (date, title,
   excerpt, read time = words ÷ 230 in PHP), "Show N more"; update its Vitest tests. **Known bug:**
   the page scrolls sideways at 390px today (renders 506px wide) — fix it here.
3. ~~**Nous post (single post, 168 posts)**~~ — done 2026-10-14. — mockup `nous-post.html`. Rewrite
   `src/Providers/Blog/templates/single-post.twig` (model on `single-project.twig`): breadcrumb,
   split title (move `ProjectPost::splitTitle()` to a shared helper, e.g. `src/Support/TitleSplitter.php`),
   "signal № N" (chronological index, in a `NousPost` model + unit test), lead = excerpt, meta strip,
   contents card, body, prev/next. **Migration:** every post starts with a legacy group ("Back To
   Nous" button + heading + "Date:" paragraph) inside `post_content` — write
   `scripts/redesign/strip-legacy-post-header.php` (exact block-signature match, skip + report
   anything else, JSON backup, `DRY_RUN` CSV for Vincent to review before any write). Post H2s have
   **no anchors** — the migration (or the template) must add ids before the Contents card can list
   them.
4. ~~**404**~~ — done 2026-10-14. Mockup `404.html`. Child `404.php` + `views/404.twig` rendering a content partial
   (slug `not-found`, seeded by `seed-content-partials.php`) so copy stays in the CMS; compact footer.
5. **Phase 6** — QA sweep, staging rehearsal from a fresh production copy, then the cutover checklist
   below (needs Vincent's explicit go-ahead).
6. **Copy edit (separate task, requested 2026-10-14)** — an English/grammar pass over all
   frontend language: page content files, partials (header, footer, 404), WPForms copy, template
   strings, and the project fields. Not part of any page build; run it on its own.
7. **Favicon (requested 2026-10-14)** — replace the current favicon with the new V mark (the
   header logo) in the accent lime/neon green. Cover the Site Icon (Settings → General, or the
   theme's favicon files if any) and check the apple-touch icon; it ships with the cutover.

### Open decisions (ask Vincent; don't assume)

- **Cloudflare Web Analytics** is injected on prod + staging, contradicting the privacy policy's "no
  analytics". Disable it in Cloudflare (his login) or name it in the policy.
- **Meet me stat strip** still has the full section gap above it; the homepage's was halved
  (`spacing-65` block margin). Offered, not yet decided.
- `bb1ca82` (docs commit) also carries the `CoverBlockStyles → MediaBlockStyles` rename; HEAD is
  correct. Recommended leaving history as is (no force-push).

## Post-launch

- Delete `scripts/redesign/` (incl. `update-contact-form.php`, `update-instagram-feed.php`), `docs/redesign-2026/mockups/`, and `scripts/setup-mega-menu.sh`;
  propose removing the RUNBOOK's mega-menu re-apply step.
- Update the README Project section (currently documents methods and a block name that don't exist).
- Propose CLAUDE.md updates for review.
