import * as bootstrap from 'bootstrap';

import './registration-form';
import './judge-votes';

window.bootstrap = bootstrap;

// Cuadritos AKA/SHIRO: al pulsar muestran su texto unos segundos y vuelven a ocultarlo (en pantallas táctiles no hay «hover»).
document.addEventListener('click', (event) => {
    const chip = event.target.closest('.side-chip');
    if (!chip) return;
    chip.classList.add('is-open');
    clearTimeout(chip._hideTimer);
    chip._hideTimer = setTimeout(() => chip.classList.remove('is-open'), 3000);
});

const root = document.documentElement;
const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

function storedMode() {
    try {
        const mode = localStorage.getItem('theme');
        return mode === 'light' || mode === 'dark' ? mode : 'auto';
    } catch (e) {
        return 'auto';
    }
}

function resolve(mode) {
    return mode === 'auto' ? (systemDark.matches ? 'dark' : 'light') : mode;
}

function applyMode(mode, persist = true) {
    root.setAttribute('data-theme-mode', mode);
    root.setAttribute('data-bs-theme', resolve(mode));
    document.querySelectorAll('[data-theme-value]').forEach((item) => {
        const active = item.dataset.themeValue === mode;
        item.classList.toggle('active', active);
        item.setAttribute('aria-current', active ? 'true' : 'false');
    });
    if (persist) {
        try {
            if (mode === 'auto') {
                localStorage.removeItem('theme');
            } else {
                localStorage.setItem('theme', mode);
            }
        } catch (e) { /* sin almacenamiento: solo esta página */ }
    }
}

document.querySelectorAll('[data-theme-value]').forEach((item) => {
    item.addEventListener('click', () => applyMode(item.dataset.themeValue));
});
applyMode(storedMode(), false);

// En modo automático, seguir los cambios del sistema.
systemDark.addEventListener('change', () => {
    if (storedMode() === 'auto') {
        applyMode('auto', false);
    }
});

// Imprimir siempre en claro y restaurar después.
window.addEventListener('beforeprint', () => root.setAttribute('data-bs-theme', 'light'));
window.addEventListener('afterprint', () => root.setAttribute('data-bs-theme', resolve(storedMode())));
