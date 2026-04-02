# Restauración Controlada: Módulo Canvas de Impresión

**Fecha:** 2026-04-02
**Diagnóstico:** 
Durante la refactorización sistemática de la UI y la introducción del dmin_layout.php, los archivos que gestionan los Canvas (PrintForms) sufrieron la amputación accidental de sus bloques <style> y estructura propietaria en la cabecera HTML (en particular en editor.php). Esto rompió completamente la grilla de edición del canvas y el renderizado en pantalla de las posiciones absolutas. Además, en index.php se habían borrado párrafos descriptivos valiosos de las tarjetas iterables de los canvas disponibles (Presupuestos, PDS, etc.).

**Archivos afectados y restaurados:**
- pp/modules/PrintForms/views/editor.php
- pp/modules/PrintForms/views/index.php
*(Nota: document_render.php no fue afectado por el bug anterior y conservó su integridad para la impresión o inyección en email).*

**Criterio de restauración e integración:**
1. Se recuperaron los archivos exactos desde el backup ubicado en D:\RXNAPP\3.3\www\rxnTiendasIAbck\20260402.
2. Se inyectó limpiamente la extensa regla de CSS (<style>) al búfer dinámico $extraHead que soporta el nuevo dmin_layout.php.
3. Se integraron con la nueva UI maestra cerrando limpiamente el búfer principal ($content) y el de scripts ($extraScripts).
4. Se eliminaron explícitamente del backup los intentos legacy de incluir el topbar de sesión repetido (user_action_menu.php), asegurando compatibilidad con el ecosistema actual (donde la cabecera principal inyecta la sesión).
5. Se restauraron las descripciones en texto (<p>) que explican el rol de cada canvas en el index.php.

**Validaciones realizadas:**
- El listado maestro en /formularios-impresion renderiza de forma consistente con el layout actual, manteniendo el estilo original de presentación estructurada.
- El canvas interactivo de "Presupuesto", "PDS" y "Cuerpo Mails" retienen su WYSIWYG al 100%, con sus herramientas flotantes y área en formato A4 sin solapamiento de DOM global provocado por el layout nuevo, reponiendo su grid especial. 

Todo el sistema PrintForms vuelve a operar como fue diseñado, pero insertado silenciosamente en el layout nuevo sin "hacks".
