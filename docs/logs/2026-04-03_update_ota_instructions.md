# Manual Operativo: Actualizaciones Automáticas "Over The Air" (OTA)

Hemos incorporado funcionalidad para que los RXN Admin puedan cargar empaquetados de nuevas mejoras directamente desde la pantalla de Mantenimiento, resolviendo la extracción sin depender de un administrador de sistemas que conecte al FTP en cada release.

---

## 🛠️ Para el Desarrollador: Cómo generar una Actualización (Entorno Local)

Cuando requiera compilar todos sus avances y generar un "Release Zip" para subirlo a Producción, **no arme el zip comprimiendo la carpeta manualmente**. Esto sube basura accidental (como la carpeta entera de imágenes `.uploads` perdiendo horas y megas, o tapando los archivos de Producción).

1. Abra su consola / terminal local apuntando a la raíz del repositorio (`rxn_suite/`).
2. Digite y ejecute el comando:
   `php tools/build_update_zip.php`
3. AG procesará sus archivos, filtrará estrictamente con los filtros de seguridad, y dejará su paquete limpio dentro de la nueva carpeta local `build_ota/rxn_update_xxx.zip`.
4. Envíe o pase ese archivo exacto de `build_ota/` al administrador que ejecutará en Producción.

---

## 🚀 Para el RXN Admin: Cómo aplicar la Actualización (Producción)

Usted acaba de recibir el archivo `.zip` de un desarrollador (o de AG) conteniendo nuevas funciones, soluciones de bugs o migraciones.

**Paso a paso:**

1. **Ingreso:** Navegue en su BackOffice de Producción hacia la pestaña de **"Mantenimiento y Actualizaciones"**.
2. **Carga:** En la tarjeta superior "Actualización del Sistema (OTA)", pulse sobre *Examinar/Seleccionar archivo* y elija su archivo `.zip`.
3. **Instalación:** Haga clic sobre el botón azul **Instalar**. 
4. **Respaldos Previos Automáticos:** Aguarde pacientemente mientras cargue el sistema. Durante este lapso, el módulo pausará su instalación y forzará dos cosas:
   - Exportará la Base de Datos tal cual está, por seguridad.
   - Creará una foto (Backup ZIP) del código fuente actual.
5. Si superó las barreras de protección de los directorios vitales, su pantalla recargará con un aviso verde de Éxito.
6. **Ejecutar Migraciones:** Observe debajo la tarjeta naranja de "Actualizaciones de BD (Migraciones)". Si la actualización inyectó nuevas mejoras de datos, notará que hay **Migraciones Pendientes**. Presione "Ejecutar Pendientes" para terminar el trabajo.
7. **Validar Sistema:** Compruebe navegando su Backoffice que la nueva funcionalidad esté viva.

--- 

### Exclusiones y Protecciones (No debe temer)
El motor de despliegue jamás sobreescribirá:
- `.env` (Credenciales y variables únicas del Servidor)
- `storage/` (Archivos Json cacheados que varían cada minuto o listado de backups previos)
- `public/uploads/` (Memoria física de lo que los inquilinos suben, como logos o imágenes)
- `.htaccess` (Rutas del webserver Apache)

> Si su paquete demora demasiado o arroja error por *Upload Size*, deberá pedir a su hosting provider elevar el límite técnico de PHP en `upload_max_filesize` a al menos 100MB contemplando las carpetas Vendor.
