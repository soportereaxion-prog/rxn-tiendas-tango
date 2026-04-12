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
 * Las credenciales OAuth (client_id, client_secret, redirect_uri) se leen de
 * la tabla empresa_config_crm POR EMPRESA, no de .env. De esta forma cada
 * tenant puede autogestionar su integracion con Google sin editar archivos
 * del servidor ni reiniciar nada.
 *
 * La unica variable de entorno que se necesita a nivel sistema es APP_KEY,
 * usada para encriptar tokens y el client_secret en la base de datos.
 *
 * Modos de autenticacion (empresa_config_crm.agenda_google_auth_mode):
 *   - 'usuario'  -> cada operador conecta su propia cuenta Google
 *   - 'empresa'  -> una sola conexion compartida (calendar corporativo)
 *   - 'ambos'    -> empresa-wide + cada usuario que quiera conectar lo suyo
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

    // ---- Configuracion per-empresa ----

    /**
     * Lee la configuracion OAuth de la empresa desde empresa_config_crm.
     * Desencripta el client_secret al vuelo.
     */
    public function loadEmpresaConfig(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT
                google_oauth_client_id,
                google_oauth_client_secret,
                google_oauth_redirect_uri,
                agenda_google_auth_mode
            FROM empresa_config_crm
            WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([':empresa_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'client_id' => '',
                'client_secret' => '',
                'redirect_uri' => '',
                'auth_mode' => 'usuario',
            ];
        }

        $secret = '';
        $rawSecret = trim((string) ($row['google_oauth_client_secret'] ?? ''));
        if ($rawSecret !== '') {
            try {
                $secret = $this->decrypt($rawSecret, $empresaId);
            } catch (\Throwable) {
                $secret = ''; // Desencriptacion fallo (APP_KEY cambio?)
            }
        }

        return [
            'client_id' => trim((string) ($row['google_oauth_client_id'] ?? '')),
            'client_secret' => $secret,
            'redirect_uri' => trim((string) ($row['google_oauth_redirect_uri'] ?? '')),
            'auth_mode' => in_array($row['agenda_google_auth_mode'] ?? 'usuario', ['usuario', 'empresa', 'ambos'], true)
                ? $row['agenda_google_auth_mode']
                : 'usuario',
        ];
    }

    /**
     * Devuelve true si las 3 credenciales OAuth estan configuradas para la empresa.
     */
    public function isConfigured(int $empresaId): bool
    {
        $config = $this->loadEmpresaConfig($empresaId);
        return $config['client_id'] !== ''
            && $config['client_secret'] !== ''
            && $config['redirect_uri'] !== '';
    }

    /**
     * Devuelve los nombres de los campos faltantes para la empresa.
     */
    public function getMissingConfigFields(int $empresaId): array
    {
        $config = $this->loadEmpresaConfig($empresaId);
        $missing = [];
        if ($config['client_id'] === '') $missing[] = 'Client ID';
        if ($config['client_secret'] === '') $missing[] = 'Client Secret';
        if ($config['redirect_uri'] === '') $missing[] = 'Redirect URI';
        return $missing;
    }

    /**
     * Guarda/actualiza las credenciales OAuth para una empresa.
     * El client_secret se encripta antes de persistir.
     * Si el secret viene vacío, se preserva el existente.
     */
    public function saveEmpresaConfig(int $empresaId, string $clientId, string $clientSecret, string $redirectUri, string $authMode): void
    {
        $authMode = in_array($authMode, ['usuario', 'empresa', 'ambos'], true) ? $authMode : 'usuario';

        // Si el secret viene vacio, preservar el existente
        $encSecret = null;
        if (trim($clientSecret) !== '') {
            $encSecret = $this->encrypt(trim($clientSecret), $empresaId);
        }

        $stmt = $this->db->prepare('SELECT id, google_oauth_client_secret FROM empresa_config_crm WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([':empresa_id' => $empresaId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $secretToStore = $encSecret ?? ($existing['google_oauth_client_secret'] ?? null);
            $stmt = $this->db->prepare('UPDATE empresa_config_crm SET
                    google_oauth_client_id = :client_id,
                    google_oauth_client_secret = :client_secret,
                    google_oauth_redirect_uri = :redirect_uri,
                    agenda_google_auth_mode = :auth_mode
                WHERE empresa_id = :empresa_id');
            $stmt->execute([
                ':client_id' => trim($clientId) !== '' ? trim($clientId) : null,
                ':client_secret' => $secretToStore,
                ':redirect_uri' => trim($redirectUri) !== '' ? trim($redirectUri) : null,
                ':auth_mode' => $authMode,
                ':empresa_id' => $empresaId,
            ]);
        }
        // Si no existe registro en empresa_config_crm, no hacemos insert.
        // El registro lo crea EmpresaConfigService cuando se guarda la config por primera vez.
    }

    // ---- OAuth flow ----

    public function getAuthUrl(int $empresaId, ?int $usuarioId, string $mode): string
    {
        $config = $this->loadEmpresaConfig($empresaId);
        if ($config['client_id'] === '' || $config['redirect_uri'] === '') {
            throw new RuntimeException('Credenciales OAuth de Google no configuradas para esta empresa. Completá los datos en Configuración CRM.');
        }

        $state = $this->encodeState([
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'mode' => $mode,
            'nonce' => bin2hex(random_bytes(8)),
        ]);

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

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
            $usuarioId = null;
        }

        $config = $this->loadEmpresaConfig($empresaId);
        if ($config['client_id'] === '' || $config['client_secret'] === '' || $config['redirect_uri'] === '') {
            throw new RuntimeException('Credenciales OAuth incompletas para esta empresa. Verificá la configuración CRM.');
        }

        $response = $this->httpPost(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'grant_type' => 'authorization_code',
        ]);

        if (!isset($response['access_token']) || !isset($response['refresh_token'])) {
            throw new RuntimeException('Google no devolvio los tokens esperados: ' . json_encode($response));
        }

        $userInfo = $this->httpGet(self::USERINFO_URL, $response['access_token']);
        $googleEmail = (string) ($userInfo['email'] ?? 'unknown@google');

        $expiresIn = (int) ($response['expires_in'] ?? 3600);
        $expiry = (new DateTimeImmutable())->modify('+' . $expiresIn . ' seconds')->format('Y-m-d H:i:s');

        $accessEnc = $this->encrypt((string) $response['access_token'], $empresaId);
        $refreshEnc = $this->encrypt((string) $response['refresh_token'], $empresaId);
        $scope = (string) ($response['scope'] ?? self::SCOPE);

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

    // ---- Multi-auth: resolver todos los destinos de sync ----

    /**
     * Devuelve el auth activo para UNA operacion (compat con Fase 2 original).
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

    /**
     * Devuelve TODOS los auths a los que hay que pushear un evento (modo 'ambos').
     * Para modo 'usuario' devuelve solo el del usuario, para 'empresa' solo el global.
     */
    public function getActiveAuths(int $empresaId, ?int $usuarioId): array
    {
        $mode = $this->resolveAgendaMode($empresaId);
        $auths = [];

        // Auth empresa-wide
        if ($mode === 'empresa' || $mode === 'ambos') {
            $empresaAuth = $this->findAuth($empresaId, null);
            if ($empresaAuth !== null) {
                $auths[] = $empresaAuth;
            }
        }

        // Auth del usuario
        if (($mode === 'usuario' || $mode === 'ambos') && $usuarioId !== null) {
            $userAuth = $this->findAuth($empresaId, $usuarioId);
            if ($userAuth !== null) {
                $auths[] = $userAuth;
            }
        }

        return $auths;
    }

    public function resolveAgendaMode(int $empresaId): string
    {
        $stmt = $this->db->prepare('SELECT agenda_google_auth_mode FROM empresa_config_crm WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([':empresa_id' => $empresaId]);
        $mode = (string) ($stmt->fetchColumn() ?: 'usuario');

        return in_array($mode, ['usuario', 'empresa', 'ambos'], true) ? $mode : 'usuario';
    }

    /**
     * Devuelve el access_token desencriptado y refrescado si es necesario.
     */
    public function getValidAccessToken(array $auth): string
    {
        $empresaId = (int) $auth['empresa_id'];
        $expiry = new DateTimeImmutable((string) $auth['token_expiry']);
        $now = new DateTimeImmutable();

        if ($expiry->getTimestamp() - $now->getTimestamp() > 120) {
            return $this->decrypt((string) $auth['access_token'], $empresaId);
        }

        $refreshToken = $this->decrypt((string) $auth['refresh_token'], $empresaId);
        $config = $this->loadEmpresaConfig($empresaId);

        if ($config['client_id'] === '' || $config['client_secret'] === '') {
            throw new RuntimeException('Credenciales OAuth de Google incompletas para refresh — empresa ' . $empresaId);
        }

        try {
            $response = $this->httpPost(self::TOKEN_URL, [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
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

    public function encrypt(string $plain, int $empresaId): string
    {
        $key = $this->deriveKey($empresaId);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('Fallo openssl_encrypt.');
        }
        return base64_encode($iv . $cipher);
    }

    public function decrypt(string $encoded, int $empresaId): string
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
}
