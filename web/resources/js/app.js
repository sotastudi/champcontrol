import * as bootstrap from 'bootstrap';

import './registration-form';
import './judge-votes';
import './offline-scores';
import './tatami-order';

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
    if (!mine || !theirs) return;
    theirs.disabled = mine.checked;
    const theirTwo = document.querySelector(`[data-wazari-for="${base}-${other}"][value="2"]`);
    if (theirTwo) theirTwo.disabled = mine.checked;
};

// Guardar solo con la bandera de todos los jueces, salvo si el combate se decide por ippon o abandono.
const syncSave = (form) => {
    if (!form || !form.matches('[data-score-form]')) return;
    const save = form.querySelector('[data-score-save]');
    const hint = form.querySelector('[data-score-hint]');
    const decided = !!form.querySelector('select[name="abandoned_slot"]')?.value
        || [...document.querySelectorAll(`[data-ippon-for^="${form.id}-"]`)].some((box) => box.checked);
    const groups = [...form.querySelectorAll('[data-judge-group]')];
    const allVoted = groups.every((group) => group.querySelector('input:checked'));
    const ok = decided || allVoted;
    if (save) save.disabled = !ok;
    if (hint) hint.classList.toggle('d-none', ok);
};

// Quien gana por ippon se lleva la bandera de todos los jueces (rojo = AKA, blanco = SHIRO).
const applyIpponVotes = (key) => {
    const base = key.slice(0, -2);
    const value = key.slice(-1) === 'a' ? 'aka' : 'shiro';
    document.getElementById(base)?.querySelectorAll('[data-judge-group]').forEach((group) => {
        const radio = group.querySelector(`input[value="${value}"]`);
        if (radio) radio.checked = true;
    });
};

const submitScore = (control) => {
    const form = document.getElementById(control.getAttribute('form'));
    if (form && !form.dataset.submitting) {
        form.dataset.submitting = '1';
        form.requestSubmit();
    }
};

document.addEventListener('change', (event) => {
    const target = event.target;
    const formId = target.getAttribute?.('form') || target.form?.id;
    const wazari = target.closest('[data-wazari-for]');
    const ippon = target.closest('[data-ippon-for]');

    if (wazari) {
        const key = wazari.dataset.wazariFor;
        const box = document.querySelector(`[data-ippon-for="${key}"]`);
        if (box) {
            if (wazari.value === '2') {
                if (!box.checked) box.dataset.auto = '1';
                box.checked = true;
                syncIppon(key);
                applyIpponVotes(key);
                syncSave(document.getElementById(formId));
                submitScore(wazari);
                return;
            }
            if (box.dataset.auto === '1') {
                box.checked = false;
                box.dataset.auto = '';
                syncIppon(key);
            }
        }
    } else if (ippon) {
        syncIppon(ippon.dataset.ipponFor);
        if (ippon.checked) applyIpponVotes(ippon.dataset.ipponFor);
        syncSave(document.getElementById(formId));
        if (ippon.checked) submitScore(ippon);
        return;
    }

    syncSave(document.getElementById(formId) || target.closest('form'));
});

document.querySelectorAll('[data-score-form]').forEach(syncSave);

// Botón de usuario: el texto se ve al pasar el cursor; en pantallas táctiles anchas (tablet) el primer toque lo muestra
// unos segundos y el segundo toque navega. En móvil estrecho (menos de 768 px) navega directamente.
document.addEventListener('click', (event) => {
    const chip = event.target.closest('[data-user-chip]');
    if (!chip) return;
    const touchWide = window.matchMedia('(hover: none)').matches && window.innerWidth >= 768;
    if (touchWide && !chip.classList.contains('is-open')) {
        event.preventDefault();
        chip.classList.add('is-open');
        clearTimeout(chip._hideTimer);
        chip._hideTimer = setTimeout(() => chip.classList.remove('is-open'), 3000);
    }
});
