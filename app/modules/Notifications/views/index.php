<?php
$pageTitle = 'Notificaciones';
$csrf = \App\Core\CsrfHelper::generateToken();
ob_start();
?>
<div class="container-fluid mt-2 mb-5 rxn-form-shell">
    <div class="rxn-module-header mb-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="fw-bold mb-1"><i class="bi bi-bell-fill text-warning"></i> Notificaciones</h1>
            <div class="text-muted small">
                <?= (int) $total ?> en total ·
                <span class="<?= $unread > 0 ? 'text-warning fw-semibold' : '' ?>"><?= (int) $unread ?> sin leer</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($unread > 0): ?>
                <button id="markAllRead" class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-check2-all"></i> Marcar todas como leídas
                </button>
            <?php endif; ?>
            <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm" title="Volver">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <ul class="nav nav-pills mb-3 small" role="tablist">
        <?php foreach (['all' => 'Todas', 'unread' => 'No leídas', 'read' => 'Leídas'] as $key => $label): ?>
            <li class="nav-item">
                <a class="nav-link <?= $filter === $key ? 'active' : '' ?>" href="?filter=<?= $key ?>">
                    <?= $label ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($items)): ?>
        <div class="alert alert-secondary">
            <i class="bi bi-inbox"></i>
            <?php if ($filter === 'unread'): ?>
                No tenés notificaciones sin leer.
            <?php elseif ($filter === 'read'): ?>
                Todavía no marcaste ninguna como leída.
            <?php else: ?>
                No tenés notificaciones por ahora.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card rxn-form-card">
            <ul class="list-group list-group-flush rxn-notif-list">
                <?php foreach ($items as $n): ?>
                    <?php
                    $isRead = !empty($n['is_read']);
                    $createdLabel = '';
                    try {
                        $dt = new DateTimeImmutable((string) $n['created_at']);
                        $createdLabel = $dt->format('d/m/Y H:i');
                    } catch (\Throwable $_) {}
                    ?>
                    <li class="list-group-item d-flex gap-3 align-items-start <?= $isRead ? '' : 'rxn-notif-unread' ?>" data-notif-id="<?= (int) $n['id'] ?>">
                        <div class="rxn-notif-dot <?= $isRead ? 'is-read' : '' ?>"></div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) $n['title']) ?></div>
                                    <?php if (!empty($n['body'])): ?>
                                        <div class="small text-muted mt-1"><?= nl2br(htmlspecialchars((string) $n['body'])) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-1">
                                        <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars((string) $n['type']) ?></span>
                                        <span class="ms-2"><?= htmlspecialchars($createdLabel) ?></span>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <?php if (!empty($n['link'])): ?>
                                        <a href="<?= htmlspecialchars((string) $n['link']) ?>" class="btn btn-sm btn-outline-primary" title="Abrir">
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$isRead): ?>
                                        <button class="btn btn-sm btn-outline-success rxn-notif-read" title="Marcar como leída">
                                            <i class="bi bi-check2"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger rxn-notif-delete" title="Eliminar">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    const csrfToken = <?= json_encode($csrf) ?>;

    function postJson(url) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(csrfToken)
        }).then(r => r.json());
    }

    document.querySelectorAll('.rxn-notif-read').forEach(btn => {
        btn.addEventListener('click', function () {
            const li = btn.closest('[data-notif-id]');
            const id = li.dataset.notifId;
            postJson('/notifications/' + id + '/leer').then(() => {
                li.classList.remove('rxn-notif-unread');
                li.querySelector('.rxn-notif-dot')?.classList.add('is-read');
                btn.remove();
            });
        });
    });

    document.querySelectorAll('.rxn-notif-delete').forEach(btn => {
        btn.addEventListener('click', function () {
            const li = btn.closest('[data-notif-id]');
            const id = li.dataset.notifId;
            postJson('/notifications/' + id + '/eliminar').then(() => {
                li.style.transition = 'opacity 0.2s';
                li.style.opacity = '0';
                setTimeout(() => li.remove(), 200);
            });
        });
    });

    const allBtn = document.getElementById('markAllRead');
    if (allBtn) {
        allBtn.addEventListener('click', function () {
            postJson('/notifications/marcar-todas-leidas').then(() => {
                location.reload();
            });
        });
    }
})();
</script>
<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
