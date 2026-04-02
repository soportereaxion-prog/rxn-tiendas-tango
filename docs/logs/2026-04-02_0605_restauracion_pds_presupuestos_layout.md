# Restauración de Layout: PDS y Presupuestos

Se ha completado la restauración guiada por backup de las estructuras visuales de Formularios de Presupuestos y Pedidos de Servicio (PDS), que habían colapsado tras la migración masiva de componentes a la arquitectura modular.

## Causa raíz detectada
Al migrar los componentes mediante un script de reemplazo automático de cabeceras estándar de Bootstrap, se cortó desde el `<head>` hasta la apertura del `<body>` para reemplazarlo por el render natural del Framework PHP. Sin embargo, **estos dos módulos poseían cientos de líneas de código CSS inyectadas en su cabecera local mediante tags `<style>`** que definían sus grillas custom (`.crm-sheet-grid` y `.crm-budget-grid`). La supresión de estos tags destruyó el render de escritorio.

## Resolución Aplicada
Se utilizó un backup para extraer únicamente los tags `<style>` caídos. Estos fueron inyectados al principio de cada fichero valiéndose exclusivamente del nuevo estándar de envoltura `$extraHead`, respetando lo siguiente:
- `$usePageHeader = false;` (por defecto o seteado fijo). Al ser `false`, estas vistas monstruosas conservan sus cabeceras interactivas pesadas, impidiendo el dibujado duplicado del `page_header` nuevo por encima del de la app original.
- Cero duplicación de código de layout global. El `admin_layout.php` retiene el motor.

Archivos curados vía backup local:
1. `app/modules/CrmPedidosServicio/views/form.php`
2. `app/modules/CrmPresupuestos/views/form.php`
