# Ajuste Bisturí — Debug Controlado de Selectores Tango

## Motivo del Debug temporal
A pesar de refactorizar la extracción paginada (Fix de páginas, Fix de Headers, Fix de Endpoints), la Jefatura reporta que el FrontEnd sigue arrojando advertencia de "No mathea API / Cód obsoleto".
Debido a que el Sandbox local de Inteligencia Artificial opera sobre una base MOCK donde la API de Axoft no devuelve entidades maestras reales (porque la empresa=1 no existe en la nube), se ha inyectado un Dumper silente pasivo para diagnosticar *in-situ* el payload que Jefatura recibe en su entorno productivo.

## Alcance del Parche Evaluativo
1. **`TangoApiClient.php`:**
   Se abrieron temporalmente dos slots `public $debugLastRawDepositos` y `public $debugLastRawListas` para atrapar *crudo* el `json_decode` exacto que dicta la conectividad nativa ante `process=2941` y `process=984` en la primera página de la paginación.
2. **`EmpresaConfigController.php::getConnectTangoMetadata()`:**
   En el preciso instante en que la UI hace click en *"Validar Conexión"* y el Backend traduce el JSON de Axoft a Arrays `['id'=>xx, 'desc'=>yy]`, se inserta una intercepción transaccional temporal.
   La intercepción crea o actualiza cíclicamente el archivo local plano: `logs/debug_selectores_connect.json`.

## Qué contiene el Debug Dump (`logs/debug_selectores_connect.json`)
La Jefatura puede abrir este archivo JSON y observará:
- `FECHA`: Timestamp de la intercepción empírica.
- `LISTAS_API_RAW` | `DEPOSITOS_API_RAW`: El json 100% puro emanado por Axoft API en esa empresa, demostrando innegablemente qué nombres de campos (Keys) envían y con qué exacto tipo de dato numérico o caracteres espaciados arriban los Value.
- `VALOR_DB`: El int o varchar duro existente en MySQL Tienda. 
- `X_NORMALIZADAS`: La lista sanitizada bajo la actual heurística PHP. Muestra visiblemente una bandera `MATCH_L1: SI/NO` si la coincidencia interna que alimenta JavaScript fue teórica o fallida.

## Mecanismo de uso y retiro
1. Se despliega a `main`.
2. La Jefatura o testers simulan una edición en el Panel Tienda -> *"Validar Conexión"*.
3. Se abre el texto plano `logs/debug_selectores_connect.json`.
4. El diagnóstico será evidente a los ojos del operador técnico (desalineación de llaves, desalineación de IDs subyacentes, etc.).
5. Se notificará la solución definitiva y se revertirán las líneas pasivas.
