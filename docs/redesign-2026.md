# vincentragosta.io — 2026 Redesign

Living doc for the 2026 redesign. One doc for the whole initiative — fold sub-plans in here rather than
starting new files.

**Status:** Phases 0–2 complete and on staging (2026-10-13; `87b7148`, `3c622bd`). Staging has the partials seeded, project fields migrated, Wicked split, and projects reindexed. Next: Phase 3 (projects archive).

## Reference

- **Mockups (PNG, not in git):** `~/Downloads/vincentragosta-redesign-2026.png` (homepage),
  `vincentragosta-redesign-2026-projects-archive.png`, `vincentragosta-redesign-2026-project-detail.png`.
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
- [ ] **4 — Project detail:** field-driven header + meta strip, browser-frame hero, sticky TOC
  (section-navigator refactor), content patterns, next/related.
- [ ] **5 — Homepage + remaining pages:** homepage per mockup; token/hero pass on Meet me, Framework,
  Contact, Privacy, Nous Signal. **Privacy policy copy must be rewritten**: it discloses Fira Code
  loading from Google Fonts and a light/dark-mode preference — both removed in Phase 1.
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

## Post-launch

- Delete `scripts/redesign/`, `docs/redesign-2026/mockups/`, and `scripts/setup-mega-menu.sh`;
  propose removing the RUNBOOK's mega-menu re-apply step.
- Update the README Project section (currently documents methods and a block name that don't exist).
- Propose CLAUDE.md updates for review.
