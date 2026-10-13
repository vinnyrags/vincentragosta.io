# vincentragosta.io — 2026 Redesign

Living doc for the 2026 redesign. One doc for the whole initiative — fold sub-plans in here rather than
starting new files.

**Status:** Phase 0 complete (2026-10-13). Next: Phase 1 (foundation).

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
| Content delivery | Scripted cutover (`scripts/redesign/`). **Never `make push-production`** — the same DB holds itzenzo.tv's products and unified queue. |

## Branching during the build

- `develop` is the redesign integration branch; staging previews it. `main`/production keeps the
  current design until launch.
- **Hotfixes** (e.g. Shop provider / itzenzo backend): branch off `main` → merge to `main` →
  `git push production main` (verify the `old..new main -> main` line) → merge `main` back into `develop`.
  `make deploy-production` will not work while `develop` has diverged — that is expected.

## Phase 0 findings

Preset classes used in existing content (local DB, content current through 2026-08-13; re-check on
production during the cutover rehearsal):

- **Font-family classes:** none — Roobert / Reckless Neue / Fira Code slugs can be removed outright.
- **Color classes:** only `has-nous-red-color` (2) — keep the `nous-red` slug.
- **Font sizes:** `30` (449), `10` (272), `70` (174), `40` (26), `60` (1) — keep slugs 10–70, re-value.
- **Style variants:** `is-style-muted` (1638), `subheading` (18), `pills` (12), `animated` (8),
  `numbered-list` (2), `nous-accent` (2), `dark` (2), `focal-top` (53, IX) — all must keep working.

## Phases

- [x] **0 — Setup:** this doc, mockup source, preset-class audit.
- [ ] **1 — Foundation:** tokens (palette re-valued under existing slugs, new font families + display
  sizes), remove light mode, self-host fonts, style vocabulary (`eyebrow`, `chips`, `panel`,
  `stat-strip`, `terminal`, serif-italic accent format), new header + footer.
- [ ] **2 — Project data model:** ACF `group_project_fields` (`client`, `role`, `project_year`,
  `technologies`, `stack_summary`, `live_url`, `case_study`, `status_label`), `ProjectPost` getters,
  retire `ProjectYearExtractor`, migration scripts with dry-run CSV.
- [ ] **3 — Projects archive:** stats, category pills, featured case studies, year-grouped index,
  Grid/Index toggle (`?view=`), show-more.
- [ ] **4 — Project detail:** field-driven header + meta strip, browser-frame hero, sticky TOC
  (section-navigator refactor), content patterns, next/related.
- [ ] **5 — Homepage + remaining pages:** homepage per mockup; token/hero pass on Meet me, Framework,
  Contact, Privacy, Nous Signal.
- [ ] **6 — QA + cutover:** axe, contrast, mobile, font payload; staging rehearsal from a fresh
  production copy; production cutover.

## Cutover checklist (production)

1. `mysqldump` the production DB.
2. `migrate-project-fields.php` on production (additive; old theme ignores the fields).
3. `make deploy-production` — confirm the ref-update line, not the banner.
4. `strip-legacy-project-header.php` + `cutover.php` on production.
5. `wp cache flush`; FastCGI check — compare `<title>` across `/`, `/projects/`, `/contact/`, twice;
   check `X-FastCGI-Cache`.
6. Smoke-test itzenzo.tv (GraphQL/REST).
7. **Rollback:** push the previous `main` sha to production and restore the dump; per-project JSON
   backups from step 4 allow a partial content restore.

## Post-launch

- Delete `scripts/redesign/` and `docs/redesign-2026/mockups/`.
- Update the README Project section (currently documents methods and a block name that don't exist).
- Propose CLAUDE.md updates for review.
