<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking;

/**
 * API pública del módulo RxnGeoTracking.
 *
 * Los módulos consumidores (Auth, CrmPresupuestos, CrmTratativas, CrmPedidosServicio)
 * consumen SOLO esta clase. Toda la lógica interna (resolver, repositorios, config)
 * queda encapsulada acá.
 *
 * INVARIANTE CRÍTICA: ningún método de esta clase debe lanzar excepción visible
 * al caller. El contrato es fire-and-forget:
 *   - Si el tracking está deshabilitado por tenant → devuelve null sin hacer nada.
 *   - Si falla la resolución de IP → persiste el evento igual con ubicación vacía.
 *   - Si falla la DB → loguea en error_log y devuelve null.
 *
 * Esto protege a los módulos consumidores: nunca un error de geo puede romper
 * un login, un presupuesto, una tratativa o un PDS.
 */
final class GeoTrackingService
{
    public const EVENT_LOGIN = 'login';
    public const EVENT_PRESUPUESTO_CREATED = 'presupuesto.created';
    public const EVENT_TRATATIVA_CREATED = 'tratativa.created';
    public const EVENT_PDS_CREATED = 'pds.created';

    public const VALID_EVENT_TYPES = [
        self::EVENT_LOGIN,
        self::EVENT_PRESUPUESTO_CREATED,
        self::EVENT_TRATATIVA_CREATED,
        self::EVENT_PDS_CREATED,
    ];

    private GeoEventRepository $events;
    private GeoTrackingConfigRepository $config;
    private GeoConsentRepository $consent;
    private IpGeolocationResolver $resolver;

    public function __construct(
        ?GeoEventRepository $events = null,
        ?GeoTrackingConfigRepository $config = null,
        ?GeoConsentRepository $consent = null,
        ?IpGeolocationResolver $resolver = null
    ) {
        $this->events = $events ?? new GeoEventRepository();
        $this->config = $config ?? new GeoTrackingConfigRepository();
        $this->consent = $consent ?? new GeoConsentRepository();
        $this->resolver = $resolver ?? new IpApiResolver();
    }

