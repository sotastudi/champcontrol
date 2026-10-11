# Decisiones pendientes

Valores provisionales adoptados en la primera versión mientras no se confirmen con el reglamento de los campeonatos reales.

| Decisión | Valor provisional | Dónde está en el código | Quién decide |
| --- | --- | --- | --- |
| Regla del bronce | `BronzeRule::BronzeMatch` (propuesta: combate por el bronce entre los perdedores de semifinal). Alternativa: `BronzeRule::SharedBronze` (dos terceros compartidos). La UI de categoría y de cuadro lo marca como «DECISIÓN PENDIENTE». | `app/Enums/BronzeRule.php`, `app/Tournament/PodiumCalculator.php`, vistas de categoría y de cuadro | Organización / reglamento del campeonato |
| Retiradas y descalificaciones | **No implementadas.** No hay victoria automática ni estado específico. Las bajas de inscritos con resultados en el cuadro se resuelven manualmente: `RegistrationService::changeStatus` bloquea cualquier cambio de estado distinto de «confirmada» en una inscripción que está en un cuadro con resultados (no solo la cancelación). | `app/Registrations/RegistrationService.php` | Organización / reglamento |
| Una persona en varias categorías del mismo evento | Permitido. Identidad = nombre + apellidos normalizados + fecha de nacimiento, por evento. | `Competitor::normalizeName`, `app/Registrations/RegistrationService.php` | Organización |
| Un único inscrito en una categoría | Sin cuadro ni campeón automático; la clasificación se resuelve manualmente. | `app/Tournament/BracketGenerator.php` | Organización / reglamento |
| Edad mínima del formulario público | 18 años en la fecha del evento (`PUBLIC_FORM_MIN_AGE`). Ver `docs/menores.md`. | `config/champcontrol.php` | Organización, con asesoría legal |
| Correcciones tras finalizar el evento | Requieren reabrir el evento antes de corregir un resultado. | `app/Tournament/ResultRecorder.php` | Organización |

## Notas de implementación

- **Cambios de inscripciones con resultados ya registrados.** Una vez hay resultados en la categoría, las nuevas confirmaciones no regeneran el cuadro: este queda marcado como «desactualizado» y el organizador lo resuelve manualmente según el reglamento. Solo se puede regenerar mientras no haya resultados.
- **Cancelaciones con resultados en el cuadro.** Cambiar de «confirmada» a otro estado (cancelar o volver a pendiente) una inscripción que ya figura en el cuadro con resultados está bloqueado (ver retiradas y descalificaciones, arriba).
