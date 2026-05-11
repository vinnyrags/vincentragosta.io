## HIGH PRIORITY
- audit pure-read REST endpoints for GraphQL migration. Codebase rule is "GraphQL for reads, REST for writes/transactions." Two known violations of that rule today, both frontend-consumed pure reads:
    1. **`/shop/v1/pull-boxes/active`** (`PullBoxActiveEndpoint`) — there is *already* an `activePullBox` GraphQL field exposed in `PullBoxGraphQL.php`, but the frontend still proxies the REST endpoint via `src/app/api/pull-boxes/active/route.ts`. The PullBoxes modal slot grid AND the homepage tile count badge both consume the REST path. Migrating the frontend to call `activePullBox` via GraphQL would let us delete the REST endpoint + the Next route handler + ~30 lines of proxy code. Real-time concern (no-cache fetch on each modal open) is satisfiable via GraphQL with `{ cache: "no-store" }` on the fetch.
    2. **`/shop/v1/pack-battle/current`** (`CurrentPackBattleEndpoint`) — pure read of the current open pack battle, frontend-consumed. No corresponding GraphQL field today. Mirror the `topPricedCards` pattern: register a `currentPackBattle` root field returning the existing PackBattle type. Confirms the "GraphQL for reads" pattern is consistent across the catalog/queue/pull-box/pack-battle domain.
  Queue read endpoints (`QueueSnapshotEndpoint`, `QueueSessionsListEndpoint`, `QueueSessionEntriesEndpoint`) stay REST — they serve Nous bot which doesn't consume GraphQL. The shape difference (`serializeEntryRaw` for Discord) is the justification.
