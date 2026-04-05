<?php

declare(strict_types=1);

namespace App\Modules\CrmLlamadas;

use App\Core\Database;
use PDO;

class WebhookController
{
    public function handleAnura(string $slug): void
    {
        // El webhook envía los datos por POST (x-www-form-urlencoded)
        // Ejemplo esperado (basado en el código legacy del usuario):
        // $_POST['grabar'] etc.
        // Capturar todo el payload, en formato bruto por las dudas
        $body = file_get_contents('php://input');
        $data = !empty($_POST) ? $_POST : json_decode($body, true);
        
        if (!$data || !is_array($data)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
            return;
        }

        /* 
        Mapeo de campos de Anura discriminando formato nuevo y legacy:
        */
        if (isset($data['numero_origen']) || isset($data['precio'])) {
            // Nuevo formato
            $fecha = $data['fecha'] ?? date('Y-m-d H:i:s');
            $origen = $data['origen'] ?? null;
            $numero_origen = $data['numero_origen'] ?? null;
            $destino = $data['destino'] ?? null;
            $duracion = $data['duracion'] ?? null;
            $interno_central = $data['interno'] ?? null;
            $evento_link = $data['link_mp'] ?? null;
            $atendio = $data['atendio'] ?? null;
            $mp3 = $data['mp3'] ?? null;
        } else {
            // Formato antiguo / fallback
            $fecha = $data['tiempo'] ?? date('Y-m-d H:i:s');
            $origen = $data['origen_region'] ?? null;
            $numero_origen = $data['origen'] ?? null;
            $destino = $data['destino'] ?? null;
            $duracion = $data['segundos'] ?? null;
            $interno_central = $data['interno'] ?? null;
            $evento_link = $data['link'] ?? null;
            $atendio = $data['terminal'] ?? null;
            $mp3 = $data['mp3'] ?? null;
        }
        
        $json_bruto = json_encode($data, JSON_UNESCAPED_UNICODE);

        try {
            // Resolver empresa por slug
            $repoEmpresas = new \App\Modules\Empresas\EmpresaRepository();
            $empresa = $repoEmpresas->findBySlug($slug);

            if (!$empresa) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Empresa no encontrada para el slug proporcionado.']);
                return;
            }

            $empresaId = (int)$empresa->id;
            $usuarioId = null;

            $db = Database::getConnection();

            // Si el webhook trae 'atendio' o 'interno_central', intentamos identificar al usuario
            $internoAgenteParseado = substr((string)$atendio, 0, 3);
            
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE (anura_interno = :interno OR anura_interno = :internoParseado) AND empresa_id = :empresa_id AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([
                ':interno' => $interno_central,
                ':internoParseado' => $internoAgenteParseado,
                ':empresa_id' => $empresaId
            ]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                $usuarioId = (int)$usuario['id'];
            }

            // [NUEVO] - Buscar si este numero u origen ya está mapeado a un cliente de Tango
            $clienteId = null;
            $numeroBuscado = $numero_origen ?: $origen;
            if (!empty($numeroBuscado)) {
                $stmtPhone = $db->prepare("SELECT cliente_id FROM crm_telefonos_clientes WHERE empresa_id = :empresa_id AND numero_origen = :numero_origen LIMIT 1");
                $stmtPhone->execute([
                    ':empresa_id' => $empresaId,
                    ':numero_origen' => $numeroBuscado
                ]);
                $phoneRow = $stmtPhone->fetch(PDO::FETCH_ASSOC);
                if ($phoneRow && !empty($phoneRow['cliente_id'])) {
                    $clienteId = (int)$phoneRow['cliente_id'];
                }
            }

            $sql = "INSERT INTO crm_llamadas 
                    (empresa_id, usuario_id, cliente_id, fecha, origen, numero_origen, destino, duracion, interno, atendio, evento_link, mp3, json_bruto)
                    VALUES 
                    (:empresa_id, :usuario_id, :cliente_id, :fecha, :origen, :numero_origen, :destino, :duracion, :interno, :atendio, :evento_link, :mp3, :json_bruto)";
            
            $stmtInsert = $db->prepare($sql);
            $stmtInsert->execute([
                ':empresa_id' => $empresaId,
                ':usuario_id' => $usuarioId,
                ':cliente_id' => $clienteId,
                ':fecha' => $fecha,
                ':origen' => $origen,
                ':numero_origen' => $numero_origen,
                ':destino' => $destino,
                ':duracion' => $duracion,
                ':interno' => $interno_central,
                ':atendio' => $atendio,
                ':evento_link' => $evento_link,
                ':mp3' => $mp3,
                ':json_bruto' => $json_bruto,
            ]);

            http_response_code(200);
            echo json_encode(['status' => 'success']);
        } catch (\Throwable $e) {
            error_log('Error en Anura Webhook: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
        }
    }
    public function testHook(string $slug): void
    {
        // Esta URL es solo para emular internamente.
        // Simulamos un payload POST hacia handleAnura.
        $randomDuration = rand(30, 300); // 30s a 5min
        $interno = $_GET['interno'] ?? '100';
        
        $testData = [
            'origen' => 'Restringido',
            'numero_origen' => '1155667788',
            'precio' => '0.0',
            'fecha' => date('Y-m-d H:i:s'),
            'duracion' => (string)$randomDuration,
            'interno' => $interno,
            'atendio' => '104-TestAgent',
            'tiempo_espera' => '0',
            'terminal' => '',
            'link_mp' => 'HANGUP',
            'destino' => '104',
            'mp3' => 'https://example.com/audio/mock-call-' . rand(100, 999) . '.mp3',
            'prueba' => '',
            'prueba2' => ''
        ];

        // Simulamos que el POST está lleno
        $_POST = $testData;
        
        $this->handleAnura($slug);
    }
}
