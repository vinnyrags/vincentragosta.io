## HIGH PRIORITY
- start working on content

## MEDIUM PRIORITY
- frontend testing with jest? cypress? what would we want to test, our critical path is probably the path to the contact form and successfully sending an email
- DO LAST full code audit, revisit core concepts, see if we hold true to everything we tried to put forth, is there room for any optimizations, lets look at all php code first and then perform a separate audit with a separate plan for the frontend files.

## LOW PRIORITY
- consider dynamic search on project archive, search input bar to the left of the filters
- if you were to fully evaluate my current build process against 2026 best wordpress build process, what would you recommend i change or update to become more in line with those standards?
- audit codebase to ensure we have no stale code/artifacts, while the backend (everything in /src) definitely needs to be audited, lets look especially at the frontend assets in all of these providers in both the parent and child themes
- update readmes, is it overkill with how many readmes we have? should we consolidate anywhere? should we have one big root directory read me that talks about everything? and let the code speak for itself with the directory readmes. I am leaning this way after building this entire project and littering the entire project with readmes, seems a bit tough to manage.



- do i need Config/container.php in my child theme?
- i dont like the idea of referencing partials directory from a provider, the twig ttemplate code should live with the provider, same thing for dropdown.twig this should exist with the code (I am assuming is in theme provider?)
- is there anything in theme provider that could maybe be its own provider?
- the current setup for providers/blocks block directory doesnt quite make sense, we have frontend and templates, well templates is considered frontend-- i know what I was trying to do but i think I am outgrowing it, lets remove the directories and go back to all of the files dumped in the block directory (effectively removing frontend and templates directories within the block directory). lets build out an efficient and effective implementation plan to address this work.
- consider moving projects archive to CMS? what are pros and cons to this-- lets consider current setup, and community best practices.