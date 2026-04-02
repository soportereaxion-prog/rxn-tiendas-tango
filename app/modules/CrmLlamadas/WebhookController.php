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
        Mapeo de campos típicos de Anura (basado en el código legacy):
        $tiempo, $origen, $destino, $segundos, $origen_region, $interno, $link, $terminal, $mp3
        */
        $fecha = $data['tiempo'] ?? date('Y-m-d H:i:s');
        $origen = $data['origen_region'] ?? null;
        $numero_origen = $data['origen'] ?? null;
        $destino = $data['destino'] ?? null;
        $duracion = $data['segundos'] ?? null;
        $interno_central = $data['interno'] ?? null;
        $evento_link = $data['link'] ?? null;
        $terminal = $data['terminal'] ?? null;
        $mp3 = $data['mp3'] ?? null;
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

            if ($terminal) {
                // El terminal tiene la información operativa, el interno del agente son los 3 primeros caracteres según especificación
                $internoAgente = substr((string)$terminal, 0, 3);
                
                // Buscar qué usuario tiene este interno asociado en esta empresa
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE anura_interno = :interno AND empresa_id = :empresa_id AND deleted_at IS NULL LIMIT 1");
                $stmt->execute([':interno' => $internoAgente, ':empresa_id' => $empresaId]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($usuario) {
                    $usuarioId = (int)$usuario['id'];
                }
            }

            $sql = "INSERT INTO crm_llamadas 
                    (empresa_id, usuario_id, fecha, origen, numero_origen, destino, duracion, interno, atendio, evento_link, mp3, json_bruto)
                    VALUES 
                    (:empresa_id, :usuario_id, :fecha, :origen, :numero_origen, :destino, :duracion, :interno, :atendio, :evento_link, :mp3, :json_bruto)";
            
            $stmtInsert = $db->prepare($sql);
            $stmtInsert->execute([
                ':empresa_id' => $empresaId,
                ':usuario_id' => $usuarioId,
                ':fecha' => $fecha,
                ':origen' => $origen,
                ':numero_origen' => $numero_origen,
                ':destino' => $destino,
                ':duracion' => $duracion,
                ':interno' => $interno_central,
                ':atendio' => $terminal,
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
            'tiempo' => date('Y-m-d H:i:s'),
            'origen_region' => 'Buenos Aires',
            'origen' => '1155667788',
            'destino' => '0800-444-1234',
            'segundos' => (string)$randomDuration,
            'interno' => $interno,
            'link' => 'HANGUP',
            'terminal' => $interno . '_TestAgent',
            'mp3' => 'https://example.com/audio/mock-call-' . rand(100, 999) . '.mp3'
        ];

        // Simulamos que el POST está lleno
        $_POST = $testData;
        
        $this->handleAnura($slug);
    }
}
