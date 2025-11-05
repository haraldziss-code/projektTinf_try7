/**
 * Sportgeräteverwaltung BKT - Main JavaScript
 * Allgemeine JavaScript-Funktionen für das Sportgeräteverwaltungssystem
 */

// Globale Variablen
let csrfToken = '';
let currentUser = null;

// Initialisierung beim DOM laden
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * Anwendung initialisieren
 */
function initializeApp() {
    // CSRF-Token aus Meta-Tag oder hidden input holen
    const csrfElement = document.querySelector('meta[name="csrf-token"]') ||
                        document.querySelector('input[name="csrf_token"]');
    if (csrfElement) {
        csrfToken = csrfElement.getAttribute('content') || csrfElement.value;
    }

    // Tooltips initialisieren
    initializeTooltips();

    // Auto-Save für Formulare
    initializeAutoSave();

    // Bestätigungsdialoge
    initializeConfirmations();

    // Suche mit Enter-Taste
    initializeSearch();

    // Datum-Validierung
    initializeDateValidation();

    // Session-Timeout Warnung
    initializeSessionWarning();

    // Loading-Indikatoren
    initializeLoadingIndicators();

    // Responsive Navigation
    initializeResponsiveNav();
}

/**
 * Bootstrap Tooltips initialisieren
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Auto-Save für Formulare
 */
function initializeAutoSave() {
    const forms = document.querySelectorAll('form[data-auto-save="true"]');

    forms.forEach(form => {
        let saveTimeout;

        form.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                saveFormData(form);
            }, 2000); // Nach 2 Sekunden Inaktivität speichern
        });
    });
}

/**
 * Formulardaten im localStorage speichern
 */
function saveFormData(form) {
    const formId = form.id || form.getAttribute('action');
    const formData = new FormData(form);
    const data = {};

    formData.forEach((value, key) => {
        if (key !== 'csrf_token') { // CSRF-Token nicht speichern
            data[key] = value;
        }
    });

    localStorage.setItem('form_' + formId, JSON.stringify(data));
    showSaveIndicator('Gespeichert');
}

/**
 * Gespeicherte Formulardaten wiederherstellen
 */
function restoreFormData(form) {
    const formId = form.id || form.getAttribute('action');
    const savedData = localStorage.getItem('form_' + formId);

    if (savedData) {
        const data = JSON.parse(savedData);

        Object.keys(data).forEach(key => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = data[key];
                } else {
                    field.value = data[key];
                }
            }
        });
    }
}

/**
 * Save-Indikator anzeigen
 */
