## HIGH PRIORITY
- consider creating a blog that ai can automate/schedule posts for (consider rikkos corner or a little bot corner where we are upfront about the ai generated news, and how it will publish ai related/comsci related news daily)
- configure relevanssi, get search page working, create either integrated search results (between projects and blog), or have two separate sections
- lets look at resume.md, we need to encapsulate any missing data from this document on my abouot page (content/pages/about.html), where appropriate please create new alternating core/groups to add the new content, we can discuss opportunity to create any new blocks where necessary, in particular we need to add group containing skills overview to about page, place where we will drop technical jargon, need to think of a clean way to display a lot of skills in a clean and efficient manner, I am open to suggestions on how to best represent this data, ideally we make use of existing core gutenberg blocks (even if we need to re-enable some), if we cant figure out a clean solution with existing blocks, we could potentially create something custom. lets enter plan mode and work up an effective and efficient implementation plan on this after doing a deep dive on some research on how to best approach this.
- consider testimonials section on home page, is there a core block for blockquote? what about testimonials? do we have a core/slider block? will we need a core/slider block?
- audit sitemap, install yoast if not already present, and make sure we dont have urls out there we havent designed or look ugly, is there anything else we should consider doing with regards to best SEO practices for wordpress sites that use the free version of yoast seo (best practices in 2026), lets do a deep dive on this and work out an implemnentation plan on what to instruct me to do in the CMS or if there is any code work we should consider.
- split repos, configure satis on production server, packages.vincentragosta.io
- consider only having one tabbed stop for each shutter card, right now there is the full card and the open close toggle (plus and minus button), I feel like there should only be one that is available for tabbing, what do you think about this?

## MEDIUM PRIORITY

## LOW PRIORITY
- update staging env with prod content (pull from prod to local, then from local push to staging)
- update CMS to use patterns and not one-offs
- clean offending project images, only a handful, probably need custom work, similar to what we did for absupplies logo

## ON HOLD
- (after doing a db pull from prod), have the agent pull in all content from all pages and projects, and update the content directory accordingly, once that is done lets re-evaluate current content and look for optimizations, reduce duplication, clean up tone, its a little too punchy at times (sometimes it works, since we want short titles, but overall if a user reads a lot of content, the vibe it gives off is punchy, I wonder how we can combat this)