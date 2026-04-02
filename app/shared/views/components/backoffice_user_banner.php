<?php

$backofficeUserSummary = \App\Shared\Services\BackofficeContextService::currentUserSummary();
$backofficeUserName = (string) ($backofficeUserSummary['userName'] ?? 'Usuario');
$backofficeEmpresaNombre = trim((string) ($backofficeUserSummary['empresaNombre'] ?? ''));
$backofficeStoreUrl = $backofficeUserSummary['storeUrl'] ?? null;
$backofficeStoreLabel = (string) ($backofficeUserSummary['storeLabel'] ?? '');
$currentTheme = $_SESSION['pref_theme'] ?? 'light';
$oppositeThemeBtn = $currentTheme === 'dark' ? '☀️' : '🌙';
?>
<div class="d-flex flex-column align-items-start align-items-lg-end gap-2">
    <div class="d-flex align-items-center gap-2 bg-light border rounded-pill px-3 py-1 shadow-sm rxn-user-menu text-secondary">
        <button class="btn btn-sm btn-link p-0 text-decoration-none" id="backendThemeToggleBtn" title="Cambiar Tema" style="line-height:1; font-size:1.1rem; filter: grayscale(0.5);">
            <?= $oppositeThemeBtn ?>
        </button>
        <div class="vr mx-1 opacity-25"></div>
        <span class="small fw-semibold">
            <i class="bi bi-person-circle text-info"></i>
            Hola, <?= htmlspecialchars($backofficeUserName) ?>
            <?php if ($backofficeEmpresaNombre !== ''): ?>
                <span class="text-muted d-none d-lg-inline">| <?= htmlspecialchars($backofficeEmpresaNombre) ?></span>
            <?php endif; ?>
        </span>
        <div class="vr mx-1 opacity-25"></div>
        <a href="/rxnTiendasIA/public/logout" class="btn btn-sm text-danger p-0 d-flex align-items-center" title="Cerrar Sesi&oacute;n" style="line-height: 1;">
            <i class="bi bi-box-arrow-right fs-6"></i>
        </a>
    </div>
    <div class="px-2">
        <?php if (is_string($backofficeStoreUrl) && $backofficeStoreUrl !== ''): ?>
            <a href="<?= htmlspecialchars($backofficeStoreUrl) ?>" class="btn btn-sm btn-outline-info rounded-pill px-3" target="_blank" rel="noopener noreferrer">
                <i class="bi bi-box-arrow-up-right"></i> Abrir tienda
            </a>
        <?php else: ?>
            <span class="small text-muted"><?= htmlspecialchars($backofficeStoreLabel) ?></span>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const themeBtn = document.getElementById('backendThemeToggleBtn');
    if (themeBtn && !themeBtn.dataset.bound) {
        themeBtn.dataset.bound = "true";
        themeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const rootHtml = document.documentElement;
            const isDark = rootHtml.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            
            rootHtml.setAttribute('data-theme', newTheme);
            themeBtn.innerText = newTheme === 'dark' ? '☀️' : '🌙';

            fetch('/rxnTiendasIA/public/mi-perfil/toggle-theme', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme: newTheme })
            }).catch(err => console.error("Error toggle theme", err));
        });
    }
});
</script>
