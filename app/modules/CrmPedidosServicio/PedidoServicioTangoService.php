<?php
declare(strict_types=1);

namespace App\Modules\CrmPedidosServicio;

use App\Modules\EmpresaConfig\EmpresaConfigService;
use App\Modules\Tango\Mappers\TangoOrderMapper;
use App\Modules\Tango\TangoOrderClient;
use App\Modules\Tango\Services\TangoOrderHeaderResolver;

class PedidoServicioTangoService
{
    private PedidoServicioRepository $repository;

    public function __construct(?PedidoServicioRepository $repository = null)
    {
        $this->repository = $repository ?? new PedidoServicioRepository();
    }

    public function send(int $pedidoId, int $empresaId): array
    {
        $pedido = $this->repository->findById($pedidoId, $empresaId);
        if ($pedido === null) {
            return ['ok' => false, 'type' => 'danger', 'message' => 'El PDS no existe o no pertenece a la empresa activa.'];
        }

        if (empty($pedido['fecha_finalizado'])) {
            return ['ok' => false, 'type' => 'warning', 'message' => 'Antes de enviar a Tango debes completar la fecha de finalización del PDS.'];
        }

        $tiempoDecimal = $this->resolveTiempoDecimal($pedido);
        if ($tiempoDecimal === null || $tiempoDecimal <= 0) {
            return ['ok' => false, 'type' => 'warning', 'message' => 'El tiempo neto calculado debe ser mayor a cero para enviar el PDS a Tango.'];
        }

        if (empty($pedido['articulo_codigo'])) {
            return ['ok' => false, 'type' => 'danger', 'message' => 'El artículo seleccionado no tiene código externo para Tango.'];
        }

        $cliente = $this->repository->findClientById($empresaId, (int) ($pedido['cliente_id'] ?? 0));
        if ($cliente === null || empty($cliente['id_gva14_tango'])) {
            return ['ok' => false, 'type' => 'danger', 'message' => 'El cliente del PDS no tiene la relación comercial Tango resuelta.'];
        }

        $config = EmpresaConfigService::forCrm()->getConfig();
        $token = trim((string) ($config->tango_connect_token ?? ''));
        $companyId = trim((string) ($config->tango_connect_company_id ?? ''));
        $apiUrl = trim((string) ($config->tango_api_url ?? ''));
        $clientKey = trim((string) ($config->tango_connect_key ?? ''));

        if ($token === '' || $companyId === '' || ($apiUrl === '' && $clientKey === '')) {
            return ['ok' => false, 'type' => 'danger', 'message' => 'La configuración CRM de Tango está incompleta. Revisa Token, Company ID y Llave/URL.'];
        }

        if ($apiUrl === '' && $clientKey !== '') {
            $apiUrl = sprintf('https://%s.connect.axoft.com/Api', str_replace('/', '-', $clientKey));
        } elseif ($apiUrl !== '') {
            $apiUrl = rtrim($apiUrl, '/');
            if (!str_ends_with(strtolower($apiUrl), '/api')) {
                $apiUrl .= '/Api';
            }
        }

        $payload = [];

        try {
            $tangoClient = new TangoOrderClient($apiUrl, $token, $companyId, $clientKey !== '' ? $clientKey : null);
            $idSta11 = $tangoClient->getArticleIdByCode((string) $pedido['articulo_codigo']);
            if ($idSta11 === null) {
                throw new \RuntimeException('El artículo del PDS no existe como ID_STA11 válido en Tango Connect.');
            }

            $precioUnitario = isset($pedido['articulo_precio_unitario']) ? (float) $pedido['articulo_precio_unitario'] : 0.0;
            $cabecera = [
                'id' => (int) $pedido['id'],
                'empresa_id' => $empresaId,
                'created_at' => (string) ($pedido['fecha_inicio'] ?? $pedido['created_at'] ?? date('Y-m-d H:i:s')),
                'observaciones' => $this->buildObservaciones($pedido),
                'total' => round($precioUnitario * $tiempoDecimal, 2),
            ];

            $clientePayload = [
                'codigo_tango' => $cliente['codigo_tango'] ?? null,
                'id_gva14_tango' => $cliente['id_gva14_tango'] ?? null,
                'id_gva01_tango' => $cliente['id_gva01_tango'] ?? null,
                'id_gva10_tango' => $cliente['id_gva10_tango'] ?? null,
                'id_gva23_tango' => $cliente['id_gva23_tango'] ?? null,
                'id_gva24_tango' => $cliente['id_gva24_tango'] ?? null,
                'nombre' => $cliente['razon_social'] ?? ($pedido['cliente_nombre'] ?? ''),
                'apellido' => '',
                'documento' => $cliente['documento'] ?? ($pedido['cliente_documento'] ?? ''),
            ];

            $renglones = [[
                'id_sta11_tango' => $idSta11,
                'codigo_articulo' => (string) $pedido['articulo_codigo'],
                'cantidad' => $tiempoDecimal,
                'precio_unitario' => $precioUnitario,
            ]];

            $headerResolver = new TangoOrderHeaderResolver('crm');
            // Resolver el usuario operativo del perfil Tango:
            // resolveForCurrentContext() usa al admin logueado, que puede NO tener perfil Tango.
            // Buscamos el primer usuario activo de la empresa con perfil Tango configurado.
            $tangoUser = $this->resolveTangoUser($empresaId);
            $resolvedHeaders = $headerResolver->resolveFromConfig($config, $clientePayload, $tangoUser);

            if (isset($pedido['clasificacion_id_tango']) && (int)$pedido['clasificacion_id_tango'] > 0) {
                $resolvedHeaders['ID_GVA81'] = (int)$pedido['clasificacion_id_tango'];
            }

            $payload = TangoOrderMapper::map($cabecera, $renglones, $clientePayload, $resolvedHeaders);
            $response = $tangoClient->sendOrder($payload);

            if ($this->shouldRetryWithoutObservaciones($response)) {
                $payloadWithoutObservaciones = $payload;
                unset($payloadWithoutObservaciones['OBSERVACIONES']);
                $response = $tangoClient->sendOrder($payloadWithoutObservaciones);
                $payload = $payloadWithoutObservaciones;
            }

            if ($this->isSuccessfulResponse($response)) {
                $pedidoNumero = $this->extractOrderNumber($response['data'] ?? []);
                $this->repository->markAsSentToTango(
                    $pedidoId,
                    $empresaId,
                    $pedidoNumero,
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    json_encode($response, JSON_UNESCAPED_UNICODE)
                );

                return ['ok' => true, 'type' => 'success', 'message' => 'PDS enviado a Tango correctamente. Pedido externo: #' . $pedidoNumero . '.'];
            }

            $errorText = $this->extractErrorText($response);

            $this->repository->markAsErrorToTango(
                $pedidoId,
                $empresaId,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                $errorText,
                json_encode($response, JSON_UNESCAPED_UNICODE)
            );

            return ['ok' => false, 'type' => 'danger', 'message' => 'Tango rechazó el envío del PDS. Revisa el detalle del error guardado.'];
        } catch (\Throwable $e) {
            $this->repository->markAsErrorToTango(
                $pedidoId,
                $empresaId,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                $e->getMessage()
            );

            return ['ok' => false, 'type' => 'danger', 'message' => 'No se pudo enviar el PDS a Tango: ' . $e->getMessage()];
        }
    }

