## HIGH PRIORITY
- add a threshold to the flash, so people dont cause themselves harm
- I want to add an "Interested in Nous?" to the blog page somewhere, perhaps on a new page, either way I want some descriptive text for Nous, not terribly long, maybe 2-3 paragraphs max (try for 2, 4-5 sentences each paragraph).
- need vertical scroll in header if height is not tall enough, right now it clips, the ability to scroll on the header should feel natural, that said we should reduce the gap spacing by half, that should get us out of the woods but the system should fail gracefully which in this case would be the vertical scroll
- VR favicon in our accent green please, same font as current logo in header
- teaser section on homepage for nous signal, should flicker when coming into view (pulse red), and have the red highlight text for underline etc. probably need a class to slap on the core/group block that will change accent color.
- update staging content, before going live with blog stuff

- re-evaluate parent theme code, and ensure nothing in there is opinionated what so ever, lets flag it if it is even the slightest bit opinionated please for review. i would like to see all of the offenders and I will tell you which to move to the child theme.
- consider backend plugin to house backend functionality and lighten up the parent theme to just theme-related things
- split repos, configure satis on production server, packages.vincentragosta.io
- configure relevanssi, get search page working, create either integrated search results (between projects and blog), or have two separate sections
- remove content directory on server after git push, should exist in git but not on servers (both develop and main)

## MEDIUM PRIORITY

## LOW PRIORITY
- consider having each PostProvider create its own category taxonomy, right now I am in the situation wheree blog and project categories are meshing, ideally they would have their own subset of categories. we may need to consider a strategy to move categories currently assigned to posts to the new project category after we do this.
- consider throwing detailed exceptions for core backend functionality especially but to the backend functionality as a whole, having a good error logging system should also be at the forefront when developing (at least in my platform/system), what are your thoughts on adding exception logs to the backend code? lets do a deep dive to see what this would take and write up an effective and efficient implementation plan in plan mode please.
- consider wrapping code block in a container with background and border, and then OR even add code block to table further down on framework page
- re-evaluate the current build process, ensure we are being as optimal as we can be
- update CMS to use patterns and not one-offs (rename to pattern names, offenders are the pages)
- clean offending project images, only a handful, probably need custom work, similar to what we did for absupplies logo
- can we do something cool where on page load it looks like each character on the page is being decoded before appearing, this may be something outlandish but I am curious what the lift for this would be?
