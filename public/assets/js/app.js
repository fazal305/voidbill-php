/**
 * VOIDBILL — Phase 11: UX polish.
 *
 * PHP remains authoritative for everything that matters: calculations,
 * validation, invoice numbering, and persistence. Nothing in this file
 * computes a total or decides whether an invoice is valid — it only
 * makes the existing server-rendered form more pleasant to use.
 *
 * Responsibilities here:
 *   - autosave a draft of the scalar fields (customer/invoice/settings)
 *     to localStorage, and offer to restore it after an accidental
 *     refresh — a convenience only, never financial persistence
 *   - a couple of toast confirmations for those draft actions
 *   - Ctrl/Cmd+Enter as a shortcut for "Generate Invoice"
 *   - disabling the submit buttons the instant one is clicked, so an
 *     impatient double-click can't fire two submissions
 */
(function () {
    'use strict';

    var form = document.getElementById('invoice-form');
    if (!form) {
        return;
    }

    var DRAFT_KEY = 'voidbill:draft:v1';

    // --- Toasts --------------------------------------------------------------
    function toast(message) {
        var region = document.getElementById('toast-region');
        if (!region) {
            return;
        }
        var el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'status');
        el.textContent = message;
        region.appendChild(el);
        window.setTimeout(function () {
            el.remove();
        }, 3000);
    }

    // --- Draft autosave --------------------------------------------------------
    // Only the scalar customer/invoice/settings fields are saved — not the
    // item rows. Item rows are rendered server-side (there's no client-side
    // template for them yet), so restoring a saved *number* of rows would
    // mean duplicating that server template in JavaScript. That's a
    // reasonable feature for later, but out of scope for this phase: the
    // items themselves still round-trip safely through the server on every
    // Add/Remove/Update click, which is what actually matters.
    function currentDraftFields() {
        var fields = {};
        var formData = new FormData(form);
        formData.forEach(function (value, key) {
            if (key === 'action' || key.indexOf('items[') === 0 || key === 'csrf_token') {
                return;
            }
            fields[key] = value;
        });
        return fields;
    }

    function saveDraft() {
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(currentDraftFields()));
        } catch (err) {
            // localStorage can be unavailable (private browsing, quota, etc.)
            // — autosave is a convenience, not a requirement, so fail quietly.
        }
    }

    function clearDraft() {
        try {
            localStorage.removeItem(DRAFT_KEY);
        } catch (err) {
            // see saveDraft()
        }
    }

    function loadDraft() {
        try {
            var raw = localStorage.getItem(DRAFT_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (err) {
            return null;
        }
    }

    function applyDraft(fields) {
        Object.keys(fields).forEach(function (name) {
            var field = form.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]');
            if (field) {
                field.value = fields[name];
            }
        });
    }

    function hasMeaningfulContent(fields) {
        return Object.keys(fields).some(function (key) {
            return (fields[key] || '').trim() !== '';
        });
    }

    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);

    // If this page just successfully generated an invoice, the draft it
    // came from is now redundant — clear it so it isn't offered again.
    if (form.dataset.generated) {
        clearDraft();
    }

    // Only offer recovery on a fresh page load (not right after a POST,
    // since the form already reflects whatever was just submitted).
    if (form.dataset.isPost === '0') {
        var draft = loadDraft();
        if (draft && hasMeaningfulContent(draft)) {
            showDraftBanner(draft);
        }
    }

    function showDraftBanner(draft) {
        var banner = document.createElement('div');
        banner.className = 'alert alert--info';
        banner.setAttribute('role', 'status');
        banner.innerHTML =
            'Draft recovered from your last session. ' +
            '<button type="button" class="btn btn--sm" id="draft-restore">Restore</button> ' +
            '<button type="button" class="btn btn--sm btn--ghost" id="draft-discard">Discard</button>';
        form.parentNode.insertBefore(banner, form);

        banner.querySelector('#draft-restore').addEventListener('click', function () {
            applyDraft(draft);
            banner.remove();
            toast('Draft restored.');
        });
        banner.querySelector('#draft-discard').addEventListener('click', function () {
            clearDraft();
            banner.remove();
            toast('Draft discarded.');
        });
    }

    // --- Keyboard shortcut: Ctrl/Cmd+Enter submits Generate Invoice -----------
    document.addEventListener('keydown', function (event) {
        var isSubmitCombo = (event.ctrlKey || event.metaKey) && event.key === 'Enter';
        if (!isSubmitCombo) {
            return;
        }
        var generateButton = form.querySelector('button[value="generate"]');
        if (generateButton) {
            event.preventDefault();
            generateButton.click();
        }
    });

    // --- Loading state: prevent duplicate submissions ---------------------
    // Disabling a submit button DURING its own submit event is a classic
    // trap: browsers exclude disabled controls from the serialized form
    // data at submission time, which happens right after synchronous
    // submit handlers finish — so disabling the very button that was
    // clicked strips its name=action value before the request is built,
    // silently turning every click into a no-op. (Caught by actually
    // clicking "+ Add Item" and finding it stopped adding rows — not
    // something a code read alone would have revealed.) Deferring the
    // disable with setTimeout(0) lets the browser finish reading the form
    // first.
    form.addEventListener('submit', function () {
        var buttons = form.querySelectorAll('button[type="submit"]');
        window.setTimeout(function () {
            buttons.forEach(function (button) {
                button.disabled = true;
            });
        }, 0);
    });
})();
