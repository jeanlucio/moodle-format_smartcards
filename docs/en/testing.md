# 🧪 Automated Tests

SmartCards ships with a PHPUnit test suite covering rendering, business logic, web services,
backup/restore, and Privacy API compliance. Every CI push runs against the full matrix (Moodle
4.5 → 5.2, PHP 8.2 → 8.4, PostgreSQL & MariaDB).

### Course Format Class Tests (`tests/lib_test.php`)

| Test file | Cases |
|-----------|------:|
| `lib_test.php` | 19 |
| **Subtotal** | **19** |

### Output & Content Tests (`tests/output/`, `tests/observer_test.php`, `tests/hook_listener_test.php`)

| Test file | Cases |
|-----------|------:|
| `output/courseformat/content_test.php` | 27 |
| `output/courseformat/content/cm/controlmenu_test.php` | 2 |
| `output/courseformat/content/section/controlmenu_test.php` | 3 |
| `observer_test.php` | 3 |
| `hook_listener_test.php` | 1 |
| **Subtotal** | **36** |

### Local Business-Logic Tests (`tests/local/`)

| Test file | Cases |
|-----------|------:|
| `appearance_repository_test.php` | 23 |
| `card_builder_test.php` | 23 |
| `appearance_image_store_test.php` | 9 |
| `appearance_style_resolver_test.php` | 7 |
| `section_card_builder_test.php` | 7 |
| `status_resolver_test.php` | 7 |
| `cm_description_resolver_test.php` | 6 |
| `cm_completion_resolver_test.php` | 4 |
| `section_progress_resolver_test.php` | 4 |
| **Subtotal** | **90** |

### Web Services Tests (`tests/external/`)

| Test file | Cases |
|-----------|------:|
| `save_appearance_test.php` | 14 |
| `save_section_appearance_test.php` | 13 |
| `get_appearance_test.php` | 7 |
| `get_section_appearance_test.php` | 6 |
| `toggle_section_test.php` | 3 |
| **Subtotal** | **43** |

### Backup, Restore & Privacy Tests

| Test file | Cases |
|-----------|------:|
| `backup/backup_restore_test.php` | 2 |
| `privacy/provider_test.php` | 2 |
| **Subtotal** | **4** |

| **Grand Total** | **192** |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php course/format/smartcards
```

**Line coverage by class (PHPUnit + Xdebug):**

| Class | Line coverage |
|-------|:-------------:|
| `format_smartcards` (lib.php class) | 19% |
| `format_smartcards_inplace_editable()` (lib.php function) | 100% |
| `format_smartcards_pluginfile()` (lib.php function) | 68% |
| `local\appearance_repository` | 94% |
| `local\appearance` | 100% |
| `local\appearance_palette` | 100% |
| `local\appearance_image_store` | 95% |
| `local\appearance_style_resolver` | 95% |
| `local\card_builder` | 100% |
| `local\section_card_builder` | 98% |
| `local\status_resolver` | 100% |
| `local\cm_status` | 100% |
| `local\cm_completion_resolver` | 100% |
| `local\cm_completion` | 100% |
| `local\cm_description_resolver` | 100% |
| `local\section_progress_resolver` | 100% |
| `local\section_progress` | 100% |
| `external\save_section_appearance` | 99% |
| `external\save_appearance` | 99% |
| `external\get_appearance` | 100% |
| `external\get_section_appearance` | 100% |
| `external\toggle_section` | 95% |
| `output\courseformat\content` | 98% |
| `output\courseformat\content\cm\controlmenu` | 94% |
| `output\courseformat\content\controlmenu_insert` | 88% |
| `output\courseformat\content\section\controlmenu` | 73% |
| `output\renderer` | 25% |
| `observer` | 100% |
| `hook_listener` | 100% |
| `privacy\provider` | 100% |
| **Overall** | **86%** |

> `output\renderer`'s title methods (`section_title()`/`section_title_without_link()`) are
> only exercised by a real rendered page (Behat) — no PHPUnit test here reaches them.

> `format_smartcards`'s low score is mostly two things outside a unit test's practical reach:
> `extend_course_navigation()` needs a real `global_navigation`/`navigation_node` pair (built by
> a full page load, not constructible in isolation), and `course_format_options()`'s edit-form
> branch is guarded by a function-static cache that — once populated by an earlier, unrelated
> course-creation call anywhere in the same PHPUnit process — never runs again for the rest of
> that process, so the one PHPUnit run that does exercise it doesn't get credit for it. Every
> other method on the class (the simple capability getters, `get_section_name()`,
> `get_default_section_name()`, `get_view_url()`, `page_set_course()`) is directly tested in
> `tests/lib_test.php`.

> `lib.php`'s two global functions are not part of the `format_smartcards` class, so a
> class-level `@covers \format_smartcards` does not credit them — they need their own bare
> `@covers ::functionName` target (no class name before the `::`), which PHPUnit's code-unit
> mapper resolves as a plain function lookup once the method lookup for an empty class name
> fails. `format_smartcards_inplace_editable()` is fully covered; `format_smartcards_pluginfile()`
> sits at 68% because its "file actually served" branches end in `send_stored_file()`, which
> `die()`s on success with no safe way to intercept that in-process — left to Behat instead.

> The one navstyle=sectioncards branch not reachable here (`content/section/controlmenu`'s
> Moodle 4.5-only legacy menu-item shape) is exercised for real by the CI matrix's
> `MOODLE_405_STABLE` legs, not by this single-Moodle-version local run.

## Behat Acceptance Tests

SmartCards also ships a Behat suite driving the format end-to-end in a real browser: the
card grid, availability/completion badges, the status sheet, every navigation style, the
teacher-facing appearance editor, course settings, and a full backup/restore round trip.
Every CI push runs the full suite against the same Moodle 4.5 → 5.2 matrix as PHPUnit.

### Core Rendering (`tests/behat/`)

| Feature file | Scenarios |
|--------------|----------:|
| `grid_rendering.feature` | 3 |
| `availability_badges.feature` | 3 |
| `status_sheet.feature` | 4 |
| `completion_badges.feature` | 2 |
| **Subtotal** | **12** |

### Navigation Styles & Display Options

| Feature file | Scenarios |
|--------------|----------:|
| `navstyle_accordion.feature` | 3 |
| `navstyle_tabs.feature` | 2 |
| `navstyle_sticky.feature` | 1 |
| `navstyle_sectioncards.feature` | 2 |
| `navstyle_trail.feature` | 1 |
| `generalinstyle_option.feature` | 2 |
| `progress_display.feature` | 3 |
| **Subtotal** | **14** |

### Appearance Editor, Settings & Backup

| Feature file | Scenarios |
|--------------|----------:|
| `appearance_activity.feature` | 2 |
| `appearance_section.feature` | 2 |
| `appearance_course_defaults.feature` | 1 |
| `course_settings.feature` | 2 |
| `backup_restore.feature` | 1 |
| **Subtotal** | **8** |

| **Grand Total** | **34** |

```bash
moodle-plugin-ci behat --profile chrome
```
