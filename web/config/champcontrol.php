<?php

return [
    /*
     * Edad mínima (en la fecha del evento) para usar el formulario público.
     * Valor conservador hasta definir el consentimiento del representante legal: ver docs/menores.md.
     */
    'public_form_min_age' => (int) env('PUBLIC_FORM_MIN_AGE', 18),
];
