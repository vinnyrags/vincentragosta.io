- re-evaluate current content and look for optimizations, reduce duplication, clean up tone, too punchy

## HIGH PRIORITY
- consider a blog that ai can automate/schedule posts for
- setup mx record so i can get emails at hello@vincentragosta.io
- is double tabbing in the project card silly, both link to the same place, is this a violation? should we set tabindex -1 on the image maybe?
- consider dynamic search on project archive, search input bar to the left of the sort, we also need filters for categories
- if i wanted to push my current local database to replace the staging environment (staging.vincentragosta.io), would make push-staging do this safely with all replacements and what not?
- consider updating related projects logic to pull same year, same category first, the related posts are mainly all the same since they return the latest of that same category, i am curious to your thoughts on this first before we make any adjustments

## MEDIUM PRIORITY

## LOW PRIORITY
- consider adjusting php convention where, whenever we are referencing a class, we should uppercase the first letter, for example the project repository, any reeference to that should be $Repo, instead of $repo, I can be swayed against this though if this violates any rules we are trying to abide too. please let me know your thoughts
- file architecture for assets, Hooks, Features within a provider still bothers me, and I could totally be wrong for feeling this way
- consider changing Theme.php to something else (inside of src/), is sort of confusing with the theme provider infrastructure, i could also be persuaded against feeling this way if you felt passionate about keeping it as Theme.php, since it does make the most sense.
- consider updating js frontend to use a more object oriented approach (with classes)
- any other opportunities for Factory classes? similar to what we do for IconServiceFactory, is there anywhere in the code base that would warrant/benefit from a Factory pattern approach?
- challenge current design system against accessibility driven css, are we targetting various accessible states or roles where applicable?