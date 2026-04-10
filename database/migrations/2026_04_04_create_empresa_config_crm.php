<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    $db->exec('CREATE TABLE IF NOT EXISTS empresa_config_crm LIKE empresa_config');

    $db->exec(
        'INSERT INTO empresa_config_crm (
            empresa_id,
            nombre_fantasia,
            email_contacto,
            telefono,
            tango_api_url,
            tango_connect_key,
            tango_connect_token,
            tango_connect_company_id,
            cantidad_articulos_sync,
            lista_precio_1,
            lista_precio_2,
            deposito_codigo,
            imagen_default_producto,
            usa_smtp_propio,
            smtp_host,
            smtp_port,
            smtp_user,
            smtp_pass,
            smtp_secure,
            smtp_from_email,
            smtp_from_name
        )
        SELECT
            src.empresa_id,
            src.nombre_fantasia,
            src.email_contacto,
            src.telefono,
            src.tango_api_url,
            src.tango_connect_key,
            src.tango_connect_token,
            src.tango_connect_company_id,
            src.cantidad_articulos_sync,
            src.lista_precio_1,
            src.lista_precio_2,
            src.deposito_codigo,
            src.imagen_default_producto,
            src.usa_smtp_propio,
            src.smtp_host,
            src.smtp_port,
            src.smtp_user,
            src.smtp_pass,
            src.smtp_secure,
            src.smtp_from_email,
            src.smtp_from_name
        FROM empresa_config src
        WHERE NOT EXISTS (
            SELECT 1
            FROM empresa_config_crm crm
            WHERE crm.empresa_id = src.empresa_id
        )'
    );
};
