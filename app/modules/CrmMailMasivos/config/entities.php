<?php

declare(strict_types=1);

/**
 * Metamodelo de entidades para el módulo CrmMailMasivos.
 *
 * Este archivo declara qué tablas, campos y relaciones son accesibles desde el
 * "diseñador Links" (estilo Crystal Reports) que usa el usuario para armar los
 * reportes de destinatarios sin escribir SQL.
 *
 * SEGURIDAD: Sólo las entidades y campos declarados acá son alcanzables por el
 * diseñador. El query builder del backend valida contra este metamodelo antes
 * de ejecutar cualquier cosa. Esto previene SQL injection y acceso indebido a
 * tablas sensibles (ej. passwords, tokens, configuración interna).
 *
 * CONVENCIONES:
 * - `label`          → nombre legible en la UI
 * - `table`          → tabla real en la DB
 * - `primary_key`    → clave primaria (siempre `id` por convención del proyecto)
 * - `empresa_scope`  → si la tabla filtra por empresa_id (casi todas sí)
 * - `mail_field`     → qué campo representa el destinatario de mail (si aplica)
 * - `soft_delete`    → columna de soft delete (default: 'deleted_at')
 * - `fields`         → campos expuestos como variables / filtros / columnas
 *                      type: string | int | decimal | date | datetime | bool | email
 *                      filterable: si puede usarse en filtros visuales
 *                      is_mail_target: marca el campo que contiene el mail destino
 * - `relations`      → tablas relacionadas que el usuario puede "prender"
 *                      type: hasMany | belongsTo
 *                      foreign_key: columna de la tabla ajena que apunta acá
 *                      local_key: columna local (default: 'id')
 *
 * Al agregar una entidad nueva, declarar:
 *   - Solo los campos útiles para reportes (no incluir tokens, passwords, logs).
 *   - Relaciones explícitas con foreign_key.
 */

