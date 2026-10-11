// Votos de los jueces: pulsar el botón ya marcado quita el voto.
document.addEventListener('mousedown', (event) => {
    const input = event.target.closest('[data-judge-group] label')?.control;
    if (input) input.dataset.wasChecked = input.checked ? '1' : '';
});
document.addEventListener('click', (event) => {
    const input = event.target.closest('[data-judge-group] label')?.control;
    if (input && input.dataset.wasChecked === '1') {
        event.preventDefault();
        input.checked = false;
        input.dataset.wasChecked = '';
    }
});
