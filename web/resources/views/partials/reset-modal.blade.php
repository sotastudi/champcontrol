{{-- Aviso de confirmación para reiniciar resultados. Los botones con data-reset-url rellenan el formulario y el mensaje. --}}
<div class="modal fade" id="reset-modal" tabindex="-1" aria-labelledby="reset-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border border-danger border-3">
            <div class="modal-header bg-danger text-white">
                <svg width="26" height="26" aria-hidden="true"><use href="#warn"/></svg>
                <h2 class="modal-title h5 ms-2" id="reset-modal-title">Reiniciar resultados</h2>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold fs-6" data-reset-message></p>
                <div class="alert alert-danger mb-0 d-flex align-items-center gap-2" role="alert">
                    <svg width="20" height="20" aria-hidden="true" class="flex-shrink-0"><use href="#warn"/></svg>
                    <span><strong>Esta acción no se puede deshacer.</strong> Los resultados borrados tendrás que volver a puntuarlos.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="#" data-reset-form class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-icon"><svg width="16" height="16" aria-hidden="true"><use href="#undo"/></svg> Sí, reiniciar</button>
                </form>
            </div>
        </div>
    </div>
</div>
