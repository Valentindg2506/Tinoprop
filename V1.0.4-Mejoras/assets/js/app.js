/**
 * Tinoprop Espana - JavaScript principal
 */

document.addEventListener('DOMContentLoaded', function() {
    // ========= SIDEBAR SCROLL PERSISTENCE =========
    const sidebarEl = document.getElementById('sidebar');
    const sidebarNavEl = document.querySelector('.sidebar-nav');
    const sidebarScrollKey = 'tinoprop.sidebar.scrollTop';

    function getScrollableSidebarContainer() {
        if (sidebarNavEl && sidebarNavEl.scrollHeight > sidebarNavEl.clientHeight) {
            return sidebarNavEl;
        }
        return sidebarEl;
    }

    function restoreSidebarScroll() {
        const target = getScrollableSidebarContainer();
        if (!target) return;
        try {
            const stored = sessionStorage.getItem(sidebarScrollKey);
            if (stored !== null) {
                target.scrollTop = parseInt(stored, 10) || 0;
            }
        } catch (e) {
            // Ignore storage errors.
        }
    }

    function saveSidebarScroll() {
        const target = getScrollableSidebarContainer();
        if (!target) return;
        try {
            sessionStorage.setItem(sidebarScrollKey, String(target.scrollTop || 0));
        } catch (e) {
            // Ignore storage errors.
        }
    }

    restoreSidebarScroll();

    if (sidebarNavEl) {
        sidebarNavEl.addEventListener('scroll', saveSidebarScroll, { passive: true });
        sidebarNavEl.querySelectorAll('a.nav-link').forEach(function(link) {
            link.addEventListener('click', saveSidebarScroll);
        });
    }
    if (sidebarEl) {
        sidebarEl.addEventListener('scroll', saveSidebarScroll, { passive: true });
    }
    window.addEventListener('beforeunload', saveSidebarScroll);

    // ========= DARK MODE TOGGLE =========
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    function setTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        if (themeIcon) {
            themeIcon.className = theme === 'dark' ? 'bi bi-sun fs-5' : 'bi bi-moon-stars fs-5';
        }
    }

    // Initialize icon based on current theme
    const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
    if (themeIcon) {
        themeIcon.className = currentTheme === 'dark' ? 'bi bi-sun fs-5' : 'bi bi-moon-stars fs-5';
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const current = document.documentElement.getAttribute('data-bs-theme');
            setTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    // ========= SIDEBAR TOGGLE (mobile) =========
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);

        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // ========= AUTO-CLOSE ALERTS =========
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    // ========= CONFIRM DELETE (POST instead of GET) =========
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm(this.dataset.confirm || 'Estas seguro de que deseas eliminar este elemento?')) {
                return;
            }
            // Extract params from the link href and submit as POST
            var href = this.getAttribute('href');
            if (href && href.includes('delete.php')) {
                var url = href.split('?')[0];
                var params = new URLSearchParams(href.split('?')[1] || '');
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.style.display = 'none';
                params.forEach(function(value, key) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            } else {
                // Non-delete confirmations: follow the link
                window.location.href = href;
            }
        });
    });

    // ========= IMAGE PREVIEW =========
    const imageInputs = document.querySelectorAll('input[type="file"][data-preview]');
    imageInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            const previewId = this.dataset.preview;
            const preview = document.getElementById(previewId);
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // ========= FORMAT PRICE INPUTS =========
    document.querySelectorAll('.format-precio').forEach(function(input) {
        input.addEventListener('blur', function() {
            let val = this.value.replace(/[^\d.,]/g, '').replace(',', '.');
            if (val && !isNaN(val)) {
                this.value = parseFloat(val).toFixed(2);
            }
        });
    });

    // ========= TABLE SEARCH =========
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(function(input) {
        input.addEventListener('keyup', function() {
            const tableId = this.dataset.tableSearch;
            const table = document.getElementById(tableId);
            if (!table) return;
            const filter = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    });

    // ========= TOOLTIPS =========
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) {
        return new bootstrap.Tooltip(el);
    });

    // ========= REAL BACK NAVIGATION =========
    document.querySelectorAll('a.btn, a.btn-sm, a.btn-outline-secondary').forEach(function(link) {
        const text = (link.textContent || '').trim().toLowerCase();
        const hasBackIcon = !!link.querySelector('.bi-arrow-left');
        const looksBackButton = hasBackIcon || text.startsWith('volver');
        if (!looksBackButton) return;
        if (!link.getAttribute('href') || link.getAttribute('href') === '#') return;
        if (link.dataset.disableHistoryBack === '1') return;

        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href') || '';
            const hasReferrer = !!document.referrer;
            if (window.history.length > 1 && hasReferrer) {
                e.preventDefault();
                window.history.back();
                return;
            }
            // Fallback natural: seguir href cuando no hay historial real.
            if (!href) {
                e.preventDefault();
            }
        });
    });
});

/**
 * Formatear numero como moneda EUR
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}
