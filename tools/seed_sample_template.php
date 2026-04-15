<?php

declare(strict_types=1);

/**
 * Seed one-shot: crea una plantilla HTML de prueba bonita asociada al
 * primer reporte activo que encuentre (cualquier empresa). Idempotente:
 * si ya existe una plantilla con el nombre objetivo, no hace nada.
 *
 * Uso:
 *   php tools/seed_sample_template.php
 *
 * Este archivo NO se commitea — es solo para sembrar un ejemplo en dev.
 */

define('BASE_PATH', dirname(__DIR__));

// Cargar .env
if (is_file(BASE_PATH . '/.env')) {
    foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        putenv($line);
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Modules\CrmMailMasivos\Services\ReportMetamodel;

$db = Database::getConnection();
$meta = new ReportMetamodel();

// 1. Buscar el primer reporte activo
$stmt = $db->query(
    "SELECT id, empresa_id, nombre, root_entity, config_json
     FROM crm_mail_reports
     WHERE deleted_at IS NULL
     ORDER BY id ASC
     LIMIT 1"
);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    echo "[ERROR] No hay ningun reporte activo en la DB. Crea uno primero desde /mi-empresa/crm/mail-masivos/reportes/crear\n";
    exit(1);
}

echo "Reporte encontrado: #{$report['id']} '{$report['nombre']}' (empresa {$report['empresa_id']}, raiz {$report['root_entity']})\n";

// 2. Extraer variables disponibles del reporte
$config = json_decode((string) $report['config_json'], true);
$fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];

$available = [];
foreach ($fields as $f) {
    if (!is_array($f)) continue;
    $e = (string) ($f['entity'] ?? '');
    $fname = (string) ($f['field'] ?? '');
    if ($e === '' || $fname === '') continue;
    if (!$meta->hasField($e, $fname)) continue;
    $def = $meta->getField($e, $fname);
    $available[$e . '.' . $fname] = [
        'label' => (string) ($def['label'] ?? $fname),
        'type' => (string) ($def['type'] ?? 'string'),
    ];
}

if (empty($available)) {
    echo "[ERROR] El reporte #{$report['id']} no tiene campos de salida. Edita el reporte y prende al menos un campo.\n";
    exit(1);
}

echo "Variables disponibles del reporte:\n";
foreach ($available as $token => $info) {
    echo "  - {{" . $token . "}}  ({$info['label']}, {$info['type']})\n";
}

// 3. Elegir variables "principales" buscando en TODAS las entidades disponibles
//    con heurística semántica (tipo + label), no solo por nombre literal.
$rootEntity = (string) $report['root_entity'];

// Buscador por keyword en la parte "field" del token (después del punto)
$findByFieldKeyword = function (array $keywords) use ($available): ?string {
    foreach ($keywords as $kw) {
        foreach ($available as $token => $info) {
            $fieldPart = strtolower(explode('.', $token)[1] ?? '');
            if (str_contains($fieldPart, $kw)) return $token;
        }
    }
    return null;
};
// Buscador por tipo exacto
$findByType = function (string $type) use ($available): ?string {
    foreach ($available as $token => $info) {
        if (($info['type'] ?? '') === $type) return $token;
    }
    return null;
};

// Nombre: cualquier cosa con "nombre", "razon", "apellido", "cliente"
$tokNombre = $findByFieldKeyword(['razon_social', 'nombre', 'apellido', 'cliente_nombre']);
// Email: tipo "email" o field que contenga "email"/"mail"
$tokEmail = $findByType('email') ?? $findByFieldKeyword(['email', 'mail']);
// ID: codigo_tango, numero, nro_*, id
$tokId = $findByFieldKeyword(['codigo_tango', 'numero', 'nro_']);
if ($tokId === null) {
    // Último recurso: un int cualquiera que NO sea el mismo que $tokNombre
    foreach ($available as $token => $info) {
        if (($info['type'] ?? '') === 'int' && $token !== $tokNombre) {
            $tokId = $token;
            break;
        }
    }
}

// Fallback extremo: agarrar los primeros 3 distintos
$allTokens = array_keys($available);
if ($tokNombre === null && !empty($allTokens)) $tokNombre = $allTokens[0];
if ($tokEmail === null) {
    foreach ($allTokens as $t) { if ($t !== $tokNombre) { $tokEmail = $t; break; } }
}
if ($tokId === null) {
    foreach ($allTokens as $t) { if ($t !== $tokNombre && $t !== $tokEmail) { $tokId = $t; break; } }
}

$varNombre = $tokNombre ? '{{' . $tokNombre . '}}' : '(sin campo)';
$varEmail = $tokEmail ? '{{' . $tokEmail . '}}' : '';
$varId = $tokId ? '{{' . $tokId . '}}' : '';

// 4. Asunto y body HTML bonitos estilo newsletter
$asunto = "Hola {$varNombre}, tenemos novedades para vos 💌";

