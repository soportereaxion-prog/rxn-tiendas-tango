<?php
/**
 * Componente reusable: campanita de notificaciones.
 *
 * Se incluye en el topbar global (backoffice_user_banner.php). Renderiza el
 * dropdown vacío y deja que rxn-notifications.js lo hidrate vía /notifications/feed.json.
 *
 * El badge numérico aparece arriba a la derecha del icono cuando hay no-leídas.
 * El dropdown se hidrata on-demand al hacer click — no se polea en intervalos
 * para no recargar contexto sin necesidad.
 */
$_currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$_currentEmpresaId = (int) (\App\Core\Context::getEmpresaId() ?? 0);
if ($_currentUserId <= 0 || $_currentEmpresaId <= 0) {
    return;
}
?>
<div class="dropdown rxn-notif-wrapper">
    <button class="btn btn-sm btn-link p-0 position-relative rxn-notif-trigger"
            type="button"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-expanded="false"
            title="Notificaciones"
            id="rxnNotifTrigger">
        <i class="bi bi-bell-fill" style="font-size: 1rem; color: var(--bs-secondary);"></i>
        <span class="position-absolute translate-middle badge rounded-pill bg-danger rxn-notif-badge" style="top: 4px; left: 100%; display: none; font-size: 0.6rem;">
            0
        </span>
    </button>

    <div class="dropdown-menu dropdown-menu-end rxn-notif-dropdown shadow" style="min-width: 340px; max-width: 380px;" aria-labelledby="rxnNotifTrigger">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <strong class="small">Notificaciones</strong>
            <a href="/notifications" class="small text-decoration-none">Ver todas</a>
        </div>
        <div class="rxn-notif-items" style="max-height: 360px; overflow-y: auto;">
            <div class="text-center text-muted small py-4 rxn-notif-loading">
                <i class="spinner-border spinner-border-sm"></i> Cargando…
            </div>
        </div>
        <div class="border-top px-3 py-2 text-end rxn-notif-footer" style="display: none;">
            <button class="btn btn-link btn-sm p-0 rxn-notif-mark-all">Marcar todas como leídas</button>
        </div>
    </div>
</div>
