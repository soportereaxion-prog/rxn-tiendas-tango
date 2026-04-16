# Corrección de Modales Duplicados

**Fecha**: 2026-04-03
**Módulo afecado**: Global, Layout, Vistas CRUD, Scripts Compartidos.

## Qué se hizo
1. Se limpió de más de 20 módulos (`index.php`, `form.php`, etc) la inclusión hardcodeada de `bootstrap.bundle.min.js`, ya que el mismo ya se está incluyendo correctamente a nivel de Template Base en `admin_layout.php`.
2. Se eliminó la definición redundante de `<div class="modal ... id="rxnConfirmModal">` y sus inicializadores locales mediante JS en vistas específicas como `CrmLlamadas/views/index.php` y `CrmNotas/views/index.php`. 

## Por qué
El script dinámico universal `public/js/rxn-confirm-modal.js` inicializa explícitamente en el body el mismo modal y captura los eventos de clase `.rxn-confirm-form` de todo el sistema. Esta duplicación estructural, en combinación con el bootstrap importado dos veces, causaba que el backdrop de la ventana modal se renderizara dos veces cada vez que un usuario enviaba a papelera o borraba un elemento.

## Impacto
Se corrigió la falla visual de UI en modales. Limpieza del código al delegar el render de las alertas en el componente UI universal. La base de código de las vistas es más limpia al depender del layout principal general para Bootstrap.
