# [Sincronización Piloto] — [Integración de Artículos Tango Connect]

## Contexto
Siguiendo las órdenes de Jefatura, se aterrizó la abstracción de red creando la primera "Sincronización Real" de la plataforma, usando como blanco el endpoint de Artículos de Tango Connect, conservando el espíritu multiempresa y el sigilo corporativo.

## Estructura de Módulos Involucrados
1. **App\Modules\Articulos**: Se inauguró el dominio. El `ArticuloRepository` implementa un agresivo `INSERT ... ON DUPLICATE KEY UPDATE` basándose en la constraint Idempotente `UNIQUE(empresa_id, codigo_externo)`. Nunca habrán artículos duplicados para una misma empresa bajo un mismo Connect SKU.
2. **App\Modules\EmpresaConfig**: Se mutó la Capa Visual dotando a la vista `index.php` de inputs para credenciales Connect. El Token viaja y se deposita protegido visualmente (Type Password con Script Reveal Eye). 
3. **App\Modules\Tango**: Se inyectó vida al `TangoSyncService`. Dicho engranaje inicia un logueo en base de datos (`tango_sync_logs`), extrae la configuración privada de la empresa actual, arma el Request, e invoca al `ArticuloMapper` para decodificar la data Connect al Modelo base, filtrando SKUs huérfanos. Cerrando luego con métricas duras (Omitidos, Modificados, Insertados).

## Estrategia de Idempotencia y Mapeo
* Dado que el Payload real de Connect fue simulado (no proveyeron URL base en esta iteración, se acató la prudencia de dejarlo dinámico en la UI), el `ArticuloMapper` fue erigido con fallbacks polimórficos (`$sku = Code ?? SKUCode ?? codigo`). 
* El motor MySQL administra la carga UPSERT impidiendo la corrupción o clonado por multi-corrida consecutiva.

## Validaciones Seguras y Realizadas
* Se elaboró un script volátil (`test_seed_tango.php`) inyectando directamente a memoria de DB los valores clasificados: `TOKEN: [TOKEN_OCULTO] / KEY: [KEY_OCULTA]`. 
* La prueba simulativa se rebotó exitosamente en cURL atrapando Exception `ConnectionException: Could not resolve host`, salvando la integridad de PHP y **grabando magistralmente la traza en la tabla Logs**.
* El Script fuente de prueba fue obliterado para asegurar secreto sumarial Git.

## Riesgos y Próximos Pasos
* **Ruta Real Connect**: Actualmente se documentó como URL en la vista algo genérico (`https://nexosync.tangonexo.com`). Deberá cargarse en la UI la verdadera URL proporcionada por Axoft para que la maquinaria logre el Handshake con su Token real.
* Podemos transpolar esta misma topología a "Pedidos" y "Stock".
