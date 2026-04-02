# Fix de renderizado en correos y PDF de PDS/Presupuestos

- **Qué se hizo**: Se agregó el método `View::renderToString()` a la clase principal `App\Core\View`.
- **Por qué**: Durante el envío de correos desde PDS y Presupuestos (vía `DocumentMailerService`), el sistema intentaba obtener el HTML resultante de la vista para adjuntarlo como PDF o inyectarlo como cuerpo de correo (en forma de string). Como el método se borró en el infame _--reset de la muerte_, lanzaba un Fatal Error: `Call to undefined method App\Core\View::renderToString()`.
- **Impacto**: Ahora la generación dinámica de presupuestos y pedidos desde PrintForms (Dompdf) en formato mail y archivo vuelve a funcionar correctamente sin colgarse la ventana de render.
- **Decisiones tomadas**: En lugar de hacer refactors masivos en el núcleo de la aplicación, el método se reconstruyó encapsulando `self::render($path, $data)` dentro de `ob_start()` y `ob_get_clean()`. Esto permite mantener toda la misma lógica y compatibilidad retroactiva intacta.
