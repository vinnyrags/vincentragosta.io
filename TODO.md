## HIGH PRIORITY
- reconsider filtered view by post type on search, should almost be a refresh when the post type is clicked (pagination should be intertwined with the new subset returned), does this make sense?
- turn off nous signal posts category links or consider doing what we did for the projects block with the filter and sort dropdowns and search
- light mode url search bar on mobile issue when toggling, remains red
- nous signal flash issue on mobile (menu item when in light mode)
- active state in light mode filter pills should have background filled
- change X icon to Github in footer
- create me repo, add leads, interviews, content to it, add to satis and install in project-- determine if this is a good place to house claude stuff, like what claude knows about me, my gut says no and that claude content should live in a higher folder so that it would get pulled into all projects, but that also has me thinking about where to put this me repo as well, perhaps in a similar file architecture outside of this repo, and claude would just know about it through all project context-- let me know what you think about this
- create starter repo that has project root plus child theme, rework claude.md to ensure no specific references to vincentragosta.io project, it will more than likely not have any information on me either, i.e, access to the content directory, docs directory or interviews directory, so if there are references to how I think or want claude to think/behave, then we should port that information over to claude.md.
- consider removing content directory on server after git push, I mainly use the content directory for my interaction with claude, and that is not present on the server-- should exist in git but not on servers (both develop and main)
- consider adding bit to framework page about the code is publicly accessible via packages.vincentragosta.io, is there an opportunity to talk about Mythus and IX as well? take a look at the content/pages/framework.html (prompt me to confirm if I updated this before doing anything), and look for opportunities to update the language and/or add or update any new sections

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