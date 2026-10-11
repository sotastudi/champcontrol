import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

const root = document.documentElement;

function setTheme(theme, persist = true) {
    root.setAttribute('data-bs-theme', theme);
    if (persist) {
        try { localStorage.setItem('theme', theme); } catch (e) { /* sin almacenamiento: solo esta página */ }
    }
}

document.getElementById('theme-toggle')?.addEventListener('click', () => {
    setTheme(root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
});

// Sin preferencia guardada, seguir los cambios del sistema.
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    let stored = null;
    try { stored = localStorage.getItem('theme'); } catch (err) { /* ignorar */ }
    if (!stored) {
        setTheme(e.matches ? 'dark' : 'light', false);
    }
});

// Imprimir siempre en claro y restaurar después.
let themeBeforePrint = null;
window.addEventListener('beforeprint', () => {
    themeBeforePrint = root.getAttribute('data-bs-theme');
    setTheme('light', false);
});
window.addEventListener('afterprint', () => {
    if (themeBeforePrint) {
        setTheme(themeBeforePrint, false);
        themeBeforePrint = null;
    }
});
