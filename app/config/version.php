<?php

return [
    'current_version' => '1.1.3',
    'current_build' => '20260328.5',
    'history' => [
        [
            'version' => '1.1.3',
            'build' => '20260328.5',
            'released_at' => '2026-03-28',
            'title' => 'Clientes CRM independientes',
            'summary' => 'CRM suma su propia base de clientes independiente, con la misma experiencia de validacion y persistencia validada en Tiendas.',
            'items' => [
                'Se implementó Clientes CRM en entorno independiente, replicando la UX de resolucion de Tango.',
                'Pedidos de Servicio ahora asocia y sugiere clientes usando la base local propia del CRM.',
                'Aislamiento completo de relaciones comerciales de clientes entre ambos entornos operativos.',
            ],
        ],
        [
            'version' => '1.1.2',
            'build' => '20260328.4',
            'released_at' => '2026-03-28',
            'title' => 'Bitacora en dock minimizado y recuperacion mas rapida',
            'summary' => 'La bitacora deja de colapsar como tarjeta grande y pasa a un dock chico abajo a la derecha, restaurando despues su layout expandido previo.',
            'items' => [
                'El estado minimizado ahora se ve como un dock compacto y no como una ventana deformada.',
                'Al restaurar, la bitacora recupera posicion y tamaño expandido anteriores.',
                'Se suma un launcher minimo para reabrir rapido el panel desde la esquina inferior derecha.',
            ],
        ],
        [
            'version' => '1.1.1',
            'build' => '20260328.3',
            'released_at' => '2026-03-28',
            'title' => 'Bitacora minimizable y refinamiento interno de ergonomia',
            'summary' => 'La bitacora flotante ahora puede minimizarse como una ventana compacta, conservando su tamaño expandido para volver al trabajo sin reacomodarla.',
            'items' => [
                'La bitacora suma un estado minimizado prolijo, mas cercano a una ventana reducida que a un panel deformado.',
                'El layout del widget conserva el tamaño expandido previo y lo restaura al volver a abrirlo.',
                'El header minimizado tambien permite restaurar rapido la bitacora sin romper el flujo del modulo.',
            ],
        ],
        [
            'version' => '1.1.0',
            'build' => '20260328.2',
            'released_at' => '2026-03-28',
            'title' => 'Split operativo Tiendas/CRM con categorias y primer CRM real',
            'summary' => 'La base operativa se expande con categorias comerciales en Store, separacion funcional entre Tiendas y CRM, configuracion CRM propia y el primer modulo operativo del circuito CRM.',
            'items' => [
                'Tiendas incorpora categorias locales con filtro publico y asignacion por SKU persistente.',
                'Launcher y dashboards separan Tiendas y CRM segun flags reales del tenant.',
                'CRM ya cuenta con configuracion operativa propia y servicios preparados por area.',
                'Se suma Pedidos de Servicio como primer modulo operativo real del entorno CRM.',
            ],
        ],
        [
            'version' => '1.0.0',
            'build' => '20260328.1',
            'released_at' => '2026-03-28',
            'title' => 'Base operativa con versionado visible',
            'summary' => 'Se formaliza una fuente unica de version y se publica un bloque de Novedades visible para operadores y clientes.',
            'items' => [
                'Se centralizo la version activa del sistema en una unica configuracion interna.',
                'La tienda publica ahora muestra un bloque Novedades con la release vigente.',
                'Launcher y dashboards exponen la misma version para evitar desalineaciones operativas.',
            ],
        ],
    ],
];
