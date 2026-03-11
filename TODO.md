## HIGH PRIORITY
- consider creating a blog that ai can automate/schedule posts for
- configure relevanssi, get search page working, create either integrated search results (between projects and blog), or have two separate sections
- setup mx record so i can get emails at hello@vincentragosta.io, is it possible to have emails that go to that domain, forward to my vincentpasqualeragosta@gmail.com email? lets work up an efficient and effective implementation plan to configure this for my production server please (lets do this in plan mode)
- re-evaluate current content and look for optimizations, reduce duplication, clean up tone, too punchy
- add section containing skills overview to about page, place where we will drop technical jargon, need to think of a clean way to display a lot of skills in a clean and efficient manner, I am open to suggestions on how to best represent this data, ideally we make use of existing core gutenberg blocks (even if we need to re-enable some), if we cant figure out a clean solution with existing blocks, we could potentially create something custom. lets enter plan mode and work up an effective and efficient implementation plan on this after doing a deep dive on some research on how to best approach this.

## MEDIUM PRIORITY

## LOW PRIORITY
- consider adjusting php convention where, whenever we are referencing a class, we should uppercase the first letter, for example the project repository, any reeference to that should be $Repo, instead of $repo, I can be swayed against this though if this violates any rules we are trying to abide too. please let me know your thoughts
- file architecture for assets, Hooks, Features within a provider still bothers me, and I could totally be wrong for feeling this way
- consider changing Theme.php to something else (inside of src/), is sort of confusing with the theme provider infrastructure, i could also be persuaded against feeling this way if you felt passionate about keeping it as Theme.php, since it does make the most sense.
- consider updating js frontend to use a more object oriented approach (with classes)
- any other opportunities for Factory classes? similar to what we do for IconServiceFactory, is there anywhere in the code base that would warrant/benefit from a Factory pattern approach?
- challenge current design system against accessibility driven css, are we targetting various accessible states or roles where applicable?
- update CMS to use patterns and not one-offs