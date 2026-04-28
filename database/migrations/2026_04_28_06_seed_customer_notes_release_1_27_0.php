<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.27.0.
 * Web Push: notificaciones nativas del navegador opt-in desde Mi Perfil.
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Notificaciones del navegador (opcionales)',
            'body_html' => <<<'HTML'
<p>Ahora podés activar las <strong>notificaciones nativas del navegador</strong> para recibir avisos de la suite incluso cuando tenés la pestaña cerrada o el navegador minimizado.</p>
<ul>
    <li>Andá a <strong>Mi Perfil → Notificaciones del navegador</strong> y tocá "Activar".</li>
    <li>El navegador te va a pedir permiso una sola vez. Aceptá y listo.</li>
    <li>Cada recordatorio o aviso de la campanita aparece también como notificación nativa de tu sistema operativo (Windows action center, Mac, Android pull-down).</li>
    <li>Podés desactivarlas en cualquier momento desde el mismo lugar.</li>
    <li>Compatible con Chrome, Firefox, Edge y Brave en computadora y Android. En iPhone/iPad llega cuando habilitemos la app instalable.</li>
</ul>
HTML,
            'category' => 'feature',
            'version_ref' => '1.27.0',
            'published_at' => '2026-04-28 19:00:00',
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
        $chk->execute([':t' => $n['title'], ':v' => (string) ($n['version_ref'] ?? '')]);
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
