<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades para que viajen en el OTA — release 1.23.0.
 *
 * Esta release suma capacidades nuevas pensadas para el operador móvil:
 * turnero mobile-first, notificaciones in-app (campanita) y horario laboral
 * declarado que alimenta los avisos. También se pulieron detalles del Mi
 * Perfil para los operadores que entran desde el celular.
 *
 * Lenguaje de capacidad, sin referencias a archivos/endpoints/dependencias.
 */

return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Turnero CRM — registrá tus horas desde el celular',
            'body_html' => <<<'HTML'
<p>Llega un módulo nuevo para que los operadores registren su tiempo de trabajo de forma simple y rápida, pensado de cero para usar desde el celular.</p>
<ul>
    <li><strong>Botón único</strong> grande: una vez para iniciar el turno, otra para cerrarlo. Mientras está abierto, un contador en vivo te muestra cuánto llevás trabajado en el día.</li>
    <li><strong>Concepto y tratativa opcionales</strong>: podés describir qué hiciste y vincular el turno a la tratativa en curso, para que el tiempo quede consolidado en el caso comercial.</li>
    <li><strong>Ubicación opcional</strong>: si das permiso, el sistema guarda dónde iniciaste y dónde cerraste — útil para visitas técnicas. Si no querés, todo sigue funcionando.</li>
    <li><strong>Carga diferida</strong>: si te olvidaste de registrar en vivo, podés cargar el turno después indicando inicio y fin manualmente.</li>
    <li><strong>Reflejo automático en la Agenda CRM</strong>: cada turno cerrado aparece como evento, así ves de un vistazo dónde estuvo cada operador.</li>
</ul>
HTML,
            'category' => 'feature',
            'version_ref' => '1.23.0',
            'published_at' => '2026-04-25 12:00:00',
        ],
        [
            'title' => 'Notificaciones internas — la campanita del topbar',
            'body_html' => <<<'HTML'
<p>El sistema ahora te avisa cuando hay algo que requiere tu atención, sin que tengas que estar revisando mails.</p>
<ul>
    <li><strong>Campanita en la barra superior</strong>: con un círculo rojo que muestra el número de avisos sin leer.</li>
    <li><strong>Click y resolvés</strong>: el dropdown te muestra los últimos avisos. Tap en uno y te lleva directo al lugar donde tenés que actuar.</li>
    <li><strong>Inbox completo</strong>: hay una página dedicada con filtros (todas / no leídas / leídas) y la opción de marcar todas como leídas de una sola vez.</li>
    <li><strong>Sin spam</strong>: el sistema evita mandarte el mismo aviso varias veces en el mismo día.</li>
</ul>
<p>Los primeros avisos disponibles son del turnero: te recuerda si te quedó un turno abierto desde ayer o si te olvidaste de iniciar uno.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.23.0',
            'published_at' => '2026-04-25 12:05:00',
        ],
        [
            'title' => 'Horario laboral declarado — y avisos automáticos del turnero',
            'body_html' => <<<'HTML'
<p>Cada usuario puede declarar su horario laboral tipo desde <em>Mi Perfil</em>, en bloques por día (ej: lunes 9 a 13 + 14 a 18).</p>
<ul>
    <li><strong>Es orientativo</strong>: no te bloquea nada. Solo lo usa el sistema para avisarte si te olvidaste de iniciar o cerrar un turno.</li>
    <li><strong>Avisos opt-in</strong>: vos decidís si querés que te recuerden, y cuántos minutos de tolerancia querés después del fin de un bloque.</li>
    <li><strong>Optimizado para celular</strong>: en pantalla chica cada día se muestra como una tarjeta apilada con sus bloques y el botón para sumar uno nuevo, sin scroll horizontal.</li>
</ul>
HTML,
            'category' => 'feature',
            'version_ref' => '1.23.0',
            'published_at' => '2026-04-25 12:10:00',
        ],
        [
            'title' => 'Mi Perfil más prolijo y enfocado',
            'body_html' => <<<'HTML'
<p>Refinamos <em>Mi Perfil</em> para que cada usuario vea solo lo que le aplica:</p>
<ul>
    <li><strong>Sección de SMTP de envíos masivos</strong> ahora visible solo para usuarios con permisos de administración — los operadores no la ven y, por lo tanto, no se les exponen campos técnicos que no necesitan tocar.</li>
    <li><strong>Pantallas chicas mejor aprovechadas</strong>: los inputs y selectores se adaptan al ancho disponible en lugar de quedar acotados.</li>
</ul>
HTML,
            'category' => 'mejora',
            'version_ref' => '1.23.0',
            'published_at' => '2026-04-25 12:15:00',
        ],
        [
            'title' => 'Centro de Ayuda ampliado',
            'body_html' => <<<'HTML'
<p>El Centro de Ayuda (<em>Ayuda para humanos</em>) sumó secciones nuevas para acompañar lo último que se estrenó:</p>
<ul>
    <li><strong>Horas (Turnero CRM)</strong>: cómo iniciar y cerrar turnos, geolocalización opcional, carga diferida, cruce de medianoche y reflejo en la Agenda.</li>
    <li><strong>Notificaciones</strong>: campanita, inbox, filtros, marcar como leídas, anti-duplicado.</li>
    <li><strong>Uso desde el celular</strong>: menú hamburguesa, módulos optimizados mobile y tips generales para trabajar desde un smartphone.</li>
    <li><strong>Horario laboral en Mi Perfil</strong>: cómo cargar bloques por día y configurar avisos del turnero.</li>
</ul>
HTML,
            'category' => 'mejora',
            'version_ref' => '1.23.0',
            'published_at' => '2026-04-25 12:20:00',
        ],
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
