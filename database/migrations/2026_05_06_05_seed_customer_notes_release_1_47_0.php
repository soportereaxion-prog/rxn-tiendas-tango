<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — release 1.47.0.
 *
 * Iteración 51: matriz de permisos modulares en 2 niveles (empresa + usuario)
 * y mejora UX en el dashboard de Geo Tracking. Las notas hablan en lenguaje de
 * capacidad para el cliente final, sin detalle técnico (sin paths, sin nombres
 * de clases internas, sin schema).
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Permisos por usuario para los módulos del CRM',
            'body_html' => <<<'HTML'
<p>Ahora el administrador de cada empresa puede decidir <strong>qué módulos ve cada usuario</strong> desde "Administrar cuentas". Antes los módulos eran a nivel empresa: si la empresa los contrataba, todos los operadores los veían. Ahora hay un segundo nivel para asignación granular.</p>
<ul>
    <li><strong>Bloque "Módulos habilitados"</strong> en la pantalla de edición de cada usuario. Solo muestra los módulos que la empresa tiene contratados — limpio y sin distracciones.</li>
    <li><strong>Solo administradores pueden modificar</strong>: si quien edita no es administrador de la empresa, los toggles aparecen visibles pero deshabilitados. Es seguro a nivel servidor también — manipular el HTML no sirve.</li>
    <li><strong>Por defecto, todos los usuarios arrancan con todos los módulos habilitados</strong>. Si querés restringir, los apagás explícitamente desde la pantalla del usuario.</li>
    <li><strong>Auditoría automática</strong>: cada cambio queda registrado con quién lo hizo y qué cambió, para trazabilidad interna.</li>
</ul>
<p>Los módulos cubiertos son los 11 del CRM: Notas, Llamadas, Monitoreo de Usuarios, RXN Live, Pedidos de Servicio, Agenda, Mail Masivos, Horas (Turnero), Geo Tracking, Presupuestos PWA y Horas PWA.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.47.0',
            'published_at' => '2026-05-06 14:00:00',
        ],
        [
            'title' => 'Toggle "Usuario activo" más prolijo y seguro',
            'body_html' => <<<'HTML'
<p>Ajustamos el toggle <strong>"Usuario activo"</strong> en la pantalla de edición de cuentas para evitar dos situaciones que generaban dolores de cabeza:</p>
<ul>
    <li><strong>No podés desactivar tu propia cuenta</strong>: el toggle aparece bloqueado en tu propia edición, con un mensaje claro. Si querés dar de baja a alguien, hacelo desde otro usuario administrador.</li>
    <li><strong>Solo administradores pueden activar o desactivar usuarios</strong>: si sos operador, ves el estado pero no podés modificarlo. La regla se aplica también del lado del servidor — no se puede saltar manipulando el navegador.</li>
</ul>
<p>El comportamiento de la papelera (eliminar a un usuario) ya tenía la misma protección desde antes; ahora también la tiene el toggle de activación.</p>
HTML,
            'category' => 'security',
            'version_ref' => '1.47.0',
            'published_at' => '2026-05-06 14:00:00',
        ],
        [
            'title' => 'Click en evento del Geo Tracking centra el mapa',
            'body_html' => <<<'HTML'
<p>Mejora de usabilidad en el <strong>dashboard de Geo Tracking</strong>: tocá cualquier fila de la tabla de eventos y el mapa se centra automáticamente en esa ubicación, con zoom de calle y popup de detalles.</p>
<ul>
    <li><strong>Resaltado visual</strong>: la fila clickeada queda marcada en azul para que sepas siempre cuál estás viendo.</li>
    <li><strong>Scroll suave</strong>: si estabas mirando la tabla, el mapa se posiciona en pantalla automáticamente.</li>
    <li><strong>Eventos sin ubicación</strong> (cuando el navegador no pudo obtener GPS) muestran "—" en la columna y no son clickables, evitando confusiones.</li>
    <li><strong>Eventos fuera del límite de 500 puntos del mapa</strong>: igualmente se centran y muestran un popup con la información mínima de la fila, así no te quedás sin feedback.</li>
</ul>
HTML,
            'category' => 'feature',
            'version_ref' => '1.47.0',
            'published_at' => '2026-05-06 14:00:00',
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
