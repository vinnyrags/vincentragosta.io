## HIGH PRIORITY
- excerpts for pages in DB, no need to reflect the nous tone/voice/identity, just casual yet professional and confident language.
- update the tags on posts to have a max of 10, also re-evaluate where we are aplying the nous-accent in the post title, it should be on the darkest connotated word in the heading.
- I want to add an "Interested in Nous? (or some flavor of this, i dont actually like what i just wrote all that much, hoping you can recommend something cool and enticing, and dark)" to the blog page somewhere, perhaps on a new page, either way I want some descriptive text for Nous, not terribly long, maybe 2-3 paragraphs max (try for 2, 4-5 sentences each paragraph).
- backend plugin to house backend functionality and lighten up the parent theme to just theme-related things
- split repos, configure satis on production server, packages.vincentragosta.io
- consider removing content directory on server after git push, I mainly use the content directory for my interaction with claude, and that is not present on the server-- should exist in git but not on servers (both develop and main)

## MEDIUM PRIORITY

## LOW PRIORITY
- comb through the css in both child and parent theme and make sure we are not using hard coded values where a theme.json css variable would apply.
- consider having each PostProvider create its own category taxonomy, right now I am in the situation wheree blog and project categories are meshing, ideally they would have their own subset of categories. we may need to consider a strategy to move categories currently assigned to posts to the new project category after we do this.
- consider throwing detailed exceptions for core backend functionality especially, but to the backend functionality as a whole, having a good error logging system should also be at the forefront when developing (at least in my platform/system), what are your thoughts on adding exception logs to the backend code? lets do a deep dive to see what this would take and write up an effective and efficient implementation plan in plan mode please.
- consider wrapping code block in a container with background and border, and then OR even add code block to table further down on framework page
- re-evaluate the current build process, ensure we are being as optimal as we can be
- update CMS to use patterns and not one-offs (rename to pattern names, offenders are the pages)
- clean offending project images, only a handful, probably need custom work, similar to what we did for absupplies logo
- can we do something cool where on page load it looks like each character on the page is being decoded before appearing, this may be something outlandish but I am curious what the lift for this would be?
- abstract footer margin-block-start to CMS control at the page level (acf on the page)