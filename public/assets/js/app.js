/**
 * VOIDBILL — client-side UX layer.
 *
 * Responsibilities: dynamic line items, a live totals preview, keyboard
 * shortcuts, a command palette, toast notifications, a submit loading
 * state, and a localStorage draft-recovery convenience.
 *
 * PHP remains the source of truth: this file never decides what an
 * invoice actually costs, it only mirrors what PHP is about to compute.
 */
(function () {
    'use strict';

    const form = document.getElementById('invoice-form');
    if (!form) return;

    const itemsContainer = document.getElementById('items-container');
    const itemTemplate = document.getElementById('item-row-template');
    const addItemBtn = document.getElementById('add-item-btn');
    const emptyState = document.getElementById('items-empty-state');
    const currencySymbol = form.dataset.currencySymbol || 'Rs.';
    const discountTypeInputs = form.querySelectorAll('input[name="discount_type"]');
    const discountValueInput = form.querySelector('[name="discount_value"]');
    const taxInput = form.querySelector('[name="tax_percent"]');
    const submitBtn = document.getElementById('generate-btn');
    const draftKey = 'voidbill:draft:v1';

    function money(amount) {
        const rounded = Math.round((amount + Number.EPSILON) * 100) / 100;
        const fixed = (rounded === 0 ? 0 : rounded).toFixed(2);
        const parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return currencySymbol + ' ' + parts.join('.');
    }

    function itemRows() {
        return Array.from(itemsContainer.querySelectorAll('.item-row'));
    }

    function updateEmptyState() {
        const has = itemRows().length > 0;
        emptyState.hidden = has;
        itemsContainer.hidden = !has;
    }

    function renumber() {
        itemRows().forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach((input) => {
                const field = input.dataset.field;
                input.name = `items[${index}][${field}]`;
            });
            const removeBtn = row.querySelector('.item-row__remove');
            if (removeBtn) {
                removeBtn.setAttribute('aria-label', `Remove item ${index + 1}`);
            }
        });
    }

    function addItem(values) {
        const fragment = itemTemplate.content.cloneNode(true);
        const row = fragment.querySelector('.item-row');
        if (values) {
            row.querySelector('[data-field="description"]').value = values.description || '';
            row.querySelector('[data-field="quantity"]').value = values.quantity || '';
            row.querySelector('[data-field="unit_price"]').value = values.unit_price || '';
        }
        itemsContainer.appendChild(row);
        renumber();
        updateEmptyState();
        recalculate();
        saveDraft();
        return row;
    }

    function removeItem(row) {
        row.classList.add('is-removing');
        row.addEventListener('animationend', () => {
            row.remove();
            renumber();
            updateEmptyState();
            recalculate();
            saveDraft();
        }, { once: true });
        // Fallback in case animations are disabled.
        setTimeout(() => {
            if (row.isConnected) {
                row.remove();
                renumber();
                updateEmptyState();
                recalculate();
                saveDraft();
            }
        }, 250);
    }

    function recalculate() {
        // The "generated invoice" state swaps the preview panel for a
        // read-only paper — nothing to recalculate against.
        if (!document.getElementById('preview-subtotal')) return;

        let subtotal = 0;
        itemRows().forEach((row) => {
            const qty = parseFloat(row.querySelector('[data-field="quantity"]').value) || 0;
            const price = parseFloat(row.querySelector('[data-field="unit_price"]').value) || 0;
            const total = qty * price;
            row.querySelector('.item-row__total').textContent = total > 0 ? money(total) : '—';
            subtotal += total;
        });

        const discountType = form.querySelector('input[name="discount_type"]:checked')?.value || 'percent';
        const discountValue = parseFloat(discountValueInput.value) || 0;
        let discountAmount = discountType === 'fixed' ? discountValue : subtotal * (discountValue / 100);
        discountAmount = Math.max(0, Math.min(discountAmount, subtotal));

        const taxable = Math.max(0, subtotal - discountAmount);
        const taxPercent = parseFloat(taxInput.value) || 0;
        const taxAmount = taxable * (Math.max(0, taxPercent) / 100);
        const total = taxable + taxAmount;

        document.getElementById('preview-subtotal').textContent = money(subtotal);
        document.getElementById('preview-discount').textContent = discountAmount > 0 ? '- ' + money(discountAmount) : money(0);
        document.getElementById('preview-tax').textContent = taxAmount > 0 ? '+ ' + money(taxAmount) : money(0);
        document.getElementById('preview-total').textContent = money(total);
    }

    function saveDraft() {
        try {
            const data = {
                customer_name: form.customer_name?.value || '',
                customer_company: form.customer_company?.value || '',
                customer_email: form.customer_email?.value || '',
                customer_phone: form.customer_phone?.value || '',
                customer_address: form.customer_address?.value || '',
                invoice_date: form.invoice_date?.value || '',
                discount_type: form.querySelector('input[name="discount_type"]:checked')?.value || 'percent',
                discount_value: discountValueInput.value || '',
                tax_percent: taxInput.value || '',
                notes: form.notes?.value || '',
                items: itemRows().map((row) => ({
                    description: row.querySelector('[data-field="description"]').value,
                    quantity: row.querySelector('[data-field="quantity"]').value,
                    unit_price: row.querySelector('[data-field="unit_price"]').value,
                })),
                savedAt: Date.now(),
            };
            localStorage.setItem(draftKey, JSON.stringify(data));
        } catch (err) {
            /* localStorage may be unavailable — draft recovery is a convenience, not critical */
        }
    }

    function clearDraft() {
        try { localStorage.removeItem(draftKey); } catch (err) { /* noop */ }
    }

    function restoreDraft() {
        let raw;
        try { raw = localStorage.getItem(draftKey); } catch (err) { return; }
        if (!raw) return;
        let data;
        try { data = JSON.parse(raw); } catch (err) { return; }
        if (!data || form.dataset.hasServerData === '1') return;

        Object.keys(data).forEach((key) => {
            if (key === 'items' || key === 'savedAt' || key === 'discount_type') return;
            if (form[key]) form[key].value = data[key];
        });
        if (data.discount_type) {
            const radio = form.querySelector(`input[name="discount_type"][value="${data.discount_type}"]`);
            if (radio) radio.checked = true;
        }
        if (Array.isArray(data.items) && data.items.length) {
            itemsContainer.innerHTML = '';
            data.items.forEach((item) => addItem(item));
        }
        recalculate();
        toast('Draft recovered from your last session.', 'success');
    }

    function toast(message, type) {
        const region = document.getElementById('toast-region');
        if (!region) return;
        const el = document.createElement('div');
        el.className = `toast toast--${type || 'success'}`;
        el.setAttribute('role', 'status');
        el.textContent = message;
        region.appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 200);
        }, 3200);
    }
    window.voidbillToast = toast;

    // ---- Item management ----
    addItemBtn?.addEventListener('click', () => addItem());

    itemsContainer.addEventListener('click', (e) => {
        const btn = e.target.closest('.item-row__remove');
        if (btn) {
            removeItem(btn.closest('.item-row'));
        }
    });

    itemsContainer.addEventListener('input', () => {
        recalculate();
        saveDraft();
    });

    [discountValueInput, taxInput].forEach((el) => {
        el?.addEventListener('input', () => { recalculate(); saveDraft(); });
    });
    discountTypeInputs.forEach((el) => {
        el.addEventListener('change', () => { recalculate(); saveDraft(); });
    });

    form.addEventListener('input', (e) => {
        if (!e.target.closest('.item-row') && e.target.name !== 'discount_value' && e.target.name !== 'tax_percent') {
            saveDraft();
        }
    });

    // ---- Submit loading state ----
    form.addEventListener('submit', () => {
        if (submitBtn) {
            submitBtn.classList.add('is-loading');
            submitBtn.disabled = true;
        }
        clearDraft();
    });

    // ---- Clear form ----
    document.getElementById('clear-form-btn')?.addEventListener('click', () => {
        if (!confirm('Clear all entered invoice data?')) return;
        itemsContainer.innerHTML = '';
        form.reset();
        addItem();
        clearDraft();
        recalculate();
        toast('Form cleared.', 'success');
    });

    // ---- Keyboard shortcuts ----
    document.addEventListener('keydown', (e) => {
        const mod = e.metaKey || e.ctrlKey;
        if (mod && e.key === 'Enter') {
            e.preventDefault();
            form.requestSubmit();
        }
        if (mod && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            openPalette();
        }
        if (e.key === 'Escape') {
            closePalette();
        }
    });

    // ---- Command palette ----
    const overlay = document.getElementById('cmdk-overlay');
    const paletteInput = document.getElementById('cmdk-input');
    const paletteList = document.getElementById('cmdk-list');

    const commands = [
        { label: 'Create Invoice', shortcut: 'Ctrl+Enter', run: () => form.requestSubmit() },
        { label: 'Clear Form', shortcut: '', run: () => document.getElementById('clear-form-btn')?.click() },
        { label: 'Add Item', shortcut: '', run: () => addItem() },
        { label: 'Print Invoice', shortcut: '', run: () => window.print() },
        { label: 'Open Settings', shortcut: '', run: () => { window.location.href = 'settings.php'; } },
        { label: 'Open Dashboard', shortcut: '', run: () => { window.location.href = 'dashboard.php'; } },
    ];

    function renderPalette(filter) {
        const q = (filter || '').toLowerCase();
        paletteList.innerHTML = '';
        commands
            .filter((c) => c.label.toLowerCase().includes(q))
            .forEach((c, i) => {
                const li = document.createElement('li');
                if (i === 0) li.className = 'is-active';
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.innerHTML = `<span>${c.label}</span>` + (c.shortcut ? `<kbd>${c.shortcut}</kbd>` : '');
                btn.addEventListener('click', () => { c.run(); closePalette(); });
                li.appendChild(btn);
                paletteList.appendChild(li);
            });
    }

    function openPalette() {
        if (!overlay) return;
        overlay.classList.add('is-open');
        renderPalette('');
        paletteInput.value = '';
        paletteInput.focus();
    }

    function closePalette() {
        overlay?.classList.remove('is-open');
    }

    overlay?.addEventListener('click', (e) => {
        if (e.target === overlay) closePalette();
    });
    document.getElementById('open-palette-btn')?.addEventListener('click', openPalette);
    paletteInput?.addEventListener('input', () => renderPalette(paletteInput.value));

    // ---- Init ----
    if (itemRows().length === 0 && form.dataset.hasServerData !== '1') {
        addItem();
    }
    updateEmptyState();
    restoreDraft();
    recalculate();
})();
