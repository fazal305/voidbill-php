<?php
/**
 * VOIDBILL — Phase 13: CSRF protection.
 *
 * The invoice form is a state-changing request (it can write a new
 * invoice number and record to storage/), so it needs a CSRF token like
 * any other form that changes server state — a difference from, say, a
 * read-only search form, which wouldn't need one.
 *
 * The token itself is nothing clever: a random value stored in the
 * session and echoed back as a hidden field. An attacker's page can
 * make a browser submit a form to VOIDBILL, but it can't read the
 * victim's session to learn the token, so a forged submission won't
 * carry a valid one.
 */

declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * hash_equals() instead of === — a plain string comparison can leak
 * timing information about how many leading characters matched, which
 * matters for secret comparisons even though the practical risk here
 * (a single-user local tool) is low. Using the safe comparison costs
 * nothing and is the correct habit either way.
 */
function csrfVerify(?string $submittedToken): bool
{
    return is_string($submittedToken)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}
