<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades para que viajen en el OTA — release 1.24.0.
 *
 * Tres mejoras visibles para el operador final:
 *  - Zoom personal en Mi Perfil (lupa de navegador, persistida por usuario).
 *  - Llamadas vinculadas formalmente al PDS que generaron, con badge clickable
 *    en el listado y filtro "Con PDS / Sin PDS".
 *  - Edición administrativa de turnos del módulo Horas, con trazabilidad
 *    completa (audit + notificación al dueño).
 *
 * Lenguaje de capacidad, sin referencias a archivos/endpoints/dependencias.
 */

return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Zoom personal — ajustá el tamaño de la app a tu pantalla',
            'body_html' => <<<'HTML'
<p>Cada usuario puede elegir su <strong>nivel de zoom</strong> preferido para toda la suite, persistido en su perfil.</p>
<ul>
    <li>Disponible en <em>Mi Perfil → Preferencias visuales</em> con valores 75%, 80%, 90%, 100%, 110%, 125% y 150%.</li>
    <li>Funciona como la lupa nativa del navegador: la fuente y los espaciados se ajustan, los bloques siguen ocupando todo el ancho de la pantalla.</li>
    <li>Aplica a Tiendas y CRM por igual.</li>
    <li>Útil tanto para monitores grandes (entrar más información en pantalla) como para portátiles chicos (achicar la UI sin perder layout).</li>
</ul>
HTML,
            'category' => 'feature',
            'version_ref' => '1.24.0',
            'published_at' => '2026-04-27 12:00:00',
        ],
        [
            'title' => 'Llamadas vinculadas a Pedidos de Servicio',
            'body_html' => <<<'HTML'
<p>Cuando generás un PDS desde una llamada de la central, el vínculo entre los dos queda registrado automáticamente.</p>
<ul>
    <li>El listado de Llamadas suma una columna <strong>"PDS"</strong> con un badge clickable: si la llamada generó un pedido, ves <em>PDS #N</em> y entrás directo al pedido con un click.</li>
    <li>Filtro nuevo en el embudo de la columna: podés mostrar solo las llamadas <strong>con PDS</strong> o las que <strong>no generaron PDS</strong> todavía — ideal para detectar llamadas pendientes de gestionar.</li>
    <li>Los pedidos cargados antes de esta mejora se intentan vincular automáticamente cuando el cliente y la fecha coinciden; los que no se puedan inferir quedan disponibles para vincularse hacia adelante.</li>
</ul>
HTML,
            'category' => 'feature',
            'version_ref' => '1.24.0',
            'published_at' => '2026-04-27 12:00:00',
        ],
        [
            'title' => 'Edición administrativa de turnos del Módulo Horas',
            'body_html' => <<<'HTML'
<p>Los administradores pueden ahora <strong>editar y cargar turnos</strong> en nombre de otros operadores, con trazabilidad completa.</p>
<ul>
    <li>Cada fila del listado de horas tiene un botón <strong>Editar</strong> (visible solo para administradores) que permite ajustar inicio, fin y concepto.</li>
    <li>Toda edición exige un <strong>motivo</strong> obligatorio que queda registrado.</li>
    <li>El operador dueño del turno recibe una notificación interna cuando un admin modifica o carga un turno a su nombre.</li>
    <li>En el listado, los turnos modificados muestran un ícono al lado del número con un tooltip que indica <em>quién, cuándo y por qué</em> hizo el cambio.</li>
    <li>El formulario de carga diferida también suma un selector <em>"Cargar para…"</em> visible solo para administradores.</li>
</ul>
HTML,
            'category' => 'feature',
            'version_ref' => '1.24.0',
            'published_at' => '2026-04-27 12:00:00',
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
