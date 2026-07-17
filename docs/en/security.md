# 🔐 Security & Compliance

* Capability-based access control (`format/smartcards:manageappearance`), required by both the
  activity and section appearance web services
* Every appearance web service resolves the received cmid/sectionid, derives the real course
  context, and calls `validate_context()` before any capability check — never operates on an
  isolated id without binding it to its actual course
* Web services are consumed via `core/ajax`, whose transport already includes and validates the
  session key automatically
* Uploaded card images are never stored as received: they are decoded, re-encoded as PNG, and
  size/dimension-capped before being written to the File API — ruling out SVG/polyglot payloads
  and stripping embedded metadata by construction
* File names are always fixed, never taken from the request — closing off any path-traversal
  surface when serving a card image
* A single emoji value is validated server-side (grapheme count + emoji-block check), never
  trusted from the browser's native picker alone
* Background/title colour and title font are validated against a curated, server-side palette —
  never accepted as free-form CSS
* Availability (locked/timed badges) is read exclusively from `cm_info`/`section_info`, never
  recalculated — the same restriction logic every other part of Moodle already enforces
* A restricted-but-visible section never leaks its activities' cards or progress — the same gate
  core itself applies to the activity list
* Moodle External API compliant
* Privacy API fully implemented — declared `null_provider`, since the format stores no personal
  data of its own (custom appearance is course/activity configuration, never tied to a student)
