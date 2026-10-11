// Generador de contraseñas para el formulario de controladores de tatami (aleatorio con crypto, sin caracteres ambiguos).
const ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

const generate = (length = 10) => {
    const values = new Uint32Array(length);
    crypto.getRandomValues(values);

    return Array.from(values, (v) => ALPHABET[v % ALPHABET.length]).join('');
};

document.addEventListener('click', async (event) => {
    const gen = event.target.closest('[data-generate-password]');
    if (gen) {
        const input = document.querySelector(gen.dataset.generatePassword);
        if (input) {
            input.value = generate();
            input.type = 'text';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }

        return;
    }

    const copy = event.target.closest('[data-copy-password]');
    if (copy) {
        const input = document.querySelector(copy.dataset.copyPassword);
        if (!input || !input.value) return;
        const previous = copy.textContent;
        try {
            await navigator.clipboard.writeText(input.value);
            copy.textContent = 'Copiada';
        } catch (e) {
            input.select();
            copy.textContent = 'Selecciónala (Ctrl+C)';
        }
        setTimeout(() => { copy.textContent = previous; }, 2000);
    }
});
