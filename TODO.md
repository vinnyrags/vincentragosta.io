## HIGH PRIORITY
- (HARD) project detail (continued)
- (HARD) check project aspect ratio with different sort selected on projects page

## MEDIUM PRIORITY
- (HARD) are we staying true to our DI principles and avoiding additional constructor behavior (deferring to init/register/bootstrap methods) across all of our php in both the child and parent themes? are there opportunities for improvement? lets enter plan mode to do a deep dive on what potentially needs to be fixed and the best and most efficient way to solve it.
- (EASY) update projects gutenberg block backend interface, since we now have acf is there a better approach to that experience in the editor interface
- (HARD) test core/cover to ensure we are not bulldozing other variations with our current css-- content right aligned position variants are being overriden
- (HARD) add other social icons like github

## LOW PRIORITY
- (HARD) optimize build process
- (EASY) make clean to clean build dependencies (factor into make update / build)
- (EASY) audit codebase to ensure we have no stale code/artifacts, while the backend (everything in /src) definitely needs to be audited, lets look especially at the frontend assets in all of these providers in both the parent and child themes
- (HARD) visual flourish animation pass over frontend, get recommendations
- (HARD) js functionality to read all headings with id on them and have a visual scroll bar with buttons that deeplink to those sections on the page
- (EASY) update readmes, is it overkill with how many readmes we have?
- (HARD) is there anything in the child theme that could go into the parent theme?