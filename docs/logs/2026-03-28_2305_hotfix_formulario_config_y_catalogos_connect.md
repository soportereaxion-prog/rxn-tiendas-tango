# Hotfix formulario correcto para catalogos Connect

## Que se hizo
- Se corrigio `app/modules/EmpresaConfig/views/index.php` para que las llamadas AJAX de SMTP y Tango tomen siempre `FormData` desde `#empresa-config-form` y no desde el primer `<form>` del DOM.
- Se identifico que el widget compartido de notas del modulo inserta otro formulario antes del principal, por lo que `document.querySelector('form')` estaba apuntando al formulario equivocado en la ultima iteracion.

## Por que
- Eso hacia que al recargar metadata Connect no viajara `tango_connect_company_id`, dejando `REQUEST_COMPANY_ID` vacio en debug y evitando que listas/depositos se consultaran con la empresa elegida.
- El buscador visible de empresa mostraba bien el valor, pero el AJAX estaba serializando otro formulario y por eso los selectores dependientes quedaban vacios.

## Impacto
- `ID de Empresa (Connect)` vuelve a alimentar correctamente la carga de listas y deposito.
- El fix aplica a Tiendas y CRM porque ambos usan la misma vista compartida de configuracion.

## Decisiones tomadas
- Se eligio un hotfix minimo y seguro: anclar explicitamente el JS al formulario real de configuracion en vez de depender del primer `form` encontrado en pantalla.
