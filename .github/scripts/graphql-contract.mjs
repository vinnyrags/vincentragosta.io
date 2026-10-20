// GraphQL contract check: every query itzenzo.tv sends must still work against this
// WordPress backend. Runs after each deploy (deploy.yml, job `contract`).
//
// Why: wpgraphql-acf 3.0 silently dropped options pages from the schema, which would have
// broken itzenzo.tv's site settings in production. Only a before/after run of its real
// queries caught that. This makes that check permanent.
//
// The queries come from the consumer itself, so the contract cannot drift from what
// itzenzo.tv actually sends:
//   - CI: itzenzo.tv's src/lib/graphql/queries.ts via the GitHub API (private repo;
//     ITZENZO_QUERIES_TOKEN is a fine-grained, read-only token for that one repo), at
//     ITZENZO_REF — the itzenzo.tv branch that runs against this environment (develop for
//     staging, main for production; staging.itzenzo.tv reads staging WordPress);
//   - local: QUERIES_FILE=<path to queries.ts>.
//
// Passes when every query returns HTTP 200, no GraphQL errors, and data — plus rows
// where the catalog must have them. It does not compare bytes (content changes
// legitimately); for before/after diffs on upgrades, add --snapshot <dir>.
//
//   GRAPHQL_URL=https://staging.vincentragosta.io/wp/graphql node graphql-contract.mjs [--snapshot dir]
import { readFileSync, mkdirSync, writeFileSync } from "node:fs";
import vm from "node:vm";

const endpoint = process.env.GRAPHQL_URL;
const snapIdx = process.argv.indexOf("--snapshot");
const snapDir = snapIdx > -1 ? process.argv[snapIdx + 1] : null;
if (!endpoint) { console.error("GRAPHQL_URL is required"); process.exit(2); }

async function loadQueries() {
  if (process.env.QUERIES_FILE) return readFileSync(process.env.QUERIES_FILE, "utf8");
  const token = process.env.ITZENZO_QUERIES_TOKEN;
  if (!token) { console.error("ITZENZO_QUERIES_TOKEN (or QUERIES_FILE) is required"); process.exit(2); }
  const ref = encodeURIComponent(process.env.ITZENZO_REF || "main");
  const res = await fetch(`https://api.github.com/repos/vinnyrags/itzenzo.tv/contents/src/lib/graphql/queries.ts?ref=${ref}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/vnd.github.raw+json", "User-Agent": "vincentragosta-contract" },
  });
  if (!res.ok) { console.error(`could not read itzenzo.tv queries: HTTP ${res.status}`); process.exit(2); }
  return res.text();
}

// queries.ts is a list of `export const NAME = \`…\`` template strings; evaluate them in a
// sandbox so fragments interpolated with ${…} resolve the way the app's bundler would.
const ctx = {};
vm.runInNewContext((await loadQueries()).replace(/export const /g, "var "), ctx);

// [label, query, variables, path to a list that must be non-empty]
const cases = [
  ["site-settings", "SITE_SETTINGS_QUERY", {}, null],
  ["pages", "PAGES_QUERY", {}, null],
  ["all-products", "ALL_PRODUCTS_QUERY", {}, null],
  ["top-priced", "TOP_PRICED_CARDS_QUERY", { limit: 20 }, "topPricedCards"],
  ["count-catalog", "CARD_COUNT_QUERY", { scope: "CATALOG" }, null],
  ["count-collection", "CARD_COUNT_QUERY", { scope: "COLLECTION" }, null],
  ["page-catalog-0", "CARDS_PAGE_QUERY", { scope: "CATALOG", limit: 50, offset: 0 }, "cardsByScope"],
  ["page-collection-0", "CARDS_PAGE_QUERY", { scope: "COLLECTION", limit: 50, offset: 0 }, "cardsByScope"],
];

// A query the app has that this list does not cover is a gap, not a pass.
const known = new Set(cases.map((c) => c[1]).concat("CARD_FIELDS_FRAGMENT"));
const uncovered = Object.keys(ctx).filter((k) => /_QUERY$/.test(k) && !known.has(k));

const stable = (v) => Array.isArray(v) ? v.map(stable)
  : v && typeof v === "object" ? Object.fromEntries(Object.keys(v).sort().map((k) => [k, stable(v[k])])) : v;

if (snapDir) mkdirSync(snapDir, { recursive: true });
let failed = 0;
for (const [label, name, variables, mustHaveRows] of cases) {
  const query = ctx[name];
  let problem = "";
  let body = null; let status = 0; const t = Date.now();
  if (!query) problem = `${name} no longer exists in itzenzo.tv's queries.ts — update this check`;
  else {
    try {
      const res = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json", "User-Agent": "vincentragosta-contract (GitHub Actions)" },
        body: JSON.stringify({ query, variables }),
      });
      status = res.status;
      body = await res.json().catch(() => null);
    } catch (e) { problem = `request failed: ${e.message}`; }
    if (!problem) {
      if (status !== 200) problem = `HTTP ${status}`;
      else if (!body) problem = "response is not JSON";
      else if (body.errors?.length) problem = body.errors.map((e) => e.message).join("; ");
      else if (!body.data) problem = "no data";
      else if (mustHaveRows && !(body.data[mustHaveRows]?.length > 0)) problem = `${mustHaveRows} returned no rows`;
    }
  }
  if (problem) failed++;
  if (snapDir && body) writeFileSync(`${snapDir}/${label}.json`, JSON.stringify(stable(body), null, 2));
  console.log(`${problem ? "FAIL" : "ok  "}  ${label.padEnd(18)} ${String(status).padEnd(4)} ${String(Date.now() - t).padStart(5)}ms${problem ? "  " + problem : ""}`);
}
for (const q of uncovered) { failed++; console.log(`FAIL  ${q} exists in itzenzo.tv but this check does not run it — add a case`); }
const src = process.env.QUERIES_FILE || `itzenzo.tv@${process.env.ITZENZO_REF || "main"}`;
console.log(failed ? `\n${failed} contract failure(s): ${src} against ${endpoint}` : `\nall ${cases.length} queries from ${src} pass against ${endpoint}`);
process.exit(failed ? 1 : 0);
