# [Bitácora] - Endpoint de Sincronización API e inyección en Hub Notas

## Qué se hizo
- Se implementó el método `syncExport()` en `ModuleNotesController` para poder consumir la información de la bitácora (JSON crudo y referencias de imágenes relativas) desde un sistema externo o local.
- Se agregó la ruta pública `GET /api/admin/bitacora/sync` en `routes.php` excenta del middleware de sesión humana.
- Se protegió el nuevo endpoint requiriendo un `Authorization: Bearer <TOKEN>` válido donde el token se configura centralizadamente a nivel servidor mediante la variable de entorno `SYNC_API_KEY`.
- Se inyectó visualmente el componente `$moduleNotesKey = 'crm_notas'` en las tres vistas principales del módulo CRM Notas (`index.php`, `form.php`, `show.php`), ya que previamente se había omitido.

## Por qué
- Existía un requerimiento de consolidar u observar estas anotaciones operativas "desde abajo" (un posible sistema local o base de contingencia) sin ingresar obligatoriamente al sistema web de forma humana y sin requerir descargar un ZIP manualmente cada vez que se requiere la información.
- El módulo de "CRM Notas", pese a ser el gestor de texto de clientes, no contaba con la bitácora para observaciones relativas al propio funcionamiento del módulo CRM (ironías de que el módulo Notas no tuviera dónde anotar sobre Notas).

## Impacto
- Al no definir arquitectura bidireccional, no corremos ningún riesgo de corrupción ni colisión de operaciones vivas. 
- Mantenemos el código limpio resguardando la información bajo un secreto en el `.env`, preservando los esquemas `REST` al entregar las URLs dinámicas con las rutas preexistentes originadas.
- La Bitácora ya quedó transversal al 100% de los tableros operativos del backend que la requerían. 

## Decisiones tomadas
- Se devuelve el JSON físico directo en vez de parsearlo al no haber necesidad de lógica de mutación. Si la llave viaja mal u omite el `.env`, se detiene por seguridad retornando HTTP `401 Unauthorized`.
