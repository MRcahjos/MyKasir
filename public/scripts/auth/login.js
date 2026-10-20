'use strict';
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('auth-form');
    document.getElementById('toggle-password').addEventListener('click', event => { const field = document.getElementById('password'); field.type = field.type === 'password' ? 'text' : 'password'; event.currentTarget.setAttribute('aria-label', field.type === 'password' ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi'); });
    form.addEventListener('submit', async event => { event.preventDefault(); const button = form.querySelector('[type="submit"]'); if (button.disabled) return; App.busy(button, true); try { const result = await App.http('POST', form.action, {...Object.fromEntries(new FormData(form)), remember: Boolean(form.querySelector('[name="remember"]:checked'))}, form); location.href = result.redirect; } catch (_) {} finally { App.busy(button, false); } });
});
