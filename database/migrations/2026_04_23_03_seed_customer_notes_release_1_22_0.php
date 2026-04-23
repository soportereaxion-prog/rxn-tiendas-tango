<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades para que viajen en el OTA — release 1.22.0.
 *
 * Esta release unifica también las capacidades visibles del 1.21.0
 * (que había quedado SIN OTA), así que la nota cubre ambos releases
 * juntos: mejoras transversales de UX + encuadre pleno de RXN Live.
 *
 * Lenguaje de capacidad, sin referencias a archivos/endpoints/dependencias.
 */

return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'RXN Live encuadra las vistas analíticas en tu pantalla',
            'body_html' => <<<'HTML'
<p>Tus listados de <strong>RXN Live</strong> ahora se acomodan al tamaño exacto de tu ventana, sin importar el monitor que uses.</p>
<ul>
    <li>La paginación y los totales quedan siempre visibles al pie de la pantalla — sin scroll del navegador.</li>
    <li>Cuando mostrás u ocultás el gráfico analítico, o alternás entre vista plana y tabla dinámica, el encuadre se recalcula solo.</li>
    <li>En monitores chicos o grandes, el alto disponible para las filas se aprovecha al máximo sin dejar huecos.</li>
</ul>
<p>Pensado para que recorrer muchos registros —como en Pedidos de Servicio o Ventas Histórico— sea fluido y sin fricción.</p>
HTML,
            'category' => 'mejora',
            'version_ref' => '1.22.0',
            'published_at' => '2026-04-23 12:00:00',
        ],
        [
            'title' => 'Toda la app en tu ancho completo, con tema claro coherente',
            'body_html' => <<<'HTML'
<p>Refinamos la experiencia visual en toda la suite:</p>
<ul>
    <li><strong>Ancho completo</strong> en presupuestos, pedidos de servicio, notas, tratativas, agenda, dashboards y más — aprovechás todo el espacio de tu pantalla.</li>
    <li><strong>Tema claro</strong> coherente en los módulos de CRM (notas, agenda, tratativas, llamadas) y en RXN Live — si preferís fondo claro, ahora se ve parejo en todos lados.</li>
    <li><strong>Atajo Escape = Volver</strong>: desde cualquier formulario, la tecla Escape te lleva al destino correcto (la tratativa de origen si estabas creando/editando desde una tratativa, o el listado si venías de ahí).</li>
    <li><strong>Formularios más compactos</strong>: secciones con menos aire entre sí para ver más campos de un vistazo.</li>
    <li><strong>Cambio de tema sincronizado entre pestañas</strong>: si tenés la app abierta en varias pestañas y cambiás el tema, todas se actualizan al instante.</li>
</ul>
HTML,
            'category' => 'mejora',
            'version_ref' => '1.22.0',
            'published_at' => '2026-04-23 12:05:00',
        ],
    ];

    if (empty($notes)) {
        return;
    }

    $chk = $db->prepare(
        "SELECT COUNT(*) FROM customer_notes
         WHERE title = :t AND COALESCE(version_ref, '') = :v"
    );

    $ins = $db->prepare(
        "INSERT INTO customer_notes
            (title, body_html, category, version_ref, status, published_at)
         VALUES
            (:title, :body_html, :category, :version_ref, 'published', :published_at)"
    );

    foreach ($notes as $n) {
        $chk->execute([
            ':t' => $n['title'],
            ':v' => (string) ($n['version_ref'] ?? ''),
        ]);
        if ((int) $chk->fetchColumn() > 0) {
            continue;
        }
        $ins->execute([
            ':title' => $n['title'],
            ':body_html' => $n['body_html'],
            ':category' => $n['category'],
            ':version_ref' => $n['version_ref'] ?? null,
            ':published_at' => $n['published_at'],
        ]);
    }
};
