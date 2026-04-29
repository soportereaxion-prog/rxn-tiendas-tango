<?php
$pageTitle = 'Mis borradores';
ob_start();

$csrfToken = \App\Core\CsrfHelper::generateToken();

function rxn_draft_format_bytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 2) . ' MB';
}
?>
<div class="container-fluid mt-2 mb-5 rxn-responsive-container">
    <div class="rxn-module-header mb-4 d-flex justify-content-between align-items-center">
        <h2 class="fw-bold m-0"><i class="bi bi-arrow-counterclockwise me-2"></i>Mis borradores</h2>
        <div>
            <a href="/mi-perfil" class="btn btn-outline-secondary btn-sm" title="Volver a Mi Perfil"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    <div class="alert alert-info py-2 small mb-4">
        <i class="bi bi-info-circle me-1"></i>
        Acá ves todos los formularios que dejaste a medio cargar. Al tocar <strong>Retomar</strong>, abrís el módulo y te aparece el banner para recuperar los datos.
        Los borradores se guardan automáticamente cada pocos segundos mientras tipeás.
    </div>

    <?php if (empty($drafts)): ?>
        <div class="card rxn-form-card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox" style="font-size: 2.5rem; opacity: .35;"></i>
                <p class="mt-3 mb-0 text-secondary">No tenés borradores guardados.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card rxn-form-card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:42%;">Módulo</th>
                                <th>Referencia</th>
                                <th>Última edición</th>
                                <th class="text-end">Tamaño</th>
                                <th class="text-end" style="width:200px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($drafts as $draft): ?>
                            <tr>
                                <td>
                                    <i class="bi <?= htmlspecialchars($draft['modulo_icon']) ?> me-2"></i>
                                    <strong><?= htmlspecialchars($draft['modulo_label']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($draft['ref_key'] === 'new'): ?>
                                        <span class="badge text-bg-secondary">Nuevo</span>
                                    <?php else: ?>
                                        <code>#<?= htmlspecialchars($draft['ref_key']) ?></code>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($draft['updated_at']) ?></td>
                                <td class="text-end text-secondary small"><?= rxn_draft_format_bytes($draft['payload_bytes']) ?></td>
                                <td class="text-end">
                                    <a href="<?= htmlspecialchars($draft['resume_url']) ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-arrow-right-circle"></i> Retomar
                                    </a>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-rxn-draft-discard
                                            data-modulo="<?= htmlspecialchars($draft['modulo']) ?>"
                                            data-ref="<?= htmlspecialchars($draft['ref_key']) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const csrf = <?= json_encode($csrfToken) ?>;
    document.querySelectorAll('[data-rxn-draft-discard]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modulo = btn.getAttribute('data-modulo');
            const ref = btn.getAttribute('data-ref');
            if (!confirm('¿Descartar este borrador? No se puede deshacer.')) return;

            const params = new URLSearchParams();
            params.append('csrf_token', csrf);
            params.append('modulo', modulo);
            params.append('ref', ref);

            fetch('/api/internal/drafts/discard', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString(),
            }).then(function (res) {
                if (!res.ok) throw new Error('discard_failed');
                return res.json();
            }).then(function (data) {
                if (data && data.ok) {
                    btn.closest('tr').remove();
                    if (!document.querySelector('[data-rxn-draft-discard]')) {
                        window.location.reload();
                    }
                }
            }).catch(function () {
                alert('No se pudo descartar el borrador. Intentá de nuevo.');
            });
        });
    });
})();
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
