<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seed de novedades — releases 1.43.0 + 1.43.1.
 * Cubre ambos releases del día porque vienen empaquetados en el mismo OTA.
 *
 * 1.43.0: Hub PWA / launcher central con todas las apps mobile.
 * 1.43.1: PWA Horas (turnero) end-to-end + adjuntos en desktop + UX 419 + pickers.
 */
return function (): void {
    $db = Database::getConnection();

    $notes = [
        [
            'title' => 'Nueva PWA: Horas mobile para registrar tu turno en campo',
            'body_html' => <<<'HTML'
<p>Sumamos al hub PWA una segunda app mobile pensada para técnicos y operadores que trabajan fuera de la oficina:</p>
<ul>
    <li><strong>Cronómetro vivo</strong>: tocás <em>Iniciar turno</em> y la app empieza a contar. Arriba ves en tiempo real cuántas horas llevás trabajadas en el día (suma turnos cerrados + el cronómetro abierto).</li>
    <li><strong>Funciona offline</strong>: cargás concepto, vinculás a una tratativa activa, agregás un descuento opcional con motivo, y todo queda en el celular. Cuando recuperás señal se sincroniza solo al servidor.</li>
    <li><strong>Adjuntos con la cámara</strong>: podés sacar una foto del certificado médico, planilla de obra o lo que necesites justificar, y queda atado al turno. También subir PDFs, Word, Excel.</li>
    <li><strong>Carga diferida</strong>: si te olvidaste de iniciar el turno en el momento, lo cargás después con fechas manuales (inicio + fin) y queda registrado igual.</li>
</ul>
<p>El acceso es desde el hub PWA (la app "Mobile Suite") — instalalo como app en el celular para tenerlo a mano y navegarlo sin barra del navegador.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.43.1',
            'published_at' => '2026-05-02 22:00:00',
        ],
        [
            'title' => 'Adjuntos en Horas: subí certificados y planillas desde la versión web',
            'body_html' => <<<'HTML'
<p>Ahora cualquier turno que tengas en el listado de Horas se puede acompañar con archivos:</p>
<ul>
    <li>Hacé click en el ícono <strong>📎</strong> al lado de un turno del día y entrás a la vista detalle.</li>
    <li>Subí PDF, Word, Excel o imágenes — útil para certificados médicos, planillas firmadas o cualquier respaldo del trabajo.</li>
    <li>Los adjuntos quedan atados al turno y los podés borrar si te equivocaste, mismo principio que Notas o Presupuestos.</li>
</ul>
<p>Funciona tanto desde la versión web como desde la PWA mobile (con cámara incluida). Lo carga el dueño del turno o un administrador.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.43.1',
            'published_at' => '2026-05-02 22:00:00',
        ],
        [
            'title' => 'Descuento de tiempo en Horas con motivo justificado',
            'body_html' => <<<'HTML'
<p>Si en el medio de un turno hubo una pausa larga, almuerzo, traslado no facturable o cualquier otro motivo por el cual el tiempo trabajado real es menor al bruto, ahora podés cargarlo:</p>
<ul>
    <li>Al iniciar o cargar un turno (también al editarlo), tenés un bloque opcional <strong>"Aplicar descuento al tiempo"</strong> con dos campos: el tiempo a descontar en formato HH:MM:SS y un campo de motivo.</li>
    <li>Si cargás descuento, el motivo es obligatorio — queda asentado para auditoría.</li>
    <li>El tiempo neto se calcula automáticamente y se muestra en la lista de turnos del día con un aviso amarillo.</li>
</ul>
<p>Disponible tanto en la versión web como en la PWA mobile.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.43.1',
            'published_at' => '2026-05-02 22:00:00',
        ],
        [
            'title' => 'Hub PWA: una sola entrada para todas las apps mobile',
            'body_html' => <<<'HTML'
<p>Con la suma de la PWA de Horas a la PWA de Presupuestos, organizamos el acceso:</p>
<ul>
    <li>El banner azul del CRM mobile y la card "PWA Mobile" del dashboard llevan ahora a un <strong>menú único</strong> donde elegís qué app abrir.</li>
    <li>Dentro de cualquier PWA tenés un botón nuevo en el header (📱) para volver al menú y saltar a otra app sin pasar por el backoffice.</li>
    <li>Si instalás la PWA en el celular ("Agregar a pantalla de inicio"), abre directo en el menú con todas las apps disponibles.</li>
</ul>
<p>Cuando se sumen más apps mobile en el futuro, aparecen automáticamente en el menú.</p>
HTML,
            'category' => 'feature',
            'version_ref' => '1.43.0',
            'published_at' => '2026-05-02 22:00:00',
        ],
        [
            'title' => 'Volver a iniciar sesión es más simple cuando se vence el formulario',
            'body_html' => <<<'HTML'
<p>Si dejaste la pantalla de login abierta mucho tiempo y al intentar entrar te aparecía un cartel de "sesión expirada" sin opciones, ahora lo manejamos con cariño:</p>
<ul>
    <li>El sistema detecta que tu pestaña estuvo inactiva, te lleva al formulario fresco con un aviso amable, y podés volver a ingresar tus credenciales sin recargar nada.</li>
    <li>Si te pasa dentro de un módulo del backoffice mientras trabajabas, ahora la pantalla tiene <strong>dos botones claros</strong>: "Iniciar sesión nuevamente" (te devuelve a la página donde estabas después de loguear) y "Volver atrás".</li>
</ul>
<p>Sin pestañas perdidas ni clicks innecesarios.</p>
HTML,
            'category' => 'mejora',
            'version_ref' => '1.43.1',
            'published_at' => '2026-05-02 22:00:00',
        ],
        [
            'title' => 'Pickers de Cliente y Artículo más estables al editar un PDS',
            'body_html' => <<<'HTML'
<p>Si al editar un Pedido de Servicio o un Presupuesto tocabas el campo de Cliente o Artículo (aunque sea sin cambiar nada) y al guardar te decía que faltaba seleccionarlo de la lista, ese problema ya no pasa.</p>
<ul>
    <li>Ahora el sistema recuerda el estado válido del campo y lo restaura automáticamente si volvés a dejarlo igual al original.</li>
    <li>Solo si escribís algo distinto se borra el vínculo, como debe ser. Ya no hay falsos positivos al editar.</li>
</ul>
HTML,
            'category' => 'mejora',
            'version_ref' => '1.43.1',
            'published_at' => '2026-05-02 22:00:00',
        ],
    ];

    $chk = $db->prepare(
        "SELECT COUNT(*) FROM customer_notes
         WHERE title = :t AND COALESCE(version_ref, '') = :v"
    );
    $ins = $db->prepare(
        "INSERT INTO customer_notes
            (title, body_html, category, version_ref, status, published_at)
         VALUES
            (:title, :body_html, :category, :version_ref, 'published', :published_at)"
    );

    foreach ($notes as $n) {
        $chk->execute([':t' => $n['title'], ':v' => (string) ($n['version_ref'] ?? '')]);
        if ((int) $chk->fetchColumn() > 0) {
            continue;
        }
        $ins->execute([
            ':title' => $n['title'],
            ':body_html' => $n['body_html'],
            ':category' => $n['category'],
            ':version_ref' => $n['version_ref'] ?? null,
            ':published_at' => $n['published_at'],
        ]);
    }
};
