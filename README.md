# VOIDBILL

**VOIDBILL // PHP Invoice Engine**

A professional invoice-generation tool built in native PHP 8, developed
deliberately as a PHP-fundamentals learning project — and phased so that
every language feature earns its place in a real application feature
rather than being bolted on to check a box.

> **Status: Phase 8 of 15 — the professional invoice preview.** The
> preview panel is now a genuine invoice document: full business and
> customer contact details, invoice date and due date, a color-coded
> status badge, payment terms, and terms & conditions. This README, and
> the app itself, will grow with each phase.

## Why a phased build?

Earlier drafts of this project jumped straight to a fully-featured app
(dashboard, autosave, command palette, the works). That's available on
the [`v1-full-featured`](../../tree/v1-full-featured) branch if you want
to see where this ends up. But a finished app isn't the same as a project
that *teaches* — so this version of `main` is being rebuilt slowly,
phase by phase, with each PHP concept introduced at the point it's
actually needed. See [Development Phases](#development-phases) below.

## Requirements

- PHP 8.1 or later (developed and tested against PHP 8.4 — `array_is_list()`,
  used in `src/persistence.php`, needs 8.1+)

## Running Locally

```powershell
php -S localhost:8000 -t public
```

Then visit `http://localhost:8000`.

## Project Structure

```
voidbill-php/
├── public/
│   ├── index.php              Form, $_POST handling, and rendering
│   └── assets/css/
│       ├── variables.css       Design tokens (colors, spacing, type)
│       └── app.css             Shell + form + paper layout
├── src/
│   ├── calculations.php         calculateLineTotal(), calculateSubtotal(),
│   │                             calculateDiscount(), calculateTax(),
│   │                             calculateGrandTotal(), formatCurrency()
│   ├── validation.php           validateInvoiceData(), isValidDate()
│   └── persistence.php          generateInvoiceNumber(), saveInvoiceRecord(),
│                                 loadInvoices()
├── storage/                     counter.json, invoices.json (gitignored —
│                                 runtime data, regenerated on first use)
├── config/
│   └── config.php               App name, tagline, currency symbol, env,
│                                 storage file paths
├── README.md, LICENSE, .gitignore
```

## Calculation Flow

```
Line Items
    ↓
Subtotal            calculateSubtotal()
    ↓
Discount            calculateDiscount()
    ↓
Taxable Amount      ($subtotal - $discountAmount)
    ↓
Tax                 calculateTax()
    ↓
Grand Total         calculateGrandTotal()
```

Every function in [`src/calculations.php`](src/calculations.php) takes
plain values as parameters and returns a plain value — none of them read
`$_POST`, a session, or any global. That's what makes
`calculateSubtotal([['quantity' => 2, 'unitPrice' => 10000]])` testable
on its own, from a plain PHP script, with no web server involved (see
[Testing](#testing-performed-so-far) below).

## Persistence

There is no database. `src/persistence.php` reads and writes two plain
JSON files under `storage/`:

- **`counter.json`** — `{"2026": 2}` — the last sequence number issued
  per year. `generateInvoiceNumber()` opens it, takes an exclusive lock
  (`flock(LOCK_EX)`), reads the current count, increments it, writes it
  back, and only then releases the lock — so two invoices generated at
  the same instant can never receive the same number.
- **`invoices.json`** — a JSON array of every generated invoice, used by
  the dashboard/history in Phase 10.

**This is not a database**, and the README says so deliberately rather
than pretending otherwise: there's no query language, no indexes, and
every save reads the *entire* file into memory, appends one record, and
rewrites the whole thing. That's a perfectly reasonable tradeoff for a
single user generating a personal handful of invoices; it would not
scale to many concurrent users. A missing file is treated as "no
invoices yet," and a corrupted/malformed file is treated as empty
rather than crashing the app (though `saveInvoiceRecord()` refuses to
*write* into a file that already contains something other than a plain
list, rather than silently destroying whatever was there).

### Why not a `static` variable for the counter?

A `static $counter = 0;` inside a function remembers its value between
*calls* — but only within one running PHP process. Every request to
this app (via `php -S`, or a PHP-FPM worker in production) is either a
brand-new process or may be handled by a different worker than the
request before it, so a static variable's "memory" resets constantly —
in practice, it would hand out `INV-2026-0001` on almost every request.
The only way to remember a count *across separate HTTP requests* is
something that outlives the PHP process itself: a file (what
`generateInvoiceNumber()` uses), a database, or a cache like Redis.
This is exactly the distinction between *variable scope within a
script* and *state across requests* — two different problems that look
similar until you hit this exact bug.

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
| `foreach` | Rendering one editable input row per item (Phase 3); rendering the status `<select>` options list; rendering one **read-only, calculated** row per item in the invoice preview table (Phase 6) — a distinct pass from the editable one, over the same `$items` array |
| Ternary operator | `$statusOption === $invoice['status'] ? 'selected' : ''` when marking the current status option |
| Custom functions | `calculateLineTotal()`, `calculateSubtotal()`, `calculateDiscount()`, `calculateTax()`, `calculateGrandTotal()`, `formatCurrency()` in [`src/calculations.php`](src/calculations.php) |
| Function arguments | Every calculation function takes its inputs as parameters — nothing reads `$_POST` or a global directly |
| Return values | Every calculation function returns its result rather than assigning to an outer-scope variable |
| Default parameters | `formatCurrency(float $amount, string $currency = 'Rs.')` |
| Variable scope | `$subtotal` inside `calculateSubtotal()` is local — the caller only ever sees it via the return value |
| `switch` | `calculateDiscount()` branches on `$discountType` (`'percentage'` vs `'fixed'`); `statusBadgeClass()` in [`public/index.php`](public/index.php) branches on invoice status to pick a badge color — the two exact candidates the spec calls out for `switch` |
| Arithmetic operators | `$quantity * $unitPrice`, `$subtotal - $discountAmount`, `$taxableAmount + $taxAmount` |
| Logical operators | `$description === '' && $quantity === '' && $unitPrice === ''` (skip a fully-blank row); `(float)$settings['tax_percent'] < 0 \|\| (float)$settings['tax_percent'] > 100` |
| `in_array()` | Validating `$invoice['status']` and `$settings['discount_type']` against allow-lists in [`src/validation.php`](src/validation.php); also `in_array($action, ['add_item', 'remove_item'], true)` decides whether to validate at all |
| `continue` | Skipping a fully-blank item row during validation, while rendering the preview table, and while filtering rows before they're saved |
| File handling & locking | `generateInvoiceNumber()` / `saveInvoiceRecord()` in [`src/persistence.php`](src/persistence.php) — `fopen('c+')`, `flock()`, read/write through the same handle, `fclose()` |
| `json_encode()` / `json_decode()` | Reading and writing `storage/counter.json` and `storage/invoices.json` |
| `sprintf()` | Formatting the invoice number as `INV-2026-0001` with zero-padding (`%04d`) |
| Increment operator | `$sequence++;` builds each new invoice number in [`src/persistence.php`](src/persistence.php) |
| `nl2br()` | Preserving line breaks the user typed in Notes/Payment Terms/Terms & Conditions when they're rendered as HTML |

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

## Testing Performed (so far)

`src/calculations.php` was checked against the spec's own worked
examples with a standalone PHP script (no web server needed, since the
functions don't touch `$_POST`):

| Input | Expected | Actual |
|---|---|---|
| Qty 2 × Rs. 10,000, 10% discount, 5% tax | Subtotal 20,000 / Discount 2,000 / Taxable 18,000 / Tax 900 / **Total 18,900** | ✅ matched exactly |
| Three items: 1×150,000 + 1×25,000 + 6×10,000 | Subtotal **235,000** | ✅ matched exactly |
| Fixed discount (5,000) larger than subtotal (1,000) | Discount clamped to 1,000, never negative taxable amount | ✅ clamped correctly |
| `formatCurrency(2499.9975)` | `Rs. 2,500.00`, no floating-point artifact | ✅ |
| Zero discount, zero tax | Grand total exactly equals subtotal | ✅ |

The same numbers were then re-verified by actually driving the live form
in a browser (filling fields, clicking Add Item, submitting) rather than
just trusting the standalone script — both agreed. Also checked: no
console errors, and no horizontal overflow at 375px mobile width.

`src/validation.php` was checked with a second standalone script against
every case your spec's validation-testing section calls for:

| Case | Result |
|---|---|
| Empty customer name | ✅ rejected |
| Empty item description | ✅ rejected |
| Zero quantity | ✅ rejected |
| Negative quantity | ✅ rejected |
| Negative price | ✅ rejected |
| Discount over 100% | ✅ rejected |
| Tax over 100% | ✅ rejected |
| Malformed email | ✅ rejected |
| Invalid date string | ✅ rejected |
| Due date before invoice date | ✅ rejected |
| Empty item array | ✅ rejected |
| Invalid status (not in the allow-list) | ✅ rejected |
| Decimal quantity (e.g. 2.5) | ✅ accepted |
| Large monetary value | ✅ accepted |
| Multiple items with one blank trailing row | ✅ blank row skipped, real items still validated |
| Fully valid submission | ✅ no errors |

Then re-confirmed live in the browser: submitting a blank form shows
"Customer name is required." and "Add at least one invoice item." with
the exact fields outlined in red; clicking "+ Add Item" does *not*
trigger these errors (only a real "Update Preview" submission does);
fixing the fields and resubmitting clears every error and restores the
Rs. 18,900.00 totals.

The Phase 6 itemized table was verified against the spec's full worked
example (Website Development 1×150,000 + Hosting 1×25,000 +
Maintenance 6×10,000, 10% discount, 5% tax) directly in the browser —
subtotal **Rs. 235,000.00**, discount **Rs. 23,500.00**, taxable amount
**Rs. 211,500.00**, tax **Rs. 10,575.00**, grand total
**Rs. 222,075.00**, matching by hand calculation. A blank row added
with "+ Add Item" correctly counts toward the builder's "N line
item(s)" message but is skipped by `continue` in the rendered table,
so it never shows up as an empty row or breaks the totals.

**Bug found and fixed during this phase:** at mobile width, the item
table (which needs a minimum width to stay readable) was blowing out
the entire page horizontally instead of scrolling inside its own
container. The cause was `grid-template-columns: 1fr` in the mobile
media query — a bare `1fr` track's implicit minimum width is its
content's *min-content* size, not zero, so the table's `min-width` was
winning against the viewport. Fixed by changing it to
`minmax(0, 1fr)`, which lets the track shrink below its content's
natural size and defers to the table's own `overflow-x: auto`
wrapper. Re-verified with `document.body.scrollWidth ===
window.innerWidth` at 375px, not just a screenshot.

`src/persistence.php` was checked with a standalone script before ever
touching the browser:

| Case | Result |
|---|---|
| Three sequential calls | `INV-2026-0001`, `-0002`, `-0003` — no gaps, no repeats |
| Malformed JSON already in `counter.json` | Treated as empty; resumes cleanly from 0001 rather than crashing |
| Save + load two invoice records | Both round-tripped intact, in order |
| Load from a missing file | Returns `[]`, not an error |
| Load from a file containing a JSON *object* instead of a list | Returns `[]` (defensive read) |
| Save into that same corrupted file | Throws `RuntimeException` rather than silently overwriting whatever was there |

Then re-confirmed live in the browser: generating one invoice produced
`INV-2026-0001` with every submitted value correctly written to
`storage/invoices.json` (verified by reading the actual file, not just
trusting the UI); clicking "Update Preview" twice in a row left
`counter.json` completely untouched; generating a second, different
invoice produced `INV-2026-0002` with no gap or collision. No console
errors; no mobile overflow with the new two-button layout.

Phase 8's expanded preview was checked in the browser with a fully
filled-in invoice (Ahmed Traders, full contact details, payment terms,
terms & conditions): every field appeared correctly — business and
customer contact blocks, formatted invoice/due dates ("05 September
2026"), and a `PAID` status badge that correctly switched from gray to
green when the status dropdown was changed (confirming
`statusBadgeClass()`'s `switch` branches work, not just the default
case). Generated an invoice and confirmed `paymentTerms` and
`termsConditions` were saved into `storage/invoices.json` by reading
the file directly. Re-checked mobile: the new invoice-date/due-date/
status row collapses to a single column under 560px with no overflow.

## Development Phases

1. Foundation — project structure, config, design system, app shell
2. Invoice data structure (associative/multidimensional arrays)
3. Invoice form (`$_POST`, line items, add/remove)
4. PHP calculation engine (functions, arguments, return values)
5. Server-side validation
6. `foreach`-driven invoice rendering
7. Invoice numbering + JSON persistence
8. **Professional invoice preview** *(this phase)*
9. Print system
10. Dashboard / invoice history
11. UX polish (autosave, toasts, shortcuts)
12. UI/UX audit
13. Testing
14. Documentation
15. GitHub finalization

## License

[MIT](LICENSE)
