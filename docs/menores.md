# Menores de edad

Esta primera versión **no admite inscripciones de menores a través del formulario público**. Antes de habilitarlas hay que definir lo siguiente.

1. **Consentimiento del representante legal** y la forma de recogerlo (por ejemplo, declaración en el formulario, documento firmado o verificación posterior).
2. **Datos del representante** necesarios (nombre, contacto) y durante cuánto tiempo se conservan.
3. **Base legal y aviso de privacidad** aplicables (RGPD / LOPDGDD). La edad de consentimiento digital en España se sitúa, según lo previsto, en 14 años; **debe verificarse con asesoría legal**, igual que el resto de los puntos de este documento, que son una lista de cuestiones por resolver y no asesoramiento jurídico.
4. **Publicación:** los datos del representante, la fecha de nacimiento y los datos de contacto nunca se publican.

## Comportamiento actual

- El formulario público solo admite inscritos con una edad igual o superior a `PUBLIC_FORM_MIN_AGE` (18 por defecto, `config/champcontrol.php`) en la fecha del evento.
- El alta manual por el organizador sí admite menores, bajo su responsabilidad: es quien debe haber obtenido el consentimiento necesario fuera de la aplicación.
- Las páginas públicas muestran únicamente nombre, club, categoría, cuadro y resultado.
