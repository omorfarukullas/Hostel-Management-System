/**
 * js/main.js — Core UI Interactions
 * Hostel Management System — Vanilla JS only
 */

(function () {
    'use strict';

    // ── References ──
    const sidebar      = document.getElementById('sidebar');
    const mainWrapper  = document.getElementById('mainWrapper');
    const collapseBtn  = document.getElementById('sidebarCollapseBtn');
    const topbarToggle = document.getElementById('topbarToggle');

    // Create mobile overlay
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.id = 'sidebarOverlay';
    document.body.appendChild(overlay);

    const isMobile = () => window.innerWidth <= 900;

    // ── Sidebar state ──
    function loadState() { return localStorage.getItem('sidebarCollapsed') === 'true'; }

    function applyState(collapsed) {
        if (isMobile()) return;
        sidebar.classList.toggle('collapsed', collapsed);
        mainWrapper.classList.toggle('expanded', collapsed);
        if (collapseBtn) {
            collapseBtn.querySelector('span').textContent = collapsed ? '▶' : '◀';
        }
    }

    applyState(loadState());

    // ── Desktop collapse (sidebar button) ──
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            const now = !sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', now);
            applyState(now);
        });
    }

    // ── Mobile drawer ──
    function openMobileSidebar() {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('visible');
        document.body.style.overflow = 'hidden';
    }
    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('visible');
        document.body.style.overflow = '';
    }

    if (topbarToggle) {
        topbarToggle.addEventListener('click', function () {
            if (isMobile()) {
                sidebar.classList.contains('mobile-open') ? closeMobileSidebar() : openMobileSidebar();
            } else {
                const now = !sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', now);
                applyState(now);
            }
        });
    }
    overlay.addEventListener('click', closeMobileSidebar);

    window.addEventListener('resize', function () {
        if (!isMobile()) { closeMobileSidebar(); applyState(loadState()); }
    });

    // ══════════════════════════════════════════
    // MODAL HELPERS
    // ══════════════════════════════════════════
    window.openModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
    };

    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) { modal.classList.remove('open'); document.body.style.overflow = ''; }
    };

    // Close modal on dark overlay click (outside modal-card)
    document.querySelectorAll('.modal-overlay').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (e.target === el) {
                el.classList.remove('open');
                document.body.style.overflow = '';
            }
        });
    });

    // Close any open modal on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function (el) {
                el.classList.remove('open');
                document.body.style.overflow = '';
            });
        }
    });

    // ══════════════════════════════════════════
    // FLASH ALERT — auto-hide after 4 seconds
    // ══════════════════════════════════════════
    const flashAlert = document.getElementById('flashAlert');
    if (flashAlert) {
        setTimeout(function () {
            flashAlert.style.transition = 'opacity 0.6s ease';
            flashAlert.style.opacity   = '0';
            setTimeout(function () {
                if (flashAlert.parentNode) flashAlert.parentNode.removeChild(flashAlert);
            }, 600);
        }, 4000);
    }

    // ══════════════════════════════════════════
    // CONFIRM ACTION HELPER
    // ══════════════════════════════════════════
    window.confirmAction = function (message) {
        return window.confirm(message || 'Are you sure you want to proceed?');
    };

    // ══════════════════════════════════════════
    // CLIENT-SIDE TABLE SEARCH (data-search-table)
    // ══════════════════════════════════════════
    document.querySelectorAll('[data-search-table]').forEach(function (input) {
        const tableId = input.getAttribute('data-search-table');
        const table   = document.getElementById(tableId);
        if (!table) return;
        input.addEventListener('input', function () {
            const q    = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');
            let visible = 0;
            rows.forEach(function (row) {
                const match = row.textContent.toLowerCase().includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            const empty = table.closest('.table-card') && table.closest('.table-card').querySelector('.empty-state');
            if (empty) empty.style.display = visible === 0 ? '' : 'none';
        });
    });

})();
