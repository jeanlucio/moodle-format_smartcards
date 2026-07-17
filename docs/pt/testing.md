# 🧪 Testes Automatizados

O SmartCards vem com uma suíte de testes PHPUnit cobrindo renderização, lógica de negócio, web
services, backup/restore, e conformidade com a API de Privacidade. Todo push no CI roda contra a
matriz completa (Moodle 4.5 → 5.2, PHP 8.2 → 8.4, PostgreSQL e MariaDB).

### Testes de Output e Conteúdo (`tests/output/`, `tests/observer_test.php`, `tests/hook_listener_test.php`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `output/courseformat/content_test.php` | 24 |
| `output/courseformat/content/section/controlmenu_test.php` | 3 |
| `observer_test.php` | 3 |
| `hook_listener_test.php` | 1 |
| **Subtotal** | **31** |

### Testes de Lógica de Negócio Local (`tests/local/`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `appearance_repository_test.php` | 17 |
| `appearance_image_store_test.php` | 9 |
| `card_builder_test.php` | 11 |
| `section_card_builder_test.php` | 5 |
| `status_resolver_test.php` | 7 |
| `cm_completion_resolver_test.php` | 4 |
| `cm_description_resolver_test.php` | 4 |
| `section_progress_resolver_test.php` | 4 |
| **Subtotal** | **61** |

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `save_section_appearance_test.php` | 12 |
| `save_appearance_test.php` | 10 |
| `get_appearance_test.php` | 6 |
| `get_section_appearance_test.php` | 6 |
| `toggle_section_test.php` | 3 |
| **Subtotal** | **37** |

### Testes de Backup, Restore e Privacidade

| Arquivo de teste | Casos |
|-------------------|------:|
| `backup/backup_restore_test.php` | 2 |
| `privacy/provider_test.php` | 2 |
| **Subtotal** | **4** |

| **Total geral** | **133** |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php course/format/smartcards
```

### 📊 Cobertura de Testes

Medida com `moodle-coverage` (PHPUnit + Xdebug, escopo em `classes/`). Toda classe de teste usa
uma anotação `@covers` no nível de classe, então a cobertura de um método é atribuída corretamente
mesmo quando ele só é alcançado por outro método da mesma classe (ex.: `save_for_activity()`
delegando para o `save_for_item()` privado, ou `execute_returns()`/`execute_parameters()` de uma
função externa ao lado de `execute()`).

**Resumo:** Classes 25,00% (7/28) · Métodos 38,46% (40/104) · Linhas 65,46% (868/1326)

| Classe | Métodos | Linhas |
|--------|--------:|-------:|
| `local\appearance_repository` | 73,68% (14/19) | 92,71% (89/96) |
| `local\appearance_image_store` | 38,46% (5/13) | 50,78% (65/128) |
| `local\card_builder` | 0,00% (0/1) | 96,10% (74/77) |
| `local\section_card_builder` | 33,33% (1/3) | 70,18% (40/57) |
| `local\status_resolver` | 100,00% (1/1) | 100,00% (23/23) |
| `local\cm_completion_resolver` | 100,00% (1/1) | 100,00% (18/18) |
| `local\cm_description_resolver` | 100,00% (2/2) | 100,00% (15/15) |
| `local\section_progress_resolver` | 100,00% (1/1) | 100,00% (14/14) |
| `external\save_section_appearance` | 66,67% (2/3) | 99,21% (125/126) |
| `external\save_appearance` | 66,67% (2/3) | 99,25% (133/134) |
| `external\get_appearance` | 33,33% (1/3) | 59,18% (29/49) |
| `external\get_section_appearance` | 25,00% (1/4) | 58,49% (31/53) |
| `external\toggle_section` | 33,33% (1/3) | 75,00% (15/20) |
| `output\courseformat\content` | 20,00% (1/5) | 90,17% (156/173) |
| `output\courseformat\content\cm\controlmenu` | 66,67% (2/3) | 93,75% (15/16) |
| `output\courseformat\content\section\controlmenu` | 50,00% (1/2) | 46,15% (12/26) |
| `observer` | 100,00% (2/2) | 100,00% (4/4) |
| `hook_listener` | 100,00% (1/1) | 100,00% (9/9) |
| `privacy\provider` | 100,00% (1/1) | 100,00% (1/1) |

```bash
moodle-coverage course/format/smartcards
```
