<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de plantilla de mail "Novedades RXN" — release 1.43.2.
 *
 * Plantilla email-safe (tablas + inline styles) con:
 *   - Header con título centrado.
 *   - Saludo personalizado al cliente.
 *   - Mensaje introductorio.
 *   - Placeholder {{Bloque.html}} donde el BlockRenderer inyecta las cards
 *     de las novedades de customer_notes.
 *   - CTA al final invitando a entrar al sistema.
 *   - Footer con datos de contacto + leyenda legal mínima.
 *
 * Idempotente por (nombre + empresa_id). Se inserta para todas las empresas
 * activas — cada tenant tiene su copia, puede editarla independiente.
 */
return function (): void {
    $db = Database::getConnection();

    // Empresas activas (no soft-deleted).
    $empresas = $db->query("
        SELECT id FROM empresas
        WHERE COALESCE(deleted_at, '0000-00-00') = '0000-00-00' OR deleted_at IS NULL
        ORDER BY id ASC
    ")->fetchAll(\PDO::FETCH_COLUMN);

    if (empty($empresas)) {
        return;
    }

    $nombre = 'Novedades RXN — Newsletter';
    $descripcion = 'Plantilla email-safe estilo newsletter para enviar novedades de la suite a clientes. Header + saludo personalizado + bloque dinámico de novedades (cards desde customer_notes) + CTA + footer. Editá libremente desde el editor de plantillas — esta plantilla queda como base reutilizable.';
    $asunto = '🚀 Novedades de Reaxion Soluciones — {{CrmClientes.razon_social}}';

    $bodyHtml = <<<'HTML'
<!--[if mso]>
<style type="text/css">
table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
</style>
<![endif]-->

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f4f6f8; padding: 24px 12px;">
    <tr>
        <td align="center">

            <!-- Email container — 600px máx, centrado -->
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(15, 23, 42, 0.08); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;">

                <!-- HEADER -->
                <tr>
                    <td style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 32px 28px; text-align: center;">
                        <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;">
                            ✨ Hay novedades en tu suite
                        </h1>
                        <p style="margin: 10px 0 0; color: #cbd5e1; font-size: 14px; line-height: 1.5;">
                            Las últimas mejoras pensadas para que trabajes mejor.
                        </p>
                    </td>
                </tr>

                <!-- BODY -->
                <tr>
                    <td style="padding: 32px 28px 8px; color: #1e293b; font-size: 15px; line-height: 1.65;">

                        <p style="margin: 0 0 20px; font-size: 16px;">
                            ¡Hola <strong>{{CrmClientes.razon_social}}</strong>! 👋
                        </p>

                        <p style="margin: 0 0 20px;">
                            Te queremos contar las novedades que sumamos al sistema en las últimas iteraciones. Son mejoras pensadas para que tu día a día con la herramienta sea más simple y vos puedas enfocar tu energía en lo importante: <strong>tu negocio</strong>.
                        </p>

                        <p style="margin: 0 0 12px; font-size: 14px; color: #64748b;">
                            Acá va el detalle:
                        </p>

                    </td>
                </tr>

                <!-- BLOQUE DINÁMICO DE NOVEDADES (lo inyecta BlockRenderer) -->
                <tr>
                    <td style="padding: 0 16px;">
                        {{Bloque.html}}
                    </td>
                </tr>

                <!-- CTA -->
                <tr>
                    <td style="padding: 24px 28px 32px; text-align: center;">
                        <p style="margin: 0 0 18px; color: #475569; font-size: 14px; line-height: 1.6;">
                            ¿Querés ver todo en acción? Entrá al sistema y aprovechalas:
                        </p>
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto;">
                            <tr>
                                <td align="center" style="border-radius: 8px; background-color: #2563eb;">
                                    <a href="https://suite.reaxionsoluciones.com.ar/login"
                                       style="display: inline-block; padding: 13px 30px; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 15px; border-radius: 8px; letter-spacing: 0.2px;">
                                        Abrir Reaxion Suite →
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- DIVIDER SUTIL -->
                <tr>
                    <td style="padding: 0 28px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr><td style="border-top: 1px solid #e2e8f0; line-height: 1px; font-size: 1px;">&nbsp;</td></tr>
                        </table>
                    </td>
                </tr>

                <!-- FOOTER -->
                <tr>
                    <td style="padding: 24px 28px 28px; text-align: center; color: #94a3b8; font-size: 12px; line-height: 1.6;">
                        <p style="margin: 0 0 8px; color: #475569; font-weight: 600;">
                            Re@xion Soluciones
                        </p>
                        <p style="margin: 0 0 4px;">
                            <a href="https://reaxion.com.ar" style="color: #2563eb; text-decoration: none;">reaxion.com.ar</a>
                            &nbsp;·&nbsp;
                            <a href="mailto:soporte@reaxion.com.ar" style="color: #2563eb; text-decoration: none;">soporte@reaxion.com.ar</a>
                        </p>
                        <p style="margin: 0 0 12px;">
                            +54 9 11 5263 2464 &nbsp;·&nbsp; +54 9 2656 40 1333
                        </p>
                        <p style="margin: 12px 0 0; font-size: 11px; color: #94a3b8;">
                            Recibís este mail porque sos cliente de Reaxion Soluciones. Si querés dejar de recibirlos, escribinos a soporte y te damos de baja.
                        </p>
                    </td>
                </tr>

            </table>

            <!-- Espacio inferior para que el container respire en mobile -->
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr><td style="height: 16px; line-height: 1px; font-size: 1px;">&nbsp;</td></tr>
            </table>

        </td>
    </tr>
</table>
HTML;

    $availableVarsJson = json_encode([
        '{{CrmClientes.razon_social}}' => 'Razón social del cliente destinatario',
        '{{CrmClientes.email}}' => 'Email del cliente',
        '{{CrmClientes.documento}}' => 'CUIT/DNI del cliente',
        '{{Bloque.html}}' => 'Bloque dinámico con las novedades del reporte de contenido (BlockRenderer)',
    ], JSON_UNESCAPED_UNICODE);

    // Idempotencia: chequeo por nombre + empresa.
    $chk = $db->prepare("SELECT id FROM crm_mail_templates
        WHERE empresa_id = :e AND nombre = :n AND deleted_at IS NULL LIMIT 1");
    $ins = $db->prepare("INSERT INTO crm_mail_templates
        (empresa_id, nombre, descripcion, asunto, body_html, available_vars_json, created_at, updated_at)
        VALUES (:e, :n, :d, :s, :b, :v, NOW(), NOW())");

    foreach ($empresas as $empresaId) {
        $chk->execute([':e' => $empresaId, ':n' => $nombre]);
        if ($chk->fetchColumn() !== false) {
            continue; // ya existe en esta empresa
        }
        $ins->execute([
            ':e' => $empresaId,
            ':n' => $nombre,
            ':d' => $descripcion,
            ':s' => $asunto,
            ':b' => $bodyHtml,
            ':v' => $availableVarsJson,
        ]);
    }
};
