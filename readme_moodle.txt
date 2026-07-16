Bootstrap Icons
================
Description: Curated subset of 22 icon SVGs (pix/bsicons/) offered as the "Library icon"
             appearance type in the card appearance picker.
Version: 1.13.1
License: MIT
Source: https://github.com/twbs/icons

Files included (Bootstrap Icons name -> local filename):
  award, book, bullseye, calendar-event, camera-video, chat-dots, clipboard-check,
  flag, gear, journal-text, lightbulb, map, mic, mortarboard, music-note, palette,
  pencil, people, puzzle, rocket, star, trophy
  (one .svg file per name, same name as upstream; "target" does not exist upstream,
  "bullseye" is the closest equivalent and was used instead)

Changes made to the original files:
- None. Each file is the unmodified SVG from the tagged release below.

To update this library:
1. Pick a release tag from https://github.com/twbs/icons/releases
2. For each icon name above, download:
   https://raw.githubusercontent.com/twbs/icons/<tag>/icons/<name>.svg
3. Replace the matching file in pix/bsicons/
4. Update the version number in this file and in thirdpartylibs.xml
5. To add a new curated icon, also add its slug to the ICONS constant in
   amd/src/appearance_picker.js and rebuild the AMD module (grunt amd)

Fredoka / Baloo 2 / Varela Round / Nunito / Comic Neue
========================================================
Description: Curated title fonts (fonts/*.woff2) offered as the "Title font"
             (labelfont) appearance option. Only the Regular (400) weight of each
             family is bundled — card titles never render bold, so a Bold file would
             be dead weight (Varela Round does not even offer a Bold face upstream).
License: OFL-1.1
Source (specimen pages):
  https://fonts.google.com/specimen/Fredoka
  https://fonts.google.com/specimen/Baloo+2
  https://fonts.google.com/specimen/Varela+Round
  https://fonts.google.com/specimen/Nunito
  https://fonts.google.com/specimen/Comic+Neue

Changes made to the original files:
- None. Each file is the unmodified "latin" subset WOFF2 Google serves for weight 400.

To update this library (per family):
1. Fetch the family's CSS from the Google Fonts API, requesting only weight 400:
   curl -A "Mozilla/5.0 ... Chrome/120.0.0.0 ..." \
     "https://fonts.googleapis.com/css2?family=<Family+Name>:wght@400&display=swap"
   (a modern desktop-browser User-Agent is required or the API serves an older,
   non-woff2 format)
2. In the response, find the @font-face block commented "/* latin */" (unicode-range
   starting U+0000-00FF — this covers Portuguese diacritics) and copy its src url().
3. Download that URL and replace the matching file in fonts/.
4. Update the version number (the "vNN" segment of the gstatic URL) in this file and
   in thirdpartylibs.xml.