- rename or add `make sync-cards-production` — current `make sync-cards` runs `push-cards + pull-cards-publish`, but `pull-cards-publish` targets **local DDEV**, not production. Naming is misleading ("publish" reads like prod). Either rename `pull-cards-publish` → `pull-cards-local-publish` and add a new `make sync-cards-production` that chains `push-cards + pull-cards-production`, or rebrand `sync-cards` itself to be production-targeted with a separate `sync-cards-local`. Either way, the operator-facing name should clearly reflect the target environment.
- production orphan-cleanup workflow for cards — when a row is deleted from the Singles Sheet, `push-cards.js` (without `--clean`) leaves the corresponding Stripe product active, and `pull-cards-production` (which doesn't carry a `.clean` flag, intentionally — payment history blocks live-mode delete) leaves the WP post around. Result: the deleted card stays purchasable. Manual cleanup is `wp post delete <id> --force` + `curl POST https://api.stripe.com/v1/products/<id> -d active=false`. Wrap this in a `make remove-card NAME=...` target (or per-stripe-id remover) that does both atomically. First applied this manually for Leafeon V $20 / `prod_UOF2KpPFAMm3G2` on 2026-05-04.
- wire COMPOSER_AUTH for CI — the failing-CI fix (commit pending) is a pragmatic skip of `ProjectProviderTest` in CI because that test extends an IX-namespaced parent class that lives in the private `vincentragosta/ix` Composer package, which CI cannot install without auth. **Proper fix:** add `COMPOSER_AUTH` GitHub Actions secret with the contents of `/root/.composer-auth.json` from the droplet, then update `.github/workflows/test.yml` to (a) `env: COMPOSER_AUTH: ${{ secrets.COMPOSER_AUTH }}`, (b) run `composer install` from repo root (which fires the existing `reinstall-nested` post-install-cmd → installs IX + Mythus correctly), (c) drop the skip guard in ProjectProviderTest. Restores full test coverage in CI. ~10 min once secret is added.
- automate `make rebuild-staging-catalog` — currently a placeholder that prints manual steps. After live cutover, the manual procedure (wipe `stripe_product_id`/`stripe_price_id` post meta on staging, re-run `push-products.js`/`push-cards.js` against staging WP with `sk_test_*`) becomes routine. Wrap it in a single make target. Trigger: first time we hit a real cross-mode push need.


- what are cool things we can do in twitch (obs) to prepare for our twitch stream (both gaming and card night)
- draft we are back announcement
- add tiktok feed to shop page via same plugin smashballoon that we use for instagram
- finish sign up with carter pulse, need creator hub code
- investigate top discord bots/apps we could add to server to make it more fun, or enhance my experience
- i need to start working on spotify streaming playlists for the varying segments of my livestream, what do you suggest, is there a spotify api available to use that we can automate much of this?
- can i stream tiktok studio from my ipad? I have access to my mac book pro, maybe I can work something off of that? ideally i just uses my ipad to simulcast to four different streaming platforms from just the one device, that would be completely optimal
- can we research cheap shipping supplies, I have been going to staples for all of my shiping supplies and it has been expensive. I like the card mailers that had the bubble wrap inside of them.
- talk with agent about how i have a very in depth pokemon card collection (and even some really cool yu gi oh cards), we should factor displaying a portion of them each livestream into our planning
- talk with agent about how i have roughly 1K cards ready to be sold spanning from pokemon, anime and mature single cards, ranging from $1-$1000, how can we factor this into our livestream planning, I assume !sell will work fine for this
- talk about potential to only be able to stream from ipad out of the gate, what would that look like for the business, and if we cant use something like restream to simulcast what platform should I be streaming on?
- talk about table strategy, what to display for the varying parts of the livestream, be sure to update relevant documents in akivili
- sort by language as additional dropdown in products block
- new image for one piece vol 5

TODO BEFORE GOING LIVE
- we need a way for folks who refuse to have a discord account a way for them to pay shipping, I am thinking a button on the website somewhere (maybe on the shipping page? I defer to you for placement), that they can click, enter their email, the system confirms what they owe, and directs them to a stripe checkout page to pay their shipping, the system should update their record to no longer reflect the outstanding shipping balance-- does this make sense?
- we should update empty queue state to also include a note about buying from the livestream shop with perhaps a deeplink to the shop below. lets also add a button next to join discord that deep links to the livestream shop (this is in the hero section on the homepage)
- fix scrollbar issue with new nav (page jumps when nav opens by scrollbar width)
=====

- can we change !capture command to !moment instead, and update all docs and discord messaging and tests accordingly
- can we capitalize all GH repos (minus websites like itzenzo.tv and vincentragosta.io, but IX, Mythus and Akivili should all be capitalized)

- if i dont ship orders for a particular week (for domestic lets say, this scenario can be applied to international too), will the system still reset on the following Monday, and charge the buyer again if they buy in for the new week.
- setup tests buying across single cards and box product in !test command
- how can I pull money from Stripe into my personal bank account, is this something I can fire off in discord at the end of the night, or perhaps it can be automated in !offline perhaps (having its own designated command, just automated in !offline). lets talk through this first before doing anything, we would also want to make sure we are updating documentation/readmes/discord messaging/shop storefront messaging accordingly.
- setup donations link (stripe based), or section on the website, maybe in the footer?
- can we confirm if we have shipping easy tests accounted for in our !test command please?

- consider moving the giveaway stuff to the website (as opposed to in discord), is it possible to build a duck race on my website? I am sure this is quite involved, but its a fairly simple concept
- check over all discord messaging, make sure nothing is stale

- grimmsnarl V sv116/sv122 needs to bump stock to 2

- if we look at the cache work we did for celebrityautobiography.com, are there any cues we can take from there and apply to either itzenzo.tv or vincentragosta.io frontend?
- pull boxes are going to be anywhere from 75-125 cards in the box, i noticed our discord messaging shows white boxes for each entry, I am not sure we should do this, I like the idea of keeping the claims X out of X display, but wonder if it may be too much to have 125 boxes like we have 5 in our test currently
- is there an opportunity to update makefile to take similar approach that we did in celebrityautobiography with the composer script for nested installs, wondering if that could be an optimization in vincentragosta.io as well (in composer and in makefile)
- create frontend for akivili, can query anything in the repo/about me/the business, would be super cool! just a prompt to quick check things, only my laptop, bluetooth or wifi can access this website
- need time tracking app for ARTHOUSE, can you suggest software? and/or something cool we could even do custom in google sheets maybe?
- could we explore a potentially better mobile experience, currently its scroll-galore for both shops, the experience, while each card looks great, as a whole is completely unrealistic to vertically scroll through 1000+ cards or even 200+ boxes/packs on a mobile screen. wondering if we go back to two columns on mobile and display less information maybe? this still doesnt really solve the problem, but i guess its okay to infinitely scroll, having to have them search only on mobile is also not a good UI. its probably fine I think, we should just look into potentially having two cards per row displayed, title and price maybe only? let me know what you are thinking, dont implement anything yet lets talk through this. i also wonder if we can simplify the buttons on mobile, have a cart icon instead of add to cart, and just have "request" instead of request to see, and have the buttons be side by side instead of stacked.
- all of my cards for sale are holographic cards (holographs), is there any conflicting rows in the google sheet that would say otherwise? I am mainly checking in the common/uncommon scenario, I dont believe anything with those names is correct, I have cards that are technically rarer than holo-rare, can i see a list of any offenders, please dont make any changes just yet I want to review the list (if any)
- setup a nightly (daily) cron on vincentragosta.io that takes snapshot of db and saves the last three most recent in an appropriate directory, this way if anything catastrophic happens we have a backup.
- nightly cron on production that runs `node scripts/shop/audit-stripe-active.js --apply` to keep WP catalog and Stripe in sync — auto-stocks=0 + clears stale stripe IDs whenever a Stripe product becomes inactive between syncs. Posts a one-line summary to `#ops` Discord. Belt for the existing pre-flight + webhook layers; catches anything they miss. is there an opportunity here to have staging be synced with production as well (ensuring staging always matches production)

- does any documentation (readmes/akivili/itzenzo.tv pages/discord nous messaging across the various channels) need updating?

## MEDIUM PRIORITY
- switch stripe over to live mode
- we made an update to social icons hook in theme provider in the child theme that we want to move to the parent theme (confirm this action beforehand even though I cant think of any reason why we wouldnt want all social links to open in a new tab)
- if we were to review akivili in its entirety, everything in it vs the content/pages and content/projects, do you see opportunity to update the content on the current website with anything else that we have done and/or worked on that would better the cause on my website to get hired for web development business work, anything pertaining to server deployment stuff, any of the new make commands we have done, anything related to stripe? the card business? I was not sure I wanted to expose the shop in my global nav, to try to keep a clear separation ebtween the shop and the rest of my web development work, but maybe there is a fun and clever play we could even do-- I dont know about this but I could be persuaded if you make a compelling argument. I look forward to your assessment on this.
- consider cleaning up the file architecture of akivili
- flash sale in discord/stripe, how can we integrate this into a livestream moment where an alarm goes off, and a random flash sale happens

## LOW PRIORITY
- consider having each PostProvider create its own category taxonomy, right now I am in the situation wheree blog and project categories are meshing, ideally they would have their own subset of categories. we may need to consider a strategy to move categories currently assigned to posts to the new project category after we do this.
- consider throwing detailed exceptions for core backend functionality especially, but to the backend functionality as a whole, having a good error logging system should also be at the forefront when developing (at least in my platform/system), what are your thoughts on adding exception logs to the backend code? lets do a deep dive to see what this would take and write up an effective and efficient implementation plan in plan mode please.
- consider wrapping code block in a container with background and border, and then OR even add code block to table further down on framework page
- re-evaluate the current build process, ensure we are being as optimal as we can be
- update CMS to use patterns and not one-offs (rename to pattern names, offenders are the pages)
- clean offending project images, only a handful, probably need custom work, similar to what we did for absupplies logo
- abstract footer margin-block-start to CMS control at the page level (acf on the page), or perhaps even leverage the spacer/divider block
- I want to add an "Interested inv Nous? (or some flavor of this, i dont actually like what i just wrote all that much, hoping you can recommend something cool and enticing, and dark)" to the blog page somewhere, perhaps on a new page, either way I want some descriptive text for Nous, not terribly long, maybe 2-3 paragraphs max (try for 2, 4-5 sentences each paragraph).
- update the tags on posts to have a max of 10, also re-evaluate where we are applying the nous-accent in the post title, it should be on the darkest connotated word in the heading.