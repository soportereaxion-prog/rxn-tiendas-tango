<?php

declare(strict_types=1);

/**
 * Seed de prueba end-to-end para Charly:
 *   - Crea (si no existe) el cliente cyaciofani@e-reaxion.com.ar en empresa 1
 *   - Crea (si no existe) un reporte filtrado solo por ese email
 *   - Crea (si no existe) una plantilla HTML linda asociada al reporte
 *
 * Idempotente: se puede correr N veces sin duplicar.
 *
 * Uso:
 *   php tools/seed_test_cyaciofani.php
 */

define('BASE_PATH', dirname(__DIR__));

if (is_file(BASE_PATH . '/.env')) {
    foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        putenv($line);
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}
require_once BASE_PATH . '/vendor/autoload.php';

$db = \App\Core\Database::getConnection();

$EMAIL = 'cyaciofani@e-reaxion.com.ar';
$EMPRESA_ID = 1;

// ────────────────────────────────────────────────────────────
// 1. CLIENTE
// ────────────────────────────────────────────────────────────
echo "── Cliente ──\n";

$stmt = $db->prepare("SELECT id FROM crm_clientes WHERE email = :e AND empresa_id = :emp AND deleted_at IS NULL");
$stmt->execute([':e' => $EMAIL, ':emp' => $EMPRESA_ID]);
$clienteId = $stmt->fetchColumn();

if ($clienteId) {
    echo "  [OK] Cliente ya existe (id={$clienteId})\n";
} else {
    $stmt = $db->prepare(
        "INSERT INTO crm_clientes
            (empresa_id, nombre, apellido, email, razon_social, telefono, localidad, provincia, activo)
         VALUES
            (:empresa_id, :nombre, :apellido, :email, :razon_social, :telefono, :localidad, :provincia, 1)"
    );
    $stmt->execute([
        ':empresa_id' => $EMPRESA_ID,
        ':nombre' => 'Charly',
        ':apellido' => 'Yaciofani',
        ':email' => $EMAIL,
        ':razon_social' => 'e-Reaxion',
        ':telefono' => '+54 9 11 0000-0000',
        ':localidad' => 'CABA',
        ':provincia' => 'Buenos Aires',
    ]);
    $clienteId = (int) $db->lastInsertId();
    echo "  [NEW] Cliente creado (id={$clienteId})\n";
}

// ────────────────────────────────────────────────────────────
// 2. REPORTE filtrado por ese email exacto
// ────────────────────────────────────────────────────────────
echo "\n── Reporte ──\n";

$reporteNombre = "Test Charly (sólo cyaciofani)";
$reporteConfig = [
    'root_entity' => 'CrmClientes',
    'relations' => [],
    'fields' => [
        ['entity' => 'CrmClientes', 'field' => 'razon_social'],
        ['entity' => 'CrmClientes', 'field' => 'nombre'],
        ['entity' => 'CrmClientes', 'field' => 'apellido'],
        ['entity' => 'CrmClientes', 'field' => 'email'],
        ['entity' => 'CrmClientes', 'field' => 'telefono'],
        ['entity' => 'CrmClientes', 'field' => 'localidad'],
    ],
    'filters' => [
        ['entity' => 'CrmClientes', 'field' => 'email', 'op' => '=', 'value' => $EMAIL],
        ['entity' => 'CrmClientes', 'field' => 'activo', 'op' => '=', 'value' => 1],
    ],
    'mail_field' => ['entity' => 'CrmClientes', 'field' => 'email'],
];
$configJson = json_encode($reporteConfig, JSON_UNESCAPED_UNICODE);

$stmt = $db->prepare("SELECT id FROM crm_mail_reports WHERE empresa_id = :emp AND nombre = :n AND deleted_at IS NULL");
$stmt->execute([':emp' => $EMPRESA_ID, ':n' => $reporteNombre]);
$reporteId = $stmt->fetchColumn();

