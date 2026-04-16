<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking;

use App\Modules\RxnGeoTracking\Dto\GeoLocation;

/**
 * Contrato de resolución IP → ubicación aproximada.
 *
 * Implementaciones posibles:
 *   - IpApiResolver       → API externa ip-api.com (default de MVP).
 *   - MaxMindLocalResolver→ GeoLite2 self-hosted (preferible para privacidad; pendiente).
 *
 * INVARIANTE CRÍTICA: resolver() NUNCA lanza excepción. Ante cualquier error
 * (timeout, DNS, rate limit, IP privada, etc.) devuelve GeoLocation::empty().
 * El caller (GeoTrackingService) confía ciegamente en este contrato.
 */
interface IpGeolocationResolver
{
    public function resolver(string $ip): GeoLocation;
}