return [

    'CrmClientes' => [
        'label' => 'Clientes',
        'table' => 'crm_clientes',
        'primary_key' => 'id',
        'empresa_scope' => true,
        'soft_delete' => 'deleted_at',
        'mail_field' => 'email',
        'fields' => [
            'id' => ['label' => 'ID Cliente', 'type' => 'int', 'filterable' => true],
            'codigo_tango' => ['label' => 'Código Tango', 'type' => 'string', 'filterable' => true],
            'nombre' => ['label' => 'Nombre', 'type' => 'string', 'filterable' => true],
            'apellido' => ['label' => 'Apellido', 'type' => 'string', 'filterable' => true],
            'razon_social' => ['label' => 'Razón Social', 'type' => 'string', 'filterable' => true],
            'email' => ['label' => 'Email', 'type' => 'email', 'filterable' => true, 'is_mail_target' => true],
            'telefono' => ['label' => 'Teléfono', 'type' => 'string', 'filterable' => true],
            'documento' => ['label' => 'Documento', 'type' => 'string', 'filterable' => true],
            'direccion' => ['label' => 'Dirección', 'type' => 'string', 'filterable' => false],
            'localidad' => ['label' => 'Localidad', 'type' => 'string', 'filterable' => true],
            'provincia' => ['label' => 'Provincia', 'type' => 'string', 'filterable' => true],
            'codigo_postal' => ['label' => 'Código Postal', 'type' => 'string', 'filterable' => true],
            'observaciones' => ['label' => 'Observaciones', 'type' => 'string', 'filterable' => false],
            'activo' => ['label' => 'Activo', 'type' => 'bool', 'filterable' => true],
            'created_at' => ['label' => 'Fecha de alta', 'type' => 'datetime', 'filterable' => true],
        ],
        'relations' => [
            'CrmPresupuestos' => [
                'label' => 'Presupuestos del cliente',
                'type' => 'hasMany',
                'target_entity' => 'CrmPresupuestos',
                'foreign_key' => 'cliente_id',
                'local_key' => 'id',
            ],
            'CrmPedidosServicio' => [
                'label' => 'Pedidos de Servicio (PDS) del cliente',
                'type' => 'hasMany',
                'target_entity' => 'CrmPedidosServicio',
                'foreign_key' => 'cliente_id',
                'local_key' => 'id',
            ],
        ],
    ],

    'CrmPresupuestos' => [
        'label' => 'Presupuestos',
        'table' => 'crm_presupuestos',
        'primary_key' => 'id',
        'empresa_scope' => true,
        'soft_delete' => 'deleted_at',
        'fields' => [
            'id' => ['label' => 'ID Presupuesto', 'type' => 'int', 'filterable' => true],
            'numero' => ['label' => 'Número', 'type' => 'int', 'filterable' => true],
            'fecha' => ['label' => 'Fecha', 'type' => 'datetime', 'filterable' => true],
            'cliente_id' => ['label' => 'ID Cliente', 'type' => 'int', 'filterable' => true],
            'cliente_nombre_snapshot' => ['label' => 'Cliente (snapshot)', 'type' => 'string', 'filterable' => true],
            'total' => ['label' => 'Total', 'type' => 'decimal', 'filterable' => true],
            'subtotal' => ['label' => 'Subtotal', 'type' => 'decimal', 'filterable' => true],
            'descuento_total' => ['label' => 'Descuento Total', 'type' => 'decimal', 'filterable' => true],
            'impuestos_total' => ['label' => 'Impuestos Total', 'type' => 'decimal', 'filterable' => true],
            'estado' => ['label' => 'Estado', 'type' => 'string', 'filterable' => true],
            'vendedor_nombre_snapshot' => ['label' => 'Vendedor', 'type' => 'string', 'filterable' => true],
            'lista_nombre_snapshot' => ['label' => 'Lista de precios', 'type' => 'string', 'filterable' => true],
            'condicion_nombre_snapshot' => ['label' => 'Condición de venta', 'type' => 'string', 'filterable' => true],
            'usuario_nombre' => ['label' => 'Usuario que cargó', 'type' => 'string', 'filterable' => true],
            'nro_comprobante_tango' => ['label' => 'Nro Comprobante Tango', 'type' => 'string', 'filterable' => true],
            'created_at' => ['label' => 'Creado', 'type' => 'datetime', 'filterable' => true],
        ],
        'relations' => [
            'CrmClientes' => [
                'label' => 'Cliente del presupuesto',
                'type' => 'belongsTo',
                'target_entity' => 'CrmClientes',
                'foreign_key' => 'cliente_id',
                'local_key' => 'id',
            ],
            'CrmPresupuestoItems' => [
                'label' => 'Ítems del presupuesto',
                'type' => 'hasMany',
                'target_entity' => 'CrmPresupuestoItems',
                'foreign_key' => 'presupuesto_id',
                'local_key' => 'id',
            ],
        ],
    ],

    'CrmPresupuestoItems' => [
        'label' => 'Ítems de Presupuestos',
        'table' => 'crm_presupuesto_items',
        'primary_key' => 'id',
        'empresa_scope' => true,
        'soft_delete' => null,
        'fields' => [
            'id' => ['label' => 'ID Ítem', 'type' => 'int', 'filterable' => false],
            'presupuesto_id' => ['label' => 'ID Presupuesto', 'type' => 'int', 'filterable' => true],
            'orden' => ['label' => 'Orden', 'type' => 'int', 'filterable' => false],
            'articulo_codigo' => ['label' => 'Código Artículo', 'type' => 'string', 'filterable' => true],
            'articulo_descripcion_snapshot' => ['label' => 'Artículo', 'type' => 'string', 'filterable' => true],
            'cantidad' => ['label' => 'Cantidad', 'type' => 'decimal', 'filterable' => true],
            'precio_unitario' => ['label' => 'Precio Unitario', 'type' => 'decimal', 'filterable' => true],
            'bonificacion_porcentaje' => ['label' => 'Bonif. %', 'type' => 'decimal', 'filterable' => true],
            'importe_neto' => ['label' => 'Importe Neto', 'type' => 'decimal', 'filterable' => true],
            'lista_codigo_aplicada' => ['label' => 'Lista Aplicada', 'type' => 'string', 'filterable' => true],
        ],
        'relations' => [
            'CrmPresupuestos' => [
                'label' => 'Presupuesto del ítem',
                'type' => 'belongsTo',
                'target_entity' => 'CrmPresupuestos',
                'foreign_key' => 'presupuesto_id',
                'local_key' => 'id',
            ],
        ],
    ],

    'CrmPedidosServicio' => [
        'label' => 'Pedidos de Servicio (PDS)',
        'table' => 'crm_pedidos_servicio',
        'primary_key' => 'id',
        'empresa_scope' => true,
        'soft_delete' => 'deleted_at',
        'mail_field' => 'cliente_email',
        'fields' => [
            'id' => ['label' => 'ID PDS', 'type' => 'int', 'filterable' => true],
            'numero' => ['label' => 'Número', 'type' => 'int', 'filterable' => true],
            'fecha_inicio' => ['label' => 'Fecha Inicio', 'type' => 'datetime', 'filterable' => true],
            'fecha_finalizado' => ['label' => 'Fecha Finalizado', 'type' => 'datetime', 'filterable' => true],
            'cliente_id' => ['label' => 'ID Cliente', 'type' => 'int', 'filterable' => true],
            'cliente_nombre' => ['label' => 'Cliente', 'type' => 'string', 'filterable' => true],
            'cliente_email' => ['label' => 'Email Cliente', 'type' => 'email', 'filterable' => true, 'is_mail_target' => true],
            'cliente_documento' => ['label' => 'Documento Cliente', 'type' => 'string', 'filterable' => true],
            'solicito' => ['label' => 'Solicitó', 'type' => 'string', 'filterable' => true],
            'nro_pedido' => ['label' => 'Nro Pedido', 'type' => 'string', 'filterable' => true],
            'estado_tango' => ['label' => 'Estado', 'type' => 'string', 'filterable' => true],
            'articulo_codigo' => ['label' => 'Código Artículo', 'type' => 'string', 'filterable' => true],
            'articulo_nombre' => ['label' => 'Artículo', 'type' => 'string', 'filterable' => true],
            'articulo_precio_unitario' => ['label' => 'Precio Artículo', 'type' => 'decimal', 'filterable' => true],
            'clasificacion_codigo' => ['label' => 'Clasificación', 'type' => 'string', 'filterable' => true],
            'clasificacion_descripcion' => ['label' => 'Descripción Clasif.', 'type' => 'string', 'filterable' => true],
            'diagnostico' => ['label' => 'Diagnóstico', 'type' => 'string', 'filterable' => false],
            'tiempo_decimal' => ['label' => 'Horas (decimal)', 'type' => 'decimal', 'filterable' => true],
            'usuario_nombre' => ['label' => 'Usuario que cargó', 'type' => 'string', 'filterable' => true],
            'created_at' => ['label' => 'Creado', 'type' => 'datetime', 'filterable' => true],
        ],
        'relations' => [
            'CrmClientes' => [
                'label' => 'Cliente del PDS',
                'type' => 'belongsTo',
                'target_entity' => 'CrmClientes',
                'foreign_key' => 'cliente_id',
                'local_key' => 'id',
            ],
        ],
    ],

];