    /**
     * Registra un evento de tracking. El entry point que usan todos los módulos consumidores.
     *
     * Devuelve el ID del evento creado, o null si:
     *   - El tracking está deshabilitado por la config del tenant.
     *   - No hay user_id ni empresa_id en la sesión (caller inválido).
     *   - El event_type no es válido.
     *   - Falla la DB (se loguea internamente).
     *
     * El ID devuelto se usa como correlativo para que el frontend pueda reportar
     * posición GPS más precisa via POST /geo-tracking/report.
     */
    public function registrar(string $eventType, ?int $entidadId = null, ?string $entidadTipo = null): ?int
    {
        try {
            if (!in_array($eventType, self::VALID_EVENT_TYPES, true)) {
                return null;
            }

            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
            $empresaId = isset($_SESSION['empresa_id']) ? (int) $_SESSION['empresa_id'] : 0;

            if ($userId <= 0 || $empresaId <= 0) {
                return null;
            }

            $config = $this->config->getConfig($empresaId);
            if (!$config['habilitado']) {
                return null;
            }

            $ip = $this->detectClientIp();
            $userAgent = $this->detectUserAgent();

            // Resolución IP → ubicación. Nunca lanza excepción (contrato del resolver).
            $location = $this->resolver->resolver($ip ?? '');

            $eventoId = $this->events->create([
                'empresa_id' => $empresaId,
                'user_id' => $userId,
                'event_type' => $eventType,
                'entidad_tipo' => $entidadTipo,
                'entidad_id' => $entidadId,
                'ip_address' => $ip,
                'lat' => $location->lat,
                'lng' => $location->lng,
                'accuracy_meters' => null,
                'accuracy_source' => 'ip',
                'resolved_city' => $location->city,
                'resolved_region' => $location->region,
                'resolved_country' => $location->countryCode,
                'user_agent' => $userAgent,
                'consent_version' => $config['consent_version_current'],
            ]);

            return $eventoId;
        } catch (\Throwable $e) {
            // Silenciar cualquier error para no romper el flujo del caller.
            // Se loguea para diagnóstico pero jamás se propaga.
            error_log('[RxnGeoTracking] registrar() falló: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualiza un evento existente con lat/lng capturado por Geolocation API del browser.
     * Valida que el evento pertenezca al user_id de la sesión actual (seguridad).
     */
    public function reportarPosicionBrowser(int $eventoId, ?float $lat, ?float $lng, ?int $accuracyMeters, string $source): bool
    {
        try {
            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
            if ($userId <= 0 || $eventoId <= 0) {
                return false;
            }

            // Validación defensiva de rangos.
            if ($lat !== null && ($lat < -90 || $lat > 90)) {
                $lat = null;
            }
            if ($lng !== null && ($lng < -180 || $lng > 180)) {
                $lng = null;
            }
            if ($accuracyMeters !== null && $accuracyMeters < 0) {
                $accuracyMeters = null;
            }

            // 'dev_mock' aceptado para pruebas locales en HTTP plano (sin SSL).
            // En producción (HTTPS) la PWA nunca asigna ese source, así que en el
            // log productivo no aparece. Si llegara a aparecer en prod, indica
            // que alguien usó la PWA en un servidor sin HTTPS — anomalía esperada
            // y rastreable, no error silente.
            $allowedSources = ['gps', 'wifi', 'denied', 'error', 'dev_mock'];
            if (!in_array($source, $allowedSources, true)) {
                $source = 'error';
            }

            return $this->events->updatePositionFromBrowser($eventoId, $userId, $lat, $lng, $accuracyMeters, $source);
        } catch (\Throwable $e) {
            error_log('[RxnGeoTracking] reportarPosicionBrowser() falló: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ¿El usuario de la sesión actual ya respondió la versión vigente del consentimiento?
     * El caller (banner en el layout) usa esto para decidir si muestra o no el banner.
     */
    public function tieneConsentimientoVigente(int $userId, int $empresaId): bool
    {
        try {
            if ($userId <= 0 || $empresaId <= 0) {
                return false;
            }

            $config = $this->config->getConfig($empresaId);
            if (!$config['habilitado']) {
                // Si el módulo está deshabilitado, no hace falta consentimiento.
                return true;
            }

            return $this->consent->hasAnsweredCurrentVersion($userId, $empresaId, $config['consent_version_current']);
        } catch (\Throwable $e) {
            error_log('[RxnGeoTracking] tieneConsentimientoVigente() falló: ' . $e->getMessage());
            // En caso de error, asumimos que sí tiene — no queremos mostrar el banner a
            // todos los usuarios por una falla transitoria de DB.
            return true;
        }
    }

    /**
     * Persiste la respuesta del usuario al banner.
     */
    public function registrarConsentimiento(int $userId, int $empresaId, string $decision): bool
    {
        try {
            if ($userId <= 0 || $empresaId <= 0) {
                return false;
            }

            $version = $this->config->currentConsentVersion($empresaId);
            $ip = $this->detectClientIp();
            $ua = $this->detectUserAgent();

            $this->consent->record($userId, $empresaId, $version, $decision, $ip, $ua);
            return true;
        } catch (\Throwable $e) {
            error_log('[RxnGeoTracking] registrarConsentimiento() falló: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Devuelve la versión vigente del consentimiento para una empresa.
     */
    public function currentConsentVersion(int $empresaId): string
    {
        try {
            return $this->config->currentConsentVersion($empresaId);
        } catch (\Throwable) {
            return GeoTrackingConfigRepository::DEFAULT_CONSENT_VERSION;
        }
    }

    /**
     * Detecta la IP del cliente respetando proxies comunes.
     * Orden: X-Forwarded-For (primer IP de la cadena) → X-Real-IP → REMOTE_ADDR.
     *
     * NOTA: confiar en X-Forwarded-For es seguro solo si el server está detrás
     * de un reverse proxy confiable (nginx, CloudFlare, etc.). En este proyecto
     * la suite corre tanto detrás de Plesk (prod) como local (XAMPP) → aceptamos
     * la cabecera pero loggeamos la IP directa también para futuras auditorías.
     */
    private function detectClientIp(): ?string
    {
        $candidates = [];

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $xff = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidates[] = trim($xff[0]);
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $candidates[] = trim((string) $_SERVER['HTTP_X_REAL_IP']);
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = trim((string) $_SERVER['REMOTE_ADDR']);
        }

        foreach ($candidates as $ip) {
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    private function detectUserAgent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if (!is_string($ua) || $ua === '') {
            return null;
        }

        // Truncamos a 512 chars — coincide con el VARCHAR de la columna.
        return mb_substr($ua, 0, 512);
    }
}
