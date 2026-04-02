<?php
$userNameText = htmlspecialchars($_SESSION['user_name'] ?? 'Usuario');
$currentTheme = $_SESSION['pref_theme'] ?? 'light';
$oppositeThemeBtn = $currentTheme === 'dark' ? '☀️' : '🌙';
?>
<div class="d-none d-md-flex align-items-center gap-2 bg-light border rounded-pill px-3 py-1 shadow-sm rxn-user-menu text-secondary">
    <button class="btn btn-sm btn-link p-0 text-decoration-none" id="backendThemeToggleBtn" title="Cambiar Tema" style="line-height:1; font-size:1.1rem; filter: grayscale(0.5);">
        <?= $oppositeThemeBtn ?>
    </button>
    <div class="vr mx-1 opacity-25"></div>
    <span class="small fw-bold">
        <i class="bi bi-person text-info"></i> <?= $userNameText ?>
    </span>
    <div class="vr mx-1 opacity-25"></div>
    <a href="/rxnTiendasIA/public/logout" class="btn btn-sm text-danger p-0 d-flex align-items-center" title="Cerrar Sesi&oacute;n" style="line-height: 1;">
        <i class="bi bi-box-arrow-right fs-6"></i>
    </a>
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
