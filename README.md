# VOIDBILL

**VOIDBILL // PHP Invoice Engine**

A professional invoice-generation tool built in native PHP 8, developed
deliberately as a PHP-fundamentals learning project — and phased so that
every language feature earns its place in a real application feature
rather than being bolted on to check a box.

> **Status: Phase 1 of 15 — foundation only.** There is no invoice form,
> no calculation engine, and no persistence yet. This README, and the
> app itself, will grow with each phase.

## Why a phased build?

Earlier drafts of this project jumped straight to a fully-featured app
(dashboard, autosave, command palette, the works). That's available on
the [`v1-full-featured`](../../tree/v1-full-featured) branch if you want
to see where this ends up. But a finished app isn't the same as a project
that *teaches* — so this version of `main` is being rebuilt slowly,
phase by phase, with each PHP concept introduced at the point it's
actually needed. See [Development Phases](#development-phases) below.

## Requirements

- PHP 8.0 or later (developed and tested against PHP 8.4)

## Running Locally

```powershell
php -S localhost:8000 -t public
```

Then visit `http://localhost:8000`.

## Project Structure (Phase 1)

```
voidbill-php/
├── public/
│   ├── index.php              Application shell (no form logic yet)
│   └── assets/css/
│       ├── variables.css       Design tokens (colors, spacing, type)
│       └── app.css             Shell layout
├── config/
│   └── config.php              App name, tagline, currency symbol, env
├── README.md, LICENSE, .gitignore
```

This will grow: `src/` (calculation, validation, storage classes) arrives
in Phase 4 onward; `storage/` when persistence is introduced in Phase 7.

## PHP Concepts Demonstrated (so far)

| PHP Concept | VOIDBILL Usage |
|---|---|
| Variables & data types | `$appName` (string), `$itemCount` (int), `$isDev` (bool) in [`public/index.php`](public/index.php) |
| Associative arrays | `$config` loaded from [`config/config.php`](config/config.php) |
| Indexed arrays | `$items = []` in [`public/index.php`](public/index.php) — populated for real in Phase 2 |
| `count()` | Counting `$items` to decide the empty-state message |
| Comparison operators | `$config['env'] === 'development'` |
| `if / else` | Choosing the builder panel's placeholder message based on `$itemCount` |

This table will keep growing through Phase 15 — a concept is only listed
here once it's genuinely present in the code, not in anticipation of a
future phase.

## Development Phases

1. **Foundation** — project structure, config, design system, app shell *(this phase)*
2. Invoice data structure (associative/multidimensional arrays)
3. Invoice form (`$_POST`, line items, add/remove)
4. PHP calculation engine (functions, arguments, return values)
5. Server-side validation
6. `foreach`-driven invoice rendering
7. Invoice numbering + JSON persistence
8. Professional invoice preview
9. Print system
10. Dashboard / invoice history
11. UX polish (autosave, toasts, shortcuts)
12. UI/UX audit
13. Testing
14. Documentation
15. GitHub finalization

## License

[MIT](LICENSE)
