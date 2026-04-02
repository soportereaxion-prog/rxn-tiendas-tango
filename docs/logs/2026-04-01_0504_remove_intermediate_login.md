# Log de Modificación - Eliminación de pantalla intermedia de login

## Qué se hizo
1. Se modificó el archivo `app/config/routes.php` para interceptar a los usuarios no logueados en la ruta raíz (`/`) y forzar una redirección directa hacia `/login`.
2. Se simplificó la vista `app/modules/dashboard/views/home.php`, eliminando la lógica condicional `if (!$isLoggedIn)` y la tarjeta "Ingresar al Sistema".
3. Se garantizó que al renderizar el launcher principal, el usuario ya tenga una sesión activa obligatoriamente.

## Por qué
El usuario solicitó eliminar el paso intermedio que exigía hacer clic en "Ingresar al Sistema" desde el dashboard público para recién ir a cargar sus credenciales, mejorando la UX al ir directo a loguear si no existe una sesión iniciada.

## Impacto
* **Routes**: La ruta raíz `/` ahora chequea `empty($_SESSION['user_id'])`.
* **Home**: Queda como un dashboard exclusivo para personas autenticadas.
* **Flujo**: Cualquiera que intente entrar a `/` sin loguearse será llevado a `/login`.

## Decisiones tomadas
* Mantener la validación en el enrutamiento para evitar cargar vistas si no hay sesión.
* Se eliminaron checks de la vista ya que el acceso no autenticado no es posible a ese punto.