if ($reporteId) {
    $stmt = $db->prepare("UPDATE crm_mail_reports SET config_json = :c, root_entity = :r WHERE id = :id");
    $stmt->execute([':c' => $configJson, ':r' => 'CrmClientes', ':id' => $reporteId]);
    echo "  [OK] Reporte ya existía (id={$reporteId}), config actualizado\n";
} else {
    $stmt = $db->prepare(
        "INSERT INTO crm_mail_reports
            (empresa_id, nombre, descripcion, root_entity, config_json, created_by)
         VALUES
            (:empresa_id, :nombre, :desc, 'CrmClientes', :config_json, NULL)"
    );
    $stmt->execute([
        ':empresa_id' => $EMPRESA_ID,
        ':nombre' => $reporteNombre,
        ':desc' => 'Reporte de prueba Fase 4 — sólo devuelve a Charly para testing directo de envíos',
        ':config_json' => $configJson,
    ]);
    $reporteId = (int) $db->lastInsertId();
    echo "  [NEW] Reporte creado (id={$reporteId})\n";
}

// ────────────────────────────────────────────────────────────
// 3. PLANTILLA HTML asociada
// ────────────────────────────────────────────────────────────
echo "\n── Plantilla ──\n";

$plantillaNombre = "Test Charly — Saludo Fase 4";
$asunto = "Hola {{CrmClientes.nombre}}, tu módulo Mail Masivos está vivo 🚀";

$bodyHtml = <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:40px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
  <tr>
    <td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:40px 40px 32px;text-align:center;color:#ffffff;">
            <div style="font-size:48px;margin-bottom:8px;">🚀</div>
            <h1 style="margin:0;font-size:26px;font-weight:700;letter-spacing:-0.02em;">
              ¡Funcionó, {{CrmClientes.nombre}}!
            </h1>
            <p style="margin:10px 0 0;font-size:14px;opacity:0.95;">
              Este mail salió del módulo Mail Masivos que acabamos de armar
            </p>
          </td>
        </tr>

        <!-- Cuerpo -->
        <tr>
          <td style="padding:36px 40px 16px;color:#2d3748;line-height:1.65;font-size:15px;">
            <p style="margin:0 0 18px;">
              Hola <strong>{{CrmClientes.nombre}} {{CrmClientes.apellido}}</strong>,
            </p>

            <p style="margin:0 0 18px;">
              Si estás leyendo esto es porque la Fase 4 del módulo CrmMailMasivos está
              andando de punta a punta: reporte seleccionó el destinatario, plantilla
              renderizó las variables, job insertó los items, n8n procesó la cola y
              PHPMailer depositó este mail en tu inbox.
            </p>

            <p style="margin:0 0 24px;">
              Cada eslabón hizo su parte. 🎯
            </p>

            <!-- Info card con los datos del destinatario -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;margin-top:8px;">
              <tr>
                <td style="padding:20px 24px;">
                  <p style="margin:0 0 10px;font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#764ba2;font-weight:700;">
                    Datos que se reemplazaron en vivo
                  </p>
                  <table style="width:100%;font-size:13px;color:#2d3748;">
                    <tr>
                      <td style="padding:4px 0;color:#718096;width:110px;">Razón social:</td>
                      <td style="padding:4px 0;"><strong>{{CrmClientes.razon_social}}</strong></td>
                    </tr>
                    <tr>
                      <td style="padding:4px 0;color:#718096;">Email:</td>
                      <td style="padding:4px 0;"><strong>{{CrmClientes.email}}</strong></td>
                    </tr>
                    <tr>
                      <td style="padding:4px 0;color:#718096;">Teléfono:</td>
                      <td style="padding:4px 0;"><strong>{{CrmClientes.telefono}}</strong></td>
                    </tr>
                    <tr>
                      <td style="padding:4px 0;color:#718096;">Localidad:</td>
                      <td style="padding:4px 0;"><strong>{{CrmClientes.localidad}}</strong></td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <p style="margin:28px 0 0;color:#718096;font-size:13px;font-style:italic;">
              — Lumi 💜
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#fafbfc;padding:22px 40px;text-align:center;border-top:1px solid #edf2f7;">
            <p style="margin:0;color:#a0aec0;font-size:11px;line-height:1.6;">
              Plantilla generada por <code>tools/seed_test_cyaciofani.php</code><br>
              Reporte: "Test Charly (sólo cyaciofani)" · Plantilla: "Test Charly — Saludo Fase 4"
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
HTML;

