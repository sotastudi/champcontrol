# Decisiones pendientes

Valores provisionales adoptados en la primera versión mientras no se confirmen con el reglamento de los campeonatos reales.

| Decisión | Valor provisional | Dónde está en el código | Quién decide |
| --- | --- | --- | --- |
| Regla del bronce | **APROBADA**: `BronzeRule::BronzeMatch` (combate por el bronce entre los perdedores de semifinal). `BronzeRule::SharedBronze` (dos terceros compartidos) sigue disponible como opción por categoría. | `app/Enums/BronzeRule.php`, `app/Tournament/PodiumCalculator.php`, vistas de categoría y de cuadro | Organización / reglamento del campeonato |
| Retiradas y descalificaciones | **IMPLEMENTADAS** con estas reglas: solo se registra una baja cuando la categoría ya tiene resultados reales (antes, cancelar la inscripción y regenerar el cuadro). Cada combate pendiente del retirado/descalificado se da por ganado al rival por incomparecencia (walkover); si el rival aún no está definido, se resuelve al llegar (en cascada). Los resultados ya jugados se conservan. Un walkover cuenta como resultado real y no se puede corregir. Si ambos participantes de un combate están de baja, queda pendiente para resolución manual. Podio: el retirado conserva su puesto; el descalificado desaparece del podio y del CSV sin renumerar a los demás, y el panel avisa del puesto vacante (se resuelve según el reglamento). El motivo es privado: las páginas públicas solo muestran «Gana por incomparecencia». **Pendiente:** deshacer una baja y sanciones/puntos (no existen). | `app/Tournament/WithdrawalRecorder.php`, `app/Tournament/BracketAdvancer.php`, `app/Tournament/PodiumCalculator.php`, `app/Tournament/ResultRecorder.php` | Organización / reglamento |
| Una persona en varias categorías del mismo evento | Permitido. Identidad = nombre + apellidos normalizados + fecha de nacimiento, por evento. | `Competitor::normalizeName`, `app/Registrations/RegistrationService.php` | Organización |
| Un único inscrito en una categoría | Sin cuadro ni campeón automático; la clasificación se resuelve manualmente. | `app/Tournament/BracketGenerator.php` | Organización / reglamento |
| Edad mínima del formulario público | 18 años en la fecha del evento (`PUBLIC_FORM_MIN_AGE`). Ver `docs/menores.md`. | `config/champcontrol.php` | Organización, con asesoría legal |
| Correcciones tras finalizar el evento | Requieren reabrir el evento antes de corregir un resultado. | `app/Tournament/ResultRecorder.php` | Organización |

## Notas de implementación

- **Cambios de inscripciones con resultados ya registrados.** Una vez hay resultados en la categoría, las nuevas confirmaciones no regeneran el cuadro: este queda marcado como «desactualizado» y el organizador lo resuelve manualmente según el reglamento. Solo se puede regenerar mientras no haya resultados.
- **Cancelaciones con resultados en el cuadro.** Cambiar de «confirmada» a otro estado (cancelar o volver a pendiente) una inscripción que ya figura en el cuadro con resultados está bloqueado (para bajas con resultados, usar «Retiradas y descalificaciones» en el cuadro; ver arriba).