$bodyHtml = <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:40px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
  <tr>
    <td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,0.06);">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#0d6efd 0%,#6f42c1 100%);padding:32px 40px;text-align:center;color:#ffffff;">
            <h1 style="margin:0;font-size:24px;font-weight:700;letter-spacing:-0.02em;">
              ¡Hola, {$varNombre}!
            </h1>
            <p style="margin:8px 0 0;font-size:14px;opacity:0.92;">
              Tenemos una actualización importante para contarte
            </p>
          </td>
        </tr>

        <!-- Cuerpo -->
        <tr>
          <td style="padding:36px 40px 24px;color:#2d3748;line-height:1.6;font-size:15px;">
            <p style="margin:0 0 16px;">
              Gracias por seguir confiando en nosotros. Queríamos contarte que acabamos de lanzar
              una nueva funcionalidad pensada especialmente para vos.
            </p>

            <p style="margin:0 0 24px;">
              Entrá y probala — en menos de 5 minutos vas a ver el impacto.
            </p>

            <!-- CTA -->
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;">
              <tr>
                <td style="background:#0d6efd;border-radius:8px;">
                  <a href="https://ejemplo.com/novedades"
                     style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;">
                    Ver las novedades
                  </a>
                </td>
              </tr>
            </table>

            <!-- Info card -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;margin-top:24px;">
              <tr>
                <td style="padding:18px 20px;">
                  <p style="margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;color:#718096;font-weight:600;">
                    Tus datos de contacto
                  </p>
                  <p style="margin:0;font-size:14px;color:#2d3748;">
                    <strong>Identificador:</strong> {$varId}<br>
                    <strong>Email:</strong> {$varEmail}
                  </p>
                </td>
              </tr>
            </table>

            <p style="margin:28px 0 0;color:#718096;font-size:13px;">
              Si tenés alguna duda, respondé este mail y te contestamos enseguida.
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#fafbfc;padding:20px 40px;text-align:center;border-top:1px solid #edf2f7;">
            <p style="margin:0;color:#a0aec0;font-size:12px;line-height:1.5;">
              Recibiste este mail porque estás en nuestra base de contactos.<br>
              <a href="#" style="color:#718096;text-decoration:underline;">Preferencias</a>
              &nbsp;·&nbsp;
              <a href="#" style="color:#718096;text-decoration:underline;">Desuscribirte</a>
            </p>
          </td>
        </tr>
      </table>

      <!-- Pie -->
      <p style="margin:24px 0 0;color:#a0aec0;font-size:11px;text-align:center;">
        © rxn_suite · Enviado con cariño automático
      </p>
    </td>
  </tr>
</table>
HTML;

$nombre = "Newsletter de prueba — Fase 3";
$descripcion = "Ejemplo de plantilla HTML estilo newsletter con variables del reporte asociado. Generado por seed_sample_template.php.";

// 5. Chequear si ya existe por idempotencia
$stmtCheck = $db->prepare(
    "SELECT id FROM crm_mail_templates
     WHERE empresa_id = :empresa_id AND nombre = :nombre AND deleted_at IS NULL
     LIMIT 1"
);
$stmtCheck->execute([
    ':empresa_id' => (int) $report['empresa_id'],
    ':nombre' => $nombre,
]);
$existing = $stmtCheck->fetchColumn();

$availableVarsJson = json_encode(
    array_map(
        fn($token, $info) => [
            'token' => $token,
            'column_alias' => str_replace('.', '_', $token),
            'entity_label' => explode('.', $token)[0],
            'field_label' => $info['label'],
            'type' => $info['type'],
        ],
        array_keys($available),
        array_values($available)
    )
);

if ($existing) {
    echo "\n[INFO] Ya existe una plantilla con nombre '{$nombre}' (id #{$existing}). Actualizandola...\n";
    $stmtUpd = $db->prepare(
        "UPDATE crm_mail_templates
         SET descripcion = :descripcion,
             report_id = :report_id,
             asunto = :asunto,
             body_html = :body_html,
             available_vars_json = :available_vars_json
         WHERE id = :id"
    );
    $stmtUpd->execute([
        ':id' => (int) $existing,
        ':descripcion' => $descripcion,
        ':report_id' => (int) $report['id'],
        ':asunto' => $asunto,
        ':body_html' => $bodyHtml,
        ':available_vars_json' => $availableVarsJson,
    ]);
    echo "[OK] Plantilla #{$existing} actualizada.\n";
    $templateId = (int) $existing;
} else {
    echo "\nCreando plantilla nueva...\n";
    $stmtIns = $db->prepare(
        "INSERT INTO crm_mail_templates
            (empresa_id, nombre, descripcion, report_id, asunto, body_html, available_vars_json, created_by)
         VALUES
            (:empresa_id, :nombre, :descripcion, :report_id, :asunto, :body_html, :available_vars_json, :created_by)"
    );
    $stmtIns->execute([
        ':empresa_id' => (int) $report['empresa_id'],
        ':nombre' => $nombre,
        ':descripcion' => $descripcion,
        ':report_id' => (int) $report['id'],
        ':asunto' => $asunto,
        ':body_html' => $bodyHtml,
        ':available_vars_json' => $availableVarsJson,
        ':created_by' => null,
    ]);
    $templateId = (int) $db->lastInsertId();
    echo "[OK] Plantilla #{$templateId} creada.\n";
}

echo "\n─────────────────────────────────────────────\n";
echo "Resumen:\n";
echo "  Plantilla: #{$templateId}  '{$nombre}'\n";
echo "  Empresa:   {$report['empresa_id']}\n";
echo "  Reporte:   #{$report['id']}  '{$report['nombre']}'\n";
echo "  Asunto:    " . mb_substr($asunto, 0, 80) . "...\n";
echo "  Variables usadas en el HTML:\n";
echo "    nombre → " . ($tokNombre ?? '(ninguna)') . "\n";
echo "    email  → " . ($tokEmail ?? '(ninguna)') . "\n";
echo "    id     → " . ($tokId ?? '(ninguna)') . "\n";
echo "\nAbrila en el editor:\n";
echo "  /mi-empresa/crm/mail-masivos/plantillas/{$templateId}/editar\n";
echo "─────────────────────────────────────────────\n";
