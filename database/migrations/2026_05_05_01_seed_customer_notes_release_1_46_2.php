<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.46.2 (hotfix).
 *
 * Hotfix de exportación XLSX: restauramos la dependencia OpenSpout que
 * había desaparecido silenciosamente del entorno. El cliente final no ve
 * el detalle técnico — solo que "Excel" vuelve a funcionar.
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Exportación a Excel restaurada en RXN Live y CRM Notas',
            'body_html' => <<<'HTML'
<p>El botón <strong>Excel</strong> de RXN Live y la matriz de ejemplo + importación de CRM Notas vuelven a funcionar correctamente. Si en los últimos días viste el mensaje "La exportación requiere que OpenSpout esté instalado" al intentar bajar un dataset, este hotfix lo resuelve.</p>
<p>Nada cambia en el flujo de uso — seguís exportando como siempre, y el archivo se descarga con los mismos estilos y columnas que ya conocías.</p>
HTML,
            'category' => 'fix',
            'version_ref' => '1.46.2',
            'published_at' => '2026-05-05 16:00:00',
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
