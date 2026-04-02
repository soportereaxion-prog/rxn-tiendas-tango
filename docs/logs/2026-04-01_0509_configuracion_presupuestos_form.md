# Restauracion de acceso a Presupuestos y form assignments

## Que se hizo
- Habilitar el modulo 'presupuestos' en el dashboard de CRM.
- Se añadio a 'EmpresaConfig' el registro y presentacion de variables 'pds_email_pdf_canvas_id' y 'presupuesto_email_pdf_canvas_id'.

## Por que
El reseteo previo rompio dichos registros y borro el card del modulo. Volvimos a poner todo online y operable para el usuario.

## Impacto y Decisiones
- Se separaron los selects en un Card unico de Forms de impresion.
- El Repositorio añade dinamicamente las columnas en el CRM en su constructor, previniendo fallas de BD.
