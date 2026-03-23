# [Artículos] — [Configuración Sincronizable, CRUD Transaccional y Semántica Visual]

## Contexto
El Módulo de Artículos superó exitosamente la prueba de conexión y mapeo contra el Endpoint Connect, pero requería de una refactorización de Interfaz Usuario a Nivel CRUD. Necesitábamos transicionar de un Listado rígido de visor a un panel interactivo que admita Control de Paginación para la petición Push, Edición rápida individual, y Destrucciones Masivas sin romper la contención Tenant (Empresas). Adicionalmente los Nombres de tabla exhibidos debían igualarse a la nomenclatura dictada por Jefatura.

## Configuración y Binding (Sync Parametrizado)
* **Base de Datos Configs**: Logramos inyectar exitosamente la columna `cantidad_articulos_sync` que pre-fija `50` default.
* **Service/Repository Multiempresa**: Recogemos la data por Empresa individual asegurando aislamientos. Se inyectó seguridad para forzar Números Positivos (Fallback `50`) si el cliente emitía basura no numérica.
* **Orquestador Lleno**: `TangoService` lee su respectiva llave de la BD e insta a `TangoApiClient::getArticulos(0, limit)` inyectando la cuota. Dinámica conectada de extremo a extremo sin Hardcodes molestos.

## Reingeniería de Grilla Catalográfica (CRUD Visual y UX)
* **Semántica**: El header pasó de decir 'Nombre / Descripción' a un exacto preámbulo Connect ('Descripción / Descripción Adicional').
* **Acciones Integrales**: 
  - La tabla se transformó en un `FORM` interactivo. 
  - Un Event Listener puro VanillaJS tilda y destilda masivamente los child nodes (CheckBoxes) al pulsar el Input Macro (Select All).
  - Un Botón "Editar" transfiere al usuario a un UI `/editar?id=x` con las barreras de Backend correspondientes.
* **Borrado**: Agregamos un Prompter Crítico (`Confirm('...')`) evitando deletes accidentales en un Click.

## Protección Lógica Multi-Tenant (Backend)
* **Controller**: Interrumpimos flujos si carecen de Auth. Validamos Castings (Convirtiendo Arrays Posteos Stringosos a Enteros Puros) mediante `array_map('intval')` antes de inyectarlos al SQL mitigando polución. 
* **Repository (El Nucleo Duro)**: 
  * _Eliminación Masiva_: Implementado `DELETE ... WHERE empresa_id = N AND id IN (?,?,?)`. Sin importar que el Client-Side forjase IDs manipulados para acceder a items de otro licenciado, la condición de la Querystore interceptará cualquier borrado exótico fuera del alcance Contextual.
  * _Edición de Filas_: Construido `FindById(id, empresa)` que imposibilita de facto acceder a visores form o updates de Artículos ajenos a la licencia operada.

## Pruebas
1. Migración SQL operó en 0.2ms e insertó con éxito el Column sin romper viejas configuraciones.
2. Al testear una Sincronización se valida que si el cliente escoge 10 Articulos, limitará la extracción REST a un PageSize de 10 unidades.
3. Edición de Artículo carga la Vista Simple y actualiza atributos locales limitados. El `codigo_externo` o SKU fue visualmente degradado a Status 'Disabled', protegiendo la vinculación con Axoft Cloud intocable.
4. Borrado masivo y singular responden a flujos SQL robustos y aislados.

## Riesgos y Pendientes
* El Motor Delete actual implementa Exterminio Físico del Row. No se optó por un Borrado lógico (`baja` o `deleted_at`) asumiendo que el Motor Sincronizador de Tango (`TangoSyncService`) simplemente volvería a insertarlo limpiamente la proxima vez (Al ser un motor Push, prevalecen los datos Nube), permitiendo depuraciones locales frescas sin generar basureros infinitos para artículos descontinuados en Axoft. 

## Cierre
El Módulo finaliza como una pieza operatoria completa superando la barrera del "Prototipo".
