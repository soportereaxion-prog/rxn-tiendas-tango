<?php

$backofficeUserSummary = \App\Shared\Services\BackofficeContextService::currentUserSummary();
$backofficeUserName = (string) ($backofficeUserSummary['userName'] ?? 'Usuario');
$backofficeEmpresaNombre = trim((string) ($backofficeUserSummary['empresaNombre'] ?? ''));
$backofficeStoreUrl = $backofficeUserSummary['storeUrl'] ?? null;
$backofficeStoreLabel = (string) ($backofficeUserSummary['storeLabel'] ?? '');
$currentTheme = $_SESSION['pref_theme'] ?? 'light';
$oppositeThemeBtn = $currentTheme === 'dark' ? '☀️' : '🌙';

// Determine available navigation sections for mobile offcanvas
$isAdmin = \App\Modules\Auth\AuthService::hasAdminPrivileges();
$isRxnAdmin = \App\Modules\Auth\AuthService::isRxnAdmin();
$hasTiendas = \App\Modules\Empresas\EmpresaAccessService::hasTiendasAccess();
$hasCrm = \App\Modules\Empresas\EmpresaAccessService::hasCrmAccess();

// DbSwitcher dev-only. En prod el archivo config no existe → $devDbSwitcherAvailable queda vacío
// y el dropdown directamente no se renderiza.
$devDbAvailable = [];
$devDbActive = null;
if (\App\Shared\Services\DevDbSwitcher::isEnabled() && $isRxnAdmin) {
    $devDbAvailable = \App\Shared\Services\DevDbSwitcher::getAvailable();
    $devDbActive = \App\Shared\Services\DevDbSwitcher::getActiveOverride();
    // Si no hay override explícito, la DB activa es la primera del config (convención: debe
    // coincidir con el DB_NAME del .env para que el badge refleje la realidad).
    if ($devDbActive === null && !empty($devDbAvailable)) {
        $devDbActive = array_key_first($devDbAvailable);
    }
}
?>
<div class="container-fluid px-4 d-flex justify-content-between align-items-center gap-2 mb-1">
    <!-- Left zone: hamburger (mobile) + topbar left content -->
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary d-lg-none rounded-pill px-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#rxnMobileNav" aria-controls="rxnMobileNav" aria-label="Menú">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div id="topbar-left-zone" class="d-flex align-items-center">
            <?= isset($topbarLeftHtml) ? $topbarLeftHtml : '' ?>
        </div>
    </div>
    <!-- Right zone: user menu -->
    <div class="d-flex align-items-center justify-content-end gap-2 flex-grow-0">
        <?php if (!empty($devDbAvailable)): ?>
        <?php
            // Badge visual permanente — en rojo si la DB activa NO es la primera del config
            // (convención: la primera = DB de dev "limpia"). Ayuda a no confundirse cuando
            // estamos trabajando sobre un snapshot de prod.
            $firstDb = array_key_first($devDbAvailable);
            $isDefaultDb = $devDbActive === $firstDb;
            $badgeClass = $isDefaultDb ? 'bg-success-subtle text-success' : 'bg-danger text-white';
            $activeLabel = $devDbAvailable[$devDbActive] ?? $devDbActive;
        ?>
        <form method="POST" action="/admin/dev-db-switch" class="d-flex align-items-center gap-1 bg-light border rounded-pill px-2 py-1 shadow-sm" title="Cambiar base de datos (dev only)">
            <span class="small fw-semibold text-secondary d-none d-md-inline">DB:</span>
            <select name="db" class="form-select form-select-sm border-0 bg-transparent fw-semibold py-0 <?= $isDefaultDb ? '' : 'text-danger' ?>" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
                <?php foreach ($devDbAvailable as $dbName => $label): ?>
                    <option value="<?= htmlspecialchars($dbName) ?>" <?= $dbName === $devDbActive ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <div class="d-flex align-items-center gap-2 bg-light border rounded-pill px-3 py-1 shadow-sm rxn-user-menu text-secondary">
        <?php \App\Core\View::render('app/shared/views/components/notifications_bell.php'); ?>
        <div class="vr mx-1 opacity-25"></div>
        <button class="btn btn-sm btn-link p-0 text-decoration-none" id="backendThemeToggleBtn" title="Cambiar Tema" style="line-height:1; font-size:1.1rem; filter: grayscale(0.5);">
            <?= $oppositeThemeBtn ?>
        </button>
        <div class="vr mx-1 opacity-25 d-none d-sm-block"></div>
        <span class="small fw-semibold d-none d-sm-inline">
            <i class="bi bi-person-circle text-info"></i>
            Hola, <?= htmlspecialchars($backofficeUserName) ?>
            <?php if ($backofficeEmpresaNombre !== ''): ?>
                <span class="text-muted d-none d-lg-inline">| <?= htmlspecialchars($backofficeEmpresaNombre) ?></span>
            <?php endif; ?>
        </span>
        <div class="vr mx-1 opacity-25"></div>
        <a href="/logout" class="btn btn-sm text-danger p-0 d-flex align-items-center" title="Cerrar Sesi&oacute;n" style="line-height: 1;">
            <i class="bi bi-box-arrow-right fs-6"></i>
        </a>
    </div>
    <div class="d-none d-md-block">
        <?php if (is_string($backofficeStoreUrl) && $backofficeStoreUrl !== ''): ?>
            <a href="<?= htmlspecialchars($backofficeStoreUrl) ?>" class="btn btn-sm btn-outline-info rounded-pill px-3" target="_blank" rel="noopener noreferrer">
                <i class="bi bi-box-arrow-up-right"></i> Abrir tienda
            </a>
            <span class="small text-muted mb-0"><?= htmlspecialchars($backofficeStoreLabel) ?></span>
        <?php endif; ?>
    </div>
    </div>
