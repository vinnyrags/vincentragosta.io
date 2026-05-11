## ADMIN
- finish sign up with carter pulse, need creator hub code

## ITZENZO.TV
- automate `make rebuild-staging-catalog` — currently a placeholder that prints manual steps. After live cutover, the manual procedure (wipe `stripe_product_id`/`stripe_price_id` post meta on staging, re-run `push-products.js`/`push-cards.js` against staging WP with `sk_test_*`) becomes routine. Wrap it in a single make target. Trigger: first time we hit a real cross-mode push need.

- new image for one piece vol 5, change evolving skies ETB stock to 2
- if we look at the cache work we did for celebrityautobiography.com, are there any cues we can take from there and apply to either itzenzo.tv or vincentragosta.io frontend?
- grimmsnarl V sv116/sv122 needs to bump stock to 2
- nightly cron on production that runs `node scripts/shop/audit-stripe-active.js --apply` to keep WP catalog and Stripe in sync — auto-stocks=0 + clears stale stripe IDs whenever a Stripe product becomes inactive between syncs. Posts a one-line summary to `#ops` Discord. Belt for the existing pre-flight + webhook layers; catches anything they miss. is there an opportunity here to have staging be synced with production as well (ensuring staging always matches production)
- could we explore a potentially better mobile experience, currently its scroll-galore for both shops, the experience, while each card looks great, as a whole is completely unrealistic to vertically scroll through 1000+ cards or even 200+ boxes/packs on a mobile screen. wondering if we go back to two columns on mobile and display less information maybe? this still doesnt really solve the problem, but i guess its okay to infinitely scroll, having to have them search only on mobile is also not a good UI. its probably fine I think, we should just look into potentially having two cards per row displayed, title and price maybe only? let me know what you are thinking, dont implement anything yet lets talk through this. i also wonder if we can simplify the buttons on mobile, have a cart icon instead of add to cart, and just have "request" instead of request to see, and have the buttons be side by side instead of stacked.
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
- can we research cheap shipping supplies, I have been going to staples for all of my shiping supplies and it has been expensive. I like the card mailers that had the bubble wrap inside of them.

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