function showSaveIndicator(message) {
    const indicator = document.createElement('div');
    indicator.className = 'auto-save-indicator';
    indicator.innerHTML = `<i class="bi bi-check-circle text-success"></i> ${message}`;
    indicator.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 10px 15px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 9999;
        font-size: 14px;
    `;

    document.body.appendChild(indicator);

    setTimeout(() => {
        indicator.remove();
    }, 2000);
}

/**
 * Bestätigungsdialoge für gefährliche Aktionen
 */
function initializeConfirmations() {
    const confirmButtons = document.querySelectorAll('[data-confirm]');

    confirmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

/**
 * Enter-Taste in Suchfeldern
 */
function initializeSearch() {
    const searchInputs = document.querySelectorAll('input[type="search"], .search-input input');

    searchInputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const form = this.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
    });
}

/**
 * Datum-Validierung
 */
function initializeDateValidation() {
    const dateInputs = document.querySelectorAll('input[type="date"]');

    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            validateDateInput(this);
        });
    });
}

/**
 * Datumseingabe validieren
 */
function validateDateInput(input) {
    const value = input.value;
    const min = input.getAttribute('min');
    const max = input.getAttribute('max');

    if (value && !isValidDate(value)) {
        input.setCustomValidity('Bitte geben Sie ein gültiges Datum ein.');
        showInputError(input, 'Ungültiges Datum');
    } else if (min && value < min) {
        input.setCustomValidity('Datum darf nicht vor dem ' + min + ' liegen.');
        showInputError(input, 'Datum liegt in der Vergangenheit');
    } else if (max && value > max) {
        input.setCustomValidity('Datum darf nicht nach dem ' + max + ' liegen.');
        showInputError(input, 'Datum liegt in der Zukunft');
    } else {
        input.setCustomValidity('');
        hideInputError(input);
    }
}

/**
 * Prüfen, ob ein Datum gültig ist
 */
function isValidDate(dateString) {
    const date = new Date(dateString);
    return date instanceof Date && !isNaN(date);
}

/**
 * Eingabefehler anzeigen
 */
function showInputError(input, message) {
    hideInputError(input); // Zuerst alte Fehler entfernen

    const error = document.createElement('div');
    error.className = 'invalid-feedback';
    error.textContent = message;
    error.setAttribute('data-error-for', input.id);

    input.parentNode.appendChild(error);
    input.classList.add('is-invalid');
}

/**
 * Eingabefehler ausblenden
 */
function hideInputError(input) {
    const error = input.parentNode.querySelector(`[data-error-for="${input.id}"]`);
    if (error) {
        error.remove();
    }
    input.classList.remove('is-invalid');
}

/**
 * Session-Timeout Warnung
 */
function initializeSessionWarning() {
    // Nur auf geschützten Seiten
    if (!document.body.classList.contains('authenticated')) {
        return;
    }

    let warningShown = false;

    // Alle 30 Sekunden prüfen
    setInterval(() => {
        const lastActivity = localStorage.getItem('lastActivity') || Date.now();
        const timeSinceActivity = Date.now() - parseInt(lastActivity);
        const sessionTimeout = 7200000; // 2 Stunden in Millisekunden
        const warningTime = 300000; // 5 Minuten vor Timeout

        if (timeSinceActivity > (sessionTimeout - warningTime) && !warningShown) {
            showSessionWarning();
            warningShown = true;
        }
    }, 30000);

    // Aktivität aktualisieren
    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, () => {
            localStorage.setItem('lastActivity', Date.now());
            warningShown = false;
        }, true);
    });
}

/**
 * Session-Timeout Warnung anzeigen
 */
function showSessionWarning() {
    const warning = document.createElement('div');
    warning.className = 'session-warning';
    warning.innerHTML = `
        <div class="alert alert-warning alert-dismissible fade show position-fixed"
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <h6><i class="bi bi-exclamation-triangle me-2"></i>Sitzung läuft ab</h6>
            <p class="mb-2">Ihre Sitzung läuft in wenigen Minuten ab. Möchten Sie die Sitzung verlängern?</p>
            <button type="button" class="btn btn-warning btn-sm me-2" onclick="extendSession()">Verlängern</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeSessionWarning()">Schließen</button>
        </div>
    `;

    document.body.appendChild(warning);
}

/**
 * Sitzung verlängern
 */
function extendSession() {
    // AJAX-Request zum Session-Refresh
    fetch('../includes/session_refresh.php', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken,
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            localStorage.setItem('lastActivity', Date.now());
            closeSessionWarning();
            showNotification('Sitzung wurde verlängert', 'success');
        }
    })
    .catch(error => {
        console.error('Session extension failed:', error);
    });
}

/**
 * Session-Warnung schließen
 */
function closeSessionWarning() {
    const warning = document.querySelector('.session-warning');
    if (warning) {
        warning.remove();
    }
}

/**
 * Loading-Indikatoren
 */
function initializeLoadingIndicators() {
    const loadingButtons = document.querySelectorAll('[data-loading]');

    loadingButtons.forEach(button => {
        button.addEventListener('click', function() {
            showLoading(this);
        });
    });
}

/**
 * Loading-Zustand anzeigen
 */
function showLoading(element) {
    const originalText = element.innerHTML;
    element.setAttribute('data-original-text', originalText);
    element.disabled = true;
    element.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Lädt...';

    // Automatisch zurücksetzen nach 10 Sekunden (Fallback)
    setTimeout(() => {
        hideLoading(element);
    }, 10000);
}

/**
 * Loading-Zustand ausblenden
 */
function hideLoading(element) {
    const originalText = element.getAttribute('data-original-text');
    if (originalText) {
        element.innerHTML = originalText;
        element.disabled = false;
        element.removeAttribute('data-original-text');
    }
}

/**
 * Responsive Navigation
 */
function initializeResponsiveNav() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');

    if (navbarToggler && navbarCollapse) {
        // Mobile Navigation schließen, wenn außerhalb geklickt wird
        document.addEventListener('click', function(e) {
            const isClickInside = navbarCollapse.contains(e.target) || navbarToggler.contains(e.target);

            if (!isClickInside && navbarCollapse.classList.contains('show')) {
                navbarToggler.click();
            }
        });
    }
}

/**
 * AJAX-Request mit CSRF-Schutz
 */
function ajaxRequest(url, options = {}) {
    const defaultOptions = {
        headers: {
            'X-CSRF-Token': csrfToken,
            'Content-Type': 'application/json'
        }
    };

    const mergedOptions = { ...defaultOptions, ...options };

    return fetch(url, mergedOptions)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
}

/**
 * Benachrichtigung anzeigen
 */
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = `
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
    `;

    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(notification);

    // Automatisch entfernen
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
}

/**
 * Modal mit dynamischem Inhalt laden
 */
function loadModal(url, modalId = 'dynamicModal') {
    showLoading(document.body);

    fetch(url)
        .then(response => response.text())
        .then(html => {
            hideLoading(document.body);

            // Modal erstellen oder aktualisieren
            let modal = document.getElementById(modalId);
            if (!modal) {
                modal = document.createElement('div');
                modal.id = modalId;
                modal.className = 'modal fade';
                modal.innerHTML = `
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Laden...</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body"></div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }

            modal.querySelector('.modal-body').innerHTML = html;

            // Bootstrap-Modal initialisieren
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            // Formulare im Modal initialisieren
            initializeFormsInModal(modal);
        })
        .catch(error => {
            hideLoading(document.body);
            showNotification('Fehler beim Laden des Inhalts', 'danger');
            console.error('Modal loading error:', error);
        });
}

