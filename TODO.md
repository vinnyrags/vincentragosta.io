## ADMIN
- finish sign up with carter pulse, need creator hub code

## ITZENZO.TV
- remove all whatnot orders and upload new CSV
- should consider reworking the homepage to have a sneakpeak to card catalog, collection and livestream shop, each of which should have a link (like the card catalog does to their own designated pages). we sould need to create a livestream shop page to match this.
- on the collection, cards and newly created livestream page, we should consider pagination over the load more, while still keeping the filterss and dynamic search. after thinking about it, having 750+ images displaying on a page at any given time is just a mess. lets make sure our pagination has the ability to jump to a page.
- we need to create a request to see and make an offer queue on the homepage under our whatnot callout, and potentially even under our show schedule/bundle callouts as well.
- lets remove the callout about yu-gi-oh please on the cards/collection page (lets keep things lightweight and limit a ton of text), our cleanup on the homepage is already a big step in the right direction from what it was.

- the following items were purchased, we should update our system to reflect this: One Piece Illustration Box Vol. 6, Lapras #10/62 — Fossil, Haunter #29/102 — Base Set, Growlithe #28/102 — Base Set, Grimer #57/82 — Team Rocket, Bulbasaur #44/102 — Base Set #1 (sold two of these), Surfing Pikachu V #8 — Celebrations, Machamp #59/108 — Evolutions, Eevee VMAX #SWSH087 — SWSH: Sword & Shield Promo Cards
- lets work on a request to see/make an offer queue that I can watch in real time during my whatnot stream. For the moment lets disable 
- we should create little dividers in between each activity feed item and lets reduce the font size of the items by 50% please 
- reconsider the activity feed entirely (it is very noisy)
- wire up Stripe `checkout.session.expired` webhook handler — when a buyer abandons a Stripe checkout (e.g. clicks back), stock stays held at the decremented count until manually fixed. Subscribe to the event on the existing Stripe webhook endpoint, parse the session's line_items, and restore stock_quantity by the held qty for each. Idempotent. Surfaced 2026-05-13 when a customer clicked back on the SAO Alicization Vol 2 listing and the stock had to be hand-restored via wp post meta update + /api/revalidate.
- remove instagram link on /live #announcements post (just tiktok)
- Yu-Gi-Oh singles are joining the catalog soon. Pokemon and anime are live today — Yu-Gi-Oh inventory drops as it gets inspected and listed., can we add japanese singles to this note as well on /cards
- CI is still failing-- [vinnyrags/itzenzo.tv] test workflow run

test: All jobs have failed
View workflow run

Status	Job	Annotations
vitest
test / vitest
Failed in 1 minute

- can we retroactively fill the activity feed from the first livestreams events
- lets consider the info bubble (popover/title) to display the duck race information for example, maybe a little one liner but a more detailed description in the info bubble
- automate `make rebuild-staging-catalog` — currently a placeholder that prints manual steps. After live cutover, the manual procedure (wipe `stripe_product_id`/`stripe_price_id` post meta on staging, re-run `push-products.js`/`push-cards.js` against staging WP with `sk_test_*`) becomes routine. Wrap it in a single make target. Trigger: first time we hit a real cross-mode push need.
- we should allow for the opportunity for folks to submit a note on purchase, idk if stripe has this sort of thing built in to its checkout flow, and we can capture it for my review when looking at their order (maybe some flavor of displaying the note in the queue), we definitely want to capture it somewhere in the appropriate DB

- if we look at the cache work we did for celebrityautobiography.com, are there any cues we can take from there and apply to either itzenzo.tv or vincentragosta.io frontend?

- DISCORD: pull boxes are going to be 50 cards in the box, i noticed our discord messaging shows white boxes for each slot in the pull box, I am not sure we should do this, I like the idea of keeping the claims X out of X display, but wonder if it may be too much to have 125 boxes like we have 5 in our test currently
- consider moving the giveaway stuff to the website (as opposed to in discord), is it possible to build a duck race on my website? I am sure this is quite involved, but its a fairly simple concept
- setup donations link (stripe based), or section on the website, maybe in the footer?

