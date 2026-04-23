<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades para que viajen en el OTA — release 1.20.1 (hotfix).
 *
 * Placeholder: el release es puramente interno (TypeError en tipado de
 * CrmNota::$tratativa_numero). No hay capacidad nueva visible para el
 * cliente final, por lo tanto el array de notas queda vacío a propósito.
 *
 * Se mantiene el archivo por convención: cada bump de versión genera su
 * migración de seed, vacía o no, para que el patrón quede visible en el repo.
 */

return function (): void {
    $db = Database::getConnection();

    $notes = [
        // Sin notas — hotfix interno sin capacidad visible para el cliente.
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
