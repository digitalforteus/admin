import './bootstrap';
import { Passkeys } from '@laravel/passkeys';

const passkeyError = (button, error) => {
    const message = button.parentElement?.parentElement?.querySelector('[data-passkey-error]')
        ?? document.querySelector('[data-passkey-error]');

    if (message) {
        message.textContent = error instanceof Error ? error.message : 'The passkey operation failed.';
        message.classList.remove('hidden');
    }
};

document.querySelectorAll('[data-passkey-login], [data-passkey-register], [data-passkey-confirm]').forEach((button) => {
    button.disabled = ! Passkeys.isSupported();
});

const dismissToast = (toast) => {
    if (! toast || toast.dataset.dismissing !== undefined) {
        return;
    }

    toast.dataset.dismissing = '';
    toast.classList.add('translate-y-2', 'opacity-0');
    toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    setTimeout(() => toast.remove(), 300);
};

const syncAbilityColumn = (table, verb) => {
    const toggles = [...table.querySelectorAll(`[data-ability-verb="${verb}"]`)];
    const checked = toggles.length > 0 && toggles.every((toggle) => toggle.checked);

    table.querySelectorAll(`[data-ability-column="${verb}"]`).forEach((button) => {
        button.setAttribute('aria-pressed', String(checked));
    });
};

document.querySelectorAll('[data-ability-column]').forEach((button) => {
    syncAbilityColumn(button.closest('table'), button.dataset.abilityColumn);
});

document.querySelectorAll('[data-toast][data-autodismiss]').forEach((toast) => {
    setTimeout(() => dismissToast(toast), Number(toast.dataset.autodismiss));
});

document.querySelector('[data-token-dialog]')?.showModal();

const googleOneTap = document.querySelector('[data-google-one-tap]');

if (googleOneTap) {
    const script = document.createElement('script');

    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.onload = () => {
        google.accounts.id.initialize({
            client_id: googleOneTap.dataset.clientId,
            callback: ({ credential }) => {
                fetch(googleOneTap.dataset.loginUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ credential }),
                })
                    .then((response) => response.ok ? response.json() : Promise.reject(new Error('Google sign-in failed.')))
                    .then(({ redirect }) => window.location.assign(redirect));
            },
        });

        google.accounts.id.prompt();
    };

    document.head.append(script);
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-dismiss-toast]');

    if (button) {
        dismissToast(button.closest('[data-toast]'));
    }

    const passkeyLogin = event.target.closest('[data-passkey-login]');

    if (passkeyLogin) {
        passkeyLogin.disabled = true;
        Passkeys.verify()
            .then((response) => window.location.assign(response.redirect ?? '/'))
            .catch((error) => passkeyError(passkeyLogin, error))
            .finally(() => passkeyLogin.disabled = false);
    }

    const passkeyRegister = event.target.closest('[data-passkey-register]');

    if (passkeyRegister) {
        const name = document.querySelector('[data-passkey-name]')?.value.trim();

        if (! name) {
            passkeyError(passkeyRegister, new Error('Enter a name for this passkey.'));
        } else {
            passkeyRegister.disabled = true;
            Passkeys.register({ name })
                .then(() => window.location.reload())
                .catch((error) => passkeyError(passkeyRegister, error))
                .finally(() => passkeyRegister.disabled = false);
        }
    }

    const passkeyConfirm = event.target.closest('[data-passkey-confirm]');

    if (passkeyConfirm) {
        passkeyConfirm.disabled = true;
        Passkeys.verify({
            routes: {
                options: passkeyConfirm.dataset.passkeyConfirmOptions,
                submit: passkeyConfirm.dataset.passkeyConfirmSubmit,
            },
        })
            .then((response) => window.location.assign(response.redirect ?? '/'))
            .catch((error) => passkeyError(passkeyConfirm, error))
            .finally(() => passkeyConfirm.disabled = false);
    }

    const openDialog = event.target.closest('[data-delete-dialog-open]');

    if (openDialog) {
        document.getElementById(openDialog.dataset.deleteDialogOpen)?.showModal();
    }

    if (event.target.closest('[data-delete-dialog-close]')) {
        event.target.closest('[data-delete-dialog]')?.close();
    }

    if (event.target.closest('[data-token-dialog-close]')) {
        event.target.closest('[data-token-dialog]')?.close();
    }

    const copyToken = event.target.closest('[data-copy-token]');

    if (copyToken) {
        const token = copyToken.closest('[data-token-dialog]')?.querySelector('[data-token-value]')?.textContent.trim();

        if (token) {
            navigator.clipboard.writeText(token).then(() => {
                copyToken.textContent = 'Copied';
                copyToken.disabled = true;
            });
        }
    }

    const abilityColumn = event.target.closest('[data-ability-column]');

    if (abilityColumn) {
        const table = abilityColumn.closest('table');
        const verb = abilityColumn.dataset.abilityColumn;
        const toggles = [...table.querySelectorAll(`[data-ability-verb="${verb}"]`)];
        const checked = ! toggles.every((toggle) => toggle.checked);

        toggles.forEach((toggle) => {
            toggle.checked = checked;
        });

        syncAbilityColumn(table, verb);
    }
});

document.addEventListener('input', (event) => {
    if (event.target.matches('[data-ability-verb]')) {
        syncAbilityColumn(event.target.closest('table'), event.target.dataset.abilityVerb);
    }

    if (event.target.matches('[data-delete-confirm-input]')) {
        const dialog = event.target.closest('[data-delete-dialog]');
        const submit = dialog?.querySelector('[data-delete-confirm-submit]');

        if (submit) {
            submit.disabled = event.target.value !== 'delete';
        }
    }
});
