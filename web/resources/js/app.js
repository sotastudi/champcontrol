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

// 2 wazari = 1 ippon y el ippon gana al instante: al elegir 2 wazari se marca el ippon y, con ippon marcado, se guarda la puntuación,
// con lo que el participante queda como ganador. Al bajar de 2 wazari se desmarca el ippon si lo había marcado el propio selector.
const submitScore = (control) => {
    const form = document.getElementById(control.getAttribute('form'));
    if (form && !form.dataset.submitting) {
        form.dataset.submitting = '1';
        form.requestSubmit();
    }
};

document.addEventListener('change', (event) => {
    const select = event.target.closest('[data-wazari-for]');
    if (select) {
        const ippon = document.querySelector(`[data-ippon-for="${select.dataset.wazariFor}"]`);
        if (!ippon) return;
        if (select.value === '2') {
            if (!ippon.checked) ippon.dataset.auto = '1';
            ippon.checked = true;
            submitScore(select);
        } else if (ippon.dataset.auto === '1') {
            ippon.checked = false;
            ippon.dataset.auto = '';
        }
        return;
    }

    const ippon = event.target.closest('[data-ippon-for]');
    if (ippon && ippon.checked) {
        submitScore(ippon);
    }
});
