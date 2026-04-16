# Refactorización a Título de Pestaña Dinámico y Rebranding a RXN Suite

## Qué se hizo
- **Purga de Hardcodeo:** Se eliminó la inyección residual `$pageTitle = "RXN Tiendas IA"` en más de 60 vistas ubicadas en `app/modules/*/views/*.php`.
- **Nuevo Modelo en BD**: Se añadió la migración `database_migrations_empresa_titulo.php` incluyendo la columna `titulo_pestana VARCHAR(100) NULL` a la tabla `empresas`.
- **ABM de Empresa**: Se refactorizaron `EmpresaController.php`, `EmpresaService.php` y `EmpresaRepository.php` para soportar la creación y actualización del layout tab title a demanda.
- **Vistas UI**: Actualización de los formularios `crear.php` y `editar.php` en Empresas.
- **Admin Layout**: El `admin_layout.php` ahora evalúa la variable `$empresaObj->titulo_pestana` y aplica un string compuesto (ej. `Módulo | Acme Corp`) y usa un fallback neutro: "RXN Suite".

## Por qué
- La leyenda estática rompía la neutralidad y el concepto de la solución marca blanca "Multi-tenant".
- Además, consolidamos la nomenclatura operativa hacia "RXN Suite" a fin de homogenizar la suite (CRM y Tiendas consolidados).

## Impacto Inmediato
- Requiere correr la migración de la DB en `/admin/mantenimiento`. En caso negativo la tabla `empresas` provocará un Fatal Error por el repositorio al no mapear la columna.
- Las vistas pasaron la responsabilidad 100% al Layout, permitiendo que a futuro si el nombre del módulo pasa a ser configurable también recaiga sin problemas.