$availableVars = json_encode([
    ['token' => 'CrmClientes.razon_social', 'column_alias' => 'CrmClientes_razon_social', 'entity_label' => 'Clientes', 'field_label' => 'Razón Social', 'type' => 'string'],
    ['token' => 'CrmClientes.nombre', 'column_alias' => 'CrmClientes_nombre', 'entity_label' => 'Clientes', 'field_label' => 'Nombre', 'type' => 'string'],
    ['token' => 'CrmClientes.apellido', 'column_alias' => 'CrmClientes_apellido', 'entity_label' => 'Clientes', 'field_label' => 'Apellido', 'type' => 'string'],
    ['token' => 'CrmClientes.email', 'column_alias' => 'CrmClientes_email', 'entity_label' => 'Clientes', 'field_label' => 'Email', 'type' => 'email'],
    ['token' => 'CrmClientes.telefono', 'column_alias' => 'CrmClientes_telefono', 'entity_label' => 'Clientes', 'field_label' => 'Teléfono', 'type' => 'string'],
    ['token' => 'CrmClientes.localidad', 'column_alias' => 'CrmClientes_localidad', 'entity_label' => 'Clientes', 'field_label' => 'Localidad', 'type' => 'string'],
]);

$stmt = $db->prepare("SELECT id FROM crm_mail_templates WHERE empresa_id = :emp AND nombre = :n AND deleted_at IS NULL");
$stmt->execute([':emp' => $EMPRESA_ID, ':n' => $plantillaNombre]);
$plantillaId = $stmt->fetchColumn();

if ($plantillaId) {
    $stmt = $db->prepare(
        "UPDATE crm_mail_templates
         SET report_id = :rid, asunto = :a, body_html = :b, available_vars_json = :v
         WHERE id = :id"
    );
    $stmt->execute([
        ':id' => $plantillaId,
        ':rid' => $reporteId,
        ':a' => $asunto,
        ':b' => $bodyHtml,
        ':v' => $availableVars,
    ]);
    echo "  [OK] Plantilla ya existía (id={$plantillaId}), contenido actualizado\n";
} else {
    $stmt = $db->prepare(
        "INSERT INTO crm_mail_templates
            (empresa_id, nombre, descripcion, report_id, asunto, body_html, available_vars_json, created_by)
         VALUES
            (:empresa_id, :nombre, :desc, :rid, :a, :b, :v, NULL)"
    );
    $stmt->execute([
        ':empresa_id' => $EMPRESA_ID,
        ':nombre' => $plantillaNombre,
        ':desc' => 'Plantilla de prueba Fase 4 — apunta al reporte filtrado por cyaciofani',
        ':rid' => $reporteId,
        ':a' => $asunto,
        ':b' => $bodyHtml,
        ':v' => $availableVars,
    ]);
    $plantillaId = (int) $db->lastInsertId();
    echo "  [NEW] Plantilla creada (id={$plantillaId})\n";
}

// ────────────────────────────────────────────────────────────
// RESUMEN
// ────────────────────────────────────────────────────────────
echo "\n─────────────────────────────────────────────\n";
echo "Todo listo para Fase 4:\n";
echo "  Cliente:    #{$clienteId}  {$EMAIL}\n";
echo "  Reporte:    #{$reporteId}  '{$reporteNombre}'\n";
echo "  Plantilla:  #{$plantillaId}  '{$plantillaNombre}'\n";
echo "\nLinks:\n";
echo "  Reporte:    /mi-empresa/crm/mail-masivos/reportes/{$reporteId}/editar\n";
echo "  Plantilla:  /mi-empresa/crm/mail-masivos/plantillas/{$plantillaId}/editar\n";
echo "─────────────────────────────────────────────\n";
