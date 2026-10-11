// Botones «Reiniciar»: rellenan el aviso de confirmación (título, mensaje y destino) antes de abrirlo.
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-reset-url]');
    if (!trigger) return;
    const modal = document.getElementById('reset-modal');
    if (!modal) return;
    modal.querySelector('[data-reset-form]').action = trigger.dataset.resetUrl;
    modal.querySelector('#reset-modal-title').textContent = trigger.dataset.resetTitle || 'Reiniciar resultados';
    modal.querySelector('[data-reset-message]').textContent = trigger.dataset.resetMessage || '';
});
