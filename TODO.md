## HIGH PRIORITY
- what are cool things we can do in twitch (obs) to prepare for our twitch stream (both gaming and card night)
- draft we are back announcement
- add tiktok feed to shop page via same plugin smashballoon that we use for instagram
- finish sign up with carter pulse, need creator hub code
- investigate top discord bots/apps we could add to server to make it more fun, or enhance my experience
- i need to start working on spotify streaming playlists for the varying segments of my livestream, what do you suggest, is there a spotify api available to use that we can automate much of this?
- can i stream tiktok studio from my ipad? I have access to my mac book pro, maybe I can work something off of that? ideally i just uses my ipad to simulcast to four different streaming platforms from just the one device, that would be completely optimal
- can we research cheap shipping supplies, I have been going to staples for all of my shiping supplies and it has been expensive. I like the card mailers that had the bubble wrap inside of them.
- talk with agent about how i have a very in depth pokemon card collection (and even some really cool yu gi oh cards), we should factor displaying a portion of them each livestream into our planning
- talk with agent about how i have roughly 1K cards ready to be sold spanning from pokemon, anime and mature single cards, ranging from $1-$1000, how can we factor this into our livestream planning, I assume !sell will work fine for this
- talk about potential to only be able to stream from ipad out of the gate, what would that look like for the business, and if we cant use something like restream to simulcast what platform should I be streaming on?
- talk about table strategy, what to display for the varying parts of the livestream, be sure to update relevant documents in akivili
- sort by language as additional dropdown in products block
- new image for one piece vol 5

- consider reworking coupons to support multiple coupons at once (we actually may not want to do this)
- can we change !capture command to !moment instead, and update all docs and discord messaging and tests accordingly

- can folks react to a minecraft message to have the minecraft server invitation link DM'ed to them via nous? we should have messaging around this in discord (like how to join the various minecraft SMPs that we have, maybe in #welcome), this should probably live somewhere practical, maybe there is an opportunity to combine some channels, as it stands we have #roles, #verify and now there is a potential to add another channel here for a single opt-in use, I want to protect the user experiencee at all costs, I dont want them to have to go through 100 hoops to get access to things but I also understand we have limitations in discord. i am curious to the plan you come up with for this as this would be a great addition to the server. I also have three servers at the moment that we have running that folks can get an invitation too, java hardcore survival, bedrock horror survival and our OG creative server (that has existed for over 5 years). now that i think about it we should probably repurpose #minecfraft channel to have it be react only, and we do our invitation reactions/messaging there, if there are any messages currently in the channel we should remove them to prepare for just these interactions.
- can we setup a script to hook into my itzenzoTTV google sheets doc, specifically the Singles tab, and help build out this data. can we add two new columns, one for set number (the number the card is in its set), and another for set (the name of the set the card belongs too), are you able to help me populate these values for all 344 current values we have. by the time I am done with this I should have close to 500 cards in this list. we have access to google sheets, you can check the makefile sync products command to see how we are gaining access to google sheets

- create singles shop (without pictures), folks should be able to buy singles from my shop, in discord they should be able to request to show a single, create queue entry to review/look for a card for review for a customer

- if i never ship orders, but the week passes for domestic orders, will the system still reset on the Monday, and charge the buyer again if they bought in for the new week, I also want to know if I wanted to waive shipping for the next week for someone, what command I would run to complete this. I am just confirming process with these questions, no need to change any code lets just have a conversation about this.
- refund should kill the shippingeasy order (if possible, and if this makes sense)

- create community page menu item, on the new page we talk about
- font family for headings in page section content should be the same font family we use in the header nav links

## MEDIUM PRIORITY
- switch stripe over to live mode
- we made an update to social icons hook in theme provider in the child theme that we want to move to the parent theme (confirm this action beforehand even though I cant think of any reason why we wouldnt want all social links to open in a new tab)
- if we were to review akivili in its entirety, everything in it vs the content/pages and content/projects, do you see opportunity to update the content on the current website with anything else that we have done and/or worked on that would better the cause on my website to get hired for web development business work, anything pertaining to server deployment stuff, any of the new make commands we have done, anything related to stripe? the card business? I was not sure I wanted to expose the shop in my global nav, to try to keep a clear separation ebtween the shop and the rest of my web development work, but maybe there is a fun and clever play we could even do-- I dont know about this but I could be persuaded if you make a compelling argument. I look forward to your assessment on this.
- create starter repo that has project root plus child theme, rework claude.md to ensure no specific references to vincentragosta.io project, it will more than likely not have any information on me either, i.e, access to the content directory, docs directory or interviews directory, so if there are references to how I think or want claude to think/behave, then we should port that information over to claude.md.
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