    public static function decimalHoursFromSeconds(?int $seconds): ?float
    {
        if ($seconds === null) {
            return null;
        }

        return round(max(0, $seconds) / 3600, 4);
    }

    private function resolveTiempoDecimal(array $pedido): ?float
    {
        if (isset($pedido['tiempo_decimal']) && $pedido['tiempo_decimal'] !== null && $pedido['tiempo_decimal'] !== '') {
            return round((float) $pedido['tiempo_decimal'], 4);
        }

        return self::decimalHoursFromSeconds(isset($pedido['duracion_neta_segundos']) ? (int) $pedido['duracion_neta_segundos'] : null);
    }

    private function buildObservaciones(array $pedido): string
    {
        $chunks = [
            'PDS #' . (int) ($pedido['numero'] ?? 0),
            'Solicito: ' . trim((string) ($pedido['solicito'] ?? 'N/D')),
            'Clasificacion: ' . trim((string) ($pedido['clasificacion_codigo'] ?? 'N/D')) . (trim((string) ($pedido['clasificacion_descripcion'] ?? '')) !== '' ? ' - ' . trim((string) ($pedido['clasificacion_descripcion'] ?? '')) : ''),
            'Tiempo decimal: ' . number_format((float) ($this->resolveTiempoDecimal($pedido) ?? 0), 4, '.', ''),
        ];

        $diagnostico = trim((string) ($pedido['diagnostico'] ?? ''));
        if ($diagnostico !== '') {
            $chunks[] = 'Diagnostico: ' . preg_replace('/\s+/', ' ', $diagnostico);
        }

        $motivo_descuento = trim((string) ($pedido['motivo_descuento'] ?? ''));
        if ($motivo_descuento !== '') {
            $chunks[] = 'Motivo Descuento: ' . preg_replace('/\s+/', ' ', $motivo_descuento);
        }

        return mb_substr(implode(' | ', $chunks), 0, 950);
    }

