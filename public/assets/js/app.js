function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('hidden');
        const overlay = document.getElementById('modal-overlay');
        if (overlay) overlay.classList.remove('hidden');
        const main = document.getElementById('main-content');
        if (main) main.classList.add('modal-open');
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hidden');
        const overlay = document.getElementById('modal-overlay');
        if (overlay) overlay.classList.add('hidden');
        const main = document.getElementById('main-content');
        if (main) {
            var hasOpenModal = document.querySelector('.modal:not(.hidden):not(#preview-modal)');
            var previewOpen = document.getElementById('preview-modal') && !document.getElementById('preview-modal').classList.contains('hidden');
            if (!hasOpenModal && !previewOpen) {
                main.classList.remove('modal-open');
            }
        }
    }
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'toastOut 250ms cubic-bezier(0.1, 0.9, 0.2, 1) forwards';
        setTimeout(() => toast.remove(), 250);
    }, 3000);
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

function isAuthError(response) {
    return response.status === 401;
}

function handleAuthError() {
    const i18n = window.I18N_MESSAGES || {};
    const message = i18n.session_expired || 'Your session has expired. Please log in again.';
    const okText = i18n.ok || 'OK';
    alertModal(message, { okText: okText }).then(function() {
        window.location.href = BASE + '/login';
    });
}

function guardAuth(response) {
    if (isAuthError(response)) {
        handleAuthError();
        return Promise.reject(new Error('auth_required'));
    }
    return response;
}

function apiCall(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken()
        }
    };
    if (data) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }
    return fetch(url, options).then(guardAuth).then(r => r.json());
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target !== this) return;
            var dialog = document.getElementById('dialog-modal');
            if (dialog && !dialog.classList.contains('hidden') && typeof cancelDialog === 'function') {
                cancelDialog();
                return;
            }
            document.querySelectorAll('.modal').forEach(m => m.classList.add('hidden'));
            this.classList.add('hidden');
            const main = document.getElementById('main-content');
            if (main) main.classList.remove('modal-open');
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var dialog = document.getElementById('dialog-modal');
            if (dialog && !dialog.classList.contains('hidden') && typeof cancelDialog === 'function') {
                cancelDialog();
                return;
            }
            var previewModal = document.getElementById('preview-modal');
            if (previewModal && !previewModal.classList.contains('hidden')) {
                Preview.close();
                return;
            }
            document.querySelectorAll('.modal').forEach(m => m.classList.add('hidden'));
            var overlay = document.getElementById('modal-overlay');
            if (overlay) overlay.classList.add('hidden');
            var main = document.getElementById('main-content');
            if (main) main.classList.remove('modal-open');
            var langDD = document.getElementById('lang-dropdown');
            if (langDD) langDD.classList.remove('open');
        }
    });

    document.addEventListener('click', function(e) {
        var link = e.target.closest('a.auth-check-download');
        if (!link) return;
        e.preventDefault();
        fetch(link.href, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(guardAuth)
            .then(function() { window.location.href = link.href; })
            .catch(function() {});
    });
});

document.addEventListener('DOMContentLoaded', function() {
    var trigger = document.getElementById('lang-trigger');
    var dropdown = document.getElementById('lang-dropdown');
    var panel = document.getElementById('lang-panel');
    var valueInput = document.getElementById('lang-value');
    var form = document.getElementById('lang-form');
    if (!trigger || !dropdown || !panel || !valueInput || !form) return;

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
    });

    panel.querySelectorAll('.lang-option').forEach(function(opt) {
        opt.addEventListener('click', function(e) {
            e.stopPropagation();
            var val = this.dataset.value;
            if (val === valueInput.value) {
                dropdown.classList.remove('open');
                return;
            }
            valueInput.value = val;
            form.submit();
        });
    });

    document.addEventListener('click', function() {
        dropdown.classList.remove('open');
    });
});