- can we confirm if we have shipping easy tests accounted for in our !test command please?
- how can I pull money from Stripe into my personal bank account, is this something I can fire off in discord at the end of the night, or perhaps it can be automated in !offline perhaps (having its own designated command, just automated in !offline). lets talk through this first before doing anything, we would also want to make sure we are updating documentation/readmes/discord messaging/shop storefront messaging accordingly.
- setup tests buying across single cards and box product in !test command
- can we change !capture command to !moment instead, and update all docs and discord messaging and tests accordingly
- add tiktok feed to the about page on itzenzo.tv via same plugin smashballoon that we use for instagram
- investigate top discord bots/apps we could add to server to make it more fun, or enhance my experience
- can i stream tiktok studio from my ipad? I have access to my mac book pro, maybe I can work something off of that? ideally i just uses my ipad to simulcast to four different streaming platforms from just the one device, that would be completely optimal
- can we research cheap shipping supplies, I have been going to staples for all of my shipping supplies and it has been expensive. I like the card mailers that had the bubble wrap inside of them.
- SECURITY: run `apt-get update && apt-get upgrade -y` on production droplet (174.138.70.29) — Lynis audit on 2026-05-14 flagged warning PKGS-7392 (vulnerable packages). Quick fix, ~2 min. Tracked as 2026-05-14-001 in `akivili/business/security-scans/2026-05-14/SUMMARY.md` (target: 7 days).
- SECURITY: add HTTP security response headers to itzenzo.tv and vincentragosta.io — Mozilla Observatory scored both D (30/100) on 2026-05-14 due to missing CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy. Add via nginx config; target Observatory grade B+ within 90 days. Tracked as 2026-05-14-002 in `akivili/business/security-scans/2026-05-14/SUMMARY.md`.

## AKIVILI
- capitalize github name to Akivili
- consider cleaning up the file architecture of akivili
- create frontend for akivili, can query anything in the repo/about me/the business, would be super cool! just a prompt to quick check things, only my laptop, bluetooth or wifi can access this website

## MYTHUS
- capitalize github name to Mythus
- consider throwing detailed exceptions for core backend functionality especially, but to the backend functionality as a whole, having a good error logging system should also be at the forefront when developing (at least in my platform/system), what are your thoughts on adding exception logs to the backend code? lets do a deep dive to see what this would take and write up an effective and efficient implementation plan in plan mode please.

## IX
- capitalize github name to IX
- we made an update to social icons hook in theme provider in the child theme that we want to move to the parent theme (confirm this action beforehand even though I cant think of any reason why we wouldnt want all social links to open in a new tab)

## VINCENTRAGOSTA.IO
- consider revamping vincentragosta.io nav overlay to look and feel like itzenzo.tv nav overlay
- is there an opportunity to update makefile to take similar approach that we did in celebrityautobiography with the composer script for nested installs, wondering if that could be an optimization in vincentragosta.io as well (in composer and in makefile)
- setup a nightly (daily) cron on vincentragosta.io that takes snapshot of db and saves the last three most recent in an appropriate directory, this way if anything catastrophic happens we have a backup.
- consider wrapping code block in a container with background and border, and then OR even add code block to table further down on framework page
- re-evaluate the current build process, ensure we are being as optimal as we can be
- abstract footer margin-block-start to CMS control at the page level (acf on the page), or perhaps even leverage the spacer/divider block
- I want to add an "Interested inv Nous? (or some flavor of this, i dont actually like what i just wrote all that much, hoping you can recommend something cool and enticing, and dark)" to the blog page somewhere, perhaps on a new page, either way I want some descriptive text for Nous, not terribly long, maybe 2-3 paragraphs max (try for 2, 4-5 sentences each paragraph).