// Cuenta atrás del cierre de inscripciones: se actualiza cada segundo con la hora del servidor (ISO 8601 con zona) y
// al llegar a cero recarga la página una vez para que el botón de inscribirse desaparezca.
const timers = [...document.querySelectorAll('[data-countdown-to]')];

if (timers.length) {
    const pad = (n) => String(n).padStart(2, '0');
    const tick = () => {
        const now = Date.now();
        timers.forEach((el) => {
            const left = Math.max(0, Math.floor((new Date(el.dataset.countdownTo).getTime() - now) / 1000));
            const days = Math.floor(left / 86400);
            el.querySelector('[data-countdown-text]').textContent =
                `${days > 0 ? `${days} d ` : ''}${pad(Math.floor((left % 86400) / 3600))}:${pad(Math.floor((left % 3600) / 60))}:${pad(left % 60)}`;
            el.classList.toggle('text-danger', left < 86400);
            el.classList.toggle('fw-semibold', left < 86400);
            if (left === 0 && !el.dataset.done) {
                el.dataset.done = '1';
                setTimeout(() => location.reload(), 1500);
            }
        });
    };
    tick();
    setInterval(tick, 1000);
}
