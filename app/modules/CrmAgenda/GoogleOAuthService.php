<?php
declare(strict_types=1);

namespace App\Modules\CrmAgenda;

use App\Core\Database;
use PDO;
use DateTimeImmutable;
use RuntimeException;

/**
 * Servicio de autenticacion OAuth2 con Google Calendar — cliente cURL nativo.
 *
 * Implementa el flujo completo:
 *   1. getAuthUrl()            -> URL para redirigir al consent screen de Google
 *   2. handleCallback(code)    -> cambia el code por access_token + refresh_token y persiste
 *   3. getActiveAuth()         -> recupera el auth activo segun el modo configurado (usuario|empresa)
 *   4. refreshTokenIfNeeded()  -> refresca el access_token si esta proximo a expirar
 *   5. disconnect()            -> borra la conexion
 *
 * No depende de google/apiclient. Toda la interaccion con Google se hace con cURL nativo.
 *
 * Variables de entorno requeridas (en .env):
 *   GOOGLE_CLIENT_ID
 *   GOOGLE_CLIENT_SECRET
 *   GOOGLE_REDIRECT_URI     (ej: https://crm.tudominio.com/mi-empresa/crm/agenda/google/callback)
 *
 * Seguridad:
 *  - access_token y refresh_token se encriptan con openssl_encrypt usando clave derivada
 *    de APP_KEY + empresa_id. Si alguien roba la base, no se roba las sesiones.
 *  - Todos los errores de red/HTTP se loguean en crm_google_auth.last_error (nunca a disco).
 */
