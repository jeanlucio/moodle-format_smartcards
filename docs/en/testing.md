# 🧪 Automated Tests

SmartCards ships with a PHPUnit test suite covering rendering, business logic, web services,
backup/restore, and Privacy API compliance. Every CI push runs against the full matrix (Moodle
4.5 → 5.2, PHP 8.2 → 8.4, PostgreSQL & MariaDB).

### Output & Content Tests (`tests/output/`, `tests/observer_test.php`, `tests/hook_listener_test.php`)

| Test file | Cases |
|-----------|------:|
| `output/courseformat/content_test.php` | 24 |
| `output/courseformat/content/section/controlmenu_test.php` | 3 |
| `observer_test.php` | 3 |
| `hook_listener_test.php` | 1 |
| **Subtotal** | **31** |

### Local Business-Logic Tests (`tests/local/`)

| Test file | Cases |
|-----------|------:|
| `appearance_repository_test.php` | 17 |
| `appearance_image_store_test.php` | 9 |
| `card_builder_test.php` | 11 |
| `section_card_builder_test.php` | 5 |
| `status_resolver_test.php` | 7 |
| `cm_completion_resolver_test.php` | 4 |
| `cm_description_resolver_test.php` | 4 |
| `section_progress_resolver_test.php` | 4 |
| **Subtotal** | **61** |

### Web Services Tests (`tests/external/`)

| Test file | Cases |
|-----------|------:|
| `save_section_appearance_test.php` | 12 |
| `save_appearance_test.php` | 10 |
| `get_appearance_test.php` | 6 |
| `get_section_appearance_test.php` | 6 |
| `toggle_section_test.php` | 3 |
| **Subtotal** | **37** |

### Backup, Restore & Privacy Tests

| Test file | Cases |
|-----------|------:|
| `backup/backup_restore_test.php` | 2 |
| `privacy/provider_test.php` | 2 |
| **Subtotal** | **4** |

| **Grand Total** | **133** |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php course/format/smartcards
```

**Line coverage by class (PHPUnit + Xdebug):**

| Class | Line coverage |
|-------|:-------------:|
| `local\appearance_repository` | 93% |
| `local\appearance_image_store` | 51% |
| `local\card_builder` | 96% |
| `local\section_card_builder` | 70% |
| `local\status_resolver` | 100% |
| `local\cm_completion_resolver` | 100% |
| `local\cm_description_resolver` | 100% |
| `local\section_progress_resolver` | 100% |
| `external\save_section_appearance` | 99% |
| `external\save_appearance` | 99% |
| `external\get_appearance` | 59% |
| `external\get_section_appearance` | 58% |
| `external\toggle_section` | 75% |
| `output\courseformat\content` | 90% |
| `output\courseformat\content\cm\controlmenu` | 94% |
| `output\courseformat\content\section\controlmenu` | 46% |
| `observer` | 100% |
| `hook_listener` | 100% |
| `privacy\provider` | 100% |
| **Overall** | **65%** |

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
