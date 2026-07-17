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

### 📊 Code Coverage

Measured with `moodle-coverage` (PHPUnit + Xdebug, `classes/` scope). A class-level `@covers`
annotation is used on every test class, so a method's coverage is attributed correctly even when
it is only reached through another method of the same class (e.g. `save_for_activity()` delegating
to the private `save_for_item()`, or an external function's `execute_returns()`/
`execute_parameters()` alongside `execute()`).

**Summary:** Classes 25.00% (7/28) · Methods 38.46% (40/104) · Lines 65.46% (868/1326)

| Class | Methods | Lines |
|-------|--------:|------:|
| `local\appearance_repository` | 73.68% (14/19) | 92.71% (89/96) |
| `local\appearance_image_store` | 38.46% (5/13) | 50.78% (65/128) |
| `local\card_builder` | 0.00% (0/1) | 96.10% (74/77) |
| `local\section_card_builder` | 33.33% (1/3) | 70.18% (40/57) |
| `local\status_resolver` | 100.00% (1/1) | 100.00% (23/23) |
| `local\cm_completion_resolver` | 100.00% (1/1) | 100.00% (18/18) |
| `local\cm_description_resolver` | 100.00% (2/2) | 100.00% (15/15) |
| `local\section_progress_resolver` | 100.00% (1/1) | 100.00% (14/14) |
| `external\save_section_appearance` | 66.67% (2/3) | 99.21% (125/126) |
| `external\save_appearance` | 66.67% (2/3) | 99.25% (133/134) |
| `external\get_appearance` | 33.33% (1/3) | 59.18% (29/49) |
| `external\get_section_appearance` | 25.00% (1/4) | 58.49% (31/53) |
| `external\toggle_section` | 33.33% (1/3) | 75.00% (15/20) |
| `output\courseformat\content` | 20.00% (1/5) | 90.17% (156/173) |
| `output\courseformat\content\cm\controlmenu` | 66.67% (2/3) | 93.75% (15/16) |
| `output\courseformat\content\section\controlmenu` | 50.00% (1/2) | 46.15% (12/26) |
| `observer` | 100.00% (2/2) | 100.00% (4/4) |
| `hook_listener` | 100.00% (1/1) | 100.00% (9/9) |
| `privacy\provider` | 100.00% (1/1) | 100.00% (1/1) |

```bash
moodle-coverage course/format/smartcards
```
