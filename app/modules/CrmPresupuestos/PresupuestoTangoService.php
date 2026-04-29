<?php
declare(strict_types=1);

namespace App\Modules\CrmPresupuestos;

use App\Modules\EmpresaConfig\EmpresaConfigService;
use App\Modules\Tango\Mappers\TangoOrderMapper;
use App\Modules\Tango\TangoOrderClient;
use App\Modules\Tango\Services\TangoOrderHeaderResolver;

class PresupuestoTangoService
{
    private PresupuestoRepository $repository;

    public function __construct(?PresupuestoRepository $repository = null)
    {
        $this->repository = $repository ?? new PresupuestoRepository();
    }

    public function send(int $presupuestoId, int $empresaId): array
    {
        $presupuesto = $this->repository->findById($presupuestoId, $empresaId);
        if ($presupuesto === null) {
            return ['ok' => false, 'type' => 'danger', 'message' => 'El Presupuesto no existe o no pertenece a la empresa activa.'];
        }

        $items = $this->repository->findItemsByPresupuestoId($presupuestoId, $empresaId);
        if (empty($items)) {
            return ['ok' => false, 'type' => 'warning', 'message' => 'El presupuesto debe tener al menos un renglón para enviarse a Tango.'];
        }

        $cliente = (new \App\Modules\CrmPedidosServicio\PedidoServicioRepository())->findClientById($empresaId, (int) ($presupuesto['cliente_id'] ?? 0));
        if ($cliente === null || empty($cliente['id_gva14_tango'])) {
            return ['ok' => false, 'type' => 'danger', 'message' => 'El cliente del presupuesto no tiene la relación comercial Tango resuelta.'];
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
            
            $renglones = [];
            foreach ($items as $item) {
                $idSta11 = $tangoClient->getArticleIdByCode((string) $item['articulo_codigo']);
                if ($idSta11 === null) {
                    throw new \RuntimeException('El artículo ' . $item['articulo_codigo'] . ' no existe como ID_STA11 válido en Tango Connect.');
                }

                // Descripción original del catálogo (lo que Tango identifica como
                // el artículo) y descripción actual (lo que el operador escribió).
                // Si difieren, el mapper inyecta DESCRIPCION_ADICIONAL_ARTICULO.
                $descActual = trim((string) ($item['articulo_descripcion_snapshot'] ?? ''));
                $descOriginal = trim((string) ($item['articulo_descripcion_original'] ?? ''));
                if ($descOriginal === '') {
                    $descOriginal = $descActual;
                }

                $renglones[] = [
                    'id_sta11_tango' => $idSta11,
                    'codigo_articulo' => (string) $item['articulo_codigo'],
                    'cantidad' => (float) $item['cantidad'],
                    'precio_unitario' => (float) $item['precio_unitario'],
                    'bonificacion_porcentaje' => (float) $item['bonificacion_porcentaje'],
                    'descripcion_original' => $descOriginal,
                    'descripcion_actual'   => $descActual,
                ];
            }

            $cabecera = [
                'id' => (int) $presupuesto['id'],
                'empresa_id' => $empresaId,
                'created_at' => (string) ($presupuesto['fecha'] ?? $presupuesto['created_at'] ?? date('Y-m-d H:i:s')),
                'observaciones' => $this->buildObservaciones($presupuesto),
                'total' => (float) $presupuesto['total'],
                // Release 1.29.0 — campos nuevos de cabecera comercial que viajan a Tango.
                // El mapper los traduce a COTIZACION y LEYENDA_1..5 en el payload.
                'cotizacion' => isset($presupuesto['cotizacion']) ? (float) $presupuesto['cotizacion'] : 1.0,
                'leyenda_1' => $presupuesto['leyenda_1'] ?? null,
                'leyenda_2' => $presupuesto['leyenda_2'] ?? null,
                'leyenda_3' => $presupuesto['leyenda_3'] ?? null,
                'leyenda_4' => $presupuesto['leyenda_4'] ?? null,
                'leyenda_5' => $presupuesto['leyenda_5'] ?? null,
            ];

            $clientePayload = [
                'codigo_tango' => $cliente['codigo_tango'] ?? null,
                'id_gva14_tango' => $cliente['id_gva14_tango'] ?? null,
                'id_gva01_tango' => $cliente['id_gva01_tango'] ?? null,
                'id_gva10_tango' => $cliente['id_gva10_tango'] ?? null,
                'id_gva23_tango' => $cliente['id_gva23_tango'] ?? null,
                'id_gva24_tango' => $cliente['id_gva24_tango'] ?? null,
                'nombre' => $cliente['razon_social'] ?? ($presupuesto['cliente_nombre_snapshot'] ?? ''),
                'apellido' => '',
                'documento' => $cliente['documento'] ?? ($presupuesto['cliente_documento_snapshot'] ?? ''),
            ];

            $headerResolver = new TangoOrderHeaderResolver('crm');
            $tangoUser = $this->resolveTangoUser();
            
            if ($tangoUser === null) {
                return ['ok' => false, 'type' => 'danger', 'message' => 'Tu usuario no tiene un Perfil de Tango configurado. Por favor, editá tu usuario en la configuración y guardá tus credenciales operativas.'];
            }

            $resolvedHeaders = $headerResolver->resolveFromConfig($config, $clientePayload, $tangoUser);

            // Inyectamos explícitamente los IDs definidos en la cabecera del Presupuesto (pisando los defaults)
            if (!empty($presupuesto['lista_id_interno'])) $resolvedHeaders['ID_GVA10'] = (int) $presupuesto['lista_id_interno'];
            if (!empty($presupuesto['condicion_id_interno'])) $resolvedHeaders['ID_GVA01'] = (int) $presupuesto['condicion_id_interno'];
            if (!empty($presupuesto['vendedor_id_interno'])) $resolvedHeaders['ID_GVA23'] = (int) $presupuesto['vendedor_id_interno'];
            if (!empty($presupuesto['transporte_id_interno'])) $resolvedHeaders['ID_GVA24'] = (int) $presupuesto['transporte_id_interno'];
            if (!empty($presupuesto['clasificacion_id_tango'])) $resolvedHeaders['ID_GVA81'] = (int) $presupuesto['clasificacion_id_tango'];

            $payload = TangoOrderMapper::map($cabecera, $renglones, $clientePayload, $resolvedHeaders);
            $response = $tangoClient->sendOrder($payload);

            if ($this->shouldRetryWithoutObservaciones($response)) {
                $payloadWithoutObservaciones = $payload;
                unset($payloadWithoutObservaciones['OBSERVACIONES']);
                $response = $tangoClient->sendOrder($payloadWithoutObservaciones);
                $payload = $payloadWithoutObservaciones;
            }

            if ($this->isSuccessfulResponse($response)) {
                $numeroInt = $this->extractOrderNumber($response['data'] ?? []);
                $nroPedido = $numeroInt;

                if (is_numeric($numeroInt) && (int)$numeroInt > 0) {
                    try {
                        $orderData = $tangoClient->getOrderById((int)$numeroInt);
                        $list = $orderData['resultData']['list'] ?? $orderData['data']['resultData']['list'] ?? $orderData['data']['list'] ?? [];
                        if (isset($list[0]['NRO_PEDIDO'])) {
                            $nroPedido = trim((string)$list[0]['NRO_PEDIDO']);
                        } elseif (isset($orderData['data']['Properties']['NRO_PEDIDO'])) {
                            $nroPedido = trim((string)$orderData['data']['Properties']['NRO_PEDIDO']);
                        } elseif (isset($orderData['resultData']['Properties']['NRO_PEDIDO'])) {
                            $nroPedido = trim((string)$orderData['resultData']['Properties']['NRO_PEDIDO']);
                        } else {
                            $extractedGet = $this->extractOrderNumber($orderData);
                            if ($extractedGet !== 'N/A') {
                                $nroPedido = $extractedGet;
                            }
                        }
                    } catch (\Exception $e) {}
                }

                $this->repository->markAsSentToTango(
                    $presupuestoId,
                    $empresaId,
                    $nroPedido,
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    json_encode($response, JSON_UNESCAPED_UNICODE)
                );

                return ['ok' => true, 'type' => 'success', 'message' => 'Presupuesto enviado a Tango correctamente. Pedido externo: #' . $nroPedido . '.'];
            }

            $errorText = $this->extractErrorText($response);

            $this->repository->markAsErrorToTango(
                $presupuestoId,
                $empresaId,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                $errorText,
                json_encode($response, JSON_UNESCAPED_UNICODE)
            );

            return ['ok' => false, 'type' => 'danger', 'message' => 'Tango rechazó el envío del Presupuesto. Revisa el detalle del error guardado.'];
        } catch (\Throwable $e) {
            $this->repository->markAsErrorToTango(
                $presupuestoId,
                $empresaId,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                $e->getMessage()
            );

            return ['ok' => false, 'type' => 'danger', 'message' => 'No se pudo enviar el Presupuesto a Tango: ' . $e->getMessage()];
        }
    }

    private function buildObservaciones(array $presupuesto): string
    {
        $chunks = [
            'Presupuesto #' . (int) ($presupuesto['numero'] ?? 0),
        ];
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

    private function resolveTangoUser(): ?\App\Modules\Auth\Usuario
    {
        $currentUser = \App\Modules\Auth\AuthService::getCurrentUser();
        if ($currentUser !== null && !empty($currentUser->tango_perfil_pedido_id)) {
            return $currentUser;
        }
        return null;
    }
}
