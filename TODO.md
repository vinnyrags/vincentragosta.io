## HIGH PRIORITY
- consider removing that site-wide spacing|70 to all core/groups with background, or rather at the very elast have it be overrideable from the CMS, maybe test this one more timne before executing with claude
- build more generic modal system for age gate, that any provider, block or template can hook into and display their own messaging (trigger their own modal), can be on page load or via a trigger. please do a deep dive on how to best solve for this, and work up an effective and efficient implementation plan in plan mode and ultimately circle back and update the age gate code to reference the new modal system.
- should we add a bit anywhere on the site about our current server setup?
- create starter repo that has project root plus child theme, rework claude.md to ensure no specific references to vincentragosta.io project, it will more than likely not have any information on me either, i.e, access to the content directory, docs directory or interviews directory, so if there are references to how I think or want claude to think/behave, then we should port that information over to claude.md.

## MEDIUM PRIORITY

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