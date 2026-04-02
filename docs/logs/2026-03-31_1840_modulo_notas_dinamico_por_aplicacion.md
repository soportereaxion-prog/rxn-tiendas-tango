# [Empresas] - Deshabilitación granular de Módulo Notas en Tiendas y CRM

## Que se hizo
- Se agregaron las columnas `tiendas_modulo_notas` y `crm_modulo_notas` a la tabla `empresas` para habilitar o deshabilitar granularmente el uso del componente `Bitácora Interna` de cada entorno de aplicación.
- Se implementaron los sub swicthes checkbox en la pantalla de *Editar Empresa* (backoffice), bajo las opciones Tiendas y CRM de forma anidada y dependiente.
- Se refactorizó la lógica en Javascript de dicho formulario para reaccionar a la vinculación y arrastre de estados (checked/disabled) entre el conmutador de Empresa, los de Módulos Generales y los de submódulos (Notas).
- Se protegió el componente principal visual `app/shared/views/components/module_notes_panel.php` integrando una pequeña guarda que evalúa la URL donde corre vs los flags de estado en los que se encuentra esa instancia de la empresa.

## Por que
- Se había pactado que la visualización del módulo dependiera intrínsecamente de un control administrativo granular, permitiendo decidir desde el Backoffice Global si una empresa, a pesar de tener el entorno operativo Tiendas o CRM disponible, debe o no poder hacer uso del módulo de Bitácora.
- Porque este componente, el cual consume mucho espacio de pantalla y asume almacenamiento local en forma de JSON para notas de desarrollo con capturas, podría no desearse en todos los tenant operativos.

## Impacto
- Ahora, si en la pantalla de empresas, bajo *CRM*, se desmarca "*Módulo "Notas" en CRM*", el Panel Flotante de la Bitácora dejará de renderizarse por completo en todo el prefijo `/mi-empresa/crm/` y viceversa con `/mi-empresa/`.

## Decisiones tomadas
- Las migraciones directas de alter table se hicieron directo por medio del bootstrapping nativo (o consola/PHP) y se plasmaron en el Repositorio de Empresas y Entidad.
- Se consideró que Configuración (compartida) nunca restringe la visualización del componente asumiendo que es su hub general.