    private function extractOrderNumber(mixed $payload): string
    {
        if (!is_array($payload)) {
            return 'N/A';
        }

        foreach (['orderNumber', 'numeroPedido', 'pedidoNumero', 'NRO_PEDIDO', 'NUMERO_PEDIDO', 'savedId'] as $key) {
            if (isset($payload[$key]) && trim((string) $payload[$key]) !== '') {
                return trim((string) $payload[$key]);
            }
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $nested = $this->extractOrderNumber($value);
                if ($nested !== 'N/A') {
                    return $nested;
                }
            }
        }

        return 'N/A';
    }

    private function isSuccessfulResponse(array $response): bool
    {
        $status = (int) ($response['status'] ?? 500);
        if ($status < 200 || $status >= 300) {
            return false;
        }

        $data = $response['data'] ?? null;
        if (!is_array($data)) {
            return true;
        }

        if (array_key_exists('succeeded', $data)) {
            return (bool) $data['succeeded'];
        }

        return true;
    }

    private function shouldRetryWithoutObservaciones(array $response): bool
    {
        $data = $response['data'] ?? null;
        if (!is_array($data) || ($data['succeeded'] ?? true) !== false) {
            return false;
        }

        $messages = $data['exceptionInfo']['messages'] ?? [];
        if (!is_array($messages)) {
            return false;
        }

        foreach ($messages as $message) {
            if (is_string($message) && str_contains(strtoupper($message), 'OBSERVACIONES')) {
                return true;
            }
        }

        return false;
    }

    private function extractErrorText(array $response): string
    {
        $data = $response['data'] ?? null;
        if (is_array($data)) {
            $messages = $data['exceptionInfo']['messages'] ?? null;
            if (is_array($messages) && $messages !== []) {
                return implode(' | ', array_map(static fn ($item): string => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $messages));
            }

            if (!empty($data['message'])) {
                return (string) $data['message'];
            }

            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        return 'HTTP Error ' . (string) ($response['status'] ?? 'desconocido');
    }

    /**
     * Busca el primer usuario activo de la empresa que tenga un perfil Tango configurado.
     * Esto permite que el resolver use el perfil del operador real, no el del admin logueado,
     * que puede no tener perfil de Tango asignado.
     *
     * Prioridad:
     * 1. Usuario logueado si tiene tango_perfil_pedido_id
     * 2. Primer usuario activo de la empresa con tango_perfil_pedido_id configurado
     * 3. null → el resolver cae al config global de la empresa
     */
    private function resolveTangoUser(int $empresaId): ?\App\Modules\Auth\Usuario
    {
        // Primero: el usuario logueado, si tiene perfil Tango propio.
        $currentUser = \App\Modules\Auth\AuthService::getCurrentUser();
        if ($currentUser !== null && !empty($currentUser->tango_perfil_pedido_id)) {
            return $currentUser;
        }

        // Segundo: buscar un usuario activo de la empresa con perfil Tango.
        try {
            $userRepo = new \App\Modules\Auth\UsuarioRepository();
            $users = $userRepo->findAllByEmpresaId($empresaId);
            foreach ($users as $user) {
                if (!empty($user->tango_perfil_pedido_id) && $user->activo == 1) {
                    return $user;
                }
            }
        } catch (\Throwable $e) {
            // Silencioso — el resolver hará fallback al config global
        }

        return null;
    }
}