class GoogleOAuthService
{
    private const SCOPE = 'https://www.googleapis.com/auth/calendar.events';
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Devuelve true si las 3 variables de entorno de OAuth estan configuradas.
     * Se usa en la UI para decidir si mostrar el boton "Conectar" o un banner
     * explicativo de setup pendiente.
     */
    public function isConfigured(): bool
    {
        $vars = ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI'];
        foreach ($vars as $var) {
            if (trim((string) (getenv($var) ?: '')) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Devuelve los nombres de las variables de entorno faltantes, para mostrarlas
     * en el banner de setup pendiente.
     */
    public function getMissingEnvVars(): array
    {
        $vars = ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI'];
        $missing = [];
        foreach ($vars as $var) {
            if (trim((string) (getenv($var) ?: '')) === '') {
                $missing[] = $var;
            }
        }
        return $missing;
    }

    /**
     * Construye la URL del consent screen de Google.
     * El `state` es opaco y se usa para identificar al usuario/empresa en el callback.
     */
    public function getAuthUrl(int $empresaId, ?int $usuarioId, string $mode): string
    {
        $clientId = $this->requireEnv('GOOGLE_CLIENT_ID');
        $redirectUri = $this->requireEnv('GOOGLE_REDIRECT_URI');

        $state = $this->encodeState([
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'mode' => $mode,
            'nonce' => bin2hex(random_bytes(8)),
        ]);

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Procesa el callback OAuth: intercambia el code por tokens, obtiene el email del usuario,
     * y guarda la conexion en crm_google_auth (encriptada).
     */
    public function handleCallback(string $code, string $state): array
    {
        $stateData = $this->decodeState($state);
        if ($stateData === null) {
            throw new RuntimeException('State invalido en callback de Google OAuth.');
        }

        $empresaId = (int) $stateData['empresa_id'];
        $usuarioId = isset($stateData['usuario_id']) && $stateData['usuario_id'] !== null ? (int) $stateData['usuario_id'] : null;
        $mode = (string) ($stateData['mode'] ?? 'usuario');
        if ($mode === 'empresa') {
            $usuarioId = null; // conexion empresa-wide
        }

        $clientId = $this->requireEnv('GOOGLE_CLIENT_ID');
        $clientSecret = $this->requireEnv('GOOGLE_CLIENT_SECRET');
        $redirectUri = $this->requireEnv('GOOGLE_REDIRECT_URI');

        // 1. Intercambiar el code por tokens
        $response = $this->httpPost(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!isset($response['access_token']) || !isset($response['refresh_token'])) {
            throw new RuntimeException('Google no devolvio los tokens esperados: ' . json_encode($response));
        }

        // 2. Obtener el email del usuario autenticado
        $userInfo = $this->httpGet(self::USERINFO_URL, $response['access_token']);
        $googleEmail = (string) ($userInfo['email'] ?? 'unknown@google');

        // 3. Calcular fecha de expiracion y persistir encriptado
        $expiresIn = (int) ($response['expires_in'] ?? 3600);
        $expiry = (new DateTimeImmutable())->modify('+' . $expiresIn . ' seconds')->format('Y-m-d H:i:s');

        $accessEnc = $this->encrypt((string) $response['access_token'], $empresaId);
        $refreshEnc = $this->encrypt((string) $response['refresh_token'], $empresaId);
        $scope = (string) ($response['scope'] ?? self::SCOPE);

        // UPSERT segun (empresa_id, usuario_id)
        $existing = $this->findAuth($empresaId, $usuarioId);
        if ($existing !== null) {
            $stmt = $this->db->prepare('UPDATE crm_google_auth SET
                    google_email = :email,
                    access_token = :access_token,
                    refresh_token = :refresh_token,
                    token_expiry = :token_expiry,
                    scope = :scope,
                    connected_at = NOW(),
                    last_error = NULL
                WHERE id = :id');
            $stmt->execute([
                ':email' => $googleEmail,
                ':access_token' => $accessEnc,
                ':refresh_token' => $refreshEnc,
                ':token_expiry' => $expiry,
                ':scope' => $scope,
                ':id' => (int) $existing['id'],
            ]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO crm_google_auth
                (empresa_id, usuario_id, google_email, access_token, refresh_token, token_expiry, calendar_id, scope, connected_at)
                VALUES (:empresa_id, :usuario_id, :email, :access_token, :refresh_token, :token_expiry, :calendar_id, :scope, NOW())');
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':usuario_id' => $usuarioId,
                ':email' => $googleEmail,
                ':access_token' => $accessEnc,
                ':refresh_token' => $refreshEnc,
                ':token_expiry' => $expiry,
                ':calendar_id' => 'primary',
                ':scope' => $scope,
            ]);
        }

        return [
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'google_email' => $googleEmail,
        ];
    }

    /**
     * Devuelve el auth activo para una operacion, resolviendo automaticamente segun el modo
     * configurado en empresa_config_crm.agenda_google_auth_mode.
     *
     * - modo 'usuario' -> busca el auth del usuario. Si no tiene, retorna null.
     * - modo 'empresa' -> busca el auth empresa-wide (usuario_id IS NULL).
     */
    public function getActiveAuth(int $empresaId, ?int $usuarioId): ?array
    {
        $mode = $this->resolveAgendaMode($empresaId);

        if ($mode === 'empresa') {
            return $this->findAuth($empresaId, null);
        }

        if ($usuarioId === null) {
            return null;
        }

        return $this->findAuth($empresaId, $usuarioId);
    }

    public function resolveAgendaMode(int $empresaId): string
    {
        $stmt = $this->db->prepare('SELECT agenda_google_auth_mode FROM empresa_config_crm WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([':empresa_id' => $empresaId]);
        $mode = (string) ($stmt->fetchColumn() ?: 'usuario');

        return in_array($mode, ['usuario', 'empresa'], true) ? $mode : 'usuario';
    }

    /**
     * Devuelve el access_token desencriptado y refrescado si es necesario.
     * Si el refresh falla, loguea el error y lanza excepcion.
     */
    public function getValidAccessToken(array $auth): string
    {
        $empresaId = (int) $auth['empresa_id'];
        $expiry = new DateTimeImmutable((string) $auth['token_expiry']);
        $now = new DateTimeImmutable();

        // Si expira en menos de 2 minutos, refrescamos
        if ($expiry->getTimestamp() - $now->getTimestamp() > 120) {
            return $this->decrypt((string) $auth['access_token'], $empresaId);
        }

        $refreshToken = $this->decrypt((string) $auth['refresh_token'], $empresaId);

        $clientId = $this->requireEnv('GOOGLE_CLIENT_ID');
        $clientSecret = $this->requireEnv('GOOGLE_CLIENT_SECRET');

        try {
            $response = $this->httpPost(self::TOKEN_URL, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if (!isset($response['access_token'])) {
                throw new RuntimeException('Google refresh no devolvio access_token: ' . json_encode($response));
            }

            $newExpiry = (new DateTimeImmutable())->modify('+' . (int) ($response['expires_in'] ?? 3600) . ' seconds')->format('Y-m-d H:i:s');
            $newAccessEnc = $this->encrypt((string) $response['access_token'], $empresaId);

            $stmt = $this->db->prepare('UPDATE crm_google_auth SET access_token = :access_token, token_expiry = :token_expiry, last_error = NULL WHERE id = :id');
            $stmt->execute([
                ':access_token' => $newAccessEnc,
                ':token_expiry' => $newExpiry,
                ':id' => (int) $auth['id'],
            ]);

            return (string) $response['access_token'];
        } catch (\Throwable $e) {
            $stmt = $this->db->prepare('UPDATE crm_google_auth SET last_error = :err WHERE id = :id');
            $stmt->execute([
                ':err' => 'refresh_token failure: ' . $e->getMessage(),
                ':id' => (int) $auth['id'],
            ]);
            throw $e;
        }
    }

    public function disconnect(int $empresaId, ?int $usuarioId): bool
    {
        if ($usuarioId === null) {
            $stmt = $this->db->prepare('DELETE FROM crm_google_auth WHERE empresa_id = :empresa_id AND usuario_id IS NULL');
            $stmt->execute([':empresa_id' => $empresaId]);
        } else {
            $stmt = $this->db->prepare('DELETE FROM crm_google_auth WHERE empresa_id = :empresa_id AND usuario_id = :usuario_id');
            $stmt->execute([':empresa_id' => $empresaId, ':usuario_id' => $usuarioId]);
        }

        return $stmt->rowCount() > 0;
    }

    public function findAuth(int $empresaId, ?int $usuarioId): ?array
    {
        if ($usuarioId === null) {
            $stmt = $this->db->prepare('SELECT * FROM crm_google_auth WHERE empresa_id = :empresa_id AND usuario_id IS NULL LIMIT 1');
            $stmt->execute([':empresa_id' => $empresaId]);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM crm_google_auth WHERE empresa_id = :empresa_id AND usuario_id = :usuario_id LIMIT 1');
            $stmt->execute([':empresa_id' => $empresaId, ':usuario_id' => $usuarioId]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ---- HTTP helpers (cURL nativo) ----

    private function httpPost(string $url, array $params): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlErr !== '') {
            throw new RuntimeException('Error cURL al contactar Google OAuth: ' . $curlErr);
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Respuesta Google OAuth no es JSON valido (HTTP ' . $httpCode . '): ' . substr((string) $body, 0, 200));
        }

        if ($httpCode >= 400) {
            $err = $decoded['error'] ?? 'unknown';
            $desc = $decoded['error_description'] ?? '';
            throw new RuntimeException('Google OAuth error (HTTP ' . $httpCode . '): ' . $err . ' — ' . $desc);
        }

        return $decoded;
    }

    private function httpGet(string $url, string $bearer): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $bearer,
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlErr !== '') {
            throw new RuntimeException('Error cURL al contactar Google API: ' . $curlErr);
        }

        $decoded = json_decode((string) $body, true);
        return is_array($decoded) ? $decoded : [];
    }

    // ---- Criptografia y state ----

    private function encrypt(string $plain, int $empresaId): string
    {
        $key = $this->deriveKey($empresaId);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('Fallo openssl_encrypt.');
        }
        return base64_encode($iv . $cipher);
    }

    private function decrypt(string $encoded, int $empresaId): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 17) {
            throw new RuntimeException('Payload encriptado invalido.');
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $key = $this->deriveKey($empresaId);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new RuntimeException('Fallo openssl_decrypt — APP_KEY puede haber cambiado.');
        }
        return $plain;
    }

    private function deriveKey(int $empresaId): string
    {
        $appKey = (string) (getenv('APP_KEY') ?: 'rxn_suite_default_key_change_me');
        return hash('sha256', $appKey . '|empresa=' . $empresaId, true);
    }

    private function encodeState(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    }

    private function decodeState(string $state): ?array
    {
        $b64 = strtr($state, '-_', '+/');
        $padded = str_pad($b64, strlen($b64) + (4 - strlen($b64) % 4) % 4, '=');
        $json = base64_decode($padded, true);
        if ($json === false) {
            return null;
        }
        $data = json_decode((string) $json, true);
        return is_array($data) ? $data : null;
    }

    private function requireEnv(string $name): string
    {
        $value = (string) (getenv($name) ?: '');
        if ($value === '') {
            throw new RuntimeException("Variable de entorno $name no configurada. Agregar en .env y definir la app en Google Cloud Console.");
        }
        return $value;
    }
}
