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
// Dos participantes que se enfrentan no pueden tener ambos ippon (ni 2 wazari): con ippon en uno, se bloquean ippon y 2 wazari del otro hasta que se rectifique.
const syncIppon = (key) => {
    const [base, side] = [key.slice(0, -2), key.slice(-1)];
    const other = side === 'a' ? 'b' : 'a';
    const mine = document.querySelector(`[data-ippon-for="${base}-${side}"]`);
    const theirs = document.querySelector(`[data-ippon-for="${base}-${other}"]`);
    const theirWazari = document.querySelector(`[data-wazari-for="${base}-${other}"]`);
    if (!mine || !theirs) return;
    const blockOther = mine.checked;
    theirs.disabled = blockOther;
    if (theirWazari) {
        const two = [...theirWazari.options].find((o) => o.value === '2');
        if (two) two.disabled = blockOther;
    }
};

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
            syncIppon(select.dataset.wazariFor);
            submitScore(select);
        } else if (ippon.dataset.auto === '1') {
            ippon.checked = false;
            ippon.dataset.auto = '';
            syncIppon(select.dataset.wazariFor);
        }
        return;
    }

    const ippon = event.target.closest('[data-ippon-for]');
    if (ippon) {
        syncIppon(ippon.dataset.ipponFor);
        if (ippon.checked) submitScore(ippon);
    }
});
