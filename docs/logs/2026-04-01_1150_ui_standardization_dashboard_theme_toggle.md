# UI Standardization para Dashboard Themes y Theme Toggle

## Qué se hizo
1. Estandarizamos los cards de los menús (Launcher, Backoffice Administrador, Panel Tenant y Pantalla de Inicio CRM) con clases globales `.rxn-module-card` y `.rxn-module-icon` ubicadas de manera central en `public/css/rxn-theming.css`.
2. Quitamos fondos fijos, garantizando completa transparencia y adaptabilidad entre el Modo Claro y Oscuro para todos los dashboards.
3. Se integraron botones de 'Cambiar Tema' rápidos integrados en el backoffice a través del backend.
   - Estos botones envían una actualización en vivo vía AJAX al nuevo endpoint `POST /mi-perfil/toggle-theme`.
   - Se ajustaron los componentes del banner `app/shared/views/components/user_action_menu.php` y `app/shared/views/components/backoffice_user_banner.php`.
   - Todo esto se acopla dinámicamente según la preferencia del usuario salvada directamente en la base de datos `referencia_tema` y mantiene consistencia a lo largo de las páginas recargadas.

## Por qué
- Había problemas visuales al momento de cambiar a Modo Oscuro, donde los cards de varios tableros rompían en diseño al poseer hardcode styles (blanco sólido o bordes estáticos). Estandarizar esto mejora la escalabilidad de nuevos dashboards mediante las clases pre-hechas.
- Un acceso rápido a cambiar el tema por medio de un botón para que el usuario pueda conmutar el light/dark mode tal como funciona en la Landing pública es una mejora sustancial a la experiencia de usuario dentro del panel de operario (backend).

## Impacto
- Todos los paneles de control de acceso ahora cargan la misma clase `rxn-module-card`.
- Mayor facilidad de uso del modo oscuro en el panel trasero (tenant backoffice / superadmin).

## Decisiones tomadas
- Las clases CSS redundantes eliminadas (`.module-card`, etc.) pueden ocasionar que el "ghostclass" en SortableJS fallara. Para corregirlo, implementé manual reference al `.rxn-module-card` en los eventos inicializadores del SortableJS para cada entorno.
- En vez de depender exclusivamente del front-end para guardar el toggle en el localStorage, se configuró con un AJAX call a para que también actualice la preferencia de perfil en BD garantizando consistencia.
