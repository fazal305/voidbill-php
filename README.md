# VOIDBILL

**A professional PHP invoice generator.** Native PHP 8, server-authoritative
calculations, a dark billing-workstation UI, and a printable invoice that
looks like something you'd actually send to a client.

```
VOIDBILL
PHP INVOICE ENGINE
```

VOIDBILL was built to demonstrate real PHP fundamentals — form handling,
server-side validation, reusable classes, file-based persistence — without
looking like a tutorial project. There is no framework, no database, and no
JavaScript pretending to be the source of truth.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Screenshots](#screenshots)
- [Architecture](#architecture)
- [Calculation Logic](#calculation-logic)
- [Why PHP Is Authoritative](#why-php-is-authoritative)
- [Technology Stack](#technology-stack)
- [Installation](#installation)
- [Configuration](#configuration)
- [File Structure](#file-structure)
- [Security Considerations](#security-considerations)
- [Printing / PDF Workflow](#printing--pdf-workflow)
- [Deployment](#deployment)
- [PHP Concepts Demonstrated](#php-concepts-demonstrated)
- [Testing Performed](#testing-performed)
- [Lessons Learned](#lessons-learned)
- [Future Roadmap](#future-roadmap)
- [License](#license)

---

## Overview

Enter a customer, a list of items, a discount, and a tax rate. VOIDBILL
computes the subtotal, discount, taxable amount, tax, and grand total on the
server, assigns a sequential invoice number (`INV-2026-0001`), saves it, and
renders a printable invoice document — all from native PHP.

```
Subtotal
  ↓
Discount
  ↓
Amount After Discount
  ↓
Tax
  ↓
Final Total
```

## Features

- **Dynamic multi-item invoices** — add or remove line items with live
  totals, entirely client-side for UX, entirely re-verified server-side.
- **Server-authoritative calculations** — `InvoiceCalculator` is the only
  place invoice math happens. JavaScript only mirrors it for preview.
- **Percentage or fixed discounts**, capped so they can never exceed the
  subtotal or produce a negative taxable amount.
- **Sequential, year-scoped invoice numbers** (`INV-2026-0001`,
  `INV-2026-0002`, …) persisted with an exclusive file lock so numbers are
  never skipped or reused.
- **A real invoice document** — a printable "paper" component shared by the
  post-generation preview and the permalink view.
- **Print / Save as PDF** — a dedicated print stylesheet strips the app
  shell and leaves just the invoice, sized for A4.
- **Invoice statuses** — `DRAFT → GENERATED → SENT → PAID → OVERDUE`.
- **Dashboard** — total invoices, total value, paid, and outstanding, plus a
  recent-invoices table.
- **Business settings** — name, owner, contact details, logo upload,
  invoice prefix, currency symbol, default payment terms and T&Cs.
- **Strong server-side validation** — required fields, numeric ranges,
  malformed emails, negative numbers, oversized values, and empty item rows
  are all rejected with field-level messages (never a raw PHP error).
- **CSRF protection** on every state-changing form.
- **Safe file uploads** — MIME + extension allow-list, size cap, randomized
  filenames, and a directory `.htaccess` that refuses to execute anything
  uploaded there.
- **Keyboard-first UX** — `Ctrl/Cmd+Enter` to generate, `Ctrl/Cmd+K` for a
  command palette, `Esc` to close it.
- **Draft recovery** — unsaved form state is mirrored to `localStorage` as a
  convenience; it is never the source of truth for a saved invoice.
- **Accessible by default** — labelled inputs, error messages tied to
  fields via `aria-describedby`, visible focus states, semantic structure,
  and `prefers-reduced-motion` support.
- **Responsive** — a genuine two-column desktop layout that restructures
  into a single column on tablet/mobile, not just a shrunk desktop view.

## Screenshots

Run the app locally (see [Installation](#installation)) and visit:

| Page | What you'll see |
|---|---|
| `index.php` | Two-column workspace: invoice form on the left, a live-updating invoice preview on the right. After submitting, the right panel becomes the generated, saved invoice. |
| `dashboard.php` | Stat cards (total invoices, total value, paid, outstanding) and a status-badged table of recent invoices. |
| `invoice.php?number=INV-2026-0001` | The permalink view of a single invoice — a status dropdown and a "Print / Save PDF" button next to a full-page invoice document. |
| `settings.php` | Business profile form: contact details, logo upload, invoice prefix, currency symbol, default notes. |

A worked example (matching the numbers used throughout this README):
Ahmed Traders, three line items totalling Rs. 235,000, a 10% discount and
5% tax, resolves to a grand total of **Rs. 222,075.00** — verified against
the live app during development.

## Architecture

```
voidbill-php/
│
├── public/                  ← web root (point your server here)
│   ├── index.php             New invoice: form + live preview + generation
│   ├── dashboard.php         Stats + recent invoices
│   ├── invoice.php           Permalink view, status change, print
│   ├── settings.php          Business profile + logo upload
│   ├── partials/
│   │   ├── header.php        <head>, topbar, nav
│   │   ├── footer.php        Command palette, toasts, scripts
│   │   └── paper.php         The printable invoice document (shared)
│   ├── uploads/               Logo uploads (gitignored; .htaccess denies execution)
│   └── assets/
│       ├── css/
│       │   ├── variables.css  Design tokens (colors, spacing, type)
│       │   ├── app.css        Application shell styling
│       │   └── print.css      A4 print stylesheet
│       └── js/
│           └── app.js         Dynamic items, live preview, shortcuts, toasts
│
├── src/
│   ├── bootstrap.php          Shared init: config, session, error handling
│   ├── InvoiceCalculator.php  Subtotal → discount → tax → total
│   ├── InvoiceNumberGenerator.php  Locked, year-scoped sequential numbering
│   ├── Validator.php          Server-side validation rules
│   ├── Storage.php            JSON-file persistence (invoices + settings)
│   └── helpers.php            e(), money(), csrf_*(), old(), flash_*()
│
├── storage/                  Runtime data (gitignored): invoices.json, counter.json, business.json
├── config/
│   └── config.php             App settings: currency, limits, env, upload rules
│
├── README.md, LICENSE, .gitignore, composer.json
```

Adapted slightly from a typical MVC-ish layout: there's no router or
front controller because the app is four pages, not forty — `public/*.php`
files are the entry points, and everything they share lives in `src/`.

## Calculation Logic

All arithmetic lives in [`src/InvoiceCalculator.php`](src/InvoiceCalculator.php),
as small, testable, static methods:

```php
InvoiceCalculator::lineTotal($quantity, $unitPrice);
InvoiceCalculator::calculateSubtotal($items);
InvoiceCalculator::calculateDiscount($subtotal, $discountType, $discountValue);
InvoiceCalculator::calculateAfterDiscount($subtotal, $discountAmount);
InvoiceCalculator::calculateTax($afterDiscount, $taxPercent);
InvoiceCalculator::calculateTotal($afterDiscount, $taxAmount);
```

Every monetary value passes through `round_money()`
([`src/helpers.php`](src/helpers.php)), which rounds half-up to 2 decimal
places — so a value like `2.5 × 999.999 = 2499.9975` becomes a clean
**Rs. 2,500.00** instead of `2499.9975000000003`.

Discounts are clamped: `max(0, min(discount, subtotal))`. A fixed discount
larger than the subtotal, or a runaway percentage, can never push the
taxable amount below zero.

**Worked example** (also covered by manual testing):

```
Quantity = 2, Unit Price = 10,000, Discount = 10%, Tax = 5%

Subtotal            = 2 × 10,000        = 20,000.00
Discount (10%)       = 20,000 × 0.10     =  2,000.00
Taxable Amount        = 20,000 − 2,000    = 18,000.00
Tax (5%)              = 18,000 × 0.05     =    900.00
Total                = 18,000 + 900      = 18,900.00
```

## Why PHP Is Authoritative

`assets/js/app.js` recalculates totals on every keystroke so the user gets
instant feedback — but that code is a *preview*, not a *ledger*. The moment
the form is submitted, the browser's numbers are discarded entirely.
`index.php` rebuilds the item list from `$_POST`, validates every field with
[`Validator`](src/Validator.php), and only then calls
`InvoiceCalculator::calculate()`. A malicious or buggy client could submit
any numbers it wants; the invoice that gets saved and numbered is always the
one PHP computed. This is the same principle that governs price calculation
in any real payment or billing system: **never trust the client for money.**

## Technology Stack

- **Backend**: PHP 8+ (native — no framework)
- **Frontend**: HTML5, CSS3, vanilla JavaScript
- **Persistence**: locked JSON files (no database — see below)
- **Fonts**: Inter (UI) and JetBrains Mono (numbers, invoice numbers) via
  Google Fonts

No React, no Laravel, no Node.js, no MySQL. The goal is demonstrating PHP,
not a framework.

### Why JSON files instead of a database?

VOIDBILL is a single-user local billing tool, not a multi-tenant SaaS
product. A database would add setup friction (install MySQL, configure
credentials, run migrations) without adding a capability the app needs.
`Storage.php` uses `flock()` to make reads/writes safe against concurrent
requests, which is the actual problem a database would otherwise solve here.

## Installation

**Requirements**: PHP 8.0 or later, with the `mbstring` and `fileinfo`
extensions enabled (both are on by default in most PHP distributions).

```bash
git clone https://github.com/fazal305/voidbill-php.git
cd voidbill-php
php -S localhost:8000 -t public
```

Visit `http://localhost:8000`. No `composer install` is required — the
`composer.json` exists to declare the PHP version requirement and to make
the project composer-aware for future dependencies, but nothing is
currently pulled from Packagist.

On Windows, if `php` isn't recognized, install it first, e.g. via winget:

```bash
winget install --id PHP.PHP.8.4 -e
```

then ensure `mbstring` and `fileinfo` are uncommented in `php.ini`.

## Configuration

All configuration lives in [`config/config.php`](config/config.php), a
plain PHP array — no `.env` parser needed for a project this size:

```php
'env' => 'production',      // 'development' shows raw PHP errors
'currency' => ['code' => 'PKR', 'symbol' => 'Rs.'],
'invoice' => [
    'prefix' => 'INV',
    'max_items' => 50,
    'max_unit_price' => 999999999.99,
    'max_discount_percent' => 100,
    'max_tax_percent' => 100,
],
'upload' => [
    'max_bytes' => 2 * 1024 * 1024,
    'allowed_mime' => ['image/png', 'image/jpeg', 'image/webp'],
],
```

Business-facing settings (name, logo, invoice prefix, currency symbol,
default terms) are edited at runtime via `settings.php` and persisted to
`storage/business.json`.

## File Structure

See [Architecture](#architecture) above for the full tree with
annotations.

## Security Considerations

- **Output escaping**: every dynamic value rendered into HTML passes
  through `e()` (an `htmlspecialchars()` wrapper). No raw `$_POST` or
  stored data is echoed directly.
- **CSRF protection**: every state-changing form (`index.php`,
  `settings.php`, the status-change form on `invoice.php`) includes a
  session-bound CSRF token, verified with `hash_equals()` before any write.
- **File uploads**: logo uploads are checked against an allow-list of MIME
  types *and* extensions (`finfo_file()`, not the client-supplied MIME
  type), capped at 2MB, renamed to a random filename, and served from a
  directory whose `.htaccess` disables script execution — an uploaded
  `.php` file can never run.
- **Sessions**: `session_start()` is configured with `cookie_httponly` and
  `cookie_samesite=Lax`.
- **Error handling**: in `production` mode, `display_errors` is off and a
  global exception handler renders a generic "something went wrong" page
  instead of a stack trace; the real error still reaches PHP's error log.
- **No raw user input in HTML**: form re-population after a validation
  failure (`old()`) escapes every value.
- **Storage is outside the request path for anything that shouldn't be
  public**: `storage/` (invoices, business settings, the invoice counter)
  sits outside `public/`, so it is never web-accessible regardless of
  server configuration. Only `public/uploads/` — logo images, which are
  meant to be public — is inside the web root.

## Printing / PDF Workflow

Click **Print / Save PDF** on the generated-invoice or permalink view. This
calls the browser's native `window.print()`; [`print.css`](public/assets/css/print.css)
hides the application shell (nav, form, buttons, toasts) and leaves only the
invoice document, sized for A4 with 16mm margins. Choosing "Save as PDF" in
the browser's print dialog produces a clean PDF with no extra library
required — a dedicated PDF generator would be unnecessary weight for what
the browser already does well.

## Deployment

**GitHub Pages cannot host PHP** — it only serves static files. VOIDBILL
needs a real PHP runtime, so it targets standard PHP hosting instead:

1. Any shared/VPS host with PHP 8+ (e.g. a cPanel host, DigitalOcean droplet
   with PHP-FPM + Nginx, or a Docker container running `php:8-apache`).
2. Point the web server's document root at `voidbill-php/public/`.
3. Ensure the PHP process can write to `storage/` and `public/uploads/`
   (these are created automatically on first write if missing, but the
   parent directories must be writable).
4. Set `config/config.php`'s `env` to `'production'`.

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

For local development, PHP's built-in server (`php -S localhost:8000 -t
public`) is sufficient and is what was used throughout development and
testing of this project.

## PHP Concepts Demonstrated

- **Form handling & POST requests**: every entry point reads `$_POST`,
  validates it, and only trusts what survives validation.
- **Server-side validation**: [`Validator`](src/Validator.php) is a small,
  dependency-free class — required/optional text, email, date, numeric
  ranges, and a full item-array validator with per-row, per-field errors.
- **Sessions**: CSRF tokens and one-shot flash messages
  (`flash_set()`/`flash_get()`) both ride on `$_SESSION`.
- **Server-side calculations**: see [Calculation Logic](#calculation-logic).
- **Reusable classes**: `InvoiceCalculator`, `Validator`, `Storage`, and
  `InvoiceNumberGenerator` each have one job and no dependency on the web
  layer — they'd work identically from a CLI script.
- **Output escaping**: `e()` in [`helpers.php`](src/helpers.php).
- **File uploads**: `settings.php` handles `$_FILES['logo']` with MIME
  sniffing, extension checks, and `move_uploaded_file()`.
- **Print CSS**: a `@media print` block that treats the printed page as a
  different document from the screen view, not a scaled-down copy.
- **File locking for concurrency**: `flock()` in `Storage.php` and
  `InvoiceNumberGenerator.php` prevents two near-simultaneous invoice
  generations from reading the same counter and colliding — and, on
  Windows in particular, requires reading and writing through the *same*
  open file handle rather than a fresh `file_get_contents()`/
  `file_put_contents()` pair, since Windows enforces mandatory (not
  advisory) file locks even within the same process.

## Testing Performed

Manually verified against the running app (PHP's built-in server) during
development:

- ✅ Qty=2, Price=10,000, Discount=10%, Tax=5% → **Rs. 18,900.00** (matches
  hand calculation), confirmed identical in both the live JS preview and
  the PHP-generated invoice.
- ✅ Zero discount, zero tax → totals equal subtotal exactly.
- ✅ Multiple line items (3 items) → correct subtotal, discount, tax
  cascade, matching the spec's worked example (Rs. 222,075.00).
- ✅ Decimal quantity (2.5) × fractional price (999.999) → rounds to a
  clean Rs. 2,500.00, not a floating-point artifact.
- ✅ Fixed-amount discount, applied and clamped correctly.
- ✅ Negative quantity and negative price → rejected server-side with
  field-specific error messages; no invoice was created.
- ✅ Empty submission → every required field flagged, no PHP notices
  leaked to the page.
- ✅ Oversized discount/tax values → rejected with a clear message.
- ✅ A fully-blank trailing item row → silently skipped rather than
  rejected, so the "+ Add Item" button never traps the user.
- ✅ Invoice numbering incremented sequentially across three separate
  submissions (`INV-2026-0001`, `-0002`, `-0003`) with no gaps or repeats.
- ✅ Dashboard totals (total/paid/outstanding) matched the sum of the
  generated invoices after changing statuses.
- ✅ Mobile-width layout collapses the two-column workspace into a single
  column and switches the item grid to stacked cards.
- ✅ Print stylesheet hides the app shell and buttons, leaving only the
  invoice document.

## Lessons Learned

- **Windows file locking is mandatory, not advisory.** An early version of
  `Storage.php` opened a `flock()`-protected handle and then called
  `file_get_contents()`/`file_put_contents()` on the *same file* from
  *inside* that lock. On Linux this is harmless (advisory locks don't block
  a second open); on Windows it deadlocked into a silent permission error,
  and the invoice simply failed to persist. The fix was to read and write
  through the single already-open handle instead of opening a second one.
- **A live JS preview needs its own reality check.** The client-side
  totals were correct throughout, but they're not proof the server-side
  path works — the actual bug above only showed up by driving the real
  PHP endpoint and inspecting the saved file, not by trusting a UI that
  looked fine.
- **Validation error UI needs to render, not just flag.** Item-row fields
  were initially marked with a `has-error` CSS class on failure but had no
  visible error *text* — technically correct, invisible in practice.
  Accessible, useful validation means every flagged field needs an actual
  message a screen reader (and a human) can find.

## Future Roadmap

Deliberately **not** built, to keep VOIDBILL focused:

- Multi-user accounts / authentication
- Recurring invoices or subscription billing
- Payment gateway integration
- Multi-currency conversion (a static currency *symbol* is configurable;
  live exchange rates are out of scope)
- A relational database (would only make sense past single-user scale)

Plausible next steps if the project grows:

- Invoice editing (currently invoices are immutable once generated, only
  status changes)
- CSV export of the dashboard's invoice list
- A "duplicate invoice" action to start a new one from a past template

## License

[MIT](LICENSE) — see the LICENSE file.
