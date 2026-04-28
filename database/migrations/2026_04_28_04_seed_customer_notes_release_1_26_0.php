<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.26.0.
 *
 * Mejora de robustez visible para el operador: los recordatorios de notas
 * ahora suenan a tiempo aunque el operador no haya tenido la suite abierta
 * en ese minuto exacto.
 *
 * Lenguaje de capacidad, sin referencias a archivos/endpoints/dependencias.
 */

return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Recordatorios de notas más confiables',
            'body_html' => <<<'HTML'
<p>Los <strong>recordatorios de notas</strong> ahora se disparan <strong>a la hora exacta que pediste</strong>, aunque no estés con la suite abierta en ese momento.</p>
<ul>
    <li>Cuando entres a la suite vas a encontrar la notificación esperándote en la campanita, lista para abrir la nota correspondiente.</li>
    <li>Antes el aviso aparecía la próxima vez que abrías la suite — ahora se crea puntualmente y queda esperando.</li>
    <li>Aplica a todas las notas con fecha de recordatorio, sin que tengas que cambiar nada en tu flujo.</li>
</ul>
HTML,
            'category' => 'improvement',
            'version_ref' => '1.26.0',
            'published_at' => '2026-04-28 18:00:00',
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
