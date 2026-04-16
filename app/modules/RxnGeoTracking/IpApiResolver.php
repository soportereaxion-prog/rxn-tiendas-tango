<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking;

use App\Modules\RxnGeoTracking\Dto\GeoLocation;

/**
 * Resolver de IPs usando http://ip-api.com (free tier, 45 req/min sin auth).
 *
 * Documentación: https://ip-api.com/docs/api:json
 *
 * Free tier:
 *   - 45 requests/minuto por IP del servidor que consulta.
 *   - HTTP (no HTTPS) en free. Para HTTPS hay un plan pro de USD 13/mes.
 *   - Formato JSON: { status, country, countryCode, region, regionName, city, lat, lon, query, ... }
 *   - Status "success" o "fail" (con message).
 *
 * Decisión MVP: usamos el endpoint HTTP por ahora. Es un llamado server-side desde el CRM
 * hacia ip-api.com con la IP del cliente como payload — no expone ninguna credencial
 * y la única data sensible es la IP del cliente final, que el MODULE_CONTEXT reconoce
 * como riesgo conocido (recomendación futura: migrar a MaxMind self-hosted).
 *
 * TIMEOUT defensivo de 2 segundos — si el servicio se cae o está lento,
 * el evento se persiste igual con ubicación vacía y accuracy_source='ip'.
 */
final class IpApiResolver implements IpGeolocationResolver
{
    private const ENDPOINT = 'http://ip-api.com/json/';
    private const TIMEOUT_SECONDS = 2;

    public function resolver(string $ip): GeoLocation
    {
        $ip = trim($ip);

        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return GeoLocation::empty();
        }

        // IPs privadas/reservadas no tiene sentido consultarlas (el servicio devuelve error).
        // filter_var con flags excluye rangos privados (10.x, 192.168.x, 172.16-31.x) y reservados (127.x, ::1, etc.).
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return GeoLocation::empty();
        }

        $url = self::ENDPOINT . urlencode($ip) . '?fields=status,message,countryCode,region,regionName,city,lat,lon';

        $ch = curl_init($url);
        if ($ch === false) {
            return GeoLocation::empty();
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'rxn-suite-geo-tracking/1.0',
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            return GeoLocation::empty();
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return GeoLocation::empty();
        }

        $lat = isset($data['lat']) && is_numeric($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lon']) && is_numeric($data['lon']) ? (float) $data['lon'] : null;
        $city = isset($data['city']) && $data['city'] !== '' ? (string) $data['city'] : null;
        $region = isset($data['regionName']) && $data['regionName'] !== ''
            ? (string) $data['regionName']
            : (isset($data['region']) && $data['region'] !== '' ? (string) $data['region'] : null);
        $countryCode = isset($data['countryCode']) && is_string($data['countryCode']) && strlen($data['countryCode']) === 2
            ? strtoupper($data['countryCode'])
            : null;

        return new GeoLocation($lat, $lng, $city, $region, $countryCode);
    }
}
