# SmartCards — Moodle Course Format

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
![Moodle 4.5+](https://img.shields.io/badge/Moodle-4.5%2B-orange)
![Maturity: Alpha](https://img.shields.io/badge/Maturity-Alpha-red)

A course format that renders activities as a grid of buttons/cards while
reusing Moodle's native availability logic (`cm_info`) — replacing the
common "button image + stealth activity" workaround without losing
restriction reasons or open/close dates.

Um formato de curso que exibe as atividades como um grid de botões/cards
reaproveitando a lógica de disponibilidade nativa do Moodle (`cm_info`) —
substituindo o hack comum de "imagem-botão + atividade stealth" sem perder
o motivo da restrição nem as datas de abertura/fechamento.

---

## Features / Funcionalidades

- **Native availability badges** — 🔒 restricted, 🕒 has a deadline, no
  badge means the activity is freely accessible.
  **Badges de disponibilidade nativos** — 🔒 restrito, 🕒 com prazo, sem
  badge significa acesso livre.
- **Status sheet** — tapping a badged card opens a sheet with the reason
  and date, computed entirely from `cm_info` (never recalculated).
  **Bandeja de status** — tocar num card com badge abre uma bandeja com o
  motivo e a data, calculados inteiramente a partir do `cm_info` (nunca
  recalculados).
- **No stealth mode** — hidden activities never leave a trace for students;
  teachers see them dimmed.
  **Sem modo stealth** — atividades ocultas não deixam vestígio para o
  estudante; professores as veem esmaecidas.
- **Real, accessible titles** — the activity name is always real text
  (never drawn inside an image), so it zooms, is read by screen readers,
  and is localized.
  **Títulos reais e acessíveis** — o nome da atividade é sempre texto real
  (nunca desenhado numa imagem), então acompanha zoom, leitor de tela e
  localização.

---

## Requirements / Requisitos

| Requirement | Version |
|---|---|
| Moodle | 4.5, 5.0, 5.1, 5.2 |
| PHP | 8.1+ |

---

## Roadmap

This is an early, alpha-stage release. Custom activity appearance
(image/emoji/icon upload), configurable navigation styles, and section
cards are planned for future versions — see `SCOPE.md` (internal, not
shipped in the plugin package).

Esta é uma versão inicial, em fase alpha. Aparência customizada de
atividade (upload de imagem/emoji/ícone), estilos de navegação
configuráveis e cards de seção estão planejados para versões futuras —
ver `SCOPE.md` (documento interno, não incluído no pacote do plugin).

---

## License / Licença

GPLv3 or later. / GPLv3 ou posterior.
