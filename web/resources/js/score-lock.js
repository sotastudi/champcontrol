// Combate ya puntuado: los campos quedan bloqueados hasta pulsar «Desbloquear»; entonces se puede editar y guardar de nuevo.
const controlsOf = (formId) => [
    ...document.querySelectorAll(`[form="${formId}"]`),
    ...(document.getElementById(formId)?.querySelectorAll('input, select, button') ?? []),
];

document.querySelectorAll('[data-score-lock="locked"]').forEach((wrap) => {
    controlsOf(wrap.dataset.scoreFormId).forEach((control) => {
        if (control.type === 'hidden') return;
        control.dataset.lockedByScore = '1';
        control.disabled = true;
    });
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-score-unlock]');
    if (!button) return;
    const wrap = button.closest('[data-score-lock]');
    const formId = wrap.dataset.scoreFormId;

    controlsOf(formId).forEach((control) => {
        if (control.dataset.lockedByScore === '1') {
            control.disabled = false;
            delete control.dataset.lockedByScore;
        }
    });
    wrap.dataset.scoreLock = 'unlocked';
    button.closest('.score-lock-banner')?.classList.replace('alert-success', 'alert-warning');
    button.closest('.score-lock-banner').querySelector('span').innerHTML = '<strong>Editando.</strong> Cambia lo necesario y pulsa «Guardar puntuación».';
    button.remove();

    // Reaplicar los bloqueos propios del formulario (ippon del rival, votos pendientes) ahora que los campos están activos.
    document.dispatchEvent(new CustomEvent('score-unlocked', { detail: { formId } }));
});
