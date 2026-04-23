<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades para que viajen en el OTA — release 1.20.0.
 *
 * Patrón canónico: por cada bump de versión, una migración idempotente
 * (por title + version_ref) que siembra customer_notes para el cliente final.
 *
 * Reglas editoriales: lenguaje de capacidad, no de defecto. Sin paths, sin
 * endpoints, sin nombres de librerías. Foco en qué puede hacer hoy que ayer no.
 */

return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Nueva forma de trabajar con Notas CRM',
            'body_html' => '<p>Rediseñamos por completo la pantalla de <strong>Notas del CRM</strong> para que trabajar con ellas sea mucho más ágil. Ahora tenés la lista a la izquierda y el detalle de la nota a la derecha — recorrés todas tus notas sin tener que entrar y salir una por una.</p>'
                         . '<p>Sumamos además:</p>'
                         . '<ul>'
                         . '<li><strong>Búsqueda en vivo</strong> que filtra la lista a medida que escribís, sin recargar la página.</li>'
                         . '<li><strong>Navegación con el teclado</strong>: las flechas ↑ y ↓ te mueven entre notas, y Enter en el buscador te lleva directo al primer resultado.</li>'
                         . '<li><strong>La suite recuerda dónde estabas</strong>: si salís del listado y volvés más tarde, la nota que estabas mirando queda seleccionada. Mismo comportamiento para cada pestaña (Activos y Papelera).</li>'
                         . '<li>Al <strong>crear, editar o copiar</strong> una nota, el listado vuelve parado en esa misma nota — no más búsqueda manual para reencontrarla.</li>'
                         . '</ul>'
                         . '<p>Toda la funcionalidad anterior (importación Excel, exportación, papelera, vinculación con clientes y tratativas, etiquetas, adjuntos) se mantiene intacta.</p>',
            'category' => 'feature',
            'version_ref' => '1.20.0',
            'published_at' => '2026-04-23 20:00:00',
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
