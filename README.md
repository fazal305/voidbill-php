# VOIDBILL

**VOIDBILL // PHP Invoice Engine**

A professional invoice-generation tool built in native PHP 8, developed
deliberately as a PHP-fundamentals learning project — and phased so that
every language feature earns its place in a real application feature
rather than being bolted on to check a box.

> **Status: Phase 3 of 15 — invoice form.** There is still no calculation
> engine (Phase 4) and no validation (Phase 5) — whatever you submit is
> accepted and redisplayed as-is. This README, and the app itself, will
> grow with each phase.

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
| Associative arrays | `$config`, `$business`, `$customer`, `$settings` — related fields grouped under descriptive string keys |
| Indexed arrays | `$items` — a numbered list of line items |
| Multidimensional arrays | `$items` is an indexed array of associative arrays (each row has `description`/`quantity`/`unitPrice`); `$invoice` nests `$customer` and `$items` inside itself |
| `count()` | `count($invoice['items'])` decides the item-count message shown in both panels; also guards `array_pop()` so it never runs on an empty array |
| Comparison operators | `$config['env'] === 'development'`, `$action === 'add_item'` |
| `if / else` | Choosing the builder panel's placeholder message based on `$itemCount` |
| `if / elseif` | Routing the `add_item` / `remove_item` form action in [`public/index.php`](public/index.php) |
| Forms & `$_POST` | The whole invoice form — bracket-notation field names (`customer[name]`, `items[0][description]`) let PHP parse `$_POST` straight into the associative/multidimensional shapes from Phase 2 |
| `array_push()` | Appends a blank row to `$items` when "+ Add Item" is submitted |
| `array_pop()` | Removes the last row from `$items` when "− Remove Last Item" is submitted |
| `foreach` | Rendering one editable input row per item; rendering the status `<select>` options list |
| Ternary operator | `$statusOption === $invoice['status'] ? 'selected' : ''` when marking the current status option |

This table will keep growing through Phase 15 — a concept is only listed
here once it's genuinely present in the code, not in anticipation of a
future phase.

### A note on "Add Item" / "Remove Last Item" without JavaScript

There's no JavaScript yet (that arrives in Phase 11), so every click is a
full form submission. Both buttons share the same `<form>`, so whatever
you've already typed comes back as `$_POST` along with which button was
pressed (`name="action" value="add_item"` or `"remove_item"`). PHP applies
`array_push()`/`array_pop()` to the array reconstructed from `$_POST` and
re-renders the same page with one more or one fewer row. Removal is
deliberately last-row-only for now, matching how `array_pop()` naturally
pairs with `array_push()` — removing an arbitrary row would need
`array_splice()` or a keyed `unset()`, which isn't necessary yet.

## Development Phases

1. Foundation — project structure, config, design system, app shell
2. Invoice data structure (associative/multidimensional arrays)
3. **Invoice form (`$_POST`, line items, add/remove)** *(this phase)*
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
