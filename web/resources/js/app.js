import * as bootstrap from 'bootstrap';

import './registration-form';
import './judge-votes';

window.bootstrap = bootstrap;

// Colores AKA/SHIRO sin texto: el nombre aparece al pasar el cursor o al tocar (también con el teclado).
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el, { trigger: 'hover focus' }));

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
