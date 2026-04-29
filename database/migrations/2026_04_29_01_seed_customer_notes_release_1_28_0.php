<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.28.0.
 * Recuperación de sesión: return-URL post-login, aviso preventivo de
 * expiración y autoguardado de borradores en formularios largos.
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Tu sesión ya no te hace perder trabajo',
            'body_html' => <<<'HTML'
<p>Sumamos tres mejoras que se complementan para que jamás se te pierda lo que estás cargando, aunque se te corte la sesión, se te cierre el navegador o haya un imprevisto:</p>
<ul>
    <li><strong>Aviso preventivo</strong>: cuando tu sesión está por vencer, te aparece un banner suave abajo a la derecha con un botón <em>"Extender ahora"</em>. Un clic y seguís trabajando sin perder nada.</li>
    <li><strong>Volvés exacto al lugar</strong>: si la sesión se vence y tenés que volver a ingresar, después del login te llevamos automáticamente al mismo módulo y vista donde estabas — no más caer al dashboard y tener que rehacer la navegación.</li>
    <li><strong>Borradores autoguardados</strong>: mientras cargás un Pedido de Servicio (próximamente Presupuestos también), guardamos un borrador cada pocos segundos. Si por cualquier razón salís del formulario sin guardar, al volver te ofrecemos retomar desde el punto exacto donde lo dejaste, con todos los datos cargados.</li>
</ul>
<p>Además, en <strong>Mi Perfil → Mis borradores</strong> tenés un panel nuevo donde ves todos los formularios que dejaste a medio cargar, con la fecha de la última edición y la opción de retomarlos o descartarlos. Funciona también entre dispositivos: si arrancaste un pedido en la PC, podés retomarlo desde el celular con tu mismo usuario.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.28.0',
            'published_at' => '2026-04-29 23:00:00',
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
