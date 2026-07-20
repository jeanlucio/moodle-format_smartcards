# 🧪 Testes Automatizados

O SmartCards vem com uma suíte de testes PHPUnit cobrindo renderização, lógica de negócio, web
services, backup/restore, e conformidade com a API de Privacidade. Todo push no CI roda contra a
matriz completa (Moodle 4.5 → 5.2, PHP 8.2 → 8.4, PostgreSQL e MariaDB).

### Testes de Output e Conteúdo (`tests/output/`, `tests/observer_test.php`, `tests/hook_listener_test.php`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `output/courseformat/content_test.php` | 27 |
| `output/courseformat/content/cm/controlmenu_test.php` | 2 |
| `output/courseformat/content/section/controlmenu_test.php` | 3 |
| `observer_test.php` | 3 |
| `hook_listener_test.php` | 1 |
| **Subtotal** | **36** |

### Testes de Lógica de Negócio Local (`tests/local/`)

| Arquivo de teste | Casos |
|-------------------|------:|
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

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `save_appearance_test.php` | 14 |
| `save_section_appearance_test.php` | 13 |
| `get_appearance_test.php` | 7 |
| `get_section_appearance_test.php` | 6 |
| `toggle_section_test.php` | 3 |
| **Subtotal** | **43** |

### Testes de Backup, Restore e Privacidade

| Arquivo de teste | Casos |
|-------------------|------:|
| `backup/backup_restore_test.php` | 2 |
| `privacy/provider_test.php` | 2 |
| **Subtotal** | **4** |

| **Total geral** | **173** |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php course/format/smartcards
```

**Cobertura de linhas por classe (PHPUnit + Xdebug):**

| Classe | Cobertura de linhas |
|--------|:-------------------:|
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
| **Geral** | **81%** |

> As linhas não cobertas de `output\renderer` são `section_title()`/`section_title_without_link()`
> — só alcançadas quando uma página real renderiza o cabeçalho de uma seção, nenhum teste
> unitário aqui chega lá; o construtor, que é exercitado, registra a capability extra de
> edição. Coberto de ponta a ponta pela suíte Behat abaixo.

> O único ramo do `navstyle=sectioncards` não alcançável aqui (o formato legado de item de
> menu exclusivo do Moodle 4.5 em `content/section/controlmenu`) é exercitado de verdade
> pelas pernas `MOODLE_405_STABLE` da matriz de CI, não por esta rodada local de uma única
> versão do Moodle.

## Testes de Aceitação Behat

O SmartCards também vem com uma suíte Behat que exercita o formato de ponta a ponta num
navegador real: o grid de cards, os badges de disponibilidade/conclusão, o status sheet,
todos os estilos de navegação, o editor de aparência voltado ao professor, as
configurações do curso, e um ciclo completo de backup/restore. Todo push no CI roda a
suíte completa contra a mesma matriz Moodle 4.5 → 5.2 do PHPUnit.

### Renderização Principal (`tests/behat/`)

| Arquivo de feature | Cenários |
|----------------------|---------:|
| `grid_rendering.feature` | 3 |
| `availability_badges.feature` | 3 |
| `status_sheet.feature` | 4 |
| `completion_badges.feature` | 2 |
| **Subtotal** | **12** |

### Estilos de Navegação e Opções de Exibição

| Arquivo de feature | Cenários |
|----------------------|---------:|
| `navstyle_accordion.feature` | 3 |
| `navstyle_tabs.feature` | 2 |
| `navstyle_sticky.feature` | 1 |
| `navstyle_sectioncards.feature` | 2 |
| `navstyle_trail.feature` | 1 |
| `generalinstyle_option.feature` | 2 |
| `progress_display.feature` | 3 |
| **Subtotal** | **14** |

### Editor de Aparência, Configurações e Backup

| Arquivo de feature | Cenários |
|----------------------|---------:|
| `appearance_activity.feature` | 2 |
| `appearance_section.feature` | 2 |
| `appearance_course_defaults.feature` | 1 |
| `course_settings.feature` | 2 |
| `backup_restore.feature` | 1 |
| **Subtotal** | **8** |

| **Total geral** | **34** |

```bash
moodle-plugin-ci behat --profile chrome
```
