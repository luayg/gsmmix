const message = (element, text, type = 'danger') => {
    if (!element) return;
    element.className = `alert alert-${type} mt-3 mb-0`;
    element.textContent = text;
    element.hidden = false;
};

document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname === '/account/profile') {
        const column = document.querySelector('.customer-content .col-xl-8');
        if (column && !document.querySelector('[data-passkey-register-form]')) {
            column.insertAdjacentHTML('beforeend', `
                <section class="panel-card mt-4">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div><h2 class="h5 fw-bold mb-1">Passkeys</h2><p class="text-muted mb-0">Sign in with Windows Hello, Face ID, Touch ID, or your device screen lock.</p></div>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#passkeySetup"><i class="fas fa-key me-2"></i>Add passkey</button>
                    </div>
                    <div data-passkey-register-message hidden></div>
                </section>
                <div class="modal fade" id="passkeySetup" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Add a passkey</h2><button class="btn-close" data-bs-dismiss="modal"></button></div><form data-passkey-register-form><div class="modal-body"><p class="text-muted">Give this passkey a recognizable name, such as “Office PC” or “My phone”.</p><label class="form-label">Passkey name</label><input class="form-control form-control-lg" name="name" maxlength="120" required autocomplete="off"><div class="small text-muted mt-3"><i class="fas fa-shield-halved me-1"></i>Your fingerprint, face, or device PIN never leaves your device.</div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Continue on this device</button></div></form></div></div></div>
            `);
        }
    }

    const loginButton = document.querySelector('[data-passkey-login]');
    const loginMessage = document.querySelector('[data-passkey-login-message]');
    if (loginButton) {
        if (!window.Passkeys.isSupported()) loginButton.hidden = true;
        loginButton.addEventListener('click', async () => {
            loginButton.disabled = true;
            try {
                const result = await window.Passkeys.verify({
                    remember: () => Boolean(document.querySelector('#remember')?.checked),
                });
                window.location.assign(result.redirect || '/account');
            } catch (error) {
                message(loginMessage, error?.message || 'Passkey sign-in failed. Please try again.');
            } finally {
                loginButton.disabled = false;
            }
        });
    }

    const registerForm = document.querySelector('[data-passkey-register-form]');
    const registerMessage = document.querySelector('[data-passkey-register-message]');
    if (registerForm) {
        if (!window.Passkeys.isSupported()) {
            message(registerMessage, 'This browser or device does not support passkeys.', 'warning');
            registerForm.querySelector('button[type="submit"]').disabled = true;
        }
        registerForm.addEventListener('submit', async event => {
            event.preventDefault();
            const button = registerForm.querySelector('button[type="submit"]');
            const name = registerForm.elements.name.value.trim();
            if (!name) return;
            button.disabled = true;
            try {
                await window.Passkeys.register({ name });
                window.location.reload();
            } catch (error) {
                message(registerMessage, error?.message || 'Passkey registration failed. Please try again.');
                button.disabled = false;
            }
        });
    }

    document.querySelectorAll('[data-passkey-delete]').forEach(button => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                const response = await fetch(button.dataset.passkeyDelete, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });
                if (!response.ok) throw new Error('Unable to remove this passkey.');
                window.location.reload();
            } catch (error) {
                message(document.querySelector('[data-passkey-register-message]'), error.message);
                button.disabled = false;
            }
        });
    });
});
