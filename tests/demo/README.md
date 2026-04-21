# TemplateX Demo Seed — Fashion HR Profiles (DE)

A repeatable, fully synthetic demo set for TemplateX. Running a single PHP
script produces:

- 2 **Word templates** (`demo-template1.docx`, `demo-template2.docx`) with
  every `{{placeholder}}` from [`example-variables.md`](./example-variables.md)
- 2 **Collections** (executive vs. retail) defined in
  [`collections.json`](./collections.json)
- 6 **Datasets / candidates** with fictional German fashion-industry
  positions, salaries, career stations and benefits
- 6 **filled DOCX** files in `filled/` — one per candidate, rendered through
  the same expansion primitives the real TemplateXController uses
  (row cloning for `{{stations.*.N}}`, list expansion, checkbox toggling,
  station-details block parsing)

No data is copied from customer artifacts — the templates are rebuilt from
scratch via PhpWord with a neutral HR-profile layout.

## Contents

| Path | Purpose |
|---|---|
| `seed-demo.php` | The repeatable script. Regenerates everything below from scratch. |
| `example-variables.md` | Reference of all `{{placeholders}}`, grouped by type (scalar, list, checkbox, optional, station). |
| `demo-template1.docx` | Executive / Senior Fashion profile (full layout). |
| `demo-template2.docx` | Retail Store Manager profile (compact layout, same placeholders). |
| `collections.json` | Machine-readable export: the 2 Collections + 6 Datasets. |
| `filled/*.docx` | One rendered DOCX per candidate, using the collection's template. |

## Collections

### A — Fashion Executive Profiles (uses `demo-template1.docx`)

| Candidate | Target position | Current | Expected salary |
|---|---|---|---|
| Lena Hartmann | Head of Brand Marketing Fashion DACH | Moda Rhein AG | EUR 135.000 + 20% Bonus |
| Maximilian Graf | Director E-Commerce & Digital Fashion | Schmidt & Falkenberg Fashion | EUR 165.000 + 25–30% Bonus |
| Sophie Wagner | Business Unit Director Sportswear & Fashion | Stuttgarter Textilhaus KG | EUR 195.000 + 35–40% Bonus + LTI |

### B — Fashion Retail Manager Profiles (uses `demo-template2.docx`)

| Candidate | Target position | Current | Expected salary |
|---|---|---|---|
| Tobias Krüger | Store Manager Flagship Berlin Kurfürstendamm | Beckert Mode GmbH (München Flagship) | EUR 78.000 + 20% Bonus |
| Anja Becker | Area Manager Retail Süd (DE/AT) | Aachener Couture Group | EUR 95.000 + 20% Bonus |
| David Köhler | Visual Merchandising Lead DACH | Schmidt & Falkenberg Fashion | EUR 82.000 + 12–15% Bonus |

All 6 candidates are fictional. Any similarity to real persons is coincidental.

## How to run

```bash
# From the templatex repo root:
php tests/demo/seed-demo.php
```

The script auto-detects PhpWord from (in order):

1. `/wwwroot/synaplan/backend/vendor/autoload.php` (workspace Synaplan install)
2. `/var/www/backend/vendor/autoload.php` (docker-internal path used by the
   existing `tests/fixtures/create_template.php`)
3. `vendor/autoload.php` (local composer install, if present)

It needs **only** PhpWord for template construction. The fill step uses
pure PHP (`ZipArchive` + regex) and mirrors the same primitives the Phase A
and Phase B regression tests exercise.

## Relation to production TemplateXController

The fill routine in `seed-demo.php` is intentionally a standalone clone of
the controller's expansion logic — identical to what
`tests/phase-a-lists.php` and `tests/phase-b-stations.php` already inline.
That keeps the demo self-contained and lets it run offline, without
Symfony/Docker.

When you feed `collections.json` into a real TemplateX instance (through
the plugin's `POST /forms` and `POST /candidates` endpoints), the real
controller will produce equivalent DOCX output.

## Regenerating

Re-run `php tests/demo/seed-demo.php` any time. The script is idempotent:
it overwrites the templates, the manifest and every filled document.

## Why two templates

The original customer has "2+ templates" as a requirement. To prove that
TemplateX handles both an elaborate executive layout _and_ a compact
retail layout on the **same variable set**, Collection A points at
`demo-template1.docx` and Collection B at `demo-template2.docx`. The
variable catalogue is identical — only the layout differs.
