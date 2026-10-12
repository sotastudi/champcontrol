<?php

return [
    /*
     * Mayoría de edad a efectos de la inscripción: quien tenga menos años en la fecha del evento
     * es menor y, en el formulario público, necesita datos y consentimiento del representante legal.
     * Ver docs/menores.md (los textos legales siguen pendientes de revisión).
     */
    'adult_age' => (int) env('ADULT_AGE', 18),
];
