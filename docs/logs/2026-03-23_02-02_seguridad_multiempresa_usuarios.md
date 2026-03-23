# [Seguridad Multiempresa] — [Aislamiento Módulo Usuarios Operativos]

## Contexto
Al finalizar la Fase 1 del entorno multiempresa para la administración de usuarios, la dirección planteó el requerimiento crítico de blindar frente a falsificaciones de ID o fugas de datos (Access Violations) cruzadas entre empresas (Data Leakage).

## Auditoría y Revisión
Afortunadamente, el diseño proyectado en la iteración arquitectónica previa (`usuarios_operativos_fase_1`) ya contemplaba de raíz (Repository Pattern + Service Context) el sellado por `empresa_id` en todas y cada una de las sentencias del motor. 
Se auditaron los métodos nucleares:
- `UsuarioRepository::findByIdAndEmpresaId()`: Ejerce el cerco SQL `WHERE id = ? AND empresa_id = ?`.
- `UsuarioRepository::save()`: El Update inyecta la cláusula `WHERE id = ? AND empresa_id = ?`.
- `UsuarioService::getByIdForContext()`: Funciona como un manejador de excepciones Runtime que corta de cuajo una petición de Controlador al detectar resultados nulos (inexistencia o ajenidad).

## Implementación Formal
No se requirió refactorización intrusiva porque el estándar original ya empleaba `Context::getEmpresaId()` como única fuente de verdad, prohibiendo terminantemente recoger identificadores corporativos vía `$_POST` o `$_GET`. Se aprovechó esta iteración para cristalizar la prueba de penetración en código y documentar el aval técnico de seguridad.

## Pruebas de Estrés (Pen-Testing Automático)
Se corrió el banco de pruebas CLI `test_aislamiento.php`:
1. El script identificó a un usuario activo de la Empresa B.
2. El entorno falsificó una sesión de operador habilitado pero anclado a la Empresa A.
3. Se enviaron ataques de alteración (Updates inyectando nombres y emails falsos) y lectura de parámetros directamente al motor del Middleware.
4. **Resultado**: El sistema detuvo 100% la operatoria. El `UsuarioService` disparó las denegaciones formales, y la posterior verificación cruda en Base de Datos de la víctima acusó **0 alteraciones**. Aislamiento completo.

## Riesgos y Consideraciones a Futuro
* La técnica empleada es irrompible *siempre y cuando* cada nuevo desarrollador respete inyectar `Context::getEmpresaId()` en sus queries. De omitirse este campo en futuros repositorios (ej: Facturas, Productos), habrá fugas. 
* Si se incorpora un QueryBuilder o un ORM en el futuro, convendrá aplicar un Global Scope (Tenant Scope) para que la DB inyecte la cláusula sola por defecto.
