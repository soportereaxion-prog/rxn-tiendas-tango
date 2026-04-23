<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades para que viajen en el OTA — release 1.21.0.
 *
 * Placeholder: el release 1.21.0 está bumpeado en version.php pero NO se
 * publicó OTA en la sesión 2026-04-23 por quedar pendiente el bug del
 * scroll vertical de RxnLive (PDS/Clientes). Las notas al cliente final
 * se redactarán cuando el release efectivamente vaya a prod.
 */

return function (): void {
    $db = Database::getConnection();

    $notes = [
        // Pendiente: redactar nota al cliente cuando el release vaya a prod.
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
