<?php
/**
 * VOIDBILL — Phase 7: invoice numbering and JSON persistence.
 *
 * There is no database in this project — a small, single-user invoice
 * tool doesn't need one, and a locked JSON file solves the one problem
 * a database would otherwise be solving here: making sure two
 * near-simultaneous requests can't read the same "last invoice number"
 * and collide.
 *
 * This is NOT a database. It has no indexes, no query language, no
 * concurrent-writer scaling, and it reads/rewrites the *entire* file on
 * every save. That's fine for a personal tool generating a handful of
 * invoices; it would not be fine for many users writing at once.
 *
 * A note on variable scope: it might seem like a `static` variable
 * inside a function could remember "the last invoice number" between
 * calls. It can — but only within a single PHP process's lifetime.
 * Every HTTP request to this app is a fresh process (or, under
 * PHP-FPM, may be handled by a different worker process than the last
 * request), so a static variable would silently reset to its initial
 * value on the very next page load and hand out INV-2026-0001 forever.
 * The only way to remember state *across requests* is something
 * outside the PHP process itself — a file (what's used here), a
 * database, or a cache. That's the whole reason this file exists.
 */

declare(strict_types=1);

/**
 * Reserves and returns the next sequential invoice number for the
 * current year (e.g. "INV-2026-0001"), persisting the updated count to
 * $counterFile under an exclusive lock so two requests generating an
 * invoice at the same moment can never receive the same number.
 */
function generateInvoiceNumber(string $counterFile, string $prefix = 'INV'): string
{
    ensureDirectoryExists(dirname($counterFile));

    // 'c+' opens for read+write, creating the file if it doesn't exist yet,
    // without truncating it — important, since the file may already hold
    // last year's counts.
    $handle = fopen($counterFile, 'c+');
    if ($handle === false) {
        throw new RuntimeException("Unable to open {$counterFile} for the invoice counter.");
    }

    flock($handle, LOCK_EX);

    // Read and write through this same handle, not a fresh
    // file_get_contents()/file_put_contents() pair — opening a second
    // handle to a file this process already holds locked can deadlock
    // on some operating systems.
    $counts = readJsonFromHandle($handle);

    $year = date('Y');

    // The counter for a brand-new year starts at 0 so the increment
    // below still produces 1 — no special-casing "first invoice ever".
    $sequence = isset($counts[$year]) ? (int)$counts[$year] : 0;
    $sequence++;
    $counts[$year] = $sequence;

    writeJsonToHandle($handle, $counts);

    flock($handle, LOCK_UN);
    fclose($handle);

    return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
}

/**
 * Appends one finished invoice record to $invoicesFile.
 */
function saveInvoiceRecord(string $invoicesFile, array $invoice): void
{
    ensureDirectoryExists(dirname($invoicesFile));

    $handle = fopen($invoicesFile, 'c+');
    if ($handle === false) {
        throw new RuntimeException("Unable to open {$invoicesFile} to save the invoice.");
    }

    flock($handle, LOCK_EX);

    $invoices = readJsonFromHandle($handle);
    if (!isSequentialArray($invoices)) {
        // The file existed but didn't contain a plain list — treat it as
        // corrupt rather than losing data by overwriting it silently.
        flock($handle, LOCK_UN);
        fclose($handle);
        throw new RuntimeException("{$invoicesFile} does not contain a valid invoice list.");
    }

    $invoices[] = $invoice;
    writeJsonToHandle($handle, $invoices);

    flock($handle, LOCK_UN);
    fclose($handle);
}

/**
 * Loads every saved invoice, or an empty array if none have been
 * generated yet (or the file doesn't exist yet).
 */
function loadInvoices(string $invoicesFile): array
{
    if (!is_file($invoicesFile)) {
        return [];
    }

    $contents = file_get_contents($invoicesFile);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $decoded = json_decode($contents, true);

    return isSequentialArray($decoded) ? $decoded : [];
}

// --- Small file-handling helpers used above ---------------------------

function ensureDirectoryExists(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

/**
 * Reads whatever JSON is in an already-open file handle and decodes it,
 * treating a missing/empty/malformed file as an empty array rather than
 * failing — a fresh install, or a file someone emptied by hand, should
 * not crash the app.
 *
 * @param resource $handle
 */
function readJsonFromHandle($handle): array
{
    rewind($handle);
    $contents = stream_get_contents($handle);

    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $decoded = json_decode($contents, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Overwrites an already-open file handle's contents with $data as JSON.
 *
 * @param resource $handle
 */
function writeJsonToHandle($handle, array $data): void
{
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($handle);
}

/**
 * True if $value is a plain list (0, 1, 2, ... keys) — what a JSON
 * array (as opposed to a JSON object) decodes to in PHP.
 */
function isSequentialArray($value): bool
{
    return is_array($value) && array_is_list($value);
}
