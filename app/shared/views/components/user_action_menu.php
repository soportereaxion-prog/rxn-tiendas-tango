<?php
$userNameText = htmlspecialchars($_SESSION['user_name'] ?? 'Usuario');
?>
<div class="d-none d-md-flex align-items-center gap-2 bg-light border rounded-pill px-3 py-1 shadow-sm">
    <span class="text-secondary small fw-bold">
        <i class="bi bi-person text-info"></i> <?= $userNameText ?>
    </span>
    <div class="vr mx-1 text-secondary opacity-25"></div>
    <a href="/rxnTiendasIA/public/logout" class="btn btn-sm text-danger p-0" title="Cerrar Sesi&oacute;n" style="line-height: 1;">
        <i class="bi bi-box-arrow-right fs-6"></i>
    </a>
</div>