/**
 * Formulare in Modal initialisieren
 */
function initializeFormsInModal(modal) {
    const forms = modal.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');

            showLoading(submitButton);

            fetch(this.action, {
                method: this.method,
                body: formData,
                headers: {
                    'X-CSRF-Token': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                hideLoading(submitButton);

                if (data.success) {
                    // Modal schließen
                    bootstrap.Modal.getInstance(modal).hide();

                    // Erfolgsmeldung
                    showNotification(data.message || 'Aktion erfolgreich', 'success');

                    // Seite aktualisieren oder weiterleiten
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    // Fehlermeldungen anzeigen
                    if (data.errors) {
                        showFormErrors(form, data.errors);
                    } else {
                        showNotification(data.message || 'Fehler aufgetreten', 'danger');
                    }
                }
            })
            .catch(error => {
                hideLoading(submitButton);
                showNotification('Verbindungsfehler', 'danger');
                console.error('Form submission error:', error);
            });
        });
    });
}

/**
 * Formularfehler anzeigen
 */
function showFormErrors(form, errors) {
    // Zuerst alle alten Fehler entfernen
    form.querySelectorAll('.is-invalid, .invalid-feedback').forEach(element => {
        element.classList.remove('is-invalid');
        if (element.classList.contains('invalid-feedback')) {
            element.remove();
        }
    });

    // Neue Fehler anzeigen
    Object.keys(errors).forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (field) {
            field.classList.add('is-invalid');

            const error = document.createElement('div');
            error.className = 'invalid-feedback';
            error.textContent = Array.isArray(errors[fieldName]) ? errors[fieldName][0] : errors[fieldName];

            field.parentNode.appendChild(error);
        }
    });
}

/**
 * Datum relativ formatieren
 */
function formatRelativeDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return 'Heute';
    } else if (diffDays === 1) {
        return date > now ? 'Morgen' : 'Gestern';
    } else if (diffDays < 7) {
        return `vor ${diffDays} Tagen`;
    } else {
        return date.toLocaleDateString('de-DE');
    }
}

/**
 * Zahlen formatieren
 */
function formatNumber(number, decimals = 0) {
    return new Intl.NumberFormat('de-DE', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}

/**
 * Währung formatieren
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

/**
 * Export-Funktion für Tabellen
 */
function exportTable(tableId, format = 'csv') {
    const table = document.getElementById(tableId);
    if (!table) {
        showNotification('Tabelle nicht gefunden', 'danger');
        return;
    }

    let content = '';
    let filename = `export_${new Date().toISOString().split('T')[0]}`;

    if (format === 'csv') {
        content = tableToCSV(table);
        filename += '.csv';
    } else if (format === 'excel') {
        content = tableToExcel(table);
        filename += '.xls';
    }

    // Download initiieren
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');

    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

/**
 * Tabelle in CSV umwandeln
 */
function tableToCSV(table) {
    let csv = [];

    // Header
    const headers = [];
    table.querySelectorAll('thead th').forEach(header => {
        headers.push(header.textContent.trim());
    });
    csv.push(headers.join(';'));

    // Daten
    table.querySelectorAll('tbody tr').forEach(row => {
        const rowData = [];
        row.querySelectorAll('td').forEach(cell => {
            rowData.push(cell.textContent.trim().replace(/;/g, ','));
        });
        csv.push(rowData.join(';'));
    });

    return csv.join('\n');
}

/**
 * Seite drucken
 */
function printPage() {
    window.print();
}

/**
 * Zurück-Funktion
 */
function goBack() {
    window.history.back();
}

// Globale Funktionen für HTML-Event-Handler
window.confirmDelete = confirmDelete;
window.loadModal = loadModal;
window.exportTable = exportTable;
window.printPage = printPage;
window.goBack = goBack;
window.extendSession = extendSession;