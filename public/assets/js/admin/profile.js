$(function () {
    const form = document.getElementById('profile');
    if (!form) return;
    const messages = window.profileMessages;
    const button = form.querySelector('[type="submit"]');
    const status = document.getElementById('profile-status');

    $(form).on('submit', function (event) {
        event.preventDefault();
        if (button.disabled || !form.reportValidity()) return;
        $(form).find('.error-message').text('');
        $(form).find('.form-control').removeClass('is-invalid').removeAttr('aria-invalid');
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
        status.textContent = messages.saving;

        $.ajax({
            method: 'PUT',
            url: form.action,
            data: $(form).serialize(),
            headers: { Accept: 'application/json' },
            success: function (response) {
                status.textContent = messages.saved;
                form.querySelectorAll('[type="password"]').forEach(input => { input.value = ''; });
                document.querySelectorAll('[data-profile-name]').forEach(node => { node.textContent = response.data.name; });
                const avatar = document.querySelector('.account-avatar');
                if (avatar) avatar.textContent = Array.from(response.data.name)[0]?.toLocaleUpperCase() || '';
            },
            error: function (xhr) {
                status.textContent = xhr.status === 422 ? messages.validation : messages.error;
                if (xhr.status === 422) {
                    Object.entries(xhr.responseJSON?.errors || {}).forEach(([field, errors]) => {
                        const input = form.elements.namedItem(field);
                        if (!input) return;
                        input.classList.add('is-invalid');
                        input.setAttribute('aria-invalid', 'true');
                        const feedback = document.getElementById('error-' + field);
                        if (feedback) feedback.textContent = errors[0];
                    });
                    form.querySelector('.is-invalid')?.focus();
                }
            },
            complete: function () {
                button.disabled = false;
                form.removeAttribute('aria-busy');
            }
        });
    });
});
