<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking;

use App\Core\Context;
use App\Modules\Auth\AuthService;

/**
 * Endpoint POST /geo-tracking/consent.
 *
 * Recibe la respuesta del usuario al banner y la persiste en rxn_geo_consent.
 * Devuelve JSON con { success: bool } — no redirige ni renderiza vista.
 *
 * Payload esperado (JSON body o POST form):
 *   - decision: 'accepted' | 'denied' | 'later'
 */
class RxnGeoTrackingConsentController extends \App\Core\Controller
{
    private GeoTrackingService $service;

    public function __construct()
    {
        $this->service = new GeoTrackingService();
    }

    public function store(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        $empresaId = (int) Context::getEmpresaId();

        if ($userId <= 0 || $empresaId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Sesión inválida.']);
            return;
        }

        $decision = $this->extractDecision();

        if (!in_array($decision, GeoConsentRepository::VALID_DECISIONS, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Decisión inválida.']);
            return;
        }

        $ok = $this->service->registrarConsentimiento($userId, $empresaId, $decision);
        echo json_encode([
            'success' => $ok,
            'decision' => $decision,
            'consent_version' => $this->service->currentConsentVersion($empresaId),
        ]);
    }

    /**
     * Acepta tanto JSON body como POST form para flexibilidad del frontend.
     */
    private function extractDecision(): string
    {
        $decision = $_POST['decision'] ?? null;

        if ($decision === null) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && $raw !== '') {
                $json = json_decode($raw, true);
                if (is_array($json) && isset($json['decision'])) {
                    $decision = $json['decision'];
                }
            }
        }

        return is_string($decision) ? strtolower(trim($decision)) : '';
    }
}
