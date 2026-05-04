<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.46.0.
 *
 * Mejoras visibles para clientes que operan con la integración Tango Connect:
 * push individual de clientes/artículos desde la edición + UX de la pantalla
 * de configuración cuando se da de alta una empresa Connect nueva.
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Editás un cliente o artículo y lo subís a Tango con un click',
            'body_html' => <<<'HTML'
<p>En la edición de un cliente o de un artículo agregamos un botón <strong>Push</strong> que guarda los cambios y los lleva al ERP en una sola acción. Si tocás el nombre, el email o el teléfono y le das Push, el cambio queda persistido en RXN y replicado en Tango sin pasar por "Guardar modificaciones" primero.</p>
<ul>
    <li><strong>Campos editables desde RXN</strong>: en clientes podés tocar razón social, CUIT/Documento, email, teléfono y dirección. En artículos podés tocar el nombre. El resto de los datos comerciales del ERP no se modifican desde acá — los respeta tal como están en Tango.</li>
    <li><strong>Pull complementario</strong>: el botón Pull al lado trae el dato fresco desde Tango si alguien lo modificó en el ERP. Sirve para reflejar al toque cambios de la base comercial.</li>
    <li><strong>Guardar modificaciones queda igual</strong>: si querés persistir cambios solo en RXN sin tocar Tango (por ejemplo cuando Connect está caído), el botón Guardar funciona como siempre.</li>
</ul>
<p>Después de un Push o un Guardar te quedás en el formulario para seguir trabajando — ya no te tira al listado.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.46.0',
            'published_at' => '2026-05-04 22:00:00',
        ],
        [
            'title' => 'Alta de empresa Tango Connect más prolija y guiada',
            'body_html' => <<<'HTML'
<p>La pantalla de Configuración de la Empresa ahora es más amable cuando estás dando de alta una empresa Tango Connect por primera vez. Antes el panel de diagnóstico se encendía marcando catálogos como vacíos aunque todo estuviera bien — solo había que elegir la empresa Connect primero.</p>
<ul>
    <li><strong>Carga en dos pasos clara</strong>: al validar credenciales se llena el dropdown de empresas Connect. Cuando elegís la empresa, automáticamente se cargan listas de precio, depósitos y perfiles de pedido. Todo en una sola pantalla, sin idas y vueltas.</li>
    <li><strong>Diagnóstico que solo aparece cuando hay un problema real</strong>: el banner amarillo deja de mostrarse durante el alta. Si después de elegir la empresa algún catálogo aparece marcado, ahí sí significa que hay algo que revisar en el ERP.</li>
    <li><strong>Mensajes de progreso</strong>: el sistema te indica qué está cargando y qué falta para terminar de configurar.</li>
</ul>
HTML,
            'category' => 'mejora',
            'version_ref' => '1.46.0',
            'published_at' => '2026-05-04 22:00:00',
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
