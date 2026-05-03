<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Suma logo de Reaxion al header de la plantilla "Novedades RXN — Newsletter".
 *
 * Idempotente: si el body ya contiene `{{Suite.logo_url}}`, no toca nada.
 * El placeholder se resuelve en runtime por SuitePlaceholderResolver al
 * armar el body_snapshot del envío (multi-tenant: cada suite usa su propio
 * dominio para la URL absoluta del logo). El asset físico vive en
 * public/img/email/LogoRXN-SinFondo.png — viaja con OTA porque ReleaseBuilder
 * empaqueta toda public/.
 *
 * Sólo afecta a templates cuyo nombre matchee 'Novedades RXN — Newsletter'
 * para todas las empresas — preserva ediciones manuales del usuario en otros
 * templates.
 */
return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("
        SELECT id, empresa_id, body_html
        FROM crm_mail_templates
        WHERE nombre = 'Novedades RXN — Newsletter'
          AND deleted_at IS NULL
    ");
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    if (empty($rows)) {
        return;
    }

    // Bloque a insertar — logo centrado arriba del título, en la misma celda
    // del header con su gradiente. max-width 240px para que no rompa Outlook.
    $logoBlock = <<<'HTML'
                        <img src="{{Suite.logo_url}}"
                             alt="Reaxion Soluciones"
                             width="240"
                             style="display: inline-block; max-width: 240px; height: auto; margin: 0 auto 18px; border: 0; outline: none; text-decoration: none;">

HTML;

    // Anchor: el `<h1>` del título. Insertamos el logo justo antes.
    $anchor = '<h1 style="margin: 0; color: #ffffff;';

    $update = $db->prepare("UPDATE crm_mail_templates SET body_html = :body, updated_at = NOW() WHERE id = :id");

    foreach ($rows as $row) {
        $body = (string) $row['body_html'];

        // Idempotencia
        if (strpos($body, '{{Suite.logo_url}}') !== false) {
            continue;
        }
        // Anchor faltante (template editado a mano) → no tocamos.
        if (strpos($body, $anchor) === false) {
            continue;
        }

        $newBody = str_replace($anchor, $logoBlock . $anchor, $body);
        $update->execute([':body' => $newBody, ':id' => (int) $row['id']]);
    }
};
