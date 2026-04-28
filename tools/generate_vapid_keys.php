<?php

declare(strict_types=1);

/**
 * Generador de claves VAPID para Web Push.
 * Uso: php tools/generate_vapid_keys.php
 *
 * Imprime las claves para que las copies al .env. NO las escribe automáticamente
 * para que no sobrescribas accidentalmente claves ya en uso (rotar VAPID = todos
 * los browsers suscritos pierden la suscripción y hay que re-optar).
 *
 * Las claves se generan UNA SOLA VEZ por instalación. Si ya tenés
 * VAPID_PUBLIC_KEY y VAPID_PRIVATE_KEY en el .env, NO corras este script.
 */

require __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo PHP_EOL;
echo "=== VAPID keys generadas — copialas al .env ===" . PHP_EOL . PHP_EOL;
echo "VAPID_PUBLIC_KEY={$keys['publicKey']}" . PHP_EOL;
echo "VAPID_PRIVATE_KEY={$keys['privateKey']}" . PHP_EOL;
echo "VAPID_SUBJECT=mailto:soporte@reaxion.com.ar" . PHP_EOL . PHP_EOL;
echo "Importante:" . PHP_EOL;
echo "- NO rotes estas claves una vez en uso (los browsers suscritos pierden la sub)." . PHP_EOL;
echo "- Las MISMAS claves van al .env de local Y al de producción." . PHP_EOL;
echo "- VAPID_PUBLIC_KEY se expone al frontend; VAPID_PRIVATE_KEY NUNCA." . PHP_EOL;
