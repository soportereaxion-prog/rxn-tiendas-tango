# [Launcher/CRM/Accesos] - Split de entornos Tiendas y CRM

## Que se hizo
- Se dividio el launcher principal para mostrar `Entorno Operativo de Tiendas` y `Entorno Operativo de CRM` segun los flags reales del tenant.
- Se agrego un dashboard propio para CRM con base inicial de `Configuracion` y `Articulos CRM`.
- Se reforzaron guardas de acceso para que las rutas internas y la tienda publica dependan de `activa`, `modulo_tiendas` y `modulo_crm`.
- Se reutilizo el modulo de articulos con una variante CRM persistida en tablas separadas (`crm_articulos`, `crm_articulo_imagenes`, `crm_articulo_categoria_map`).
- Se adapto la consola de configuracion para renderizar contexto Tiendas o CRM con la misma estetica y rutas propias.

## Por que
- El flag CRM ya existia en empresas, pero todavia no gobernaba navegacion ni acceso real.
- Hacia falta separar visual y tecnicamente los entornos para que CRM pueda crecer sin contaminar el circuito de Tiendas.
- Se eligio una primera fase conservadora: CRM corto, visible y con persistencia aislada, sin sobrearquitectura temprana.

## Impacto
- El login ahora vuelve al launcher principal para elegir entorno segun habilitaciones.
- Un tenant sin `Tiendas` activo deja de poder entrar al dashboard de Tiendas o publicar su store.
- Un tenant con `CRM` activo ya puede entrar a un entorno CRM propio y operar sobre un catalogo separado del de Tiendas.
- La configuracion del tenant queda disponible desde ambos entornos con encabezados y rutas consistentes.

## Decisiones tomadas
- Se centralizo la lectura de acceso modular en `EmpresaAccessService` para evitar checks dispersos.
- Se mantuvo `Configuracion` como consola compartida del tenant en esta etapa, diferenciando solo el contexto visual y la ruta.
- Se separaron datos CRM por tablas nuevas creadas bajo estrategia `CREATE TABLE ... LIKE ...` para reutilizar estructura existente sin duplicar SQL manual.
- Se dejo `CRM` inicial con solo dos tarjetas para respetar la consigna de arranque liviano.
