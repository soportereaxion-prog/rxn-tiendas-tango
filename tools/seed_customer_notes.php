<?php

declare(strict_types=1);

/**
 * Seed inicial de customer_notes con 4 novedades reales redactadas en
 * lenguaje de usuario final (no técnico).
 *
 * Es idempotente: si la tabla ya tiene filas, NO hace nada — solo siembra
 * la primera vez. Para re-sembrar: vaciar la tabla manualmente.
 *
 * Uso:
 *   /d/RXNAPP/3.3/bin/php/php8.3.14/php.exe tools/seed_customer_notes.php
 */

define('BASE_PATH', dirname(__DIR__));

// Cargar .env
if (is_file(BASE_PATH . '/.env')) {
    foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        putenv($line);
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;

$db = Database::getConnection();

$count = (int) $db->query("SELECT COUNT(*) FROM customer_notes")->fetchColumn();
if ($count > 0) {
    echo "[seed_customer_notes] La tabla ya tiene {$count} fila(s) — no se siembra nada.\n";
    exit(0);
}

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
];

$stmt = $db->prepare(
    "INSERT INTO customer_notes (title, body_html, category, version_ref, status, published_at)
     VALUES (:title, :body_html, :category, :version_ref, 'published', :published_at)"
);

$inserted = 0;
foreach ($notes as $n) {
    $stmt->execute([
        ':title' => $n['title'],
        ':body_html' => $n['body_html'],
        ':category' => $n['category'],
        ':version_ref' => $n['version_ref'],
        ':published_at' => $n['published_at'],
    ]);
    $inserted++;
}

echo "[seed_customer_notes] Sembradas {$inserted} novedad(es) iniciales.\n";
