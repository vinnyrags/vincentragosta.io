## HIGH PRIORITY
- (HARD) project detail (continued)
- (HARD) check project aspect ratio with different sort selected on projects page

## MEDIUM PRIORITY
- (HARD) frontend testing with jest?

## LOW PRIORITY
- (HARD) optimize build process
- (EASY) audit codebase to ensure we have no stale code/artifacts, while the backend (everything in /src) definitely needs to be audited, lets look especially at the frontend assets in all of these providers in both the parent and child themes
- (HARD) js functionality to read all headings with id on them and have a visual scroll bar with buttons that deeplink to those sections on the page (do this on project detail template only)
- (EASY) update readmes, is it overkill with how many readmes we have?
- (HARD) is there anything in the child theme that could go into the parent theme?
- (HARD) are we staying true to our DI principles and avoiding additional constructor behavior (deferring to init/register/bootstrap methods) across all of our php in both the child and parent themes? are there opportunities for improvement? lets enter plan mode to do a deep dive on what potentially needs to be fixed and the best and most efficient way to solve it.
- can we come up with a clever solution for the margin-block-start on the footer on the project detail template, we dont want it to apply here