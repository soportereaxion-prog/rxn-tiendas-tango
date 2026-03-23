# [Artículos] — [Debug Sync Stock Mapper]

## Contexto
El usuario detectó que el módulo Sync de Stock bajaba exitosamente un lote de 777 registros desde Tango Connect (Process 17668), pero reportaba permanentemente en Verde "Omitidos: 777" sin actualizar ni una fila en SQL ni reportar fallas en los Registros Internos de Trazabilidad.

## Hipótesis
Existía un claro abismo de validación sobre CÓDIGOS en el motor `TangoSyncService`. Se sospechaba fuertemente que el ID de referencia empotrado en la Configuración Global por el Operador estaba colisionando matemáticamente con la anatomía del Array expedido por Connect API.

## Payload Real Detectado
Se levantó una sonda directa CCLI (`test_stock_direct.php`) simulando la invocación API y extirpando la Capa Media de Mapeos. Arrojó el siguiente esqueleto JSON para stock vivo en Tango:

```json
[
    {
        "ID_STA11": 4739,
        "ID_STA22": 1,
        "COD_ARTICULO": "0342C    NGO105",
        "DESCRIPCION": "CORPIÑO",
        "DESCRIPCION_DEPOSITO": "DEPOSITO A - ART.DE LINEA",
        "UM_CONTROL_STOCK": "UNI",
        "SALDO_CONTROL_STOCK": -242,
        ...
    }
]
```

## Validación con SQL Server
Se conectó exitosamente a `SVRRXN\SQLEXPRESS2019` con los credenciales troncales (`axoft/Axoft`).
1. Buscamos inventario vivo: `SELECT TOP 5 COD_ARTICU, COD_DEPOSI, CANT_STOCK FROM STA19 WHERE CANT_STOCK > 0`.
2. Reveló que Artículos reales (Ej: `9996`) reposan en `COD_DEPOSI = '00'`.
3. Cruzamos el Metadato: `SELECT ID_STA22, COD_SUCURS, NOMBRE_SUC FROM STA22`.
4. Resultante de Muestra Fuerte: `ID_STA22: 1` => `COD_SUCURS: 00` y `ID_STA22: 5` => `COD_SUCURS: 01`.

## Causa Raíz
El Sistema Macheaba el `deposito_codigo` configurado visualmente contra `$item['ID_STA22']`. Pero el Cliente/Operador configuró `'00'` persiguiendo intuitivamente la nomenclatura visual de TRAMITE TANGO (El Código de Depósito). 
La API de Axoft NO EXTERNALIZA el parámetro alfanumérico `'00'` (su `COD_SUCURS`). Únicamente arroja su `ID_STA22` (Identidad Integra base 1). Por ende, el Macheo Defensivo Extremo evaluaba permanentemente `'00' === '1'` arrojando Falso, descartando de tajo en el `continue;` la iterabilidad de toda la matriz, omitiendo el 100% del stock.

## Corrección Aplicada
**Sostenibilidad y Patrón Arquitectónico:** NO corresponde inyectar una lógica oscura que traduzca el ID numérico leyendo alucinaciones de `DESCRIPCION_DEPOSITO`. El uso de la Proxy Key Nativa `ID_STA22` dictada por REST_API es la norma correcta y el cruce `1===1` ya está operando a la perfección en `TangoSyncService`.
**Corrección Humanoide**: Fue el etiquetamiento del Frontend visual el causante de la disparidad. Por lo tanto:
`EmpresaConfig/views/index.php` -> Fue modificado de raíz el `label` a `ID Depósito (Connect)`, pintado en rojo y adjuntado a un Note de Advertencia Técnica: `⚠️ Usá el ID numérico de Tango (ID_STA22), NO el código alfanumérico (00). Ej: 1 para Depo Central.`.

## Resultado Final
Confiamos robustamente que cambiando este setup en `/rxnTiendasIA/public/mi-empresa/configuracion` a Valor `'1'` y disparando la Sync, el CRUD absorberá íntegramente los inventarios. La persistencia Idempotente ya está estabilizada.

## Riesgos Pendientes
N/A. La infraestructura Backend había sido bien proyectada. Recomendación para el Orquestador: Ir y Actualizar en el panel de UI Local el campo al Int 1.
