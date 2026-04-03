<?php
// VISTA TEMPORAL — solo para test de bootstrap. Eliminar cuando haya vista real.
?>
<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<h1>Render de vista: OK</h1>
    <p>Variable recibida: <strong><?= htmlspecialchars($mensaje ?? 'sin datos', ENT_QUOTES, 'UTF-8') ?></strong></p>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
