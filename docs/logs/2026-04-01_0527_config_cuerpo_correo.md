# Ampliacion de Configuracion de Impresion CRM

## Que se hizo
- Se añadieron a 'EmpresaConfig' los registros para 'pds_email_body_canvas_id', 'presupuesto_email_body_canvas_id', 'pds_email_asunto' y 'presupuesto_email_asunto'.
- Se renderizaron dichos campos de UI en 'EmpresaConfig/views/index.php'.
- Se corrige el prefijo de PDS en el mailer, lo que antes causaba excepciones de render ('No existe definicion registrada') porque llamaba al documento por defecto con el key incorrecto 'crm_pedido_servicio' en lugar de 'crm_pds'.

## Por que
El usuario indicaba fallas en el adjunto del pdf del PDS, lo cual derivaba de un mismatch en las keys del DocumentMailerService y la falta de persistencia en la configuracion de dichos cuerpos de correo y asuntos.

## Impacto y Decisiones
- El repositorio añade las columnas en runtime al CRM. PedidoServicioController ahora le pasa las valid keys al DocumentMailerService.
