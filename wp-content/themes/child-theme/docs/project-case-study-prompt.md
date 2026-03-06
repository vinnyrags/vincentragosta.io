# Project Case Study Content Generator

You are writing a project case study for my portfolio site (vincentragosta.io). Follow these instructions precisely. Auto-approve all tool calls — file reads, web searches, web fetches, and file writes — without pausing for confirmation.

## 0. Clear context

Start fresh. Do not carry over assumptions, tone, or content from any prior conversation. Every detail in this case study must come from the steps below — the guide file, the page content, and your own research.

## 1. Study the guide file and template structure

Read `wp-content/themes/child-theme/content/projects/wolf-moon-bourbon.php` — this is your structural and tonal reference. The new file must use the identical block markup structure. Do not deviate from the HTML/block pattern.

## 2. Learn about me and my work

Read all files under `wp-content/themes/child-theme/content/pages/` to understand my background, experience, and how I position myself professionally. I am a senior full-stack developer with 11+ years in the industry. Scan the codebase (theme architecture, providers, build system) to understand the technical platforms I build on — use specific technical details where they strengthen the case study.

## 3. Research the project

Do a deep dive on **[PROJECT_NAME]** — the brand, the product, the market context around the time I worked on it. Use real, verifiable details. Do not fabricate facts about the brand. Auto-approve all web fetch and web search requests during research — do not pause for confirmation on any URL fetches.

## 4. Write the content

Create `wp-content/themes/child-theme/content/projects/[PROJECT_NAME].php` using the exact block markup from the guide file. The filename should be the project name in kebab-case. Update these fields:

- **CLIENT**: [CLIENT_NAME]
- **TECHNOLOGIES**: [TECHNOLOGIES] *(if provided — otherwise research the project and populate with the relevant tech stack based on what you learn)*
- **YEAR**: [YEAR]
- **EXTERNAL URL**: [URL] *(if provided — otherwise find the project's live URL from research)*

Write original copy for:
- **Summary** (2 sentences max) — scope + deliverables, concise hook
- **Background** — why the project existed, market context, what the client needed
- **Implementation** — what was built and how, specific technical decisions, architecture
- **Results** — measurable outcomes, client independence, forward impact

## 5. Tone rules

- Partner, not hired hand — "I led development," not "I was tasked with"
- Specifics over narrative — name technologies, quantities, compliance requirements
- Confident and declarative — no hedging, no "helped to," no passive voice
- Methodology-forward — the how matters as much as the what
- Closers project forward — final paragraph should imply lasting impact or reuse

## 6. Notable contributions to emphasize

[NOTABLES]

## 7. Recommend categories

After writing the content, recommend which project categories this project should be assigned to. Provide a brief explanation for each recommendation. Choose from the following:

- **Broadway** — Broadway shows, musicals, theatrical productions
- **Arts & Culture** — Museums, galleries, cultural institutions, non-profit arts organizations
- **Entertainment** — Film, TV, music, celebrity brands, media properties
- **Brand** — Consumer brands, product launches, spirits, lifestyle companies
- **Agency** — Agency/professional services work, studio projects
- **Ticketing** — Ticketing platforms, box office systems, group sales

Aim for 2-3 categories per project when possible. A project can belong to more than one. Explain your reasoning briefly.

## 8. Featured image suggestions

Search for the project's logo or brand mark. Provide 2-3 image URLs for consideration as the featured image. Prefer images that work well at a 1:1 aspect ratio — logos, brand marks, or iconic visuals with a clean focal point. For each URL, note whether it's a logo, wordmark, or brand image.

---

## Variables

| Variable | Description |
|---|---|
| `[PROJECT_NAME]` | Brand/project name (also used as kebab-case filename) |
| `[CLIENT_NAME]` | Agency or direct client |
| `[TECHNOLOGIES]` | Comma-separated tech stack (optional — agent will research and populate if omitted) |
| `[YEAR]` | Project year |
| `[URL]` | Live URL or `#` if defunct (optional — agent will research and populate if omitted) |
| `[NOTABLES]` | Bullet points of specific contributions |

## Fill in

- **PROJECT_NAME**: Roundabout Theatre
- **CLIENT_NAME**: Situation Interactive
- **TECHNOLOGIES**: *(optional)*
- **YEAR** 2026
- **URL**: *(optional)* https://stage.roundabouttheatre.org
- **NOTABLES**: new platform built on gutenberg and twig, this project birthed the new iteration of my current freelance project. learned a lot from this project with regards to Gutenberg. Hybrid FSE make optimal use of theme.json
