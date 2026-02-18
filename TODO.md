## HIGH PRIORITY
- contact page
- content!!
- project archive cover at the top

## MEDIUM PRIORITY
- (HARD) frontend testing with jest?
- DO LAST - (HARD) full code audit, revisit core concepts, see if we hold true to everything we tried to put forth, is there room for any optimizations, lets look at all php code first and then perform a separate audit with a separate plan for the frontend files.

## LOW PRIORITY
- (HARD) optimize build process
- (EASY) audit codebase to ensure we have no stale code/artifacts, while the backend (everything in /src) definitely needs to be audited, lets look especially at the frontend assets in all of these providers in both the parent and child themes
- (HARD) js functionality to read all headings with id on them and have a visual scroll bar with buttons that deeplink to those sections on the page (do this on project detail template only)
- (EASY) update readmes, is it overkill with how many readmes we have?
- (HARD) is there anything in the child theme that could go into the parent theme?
- (HARD) are we staying true to our DI principles and avoiding additional constructor behavior (deferring to init/register/bootstrap methods) across all of our php in both the child and parent themes? are there opportunities for improvement? lets enter plan mode to do a deep dive on what potentially needs to be fixed and the best and most efficient way to solve it.
- (EASY) revisit the idea of not using gap and instead deferring to margin-block-start and override p tag globally since its spitting out 1em margin-block-start/end anyway, maybe this is the precedence we should follow. if you agree with this lets set this up and do a deep dive in the code to see where we could remove redundant code, places where we know we are receiving <p> tags from like project-detail case study sections for example. lets find all offenders of this new way of doing things and update them accordingly-- lets get an efficient plan set forth to accomplish this (plan mode)