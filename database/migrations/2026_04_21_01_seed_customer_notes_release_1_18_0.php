<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades para que viajen en el OTA — release 1.18.0.
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
            'title' => 'Ya podés adjuntar archivos a tus notas y presupuestos',
            'body_html' => '<p>Ahora cada <strong>nota</strong> y cada <strong>presupuesto</strong> puede llevar archivos adjuntos: PDFs, planillas, imágenes, comprimidos, audios, videos cortos — todo lo que necesites para tener el contexto completo a mano.</p>'
                         . '<p>Podés subir hasta <strong>10 archivos</strong> por registro y <strong>100 MB por archivo</strong>. Para imágenes sumamos un visor rápido con un clic, sin necesidad de descargarlas primero.</p>'
                         . '<p>Los archivos se guardan en forma aislada por empresa y sólo son accesibles por usuarios autorizados de tu equipo.</p>',
            'category' => 'feature',
            'version_ref' => '1.18.0',
            'published_at' => '2026-04-21 20:00:00',
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
