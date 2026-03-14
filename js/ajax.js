/**
 * js/ajax.js — AJAX Utilities
 * Hostel Management System — Vanilla JS only
 */

(function () {
    'use strict';

    // ══════════════════════════════════════════
    // ajaxPost(url, data) → Promise<Object>
    // Sends a POST request using fetch() + FormData
    // Returns parsed JSON response
    // Usage:
    //   ajaxPost('actions/fee_action.php', { action:'mark_paid', fee_id:5 })
    //     .then(res => console.log(res))
    //     .catch(err => console.error(err));
    // ══════════════════════════════════════════
    window.ajaxPost = async function (url, data) {
        const formData = new FormData();

        if (data instanceof FormData) {
            // Already FormData — use directly
            for (const [key, value] of data.entries()) {
                formData.append(key, value);
            }
        } else if (data && typeof data === 'object') {
            Object.entries(data).forEach(([key, value]) => {
                formData.append(key, value);
            });
        }

        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            throw new Error('Network error: ' + response.status + ' ' + response.statusText);
        }

        const text = await response.text();

        // Try to parse JSON; if it fails return raw text
        try {
            return JSON.parse(text);
        } catch (_) {
            return { success: false, raw: text };
        }
    };

    // ══════════════════════════════════════════
    // liveSearch(inputId, tableId)
    // Filters visible table rows in real time.
    // Hides rows whose text content doesn't contain
    // the search query (case-insensitive).
    // Shows/hides empty-state element automatically.
    // ══════════════════════════════════════════
    window.liveSearch = function (inputId, tableId) {
        const input = document.getElementById(inputId);
        const table = document.getElementById(tableId);

        if (!input || !table) {
            console.warn('liveSearch: element not found —', inputId, tableId);
            return;
        }

        const tableCard = table.closest('.table-card');
        const emptyState = tableCard ? tableCard.querySelector('.empty-state') : null;
        const countBadge = tableCard ? tableCard.querySelector('.badge.badge-info') : null;

        input.addEventListener('input', function () {
            const query   = this.value.toLowerCase().trim();
            const rows    = table.querySelectorAll('tbody tr');
            let   visible = 0;

            rows.forEach(function (row) {
                // Skip empty-state rows
                if (row.querySelector('.empty-state')) return;
                const match = row.textContent.toLowerCase().includes(query);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            // Show/hide empty state message
            if (emptyState) {
                emptyState.closest('tr').style.display = visible === 0 ? '' : 'none';
            }

            // Update count badge if present
            if (countBadge) {
                countBadge.textContent = visible;
            }
        });

        // Trigger immediately if pre-populated
        if (input.value.trim() !== '') {
            input.dispatchEvent(new Event('input'));
        }
    };

    // ══════════════════════════════════════════
    // Auto-init: scan for [data-live-search] inputs
    // Usage in HTML:
    //   <input data-live-search="myTableId" …>
    // ══════════════════════════════════════════
    document.querySelectorAll('[data-live-search]').forEach(function (input) {
        const tableId = input.getAttribute('data-live-search');
        const tempId  = 'lsInput_' + Math.random().toString(36).slice(2, 7);
        input.id = input.id || tempId;
        window.liveSearch(input.id, tableId);
    });

})();
