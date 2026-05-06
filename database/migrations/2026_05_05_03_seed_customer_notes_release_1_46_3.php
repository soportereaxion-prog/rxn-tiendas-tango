<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.46.3.
 *
 * Auditoría de eliminación de PDS. La nota apunta al beneficio de capacidad
 * para el cliente final: ahora hay rastro de qué se borró y si quedó pendiente
 * en el ERP.
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Auditoría de Pedidos de Servicio eliminados',
            'body_html' => <<<'HTML'
<p>Sumamos una nueva auditoría que registra cada Pedido de Servicio eliminado permanentemente desde la papelera. Quedan guardados todos los datos que tenía al momento del borrado: cliente, fecha, técnico, diagnóstico, número de Tango y quién hizo la eliminación.</p>
<ul>
    <li><strong>Visibilidad inmediata desde RXN Live</strong>: en el menú de RXN Live aparece un nuevo dataset "PDS Eliminados (Auditoría)" donde se puede listar, filtrar y exportar el historial de borrados.</li>
    <li><strong>Detector de huérfanos en Tango</strong>: una columna específica indica si el PDS eliminado ya tenía número de Tango asignado. Si dice "Sí — quedó huérfano en Tango", significa que el pedido sigue en el ERP y falta anularlo manualmente allá.</li>
    <li><strong>Sin pérdida de información</strong>: el snapshot completo del registro se conserva, así que cualquier dato del PDS original sigue disponible aunque ya no exista en la base activa.</li>
</ul>
<p>El registro se genera automáticamente al borrar un PDS desde la papelera. No requiere ninguna acción extra del operador.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.46.3',
            'published_at' => '2026-05-05 23:30:00',
        ],
    ];

    $check = $db->prepare("SELECT COUNT(*) FROM customer_notes WHERE title = :t AND version_ref = :v");
    $insert = $db->prepare("
        INSERT INTO customer_notes (title, body_html, category, version_ref, status, published_at, created_at, updated_at)
        VALUES (:title, :body, :category, :version_ref, 'published', :published_at, NOW(), NOW())
    ");

    foreach ($notes as $n) {
        $check->execute([':t' => $n['title'], ':v' => $n['version_ref']]);
        if ((int) $check->fetchColumn() > 0) {
            continue;
        }
        $insert->execute([
            ':title' => $n['title'],
            ':body' => $n['body_html'],
            ':category' => $n['category'],
            ':version_ref' => $n['version_ref'],
            ':published_at' => $n['published_at'],
        ]);
    }
};
