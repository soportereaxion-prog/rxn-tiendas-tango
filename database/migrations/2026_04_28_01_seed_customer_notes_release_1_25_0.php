<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.25.0.
 *
 * Dos mejoras visibles para el operador final:
 *  - Notas con recordatorio que aparecen en la Agenda y disparan la campanita.
 *  - Filtro por columna que ahora se ve prolijo en cualquier listado, sin
 *    importar cuántas filas tenga.
 *
 * Lenguaje de capacidad, sin referencias a archivos/endpoints/dependencias.
 */

return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Notas con recordatorio en la Agenda + campanita',
            'body_html' => <<<'HTML'
<p>Las <strong>notas del CRM</strong> ahora pueden tener un <strong>recordatorio opcional</strong> y aparecer en el calendario.</p>
<ul>
    <li>Al crear o editar una nota podés setear una <em>fecha y hora de recordatorio</em>.</li>
    <li>Cuando la nota tiene recordatorio se proyecta en la <strong>Agenda CRM</strong> con color rosa, junto a PDS, Presupuestos, Tratativas y Llamadas.</li>
    <li>El nuevo checkbox <em>"Notas"</em> en los filtros de la Agenda te deja mostrar u ocultar las notas con un click.</li>
    <li>A la hora del recordatorio recibís una <strong>notificación in-app</strong> en la campanita del topbar, con link directo a la nota.</li>
    <li>Pensado para los flujos de "Pendientes general": creás una nota con la próxima acción y te avisamos cuando llega el momento, sin depender de calendarios externos.</li>
</ul>
HTML,
            'category' => 'feature',
            'version_ref' => '1.25.0',
            'published_at' => '2026-04-28 12:00:00',
        ],
        [
            'title' => 'Filtros por columna más prolijos en todos los listados',
            'body_html' => <<<'HTML'
<p>El embudo de filtro por columna que está en los listados (Pedidos, Presupuestos, Tratativas, Llamadas, Clientes, Artículos, etc.) <strong>se comporta correctamente en cualquier situación</strong>, incluso cuando el listado tiene muy pocas filas o el filtro queda cerca del borde de la pantalla.</p>
<ul>
    <li>El panel ahora flota por encima del listado con anclaje fijo al ícono, sin quedar atrapado dentro del área de la tabla.</li>
    <li>Si el panel no entra para abajo, se abre automáticamente hacia arriba.</li>
    <li>Si scrolleás o redimensionás la ventana mientras el panel está abierto, se cierra solo para no quedar flotando huérfano.</li>
    <li>Aplica de forma transversal a toda la suite, sin necesidad de tocar nada en cada listado.</li>
</ul>
HTML,
            'category' => 'improvement',
            'version_ref' => '1.25.0',
            'published_at' => '2026-04-28 12:00:00',
        ],
    ];

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
