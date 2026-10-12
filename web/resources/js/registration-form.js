// Formularios de inscripción/edición de competidor: el bloque del representante legal solo aparece
// cuando hay fecha de nacimiento y el competidor es menor en la fecha del evento, y se avisa si no encaja en la categoría.
// En el formulario del plantel del club (`data-athlete-form`) no hay evento: se usa la fecha de hoy.
const form = document.querySelector('form[data-registration-form], form[data-athlete-form]');

if (form) {
    const eventDate = form.dataset.eventDate || new Date().toISOString().slice(0, 10);
    const adultAge = parseInt(form.dataset.adultAge, 10);
    const categories = JSON.parse(form.dataset.categories || '{}');
    const birth = form.querySelector('[name="birth_date"]');
    const category = form.querySelector('[name="category_id"]');
    const sex = form.querySelector('[name="sex"]');
    const weight = form.querySelector('[name="weight"]');
    const guardian = form.querySelector('[data-guardian-section]');
    const fit = form.querySelector('[data-category-fit]');

    // Años cumplidos en la fecha del evento (el día del cumpleaños ya cuenta), igual que Competitor::ageOn.
    const ageOn = (birthIso, onIso) => {
        const b = birthIso.split('-').map(Number);
        const o = onIso.split('-').map(Number);
        let age = o[0] - b[0];
        if (o[1] < b[1] || (o[1] === b[1] && o[2] < b[2])) age--;
        return age;
    };

    const validDate = (value) => /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value)) && Number(value.slice(0, 4)) >= 1900;

    const update = () => {
        const hasBirth = birth && validDate(birth.value);
        const age = hasBirth ? ageOn(birth.value, eventDate) : null;

        if (guardian) {
            guardian.classList.toggle('d-none', !(hasBirth && age < adultAge));
        }

        if (!fit) return;
        const problems = [];
        const rules = category ? categories[category.value] : null;
        if (hasBirth && rules) {
            if (age < 0) {
                problems.push('La fecha de nacimiento es posterior a la fecha del evento.');
            } else {
                if (rules.min_age !== null && age < rules.min_age) problems.push(`Edad ${age} en la fecha del evento: inferior al mínimo de la categoría (${rules.min_age}).`);
                if (rules.max_age !== null && age > rules.max_age) problems.push(`Edad ${age} en la fecha del evento: superior al máximo de la categoría (${rules.max_age}).`);
            }
            if (rules.sex && sex && sex.value && sex.value !== rules.sex) problems.push('El sexo no coincide con el de la categoría.');
            const kg = weight && weight.value !== '' ? parseFloat(weight.value) : null;
            if (kg !== null && ((rules.min_weight !== null && kg < rules.min_weight) || (rules.max_weight !== null && kg > rules.max_weight))) {
                problems.push(`Peso ${kg} kg fuera del rango de la categoría.`);
            }
        }
        fit.replaceChildren(...problems.map((text) => Object.assign(document.createElement('div'), { textContent: text })));
        fit.classList.toggle('d-none', problems.length === 0);
    };

    [birth, category, sex, weight].forEach((el) => el && ['input', 'change'].forEach((evt) => el.addEventListener(evt, update)));
    update();
}
