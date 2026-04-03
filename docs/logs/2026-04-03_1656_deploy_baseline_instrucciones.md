# Manual Operativo: Despliegue de Línea Base de Migraciones (Primer Deploy)

**Propósito:** Proporcionar las directrices de seguridad para cargar de forma inocua el flamante módulo de Actualizaciones y Resguardos a un entorno de Producción en funcionamiento. 

### 1. ¿Qué se despliega? (Paquete Entregable)
Se generó y comprimió el archivo `build_release.zip` en la raíz de su proyecto local, conteniendo la última versión estable (incluyendo el Baseline y Mantenimiento de la iteración en curso).
**Estructura empaquetada:**
- `/app` (Todo el núcleo, controladores y lógica de Baseline).
- `/public` (Punto único de acceso UI, assets y js/css).
- `/vendor` (Dependencias Composer pre-compiladas).
- `/storage` (Carpetas base para la generación de log/backups).
- `.htaccess`, `.env.example`, `composer.json` y `composer.lock`.

### 2. Archivos y Carpetas Sensibles (¡PRESERVAR!)
El comando principal es "pisar" o sobreescribir el servidor con los archivos de `build_release.zip`, **con la excepción sagrada de:**
1. `.env` (Su archivo de configuración privado).
2. `public/uploads/` (Imágenes, logos y archivos dinámicos B2C/CRM).
3. `storage/` (Archivos pre-existentes como logs o temporales).

### 3. Checklist de Despliegue (El Primer Engage)
Siga rigurosamente estos pasos para mitigar riesgos en la base actual:

✅ **Paso 1: Respaldo Preventivo Físico**
Exporte su BD productiva actual desde PHPMyAdmin u otra utilidad por seguridad. (Opcional: un rápido tar/zip del Document Root remoto).

✅ **Paso 2: Subida de la Build (Sobreescritura Ciega)**
Extraiga y reemplace con FTP o rsync el contenido de `build_release.zip` sobre el *Document Root* (`public/`) de Plesk o cPanel. Respetar preservaciones (Punto 2).

✅ **Paso 3: Acceso Cauteloso al Sistema**
Inicie sesión en Producción con su usuario maestro global (Aquel que tiene el switch/bandera de `RXN Admin` en la Base de Datos). Vaya a la **Central RXN Backoffice**.

✅ **Paso 4: Inicialización del Baseline (Crítico)**
Notará su nueva tarjeta "Sistema y Mantenimiento".
Ingrese allí. La interfaz lo recibirá con una alerta de advertencia (*"Se detectaron archivos de migración no aplicados"*). **NO HAGA CLIC EN EJECUTAR PENDIENTES.**
Baje hasta el final donde hallará la tarjeta de contención amarilla. Presione **Establecer Línea Base (Ignorar Históricas)** y apruebe el diálogo de confirmación.

✅ **Paso 5: Validación Posterior**
El sistema limpiará la alerta y mostrará una medalla verde de **"Base de datos actualizada"**. Pruebe crear un Backup MySQL de prueba usando la botonera contigua para corroborar robustez de conexión operativa del módulo.

*Misión Completada. De aquí en adelante, cada desarrollador entregará scripts incrementales e idempotentes.*
