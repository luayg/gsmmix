const message = (element, text, type = 'danger') => {
    if (!element) return;
    element.className = `alert alert-${type} mt-3 mb-0`;
    element.textContent = text;
    element.hidden = false;
};

document.addEventListener('DOMContentLoaded', () => {
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
