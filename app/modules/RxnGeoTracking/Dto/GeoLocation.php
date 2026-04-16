<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking\Dto;

/**
 * Value object para una ubicación resuelta desde IP.
 *
 * Todos los campos son opcionales porque la resolución puede ser parcial:
 * IPs privadas (localhost, LAN) generalmente devuelven null en todo, pero
 * no debe tratarse como error.
 */
final class GeoLocation
{
    public function __construct(
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?string $city = null,
        public readonly ?string $region = null,
        public readonly ?string $countryCode = null
    ) {
    }

    /**
     * Una ubicación "vacía" — usada como fallback cuando la resolución falla
     * o la IP es privada. Evita que el caller tenga que chequear null.
     */
    public static function empty(): self
    {
        return new self();
    }

    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
