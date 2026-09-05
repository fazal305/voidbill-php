# VOIDBILL

**VOIDBILL // PHP Invoice Engine**

A professional invoice-generation tool built in native PHP 8, developed
deliberately as a PHP-fundamentals learning project — and phased so that
every language feature earns its place in a real application feature
rather than being bolted on to check a box.

> **Status: Phase 14 of 15 — documentation.** This README is now
> complete: features, preview, configuration, deployment, lessons
> learned, and a future roadmap, alongside everything already written
> phase by phase. This README, and the app itself, will grow with each
> phase.

## Table of Contents

- [Why a phased build?](#why-a-phased-build)
- [Preview](#preview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation & Running Locally](#running-locally)
- [Project Structure](#project-structure)
- [Configuration](#configuration)
- [Calculation Flow](#calculation-flow)
- [Persistence](#persistence)
- [Printing](#printing)
- [UX Polish](#ux-polish)
- [UI/UX Audit](#uiux-audit)
- [Security](#security)
- [PHP Concepts Demonstrated](#php-concepts-demonstrated-so-far)
- [Testing Performed](#testing-performed-so-far)
- [Deployment](#deployment)
- [Lessons Learned](#lessons-learned)
- [Future Roadmap](#future-roadmap)
- [Development Phases](#development-phases)
- [License](#license)

## Why a phased build?

Earlier drafts of this project jumped straight to a fully-featured app
(dashboard, autosave, command palette, the works). That's available on
the [`v1-full-featured`](../../tree/v1-full-featured) branch if you want
to see where this ends up. But a finished app isn't the same as a project
that *teaches* — so this version of `main` is being rebuilt slowly,
phase by phase, with each PHP concept introduced at the point it's
actually needed. See [Development Phases](#development-phases) below.

## Preview

There's no live-hosted demo (see [Deployment](#deployment) for why),
but here's what each page actually does once it's running locally:

| Page | What's there |
|---|---|
| `index.php` — **New Invoice** | A two-column workspace: an invoice-builder form on the left (customer, dates, line items, discount/tax, notes/terms) and a live invoice preview on the right that updates on every "Update Preview". "Generate Invoice" assigns a real sequential number and saves the record. |
| `dashboard.php` — **Dashboard** | Four stat cards (total invoices, total value, paid, outstanding) and a table of the 5 most recently generated invoices, each with a color-coded status badge. |
| Print view (any invoice, via **Print Invoice**) | The same document, with the dark app shell, form, and buttons stripped away by `print.css`, laid out for A4. |

## Features

- Dynamic, validated multi-item invoicing with live server-recalculated
  totals (subtotal → discount → taxable amount → tax → grand total)
- Percentage or fixed-amount discounts, clamped so they can never
  exceed the subtotal
- Sequential, year-scoped invoice numbering (`INV-2026-0001`, ...)
  backed by a locked JSON file — see [Persistence](#persistence)
- A professional, print-ready invoice document (full business/customer
  contact details, dates, status badge, payment terms, terms &
  conditions) with a dedicated A4 print stylesheet
- A dashboard summarizing every invoice ever generated: totals, paid
  vs. outstanding, and recent history
- Strong server-side validation with field-level, accessible error
  messages — nothing is ever trusted from the client
- CSRF-protected forms and no raw PHP errors shown to users in
  production mode
- Draft autosave/recovery (`localStorage`), toast confirmations, and a
  Ctrl/Cmd+Enter shortcut for generating an invoice
- Fully keyboard-navigable, with visible focus states, accessible
  labels/error associations, and `prefers-reduced-motion` support
- Responsive from desktop down to a 375px phone, with no horizontal
  overflow anywhere

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
│   ├── dashboard.php           Invoice history + stats (read-only)
│   └── assets/
│       ├── css/
│       │   ├── variables.css   Design tokens (colors, spacing, type)
│       │   ├── app.css         Shell + form + paper layout
│       │   └── print.css       A4 print layout (loaded only for print)
│       └── js/
│           └── app.js          Draft autosave, toasts, shortcut, loading state
├── src/
│   ├── calculations.php         calculateLineTotal(), calculateSubtotal(),
│   │                             calculateDiscount(), calculateTax(),
│   │                             calculateGrandTotal(), formatCurrency()
│   ├── validation.php           validateInvoiceData(), isValidDate()
│   ├── persistence.php          generateInvoiceNumber(), saveInvoiceRecord(),
│   │                             loadInvoices()
│   └── csrf.php                 csrfToken(), csrfVerify()
├── storage/                     counter.json, invoices.json (gitignored —
│                                 runtime data, regenerated on first use)
├── config/
│   └── config.php               App name, tagline, currency symbol, env,
│                                 storage file paths
├── README.md, LICENSE, .gitignore
```

## Configuration

Everything configurable lives in [`config/config.php`](config/config.php)
as a plain PHP array — no `.env` parser needed for a project this size:

```php
return [
    'app_name'    => 'VOIDBILL',
    'app_tagline' => 'PHP INVOICE ENGINE',
    'env'         => 'production',   // 'development' shows raw PHP errors
    'currency_symbol' => 'Rs.',
    'invoice_prefix'  => 'INV',
    'storage' => [
        'counter_file'  => __DIR__ . '/../storage/counter.json',
        'invoices_file' => __DIR__ . '/../storage/invoices.json',
    ],
];
```

Business identity (name, contact details) is hardcoded near the top of
`public/index.php` rather than pulled from config — this rebuild
deliberately has no settings page (see [Future Roadmap](#future-roadmap)).
Change it there directly if you want your own details on generated
invoices. Set `env` to `'development'` locally if you need to see raw
PHP errors while working on the code; always leave it as `'production'`
for anything another person might load.

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

## Printing

Click **Print Invoice** (in the preview panel's header) to open the
browser's native print dialog — no PDF library is used or needed,
since "Print → Save as PDF" in any modern browser produces a clean PDF
on its own. [`public/assets/css/print.css`](public/assets/css/print.css)
is loaded only for the `print` media type (`<link media="print">`), so
it never affects normal browsing. When printing, it:

- Hides the dark app shell, the entire invoice-builder form, alerts,
  and anything marked `.no-print` (including the Print Invoice button
  itself — no point printing a button).
- Lets the item table drop its on-screen `min-width` and horizontal
  scroll, since a printed page can't scroll — it just lays out at full
  width instead.
- Sets `@page { size: A4; margin: 16mm; }` so the printed result is a
  standard A4 document with sensible margins.

What's left after all that is just the invoice document, on a plain
white background, exactly as it looks in the on-screen preview.

## UX Polish

[`public/assets/js/app.js`](public/assets/js/app.js) is the app's
first JavaScript file. Everything in it is a convenience layer on top
of the server-rendered form — nothing here calculates a total,
validates a field, or decides an invoice number; all of that stays in
PHP, exactly as every earlier phase established.

- **Draft autosave/recovery.** Every keystroke in a scalar field
  (customer/invoice/settings — not the item rows; see below) is saved
  to `localStorage`. Landing on a fresh, empty form when a saved draft
  exists shows a "Draft recovered" banner with **Restore**/**Discard**
  buttons. Successfully generating an invoice clears the draft
  automatically, since it's no longer "unfinished" once it's been
  generated. None of this touches `storage/` — it's purely a
  same-browser, same-device convenience, exactly as the spec asked for.
- **Toasts** confirm the two JS-only actions (Restore/Discard) that
  don't already get a server-rendered banner.
- **Ctrl/Cmd+Enter** submits "Generate Invoice" from anywhere on the
  page.
- **Duplicate-submission prevention**: every submit button is disabled
  the moment the form is submitted, so an impatient double-click can't
  fire two requests.

### Why item rows aren't part of draft recovery

Autosave only covers the scalar fields, not the item table. Item rows
are rendered entirely server-side — there's no client-side template
for "one item row" the way there is for, say, a toast. Restoring a
saved *count* of items would mean either duplicating that server
template in JavaScript or building a client-side rendering system this
project doesn't otherwise have. The item data itself was never at
risk, though: every Add/Remove/Update click already round-trips
through the server and back, which is what actually matters. Full
JavaScript-driven item management (add/remove without a page reload,
and by extension item-level autosave) is exactly the kind of thing
that exists on the [`v1-full-featured`](../../tree/v1-full-featured)
branch, but is out of scope for this deliberately restrained rebuild.

### A real bug this phase caught

The first version of the duplicate-submission guard disabled every
submit button *synchronously inside the form's own `submit` event*.
That's a classic trap: browsers exclude `disabled` controls from the
form data they serialize, and that exclusion is evaluated right after
synchronous submit handlers finish — so disabling the very button that
was just clicked stripped its `name="action"` value before the request
was built. The visible symptom: clicking "+ Add Item" silently stopped
adding rows. This wasn't something a code read caught — it only showed
up by actually clicking the button in the browser and noticing nothing
happened. Fixed by deferring the disable with `setTimeout(fn, 0)`, so
the browser finishes reading the form first.

## UI/UX Audit

This phase didn't add features — it audited what already existed,
using the project's UI/UX design skill (a searchable database of
accessibility, layout, and style guidance) plus a manual pass over
both pages. Three real, fixable issues were found and corrected in
[`public/index.php`](public/index.php):

1. **Field-level errors weren't associated with their inputs.** Every
   validation error rendered as a `<p class="field-error">` next to
   its field, but nothing connected the two programmatically — a
   screen reader user tabbing to a flagged field would hear the label
   and nothing else. Fixed with a new `describedBy()` helper that
   outputs `aria-describedby="fieldname-error"` on the input exactly
   when that field has an error, matching the field-error's `id`.
2. **Item-row inputs had no accessible label at all.** The
   description/quantity/unit-price inputs relied entirely on the
   `.items__head` column headings above them for meaning — headings
   that aren't programmatically tied to the inputs below them. Fixed
   by adding a real `<label>` per input, visually hidden with a new
   `.sr-only` utility class (clipped to 1×1px, not `display: none`,
   so it stays in the accessibility tree) since the visible column
   headings already communicate the same thing sighted users need.
3. **The main page had no `<h1>`.** `index.php` jumped straight to two
   `<h2>`s ("Invoice Builder", "Invoice Preview") with nothing above
   them — an improper heading hierarchy, and a page with no clear
   top-level landmark for assistive tech. Fixed by adding a page
   header (`<h1>New Invoice</h1>` + a one-line description), matching
   the pattern `dashboard.php` already used.

All three were verified by actually reading the rendered
`aria-describedby`/`id` pairs and the accessibility tree via
JavaScript in the browser — not just reading the source and assuming
it was correct.

**Checked and found already sound:** color contrast (muted text
against both the dark shell and the paper background comfortably
clears 4.5:1; status-badge color pairs follow well-established
accessible light-background pairings), keyboard focus indicators
(`:focus-visible` is defined globally and was never overridden),
touch-target sizing (every button clears the 24px WCAG minimum for
web), and `prefers-reduced-motion` support (already respected by the
toast and item-row animations).

**Checked and deliberately not changed:** a style search for
"developer tool / dark / technical" surfaced cyberpunk and HUD-style
presets built around multiple neon colors, glow effects, and scanline
overlays. VOIDBILL intentionally uses **one** controlled accent color
and no glow/scanline effects — a deliberate choice from the original
design brief to read as a serious utility tool rather than a sci-fi
dashboard, so those suggestions were noted and not applied.

## Security

- **Output escaping**: every dynamic value rendered into HTML passes
  through `e()`, an `htmlspecialchars()` wrapper — verified in Phase 13
  by submitting `<script>`/`<img onerror>`/`<svg onload>` payloads
  through every text field and confirming they came back escaped, not
  executable.
- **CSRF protection**: the invoice form embeds a session-bound token
  (`src/csrf.php`) and every POST is verified before any of its data —
  including `action` — is trusted. A request with a missing or wrong
  token is treated exactly like an untrusted request: ignored, with a
  clear error, never partially applied.
- **Secure sessions**: `session_start()` is configured with
  `cookie_httponly` (no JavaScript access to the session cookie) and
  `cookie_samesite=Lax`.
- **No raw PHP errors in production**: `config.php`'s `env` setting now
  actually controls `display_errors`, and a `set_exception_handler()`
  in production mode logs the real error server-side while showing the
  user a plain "Something went wrong" page — verified by throwing a
  simulated exception and confirming the friendly message renders while
  the real message only reaches the log.
- **Storage stays outside the request path**: `storage/` (invoice
  records, the invoice counter) lives outside `public/`, the directory
  a real web server (Apache/Nginx/PHP-FPM) actually serves from, so
  it's structurally unreachable regardless of server configuration —
  there's no file there to serve even if someone guessed the path.

### An investigation that turned out to be a non-issue

While testing direct access to `storage/invoices.json`, requests to it
(and to arbitrary made-up paths) returned HTTP 200 with real page
content — alarming at first glance. Reading the actual response
headers explained it: the response carried a fresh `Set-Cookie:
PHPSESSID=...` and PHP's session no-cache headers, which only
`index.php`'s own `session_start()` call produces. That means PHP's
built-in development server (`php -S`, used only for local testing —
never for production, which is exactly why the README's [Deployment](#deployment)
section points at Apache/Nginx instead) was falling back to executing
`index.php` for *any* unmatched path, including a completely
made-up filename tested for comparison. No file content was ever
actually returned — every response was the normal, safe HTML page.
This doesn't affect real deployment: Apache/Nginx don't have this
fallback behavior, and `storage/` sits outside their document root
regardless. Worth documenting precisely because it looked like a
finding before the headers explained it wasn't one.

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
| `for` | [`public/dashboard.php`](public/dashboard.php) collects up to 5 recent invoices by index — "up to N, by position" is a counted loop, not a "do this for every item" `foreach` |
| Sorting (`usort()`) | Sorting `$invoices` by `generatedAt`, newest first, before slicing the recent list |
| `array_is_list()` | Guarding `saveInvoiceRecord()`/`loadInvoices()` against a JSON file that decoded to an object instead of a list |
| Sessions | `session_start()` with `cookie_httponly`/`cookie_samesite`, used to persist the CSRF token in `$_SESSION` across requests |
| `hash_equals()` | Timing-safe comparison of the submitted CSRF token against the session's copy in [`src/csrf.php`](src/csrf.php) |
| `set_exception_handler()` | Catching any uncaught error in production mode and showing a plain message instead of a stack trace |

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

### A note on `usort()` instead of `sort()` / `rsort()`

`sort()` and `rsort()` compare whole array elements to each other —
that's perfect for a flat list of numbers or strings, but `$invoices`
is an array of associative arrays (records), and what "recent" needs
is a sort *by one field* (`generatedAt`), not by comparing entire
records. `usort()` with a comparison callback is the correct tool for
that job; reaching for `sort()`/`rsort()` here would either fail
outright or sort by some arbitrary/undefined comparison of the whole
array. This is exactly the kind of case the project tries to avoid —
using a function because it's on a list, rather than because it's
actually correct for the data.

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

Print mode can't be triggered headlessly, so it was verified by
loading `print.css`'s rules unwrapped from their `@media print` guard
into the live page (purely a testing technique — the shipped CSS stays
correctly scoped to `media="print"`) and confirming with
`getComputedStyle()`, not just a screenshot, that `.topbar`, the Print
Invoice button, and the success banner all actually compute to
`display: none`. Then tested with a 4-item invoice, a long multi-sentence
note, payment terms, and terms & conditions at A4 width (794px) — the
whole document fit cleanly with all four items visible, the table's
on-screen horizontal scroll correctly gone (full width instead, since
a printed page can't scroll), the long note wrapped without clipping,
and `document.body.scrollWidth === window.innerWidth` held with no
overflow.

`dashboard.php` was verified against real generated data (6 invoices
across 3 customers, mixed DRAFT/PAID statuses): Total Value
(Rs. 377,400.00), Paid (Rs. 166,750.00), and Outstanding
(Rs. 210,650.00) were all checked by hand against the sum of the
underlying records and matched exactly. Sorting was confirmed by
generating invoices in one order and seeing them listed
newest-generated-first (`INV-2026-0006` before `-0005`, etc.), proving
`usort()`'s comparator actually runs rather than happening to already
be in order. The 5-invoice cap was confirmed by generating a 6th
invoice and watching the oldest one (`INV-2026-0001`) drop off the
list while the "Showing the 5 most recent of 6 invoices" note appeared
— then re-checked that the note is correctly absent when there are 5
or fewer. The empty state was tested by temporarily renaming
`invoices.json` out of the way: the dashboard showed all-zero stats
and a "no invoices yet" message rather than crashing on a missing
file. No console errors; no mobile overflow (the nav wraps under 640px
using the same technique already applied on the main page).

Phase 11's UX layer was tested live in the browser, not just read:
typed a customer name, confirmed it appeared in `localStorage`
immediately; reloaded the page fresh and got the "Draft recovered"
banner; clicked Restore and confirmed the field was refilled and a
toast appeared; on a second pass, clicked Discard and confirmed the
draft was removed and a different toast appeared. Confirmed the
Ctrl+Enter shortcut actually invokes the Generate Invoice button (via
a synthetic `KeyboardEvent`, since the browser-automation tool's own
key-combo delivery didn't reliably reach the page — worth noting since
it could easily have been mistaken for an app bug) and that the
resulting invoice carried the exact values that were typed. Confirmed
the draft is cleared automatically after a successful generation.

**Bug found and fixed here:** disabling submit buttons synchronously
inside the form's `submit` handler silently broke "+ Add Item" — see
[A real bug this phase caught](#a-real-bug-this-phase-caught) above
for the full explanation. Caught by clicking the button and watching
nothing happen, confirmed the fix by clicking it again afterward and
watching the item count actually increase, and re-ran the loading
state test (submit intercepted with `preventDefault()`, checked
`button.disabled` after a tick) to confirm the fix didn't regress the
original feature.

Phase 13 ran a full quality-gate pass rather than adding a feature,
covering the spec's own testing checklist:

- **Regression**: the complete happy path (add 3 items, fill customer
  details, apply 10% discount + 5% tax, Generate Invoice) was re-run
  after every fix below and still produced the exact expected
  **Rs. 222,075.00**, confirming nothing was broken along the way.
- **Security — CSRF (a real gap, found and fixed)**: auditing the form
  for state-changing requests found there was no CSRF protection
  anywhere in this rebuild. Added `src/csrf.php` (session-bound token,
  `hash_equals()` comparison) and verified two ways: a forged POST with
  a bogus token to `action=add_item` did *not* add a row and did *not*
  apply the attacker-supplied customer name; a forged `action=generate`
  did *not* create an invoice or advance `storage/counter.json` (checked
  by reading the file directly before and after — it stayed at `7`).
- **Security — XSS**: submitted `<script>alert(1)</script>`,
  `"><img src=x onerror=alert(2)>`, and `<svg onload=alert(3)>` through
  customer name/company and an item description; all three came back
  fully escaped in the response, none executable.
- **Security — direct storage access**: investigated requests to
  `storage/invoices.json` directly; see [Security](#security) above for
  why this looked like a finding (HTTP 200, real content) but wasn't —
  it was `php -S`'s own dev-only fallback behavior executing
  `index.php`, not a file-content leak, confirmed by inspecting the
  response headers and by testing an outright made-up filename for
  comparison.
- **Robustness — production error handling (a real gap, found and
  fixed)**: `env` in `config.php` was being read but never actually
  used to control `display_errors` or catch uncaught exceptions — a
  crash in production mode would have shown a raw PHP stack trace
  despite the setting existing. Fixed with `ini_set()` +
  `set_exception_handler()`, verified with an isolated script that
  throws a `RuntimeException`: the real message went to the log while
  the HTTP response showed only the plain "Something went wrong" page.
- **Mobile / print**: re-confirmed no horizontal overflow on the
  updated pages (`scrollWidth === innerWidth` at 375px) and that the
  hidden CSRF field doesn't affect the print layout (it's a hidden
  input; nothing to hide that wasn't already invisible).

## Deployment

**GitHub Pages cannot host this project.** It only serves static
files — HTML, CSS, client-side JS — and has no PHP runtime at all.
VOIDBILL needs a server that actually executes PHP, so it targets
ordinary PHP hosting instead:

1. Any host with PHP 8.1+ — a shared/cPanel host, a VPS running
   Apache or Nginx with PHP-FPM, or a container running `php:8-apache`
   or `php:8-fpm`.
2. Point the web server's **document root at `voidbill-php/public/`**
   specifically — not the repository root. `src/`, `config/`, and
   `storage/` must stay outside the document root, which is exactly
   what makes them non-web-accessible (see [Security](#security)).
3. Make sure the PHP process can create and write to `storage/` — it's
   created automatically on first invoice generation if missing, but
   its parent directory needs to be writable.
4. Confirm `config/config.php`'s `env` is `'production'` (it is, by
   default).

Example Apache vhost:

```apache
<VirtualHost *:80>
    ServerName voidbill.example.com
    DocumentRoot /var/www/voidbill-php/public
    <Directory /var/www/voidbill-php/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

For local development or a quick demo, PHP's built-in server is
sufficient and is what every phase of this project was actually tested
against:

```powershell
php -S localhost:8000 -t public
```

## Lessons Learned

This project was explicitly built to reinforce specific PHP
fundamentals, and it did — not always in the ways originally expected:

- **Variables, arrays, and control flow** stopped being abstract once
  they had a real job: an associative array replaced a pile of loose
  `$customerName`/`$customerEmail` variables (Phase 2); `foreach` +
  `continue` skipping a blank row read more naturally than a nested
  `if` would have (Phases 5–6); `switch` earned its place exactly
  twice — discount type and invoice status — matching what the spec
  predicted before any code was written.
- **Functions with real boundaries are easier to trust.** Every
  calculation function in `src/calculations.php` takes plain arguments
  and returns a plain value, with no access to `$_POST` or a global.
  That's not a stylistic preference — it's what made it possible to
  write a 10-line standalone script that verified `calculateSubtotal()`
  against the spec's own worked example *before* the web form even
  existed, and to trust that a passing test there meant something.
- **Two real bugs only showed up by actually running the app**, not by
  reading the code:
  - A Windows-specific `flock()` deadlock (opening a second file handle
    to a file already locked by the same process) silently produced an
    empty `invoices.json` with no visible error — see
    [`v1-full-featured`](../../tree/v1-full-featured)'s history for
    where this was first hit.
  - Disabling a submit button *inside its own submit event* stripped
    its `name=action` value from the request before it was serialized,
    silently turning "+ Add Item" into a no-op (Phase 11). Both were
    caught by clicking the actual button in a browser and noticing
    nothing happened — not by inspecting the source and assuming it
    was correct.
- **Security gaps hide behind "it still works."** The app worked
  perfectly well with no CSRF protection and no production error
  handling for twelve phases — nothing about clicking through it
  suggested anything was wrong. Both were only found by deliberately
  auditing for them in Phase 13, which is exactly why that phase
  exists as a distinct step rather than being folded into "seems done."
- **`static` variables and cross-request state look similar and
  aren't.** Reaching for a `static` counter for invoice numbering would
  have worked in a quick manual test and failed unpredictably in
  practice, since a PHP-FPM worker (or a fresh CLI process) doesn't
  remember anything between separate HTTP requests. The fix wasn't a
  cleverer variable — it was recognizing the problem was about state
  *outside* the running script, which only a file, database, or cache
  can hold.

## Future Roadmap

Deliberately **not** built, to keep VOIDBILL focused on demonstrating
PHP fundamentals rather than becoming a small ERP:

- A relational database (a locked JSON file is enough at this scale;
  see [Persistence](#persistence) for exactly where that stops being
  true)
- User accounts / authentication
- A business-settings page (business identity is currently hardcoded —
  see [Configuration](#configuration))
- PDF generation via a library (the browser's own "Print → Save as
  PDF" already covers this)
- Emailing invoices
- Payment gateway integration or payment tracking beyond a manual
  status field
- Reporting beyond the dashboard's existing totals

Plausible next steps if the project grows past this scope:

- Editing a saved invoice (currently immutable once generated — only
  its status can change, via a future status-editing UI)
- CSV export of the dashboard's invoice list
- A "duplicate this invoice" action to start a new one from a past one
- Client-side item add/remove (the full JavaScript version already
  exists on [`v1-full-featured`](../../tree/v1-full-featured), kept
  out of this rebuild deliberately — see [UX Polish](#ux-polish))

## Development Phases

1. Foundation — project structure, config, design system, app shell
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
14. **Documentation** *(this phase)*
15. GitHub finalization

## License

[MIT](LICENSE)
