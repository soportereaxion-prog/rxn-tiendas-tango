<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.29.0.
 * Presupuestos: versionado, lock post-Tango, descripciones largas,
 * validaciones P0/P1/P2, Agenda extendida.
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Presupuestos más maduros: versiones, lock seguro y descripciones largas',
            'body_html' => <<<'HTML'
<p>El módulo de Presupuestos suma todo lo que faltaba para que sea un motor de cotización completo:</p>
<ul>
    <li><strong>Nuevas versiones con trazabilidad</strong>: ahora podés generar una nueva versión de un presupuesto manteniendo el original como referencia. Cada versión queda vinculada a la raíz, con badge "v2 · ver origen #N" en el form y en el listado.</li>
    <li><strong>Blindado post-envío a Tango</strong>: cuando un presupuesto se envió a Tango, queda en sólo lectura para preservar la integridad del comprobante. Para ajustar precios o condiciones, usás <em>"Nueva versión"</em> y editás esa.</li>
    <li><strong>Descripciones largas multilínea</strong>: el textarea de descripción del renglón ahora soporta texto extenso con saltos de línea. El sistema parte automáticamente la descripción en bloques que viajan a Tango como descripción principal + descripciones adicionales, respetando tus saltos manuales.</li>
    <li><strong>Cabecera comercial extendida</strong>: agregamos cotización del dólar, próximo contacto, vigencia y 5 leyendas libres. La cotización y leyendas viajan a Tango automáticamente al emitir.</li>
    <li><strong>Validaciones más claras</strong>: clasificación obligatoria, banner sticky con la lista de errores, mensajes inline campo por campo, foco automático al primer faltante, y aviso "¿salir sin guardar?" cuando intentás navegar afuera con cambios pendientes.</li>
    <li><strong>Agenda extendida</strong>: cada presupuesto puede proyectar hasta 3 eventos en la agenda CRM (principal, próximo contacto en cyan, vigencia en rojo). Se filtran con checkboxes propios.</li>
</ul>
<p>Bonus: ahora los listados recuerdan el orden y dirección de orden que elegiste — al volver al listado encontrás todo como lo dejaste.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.29.0',
            'published_at' => '2026-04-29 23:45:00',
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
