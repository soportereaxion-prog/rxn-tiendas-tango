<?php
declare(strict_types=1);

namespace App\Modules\CrmPresupuestos;

/**
 * STUB DE COMPATIBILIDAD (release 1.12.6).
 *
 * En release 1.12.5 movimos CommercialCatalogSyncService a App\Modules\RxnSync\Services.
 * Pero el classmap de Composer (vendor/composer/autoload_classmap.php) todavía tenía
 * una entrada apuntando a este path y archivo. Cualquier caller que resolviera el FQN
 * viejo (opcache PHP con versión previa del controller, o llamadores externos no
 * migrados que no detectamos en grep) terminaba tirando "file not found" al hacer
 * require de un archivo borrado → Fatal Error con HTTP 200 envuelto por xdebug.
 *
 * Solución: mantener el archivo físico como stub que extiende del servicio real.
 * Así cualquier consumidor del FQN viejo sigue funcionando transparentemente hasta
 * que composer dump-autoload regenere el classmap y/o el opcache expire naturalmente.
 *
 * Si ves este archivo después de 2026-05-15: ya es seguro borrarlo.
 */
class CommercialCatalogSyncService extends \App\Modules\RxnSync\Services\CommercialCatalogSyncService
{
    // Hereda todo del servicio real. Sin sobrecargas.
}
