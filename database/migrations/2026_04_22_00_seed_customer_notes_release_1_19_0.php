<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades para que viajen en el OTA — release 1.19.0.
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
            'title' => 'Navegación más prolija en toda la suite',
            'body_html' => '<p>Pusimos orden en la forma de moverse por la suite. Ahora el botón <strong>Volver</strong> se encuentra siempre en el mismo lugar y con el mismo estilo en cada módulo — así no hace falta buscarlo y la operación diaria fluye mejor.</p>'
                         . '<p>Además, cuando trabajás un <strong>Pedido de Servicio desde una Tratativa</strong>, ahora podés ir guardando sin que te saque del PDS: el <strong>Guardar</strong> te deja acá mismo mientras seguís componiendo, y el <strong>Volver</strong> es el que te lleva de regreso a la tratativa origen.</p>'
                         . '<p>También mejoramos varios detalles visuales menores en presupuestos, reportes y el panel de mantenimiento.</p>',
            'category' => 'mejora',
            'version_ref' => '1.19.0',
            'published_at' => '2026-04-22 20:00:00',
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
