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
- is there anything in the child theme that could go into the parent theme?
- are we staying true to our DI principles and avoiding additional constructor behavior (deferring to init/register/bootstrap methods) across all of our php in both the child and parent themes? are there opportunities for improvement? lets enter plan mode to do a deep dive on what potentially needs to be fixed and the best and most efficient way to solve it.
- reset <p> default styling, gets 1em margin-block-start/end
- accessibility pass, is our css setup to be accessibility driven (where applicable), lets do a deep dive on best practices and
- review theme.json in parent/child theme, make sure it makes sense (do this manually)