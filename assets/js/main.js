/**
 * Crown Basketball Academy Main Client Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    // Sidebar Collapse Toggle for Desktop
    const toggleBtn = document.getElementById('toggle-sidebar');
    const hamburgerBtn = document.getElementById('hamburger-menu');
    const sidebar = document.getElementById('sidebar');
    const appContainer = document.getElementById('app-container');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    // Load persisted sidebar state
    if (localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth > 1024) {
        sidebar?.classList.add('collapsed');
        appContainer?.classList.add('sidebar-collapsed');
    }

    if (toggleBtn && sidebar && appContainer) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            appContainer.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
        });
    }

    // Sidebar Hamburger Toggle for Mobile
    if (hamburgerBtn && sidebar) {
        hamburgerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
            if (sidebarOverlay) sidebarOverlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
        });
    }

    // Dismiss sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1024 && sidebar && sidebar.classList.contains('show')) {
            if (!sidebar.contains(e.target) && e.target !== hamburgerBtn) {
                sidebar.classList.remove('show');
                if (sidebarOverlay) sidebarOverlay.style.display = 'none';
            }
        }
    });

    // Custom Multi-Select Dropdown Handler
    const multiSelectContainers = document.querySelectorAll('.multi-select-container');
    multiSelectContainers.forEach(container => {
        const trigger = container.querySelector('.multi-select-trigger');
        const options = container.querySelector('.multi-select-options');
        const checkboxInputs = container.querySelectorAll('input[type="checkbox"]');
        
        if (trigger && options) {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                options.classList.toggle('show');
            });

            // Close when clicking outside
            document.addEventListener('click', (e) => {
                if (!container.contains(e.target)) {
                    options.classList.remove('show');
                }
            });

            // Update placeholder trigger text on checkbox select
            const updateTriggerText = () => {
                const selectedLabels = [];
                checkboxInputs.forEach(input => {
                    if (input.checked) {
                        const labelText = input.nextElementSibling ? input.nextElementSibling.textContent.trim() : '';
                        if (labelText) selectedLabels.push(labelText);
                    }
                });
                
                const triggerTextElement = trigger.querySelector('.multi-select-value') || trigger;
                if (selectedLabels.length > 0) {
                    triggerTextElement.textContent = selectedLabels.join(', ');
                } else {
                    triggerTextElement.textContent = trigger.getAttribute('data-placeholder') || 'Pilih Hari...';
                }
            };

            checkboxInputs.forEach(input => {
                input.addEventListener('change', updateTriggerText);
            });

            // Initialize
            updateTriggerText();
        }
    });

    // Auto-dismiss Toast alerts
    const toasts = document.querySelectorAll('.toast');
    toasts.forEach(toast => {
        setTimeout(() => {
            toast.style.animation = 'slideIn 0.3s ease reverse forwards';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 4000);

        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                toast.remove();
            });
        }
    });
});

/**
 * Show confirmation modal before executing destructive action (e.g. Delete)
 * 
 * @param {string} title - Title of the modal
 * @param {string} message - Message body of the modal
 * @param {string} actionUrl - The target redirect path for confirmed action
 */
function confirmAction(title, message, actionUrl) {
    // Check if modal backdrop already exists
    let modal = document.getElementById('confirmation-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'confirmation-modal';
        modal.className = 'modal-backdrop';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-title" id="confirm-modal-title">Konfirmasi Tindakan</div>
                <div class="modal-body" id="confirm-modal-message">Apakah Anda yakin?</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="confirm-modal-cancel">Batal</button>
                    <a class="btn btn-danger" id="confirm-modal-proceed" href="#">Lanjutkan</a>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Add events
        const cancelBtn = modal.querySelector('#confirm-modal-cancel');
        cancelBtn.addEventListener('click', () => {
            modal.classList.remove('show');
        });
        
        // Close modal when clicking on backdrop
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        });
    }

    // Set content
    modal.querySelector('#confirm-modal-title').textContent = title;
    modal.querySelector('#confirm-modal-message').textContent = message;
    modal.querySelector('#confirm-modal-proceed').setAttribute('href', actionUrl);

    // Show modal
    setTimeout(() => {
        modal.classList.add('show');
    }, 50);
}

/**
 * Display a dynamic toast notification client-side
 * 
 * @param {string} type - 'success' or 'danger'
 * @param {string} message - Text notification message
 */
function showToast(type, message) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span class="toast-message">${message}</span>
        <button class="toast-close">&times;</button>
    `;
    container.appendChild(toast);

    // Auto-dismiss
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse forwards';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 4000);

    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => {
        toast.remove();
    });
}