</div>

<!-- Mobile Navigation Offcanvas -->
<div class="offcanvas offcanvas-start rxn-mobile-nav" tabindex="-1" id="rxnMobileNav" aria-labelledby="rxnMobileNavLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="rxnMobileNavLabel">
            <i class="bi bi-grid-3x3-gap-fill me-2"></i>Navegación
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body px-0">
        <!-- User info (visible only in offcanvas on mobile) -->
        <div class="px-3 pb-3 mb-2 border-bottom">
            <div class="small text-muted">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($backofficeUserName) ?>
                <?php if ($backofficeEmpresaNombre !== ''): ?>
                    <br><span class="opacity-75"><?= htmlspecialchars($backofficeEmpresaNombre) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <nav class="rxn-mobile-nav-sections">
            <!-- Home -->
            <a href="/" class="rxn-mobile-nav-item">
                <i class="bi bi-house-door"></i> Inicio
            </a>

            <?php if ($hasCrm): ?>
            <!-- CRM Section -->
            <div class="rxn-mobile-nav-heading">CRM</div>
            <a href="/mi-empresa/crm/dashboard" class="rxn-mobile-nav-item">
                <i class="bi bi-diagram-3"></i> Dashboard CRM
            </a>
            <a href="/mi-empresa/crm/clientes" class="rxn-mobile-nav-item">
                <i class="bi bi-people"></i> Clientes
            </a>
            <a href="/mi-empresa/crm/presupuestos" class="rxn-mobile-nav-item">
                <i class="bi bi-file-earmark-spreadsheet"></i> Presupuestos
            </a>
            <a href="/mi-empresa/crm/pedidos-servicio" class="rxn-mobile-nav-item">
                <i class="bi bi-tools"></i> Pedidos de Servicio
            </a>
            <a href="/mi-empresa/crm/tratativas" class="rxn-mobile-nav-item">
                <i class="bi bi-briefcase-fill"></i> Tratativas
            </a>
            <a href="/mi-empresa/crm/agenda" class="rxn-mobile-nav-item">
                <i class="bi bi-calendar-event"></i> Agenda
            </a>
            <a href="/mi-empresa/crm/horas" class="rxn-mobile-nav-item">
                <i class="bi bi-stopwatch"></i> Horas (turnero)
            </a>
            <a href="/mi-empresa/crm/notas" class="rxn-mobile-nav-item">
                <i class="bi bi-journal-text"></i> Notas
            </a>
            <a href="/mi-empresa/crm/llamadas" class="rxn-mobile-nav-item">
                <i class="bi bi-telephone-fill"></i> Llamadas
            </a>
            <?php endif; ?>

            <?php if ($hasTiendas): ?>
            <!-- Tiendas Section -->
            <div class="rxn-mobile-nav-heading">Tiendas</div>
            <a href="/mi-empresa/dashboard" class="rxn-mobile-nav-item">
                <i class="bi bi-shop-window"></i> Dashboard Tiendas
            </a>
            <a href="/mi-empresa/articulos" class="rxn-mobile-nav-item">
                <i class="bi bi-box-seam"></i> Artículos
            </a>
            <a href="/mi-empresa/categorias" class="rxn-mobile-nav-item">
                <i class="bi bi-grid-3x3-gap"></i> Categorías
            </a>
            <a href="/mi-empresa/pedidos" class="rxn-mobile-nav-item">
                <i class="bi bi-cart3"></i> Pedidos
            </a>
            <a href="/mi-empresa/clientes" class="rxn-mobile-nav-item">
                <i class="bi bi-people"></i> Clientes Web
            </a>
            <?php endif; ?>

            <?php if ($isRxnAdmin): ?>
            <!-- Admin Section -->
            <div class="rxn-mobile-nav-heading">Administración</div>
            <a href="/admin/dashboard" class="rxn-mobile-nav-item">
                <i class="bi bi-buildings"></i> Backoffice
            </a>
            <a href="/empresas" class="rxn-mobile-nav-item">
                <i class="bi bi-building"></i> Empresas
            </a>
            <a href="/admin/mantenimiento" class="rxn-mobile-nav-item">
                <i class="bi bi-tools"></i> Mantenimiento
            </a>
            <?php endif; ?>

            <!-- Common -->
            <div class="rxn-mobile-nav-heading">Cuenta</div>
            <a href="/mi-perfil" class="rxn-mobile-nav-item">
                <i class="bi bi-person-badge"></i> Mi Perfil
            </a>
            <?php if (is_string($backofficeStoreUrl) && $backofficeStoreUrl !== ''): ?>
            <a href="<?= htmlspecialchars($backofficeStoreUrl) ?>" class="rxn-mobile-nav-item" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> Abrir Tienda
            </a>
            <?php endif; ?>
            <a href="/logout" class="rxn-mobile-nav-item text-danger">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const rootHtml = document.documentElement;

    function applyTheme(theme) {
        if (theme !== 'light' && theme !== 'dark') return;
        rootHtml.setAttribute('data-theme', theme);
        const btn = document.getElementById('backendThemeToggleBtn');
        if (btn) btn.innerText = theme === 'dark' ? '☀️' : '🌙';
    }

    // Sincronización entre pestañas: si otra pestaña cambió el tema, aplicarlo
    // acá sin necesidad de reload (storage event dispara en las OTRAS pestañas).
    window.addEventListener('storage', function (ev) {
        if (ev.key === 'rxn_theme' && ev.newValue) {
            applyTheme(ev.newValue);
        }
    });

    const themeBtn = document.getElementById('backendThemeToggleBtn');
    if (themeBtn && !themeBtn.dataset.bound) {
        themeBtn.dataset.bound = "true";
        themeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const isDark = rootHtml.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';

            applyTheme(newTheme);
            // Broadcast a otras pestañas via localStorage → storage event.
            try { localStorage.setItem('rxn_theme', newTheme); } catch (_) {}

            fetch('/mi-perfil/toggle-theme', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme: newTheme })
            }).catch(err => console.error("Error toggle theme", err));
        });
    }
});
</script>
