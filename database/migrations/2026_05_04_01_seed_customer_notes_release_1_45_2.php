<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.45.2.
 *
 * Fix técnico de UX en alta de empresa Connect. Es un release de bugfix
 * sin capacidad nueva visible para el cliente final del CRM/Tiendas
 * (es solo para el usuario admin que da de alta empresas en la suite),
 * por eso esta migración va vacía como placeholder explícito.
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [];

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
