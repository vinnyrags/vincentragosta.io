## HIGH PRIORITY
- re-evaluate current content and look for optimizations, reduce duplication, clean up tone, too punchy
- consider a blog that ai can automate/schedule posts for
- file architecture for assets, Hooks, Features within a provider still bothers me, and I could totally be wrong for feeling this way
- consider changing Theme.php to something else (inside of src/), is sort of confusing with the theme provider infrastructure, i could also be persuaded against feeling this way if you felt passionate about keeping it as Theme.php, since it does make the most sense.
- consider updating js frontend to use a more object oriented approach (with classes)
- any other opportunities for Factory classes? similar to what we do for IconServiceFactory, is there anywhere in the code base that would warrant/benefit from a Factory pattern approach?
- setup mx record so i can get emails at hello@vincentragosta.io

## MEDIUM PRIORITY

## LOW PRIORITY
- consider dynamic search on project archive, search input bar to the left of the sort, we also need filters for categories
- consider adjusting php convention where, whenever we are referencing a class, we should uppercase the first letter, for example the project repository, any reeference to that should be $Repo, instead of $repo, I can be swayed against this though if this violates any rules we are trying to abide too. please let me know your thoughts