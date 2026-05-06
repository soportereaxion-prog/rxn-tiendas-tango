<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.46.4.
 *
 * Iteración 50: auditoría de Presupuestos eliminados + endurecimiento de seguridad
 * en módulos de sincronización. La nota habla en lenguaje de capacidad para el
 * cliente final, sin detalle técnico (sin paths, sin nombres de clases, sin CVEs).
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Auditoría de Presupuestos eliminados',
            'body_html' => <<<'HTML'
<p>Extendimos la auditoría de eliminaciones permanentes que ya cubría los Pedidos de Servicio: ahora también registra cada Presupuesto borrado desde la papelera, con los mismos detalles operativos.</p>
<ul>
    <li><strong>Visibilidad desde RXN Live</strong>: nuevo dataset "Presupuestos Eliminados (Auditoría)" para listar, filtrar y exportar el historial.</li>
    <li><strong>Detector de huérfanos en Tango</strong>: una columna indica si el presupuesto eliminado ya tenía número de comprobante asignado en el ERP. Si dice "Sí — quedó huérfano en Tango", el comprobante sigue en Tango y falta anularlo allá.</li>
    <li><strong>Snapshot completo conservado</strong>: cliente, fecha, total, estado, vendedor, comentarios, tratativa asociada y todos los campos del presupuesto se conservan aunque la fila original ya no exista.</li>
    <li><strong>Atribución</strong>: queda registrado quién hizo la eliminación, automáticamente.</li>
</ul>
<p>El registro se genera de forma transparente al borrar un Presupuesto desde la papelera. No requiere ninguna acción extra del operador.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.46.4',
            'published_at' => '2026-05-06 02:00:00',
        ],
        [
            'title' => 'Refuerzo de seguridad en sincronización con Tango',
            'body_html' => <<<'HTML'
<p>Aplicamos un endurecimiento defensivo sobre la consola de Sincronización con Tango y la integración general con el ERP. Los flujos de Sync Artículos, Sync Clientes, Sync Precios, Sync Stock y Sync Catálogos quedan blindados contra disparos no autorizados desde sitios externos o correos con contenido malicioso.</p>
<ul>
    <li><strong>Protección contra disparos involuntarios</strong>: ningún flujo de sincronización puede ejecutarse sin la confirmación explícita del operador desde la propia interfaz de la suite. Esto previene que abrir un mail HTML o un link externo pueda iniciar una sincronización por accidente, aunque el usuario tenga sesión activa.</li>
    <li><strong>Mensajes de error más prolijos</strong>: ante un fallo inesperado durante la sincronización, el operador ve un aviso claro que lo invita a revisar los registros del servidor con su técnico, en lugar de un mensaje técnico extenso.</li>
    <li><strong>Sin cambios visibles para el operador habitual</strong>: el flujo normal sigue funcionando exactamente igual — los botones, las precondiciones del Circuito de Sync y el panel de resultados están intactos.</li>
</ul>
<p>Estos refuerzos también aplican a la gestión de adjuntos en Notas y Presupuestos: los mensajes de error ya no exponen detalle interno del servidor.</p>
HTML,
            'category' => 'security',
            'version_ref' => '1.46.4',
            'published_at' => '2026-05-06 02:00:00',
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
