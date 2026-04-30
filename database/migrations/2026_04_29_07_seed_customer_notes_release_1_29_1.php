<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.29.1.
 * Presupuestos: autoguardado P3 con indicador visible + Ctrl+S +
 * fix de bloqueo de cabecera tras error de validación.
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Tu presupuesto se autoguarda mientras lo cargás',
            'body_html' => <<<'HTML'
<p>Cargar un presupuesto largo ahora es mucho más seguro:</p>
<ul>
    <li><strong>Autoguardado en tiempo real</strong>: mientras completás el presupuesto, lo guardamos como borrador en el servidor cada pocos segundos. Si se corta la luz, se cierra el navegador o se vence la sesión, al volver podés retomar exacto donde lo dejaste.</li>
    <li><strong>Indicador visible en la cabecera</strong>: al lado del título del presupuesto vas a ver un cartel chiquito con tres estados claros — <em>Sin cambios</em> cuando todo está como lo guardaste, <em>Borrador autoguardado HH:MM · falta Guardar</em> cuando tus cambios ya están seguros en el server pero falta confirmar el presupuesto, y <em>Cambios sin guardar</em> cuando estás escribiendo y el autoguardado todavía no salió.</li>
    <li><strong>Atajo Ctrl+S</strong>: ahora podés guardar el presupuesto sin tocar el mouse. Funciona como en Word o Excel — apretás Ctrl+S y se dispara el botón Guardar. Lo ves listado en el panel de atajos (Shift+?).</li>
</ul>
<p>Y como bonus, arreglamos un detalle molesto: si intentabas guardar un presupuesto al que le faltaba un dato y el sistema te avisaba del error, antes la cabecera quedaba bloqueada y no podías corregir. Ahora la cabecera se mantiene editable cuando hay errores que tenés que arreglar.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.29.1',
            'published_at' => '2026-04-29 23:55:00',
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
