## HIGH PRIORITY
- what are cool things we can do in twitch (obs) to prepare for our twitch stream (both gaming and card night)
- what are cool things we can do in tiktok studio to prepare for gaming
- draft we are back announcement
- add tiktok feed to shop page via same plugin smashballoon that we use for instagram
- consider giveaway system, out of the gate we probably wont use this much but this was a core part of my business a few years ago, this was mainly run on social platforms to garner engagement and to have them join discord/livestreams, we should maybe build in a !giveaway command or something, I used to collect entries on social posts and manually put them into lists, and on the designated days run a wheel spin or duck race to denote the winner on a livestream, with all of this context, what do you feel we could do for this?
- finish sign up with carter pulse, need creator hub code
- investigate top discord bots/apps we could add to server to make it more fun, or enhance my experience
- i need to start working on spotify streaming playlists for the varying segments of my livestream, what do you suggest, is there a spotify api available to use that we can automate much of this?

- how would i offer folks a discount voucher to use at checkout lets say, i assume maybe i would need to run an enable command passing in the argument that is the voucher and discount amount maybe, and then a command to turn it off and folks who use it during that time would benefit from the voucher, not sure if this is how that would work.

- can i stream tiktok studio from my ipad? I have access to my mac book pro, maybe I can work something off of that? ideally i just uses my ipad to simulcast to four different streaming platforms from just the one device, that would be completely optimal
- i have a buddy who lives in canada that will have to pay $20/$25 to receive their product, since the shipping is much higher, I usually wait a month before I send him his order, how can we factor this scenario/experience into everything we have built thus far, how would I serve him the correct shipping without causing him nor myself any inconvenience.
- build testing plan to run through all custom discord commands to fully test the suite (manually), pleasse write out a stepwise list detailing what I should do to thoroughly test the suite of commands
- make sure youtube account is referencing itzenzottv@gmail.com
- can we research cheap shipping supplies, I have been going to staples for all of my shiping supplies and it has been expensive. I like the card mailers that had the bubble wrap inside of them.
- what is the muprhy-tax channel, a channels for dog posts? if so lets make that more clear in the description, even if this is not what the channel was intended to be, lets make the description more clear because I read it and was confused (that could just be a me thing)
- does my discord doc accurately reflect the current state of my discord?
- talk with agent about how i have a very in depth pokemon card collection (and even some really cool yu gi oh cards), we should factor displaying a portion of them each livestream into our planning
- talk with agent about how i hvae roughly 1K cards ready to be sold spanning from pokemon, anime and mature single cards, ranging from $1-$1000, how can we factor this into our livestream planning, I assume !sell will work fine for this
- pull box commands to checkout sessions with stripe, perhaps we can just use !sell here, this is actually probably the move, pull boxes are anywhere from $1 - $5
- talk about potential to only be able to stream from ipad out of the gate, what would that look like for the business, and if we cant use something like restream to simulcast what platform should I be streaming on?
- sort by language as additional dropdown in products block, add language in spreadsheet, and update on production
- add note under shop that i sell card singles, playmats and binders as well, message/join us in discord (linked with invite link) for more information
- talk about table strategy, what to display for the varying parts of the livestream, be sure to update relevant documents in akivili
- confirm emailing is working, how can we test this? where are we sending notifications in the current system?
- on !offline, am I getting a post mortem anywhere, total sales on stripe for the night and other useful information, do we need a new channel for this sort of information under command center or can we funnel this information into an existing channel. i like the idea of a feed channel for information that wont get lost in conversation
- build more generic modal system for age gate, that any block or template can use or extend to display their own messaging (trigger their own modal), can be on page load or via a trigger. please do a deep dive on how to best solve for this, and work up an effective and efficient implementation plan in plan mode and ultimately circle back and update the age gate code to reference the new modal system.
- now that shop and functionality is pretty well built and we have real product data in the shop and in stripe, what can we do to start to work on a livestream strategy with what we have available, part two to this is a content strategy, make sure to do a cost assessment with what we have available, and what we need to do to put our best foot forward in content and livestreaming.
- new image for one piece vol 5
- clean up wordpress importer on production (if we need it lets install it via composer in git)
- confirm !sell and !list will work for playmats, I also sell those adhoc during the live streams and dont necessarily want to put them in my shop (fairly explicit material on them), they range from $20-$50
- future proof the db sizing, a year from now will i run into issues with all of my data from these commands in the sqlite db
- consider abstracting itzenzottv functionality (shop + bot) into its own repo. something to keep in mind, down the line I feel like I will create a shop.vincentragosta.io subdomain and host my shop there, when doing this, I would create an itzenzottv theme on a separate project install. i would want the naming of this abstraction that I am asking of you to make sense here, when we pull the code in for this new project it should all make sense within the context of this new website and the context of its current location (vincentragosta.io theme)

## MEDIUM PRIORITY
- switch stripe over to live mode
- nous signal blog block will only display the current months blog posts in the block, we need to think through a way to check an archived page that has different months listed on the page that would maybe link to another page with the blog block on it that would showcase that months posts, I dont necessarily like this flow, there are too many steps but I also want to be careful about displaying a ton of data at once on a page while taking into account pagination or lazy loading it could get hairy, so maybe iour first step is thinking through this and how we can best sset this up. I think its a great idea and keeps the initial nous-signal page lean, 30 posts max right? if folks want to further explore trhe archives they should be able to do so from the nous-signal page by clicking a button called Archives or something cleverly written by nous himself.
- we made an update to social icons hook in theme provider in the child theme that we want to move to the parent theme (confirm this action beforehand even though I cant think of any reason why we wouldnt want all social links to open in a new tab)
- if we were to review akivili in its entirety, everything in it vs the content/pages and content/projects, do you see opportunity to update the content on the current website with anything else that we have done and/or worked on that would better the cause on my website to get hired for web development business work, anything pertaining to server deployment stuff, any of the new make commands we have done, anything related to stripe? the card business? I was not sure I wanted to expose the shop in my global nav, to try to keep a clear separation ebtween the shop and the rest of my web development work, but maybe there is a fun and clever play we could even do-- I dont know about this but I could be persuaded if you make a compelling argument. I look forward to your assessment on this.
- create starter repo that has project root plus child theme, rework claude.md to ensure no specific references to vincentragosta.io project, it will more than likely not have any information on me either, i.e, access to the content directory, docs directory or interviews directory, so if there are references to how I think or want claude to think/behave, then we should port that information over to claude.md.
- rss feed of nous signal
- consider cleaning up the file architecture of akivili
- flash sale in discord/stripe, how can we integrate this into a livestream moment where an alarm goes off, and a random flash sale happens

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