# Reconstrucción y Estandarización de Conexión Tango Connect (Resolución Post-Reset)

## Qué se hizo
1. **Priorización de URL Manual**: Se modificó `EmpresaConfigController` para que respete al 100% la URL de Connect (`tango_api_url`) configurada por el usuario en lugar de forzar automáticamente el nodo en base al Client Key.
2. **Normalización de Base URL en Cliente de API**: Se actualizó el constructor de `TangoApiClient` para aplicar dinámicamente y de forma obligatoria el sufijo `/Api` a la Base URL si este llegara a faltar.
3. **Conversión de Endpoints a Relativos Puros**: Se eliminaron todos los prefijos manuales `/Api/` distribuidos de forma heterogénea dentro de las abstracciones de procesos propios de `TangoApiClient` (como `getPerfilesPedidos`, `getArticulos`, `getStock`, etc). Ahora todos los módulos usan invocaciones limpias (`Get`, `GetById`, `GetApiLiveQueryData`), recayendo en la base URL normalizada.
4. **Protección de Datos Nulos (Snapshot)**: Se solucionó y clarificó que el guardado del `tango_perfil_snapshot_json` como "[]" ocurría a partir de la asfixia por excepcion HTTP 404 (provocada primero por un salto incorrecto de servidor `-017` vs `-014` y después por la inyección superpuesta `/Api/Api/Get`). Al corregir esto, el fetch carga los verdaderos perfiles al JS y a la Base de Datos.

## Por qué
Luego de una pérdida importante de archivos por un `git reset --hard` forzoso, la configuración y el hand-shake de la vista de Empresas -> Integraciones con el servidor de Axoft quedaron erráticos.
Se lograba validar conexión pero el arreglo de datos del payload de `Perfil de Pedidos` llegaba y se persistía vacío (`[]`), rompiendo la inyección a los selects de los Usuarios en PDS.

## Impacto
* **Usuarios (Global)**: Las entidades de usuarios ya pueden seleccionar normalmente su perfil de vendedor y talonario de Tango para confección de PDS.
* **TangoApiClient**: El cliente ha quedado significativamente más robusto e independiente del formato crudo en que los responsables de IT carguen los strings de conexión.

## Decisiones tomadas
* Re-direccionar toda la responsabilidad del ruting de path `/Api/` al constructor `__construct` del integrador.
* Sincronizar logs para cerrar este capítulo e impedir regresar hacia el error. Prevención contra resets destructivos en memoria.
