/**
 * ==============================================================================
 * Global UI Interactions & Modal Controllers (assets/js/main.js)
 * ------------------------------------------------------------------------------
 * Handles deletion modal triggers, mobile sidebar toggling, automatic flash
 * message dismissal, and tooltip initializations.
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // --------------------------------------------------------------------------
    // 1. Global Delete Confirmation Modal Handler
    // --------------------------------------------------------------------------
    const deleteModalEl = document.getElementById('deleteConfirmModal');
    let deleteModalInstance = null;

    if (deleteModalEl && typeof bootstrap !== 'undefined') {
        deleteModalInstance = new bootstrap.Modal(deleteModalEl);
        const deleteForm = document.getElementById('globalDeleteForm');
        const deleteIdInput = document.getElementById('deleteModalRecordId');
        const deleteMsg = document.getElementById('deleteModalMessage');

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();

                const recordId = btn.getAttribute('data-id');
                const recordName = btn.getAttribute('data-name') || 'this record';
                const actionUrl = btn.getAttribute('data-action');

                if (deleteForm && deleteIdInput && deleteMsg) {
                    deleteForm.action = actionUrl;
                    deleteIdInput.value = recordId;
                    deleteMsg.innerHTML = `Are you sure you want to permanently delete <strong>${escapeHtml(recordName)}</strong>?`;
                    deleteModalInstance.show();
                }
            });
        });
    }

    // --------------------------------------------------------------------------
    // 2. Mobile Responsive Sidebar Toggle
    // --------------------------------------------------------------------------
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const sidebarEl = document.getElementById('appSidebar');

    if (sidebarToggleBtn && sidebarEl) {
        sidebarToggleBtn.addEventListener('click', () => {
            sidebarEl.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', event => {
            if (window.innerWidth < 992) {
                if (!sidebarEl.contains(event.target) && !sidebarToggleBtn.contains(event.target)) {
                    sidebarEl.classList.remove('show');
                }
            }
        });
    }

    // --------------------------------------------------------------------------
    // 3. Auto-Dismiss Flash Messages after 6 seconds
    // --------------------------------------------------------------------------
    const flashAlerts = document.querySelectorAll('.flash-messages-container .alert');
    flashAlerts.forEach(alertEl => {
        setTimeout(() => {
            if (typeof bootstrap !== 'undefined') {
                const bsAlert = bootstrap.Alert.getInstance(alertEl) || new bootstrap.Alert(alertEl);
                bsAlert.close();
            } else {
                alertEl.style.transition = 'opacity 0.5s ease';
                alertEl.style.opacity = '0';
                setTimeout(() => alertEl.remove(), 500);
            }
        }, 6000);
    });

    // --------------------------------------------------------------------------
    // 4. Initialize Bootstrap Tooltips
    // --------------------------------------------------------------------------
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        if (typeof bootstrap !== 'undefined') {
            new bootstrap.Tooltip(tooltipTriggerEl);
        }
    });

    /**
     * Client-side HTML entity escaping utility.
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
