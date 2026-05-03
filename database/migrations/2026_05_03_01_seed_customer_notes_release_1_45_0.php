<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.45.0.
 *
 * Mejoras al módulo de envíos masivos para que la primera campaña real
 * llegue con el detalle visual que el cliente final espera.
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Newsletters con identidad visual — el logo viaja con cada novedad',
            'body_html' => <<<'HTML'
<p>Las novedades de la suite ahora viajan con la identidad visual de Reaxion en el header del mail. El logo se incrusta automáticamente y se ve con foco profesional en Gmail, Outlook y Apple Mail, sin importar el cliente que use el destinatario.</p>
<ul>
    <li><strong>Header con marca</strong>: el logo aparece sobre el saludo de novedades, centrado y con tamaño optimizado para inbox.</li>
    <li><strong>Compatible con todos los lectores</strong>: testeado para que se renderice bien en clientes web, escritorio y móvil.</li>
    <li><strong>Multi-empresa</strong>: cada suite usa su propio dominio para servir el logo, sin compartir configuración entre tenants.</li>
</ul>
<p>El cambio es transparente — la próxima vez que enviés novedades, el mail ya viaja con el logo arriba.</p>
HTML,
            'category' => 'mejora',
            'version_ref' => '1.45.0',
            'published_at' => '2026-05-03 12:00:00',
        ],
        [
            'title' => 'Filtros de envío con valores que se actualizan solos',
            'body_html' => <<<'HTML'
<p>Cuando armás un reporte para un envío masivo, los filtros por fecha ahora soportan valores dinámicos que se calculan en el momento de disparar — no hay que editar el reporte cada vez que querés mandar la última semana, el último mes o lo que va del año.</p>
<ul>
    <li><strong>Tokens disponibles</strong>: hoy, ayer, mañana, ahora, inicio/fin del mes corriente, inicio/fin del año corriente, y combinaciones tipo "hoy menos 7 días" o "hoy más 30 días".</li>
    <li><strong>Botón calendario</strong>: al lado del campo de valor del filtro hay un menú flotante con todas las opciones — un click y se inserta el token correspondiente.</li>
    <li><strong>Compatible con BETWEEN</strong>: para rangos podés combinar dos tokens, por ejemplo inicio del mes y fin del mes.</li>
</ul>
<p>Resultado: armás el reporte una sola vez y lo reusás todas las veces que quieras, siempre con la fecha del día.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.45.0',
            'published_at' => '2026-05-03 12:00:00',
        ],
        [
            'title' => 'Vista previa del mail completo antes de disparar la campaña',
            'body_html' => <<<'HTML'
<p>En la pantalla de creación de un envío masivo sumamos un panel de preview que muestra el mail tal cual va a llegar al cliente — con la plantilla, las novedades dinámicas embebidas y los datos del primer destinatario.</p>
<ul>
    <li><strong>Refresco a demanda</strong>: tocás "Refrescar" cuando estás listo y aparece el render real del mail en un iframe seguro.</li>
    <li><strong>Pantalla completa</strong>: con un botón abrís el preview en modal a tamaño total para validarlo cómodo, sin perder la pantalla del envío.</li>
    <li><strong>Nueva pestaña</strong>: si querés imprimirlo o compartirlo, otro botón lo abre en una pestaña separada del navegador.</li>
</ul>
<p>Sirve para validar que el bloque de novedades se renderiza correctamente y que las variables del destinatario se reemplazan como esperás antes de disparar a toda la base.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.45.0',
            'published_at' => '2026-05-03 12:00:00',
        ],
        [
            'title' => 'Auditoría completa de destinatarios antes de mandar — preview paginado',
            'body_html' => <<<'HTML'
<p>El previsualizador de reportes de destinatarios ahora pagina los resultados — podés navegar por todas las filas que va a recibir el envío, no solo las primeras diez.</p>
<ul>
    <li><strong>Páginas configurables</strong>: 10, 25, 50, 100 o 200 filas por página, lo que te quede más cómodo según el tamaño del envío.</li>
    <li><strong>Total real</strong>: arriba de la tabla siempre ves el conteo total de filas y de mails únicos antes de disparar.</li>
    <li><strong>Navegación rápida</strong>: botones de primera/anterior/siguiente/última y un campo numérico para saltar a una página puntual.</li>
</ul>
<p>Útil para revisar campañas grandes y confirmar que el reporte está filtrando exactamente a quien tiene que recibir.</p>
HTML,
            'category' => 'mejora',
            'version_ref' => '1.45.0',
            'published_at' => '2026-05-03 12:00:00',
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
