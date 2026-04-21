<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades para que viajen en el OTA — release 1.17.0.
 *
 * Este es el PATRÓN canónico de cada release a partir de Fase 6: por cada
 * bump de versión, una migración idempotente que siembra las `customer_notes`
 * nuevas de ese release. De esa manera la data viaja en el paquete OTA y las
 * empresas cliente reciben el mismo contenido editorial que curamos en dev.
 *
 * Idempotencia: se chequea por (title, version_ref). Si ya existe, skip.
 * La lógica corre en PHP (no usamos UNIQUE KEY en la tabla) para permitir
 * que en el futuro dos notas con el mismo título convivan bajo distintas
 * versiones sin constraint rígido.
 *
 * En este primer seed viajan las 4 notas iniciales (1.15.0 / 1.16.0 /
 * 1.16.1 / 1.16.x seguridad) sembradas en dev vía `tools/seed_customer_notes.php`
 * + la novedad propia del release 1.17.0 (estrenamos el canal de comunicación).
 */

return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Sincronización manual de estados de pedidos con Tango',
            'body_html' => '<p>Desde ahora podés disparar una sincronización puntual de estados de pedidos contra Tango cuando necesites ver el último movimiento sin esperar al ciclo automático.</p>'
                         . '<p>Es especialmente útil cuando un cliente consulta por un estado en tiempo real y querés darle la respuesta exacta sin margen de demora.</p>',
            'category' => 'feature',
            'version_ref' => '1.15.0',
            'published_at' => '2026-04-18 23:00:00',
        ],
        [
            'title' => 'Listado de pedidos más prolijo y ágil',
            'body_html' => '<p>Mejoramos la agrupación del listado de pedidos y pulimos la paginación para que navegar entre miles de registros sea fluido.</p>'
                         . '<p>Los pedidos ahora se agrupan por comprobante Tango y podés paginar con más comodidad, sobre todo en cuentas con volumen alto.</p>',
            'category' => 'mejora',
            'version_ref' => '1.16.0',
            'published_at' => '2026-04-19 22:00:00',
        ],
        [
            'title' => 'Cálculo en vivo y hotkeys al cargar un PDS',
            'body_html' => '<p>Mientras cargás un Pedido de Servicio, los totales se actualizan en tiempo real — nada de volver a abrir el pedido para ver el número final.</p>'
                         . '<p>También sumamos atajos de teclado: <strong>Alt + O</strong> para copiar filas, y búsqueda ágil por código de cliente y artículo. Todo pensado para que cargar un PDS largo deje de ser una ceremonia.</p>',
            'category' => 'mejora',
            'version_ref' => '1.16.1',
            'published_at' => '2026-04-19 23:50:00',
        ],
        [
            'title' => 'Mejoras de seguridad tipo NASA aplicadas',
            'body_html' => '<p>Reforzamos el aislamiento entre empresas con controles multicapa y aplicamos hardening transversal sobre los módulos de Tiendas, CRM y configuración.</p>'
                         . '<p>La plataforma pasó una auditoría interna con foco en aislamiento multi-tenant — el objetivo es que la información de tu empresa sea inalcanzable para cualquier otra, sin excepciones.</p>'
                         . '<p>No hay nada que tengas que hacer: el refuerzo es automático y transparente.</p>',
            'category' => 'seguridad',
            'version_ref' => '1.16.x',
            'published_at' => '2026-04-20 08:00:00',
        ],
        [
            'title' => 'Ahora te vamos a comunicar las novedades del producto',
            'body_html' => '<p>Estrenamos un <strong>canal oficial de comunicación</strong>: vas a empezar a recibir por email las nuevas funcionalidades, mejoras y refuerzos de seguridad a medida que se apliquen.</p>'
                         . '<p>Cada novedad llega etiquetada — <em>Nuevo</em>, <em>Mejora</em>, <em>Seguridad</em>, <em>Performance</em> o <em>Ajuste</em> — para que identifiques rápido qué cambió y cómo te afecta.</p>'
                         . '<p>Es un canal curado: solo vas a recibir lo que realmente te impacta, sin ruido.</p>',
            'category' => 'feature',
            'version_ref' => '1.17.0',
            'published_at' => '2026-04-20 22:30:00',
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
