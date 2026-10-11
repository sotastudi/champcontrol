// Orden de las categorías de un tatami: «Reordenar» activa el arrastrar y soltar y cada cambio se guarda al soltar.
// En pantallas táctiles, donde el arrastre HTML5 no funciona, quedan las flechas subir/bajar mientras se reordena.
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-reorder-toggle]');
    if (!toggle) return;
    const box = toggle.closest('.tatami-order');
    const list = box.querySelector('[data-tatami-order]');
    const on = !box.classList.contains('is-reordering');
    box.classList.toggle('is-reordering', on);
    toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
    toggle.textContent = on ? toggle.dataset.labelOn : toggle.dataset.labelOff;
    list.querySelectorAll('li').forEach((li) => { li.draggable = on; });
});

let dragging = null;

document.addEventListener('dragstart', (event) => {
    const li = event.target.closest?.('.is-reordering li[data-category-id]');
    if (!li) return;
    dragging = li;
    li.classList.add('dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', li.dataset.categoryId);
});

document.addEventListener('dragover', (event) => {
    const li = event.target.closest?.('.is-reordering li[data-category-id]');
    if (!dragging || !li || li.parentElement !== dragging.parentElement) return;
    event.preventDefault();
    document.querySelectorAll('.drop-target').forEach((el) => el.classList.remove('drop-target'));
    if (li !== dragging) li.classList.add('drop-target');
});

document.addEventListener('drop', async (event) => {
    const target = event.target.closest?.('.is-reordering li[data-category-id]');
    if (!dragging || !target || target.parentElement !== dragging.parentElement) return;
    event.preventDefault();
    const list = dragging.parentElement;
    const items = [...list.children];
    // Soltar sobre una fila: el elemento arrastrado ocupa su sitio (antes si venía de abajo, después si venía de arriba).
    if (items.indexOf(dragging) < items.indexOf(target)) target.after(dragging);
    else target.before(dragging);
    await save(list);
});

document.addEventListener('dragend', () => {
    document.querySelectorAll('.dragging, .drop-target').forEach((el) => el.classList.remove('dragging', 'drop-target'));
    dragging = null;
});

async function save(list) {
    const status = list.closest('.tatami-order').querySelector('[data-reorder-status]');
    const body = new URLSearchParams();
    [...list.children].forEach((li) => body.append('order[]', li.dataset.categoryId));
    status.className = 'small text-muted';
    status.textContent = 'Guardando…';
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(list.dataset.reorderUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
            body,
        });
        if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message || 'No se pudo guardar el orden.');
        status.className = 'small text-success';
        status.textContent = 'Orden guardado';
        // Las flechas deben reflejar el nuevo orden: se recarga al terminar de reordenar.
        list.dataset.dirty = '1';
    } catch (error) {
        status.className = 'small text-danger';
        status.textContent = error.message === 'Failed to fetch' ? 'Sin conexión: el orden no se ha guardado.' : error.message;
    }
}

// Al terminar («Hecho») tras haber cambiado el orden, recargar para refrescar el combate actual y las flechas.
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-reorder-toggle]');
    if (toggle && toggle.getAttribute('aria-pressed') === 'false' && toggle.closest('.tatami-order').querySelector('[data-tatami-order][data-dirty="1"]')) {
        location.reload();
    }
});
