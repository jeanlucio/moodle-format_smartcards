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
