<?php
// VISTA TEMPORAL — solo para test de bootstrap. Eliminar cuando haya vista real.
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Test Vista</title></head>
<body>
    <h1>Render de vista: OK</h1>
    <p>Variable recibida: <strong><?= htmlspecialchars($mensaje ?? 'sin datos', ENT_QUOTES, 'UTF-8') ?></strong></p>
</body>
</html>
