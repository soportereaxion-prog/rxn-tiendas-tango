<?php
$pageTitle = 'RXN Suite';
$extraHead = '<link rel="stylesheet" href="/css/mi-perfil.css?v=' . time() . '">';
ob_start();
?>
<?php
    $dashboardPath = $dashboardPath ?? '/mi-empresa/dashboard';
    $helpPath = $helpPath ?? '/mi-empresa/ayuda?area=tiendas';
    $formPath = $formPath ?? '/mi-perfil?area=tiendas';
    ?>
    <?php
    $smtpConfig = $smtpConfig ?? null;
    $smtpActivo = $smtpConfig ? (int) $smtpConfig['activo'] === 1 : false;
    $smtpPasswordPlaceholder = !empty($smtpConfig['password_encrypted'])
        ? 'Guardada. Tipeá para reemplazarla.'
        : 'Contraseña / App Password';
    ?>
    <div class="container-fluid mt-2 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <h2 class="fw-bold m-0">Mi Perfil B2B</h2>
            <div class="rxn-module-actions">

                <a href="<?= htmlspecialchars($helpPath) ?>" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary btn-sm" title="Volver al Entorno"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'mi_perfil';
        $moduleNotesLabel = 'Mi Perfil';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success py-2 small"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>

        <div class="card rxn-form-card shadow-sm border-0">
            <div class="card-body p-4 p-lg-5">
            <form action="<?= htmlspecialchars($formPath) ?>" method="POST">
                <div class="rxn-form-section">
                    <div class="rxn-form-section-title">Preferencias visuales</div>
                    <div class="rxn-form-section-text">Ajustes personales del panel administrativo.</div>
                    <div class="rxn-form-grid">
                        <div class="rxn-form-span-6">
                            <label class="form-label fw-medium text-dark">Tema de la Interfaz</label>
                            <select name="preferencia_tema" class="form-select">
                                <option value="light" <?= ($usuario['preferencia_tema'] ?? '') === 'light' ? 'selected' : '' ?>>🌞 Claro (Predeterminado)</option>
                                <option value="dark" <?= ($usuario['preferencia_tema'] ?? '') === 'dark' ? 'selected' : '' ?>>🌙 Oscuro (Dark Mode)</option>
                            </select>
                        </div>

                        <div class="rxn-form-span-6">
                            <label class="form-label fw-medium text-dark">Tamaño de Tipografía</label>
                            <select name="preferencia_fuente" class="form-select">
                                <option value="sm" <?= ($usuario['preferencia_fuente'] ?? '') === 'sm' ? 'selected' : '' ?>>Compacto (sm)</option>
                                <option value="md" <?= ($usuario['preferencia_fuente'] ?? '') === 'md' ? 'selected' : '' ?>>Normal (md)</option>
                                <option value="lg" <?= ($usuario['preferencia_fuente'] ?? '') === 'lg' ? 'selected' : '' ?>>Grande (lg)</option>
                            </select>
                        </div>

                        <?php $zoomActual = (int) ($usuario['preferencia_zoom'] ?? 100); ?>
                        <div class="rxn-form-span-6">
                            <label class="form-label fw-medium text-dark">Zoom de la Interfaz</label>
                            <select name="preferencia_zoom" class="form-select">
                                <?php foreach ([75, 80, 90, 100, 110, 125, 150] as $z): ?>
                                    <option value="<?= $z ?>" <?= $zoomActual === $z ? 'selected' : '' ?>>
                                        <?= $z ?>%<?= $z === 100 ? ' (Predeterminado)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Equivale a la lupa del navegador — afecta toda la app, Tiendas y CRM.</div>
                        </div>
                    </div>
                </div>

                <div class="rxn-form-section mt-4">
                    <div class="rxn-form-section-title">Agenda CRM</div>
                    <div class="rxn-form-section-text">Color con el que tus eventos aparecerán en el calendario compartido del CRM.</div>
                    <div class="rxn-form-grid">
                        <div class="rxn-form-span-6">
                            <label class="form-label fw-medium text-dark">Color de calendario</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="color" name="color_calendario" class="form-control form-control-color border-0 shadow-sm" value="<?= htmlspecialchars($usuario['color_calendario'] ?? '#007bff') ?>" title="Elegí tu color para la agenda">
                                <span class="badge rounded-pill px-3 py-2 shadow-sm" style="background: <?= htmlspecialchars($usuario['color_calendario'] ?? '#007bff') ?>; color: #fff; font-size: 0.85rem;" id="color-preview"><?= htmlspecialchars($usuario['nombre'] ?? 'Tu nombre') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info py-2 small mb-4 mt-4">
                    Solo afecta tu experiencia dentro del panel administrativo. No impacta en la portada pública.
                </div>

                <?php if (!empty($canSeeMailMasivos)): ?>
                <div class="rxn-form-section mt-4">
                    <div class="rxn-form-section-title">SMTP para Mail Masivos</div>
                    <div class="rxn-form-section-text">
                        Configuración de tu servidor SMTP personal para envíos masivos del CRM. Es independiente del SMTP transaccional de la empresa.
                        Los envíos se procesan fuera del server principal para no saturar Plesk.
                    </div>

                    <div class="rxn-form-grid">
                        <div class="rxn-form-span-8">
                            <label for="smtp_host" class="form-label fw-medium text-dark">Servidor SMTP (Host)</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                   value="<?= htmlspecialchars($smtpConfig['host'] ?? '') ?>"
                                   placeholder="ej. smtp.gmail.com">
                        </div>

                        <div class="rxn-form-span-4">
                            <label for="smtp_port" class="form-label fw-medium text-dark">Puerto</label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" min="1" max="65535"
                                   value="<?= htmlspecialchars((string) ($smtpConfig['port'] ?? 587)) ?>">
                        </div>

                        <div class="rxn-form-span-6">
                            <label for="smtp_username" class="form-label fw-medium text-dark">Usuario</label>
                            <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                   value="<?= htmlspecialchars($smtpConfig['username'] ?? '') ?>"
                                   placeholder="tuemail@tudominio.com"
                                   autocomplete="off">
                        </div>

                        <div class="rxn-form-span-6">
                            <label for="smtp_password" class="form-label fw-medium text-dark">Contraseña / App Password</label>
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                   placeholder="<?= htmlspecialchars($smtpPasswordPlaceholder) ?>"
                                   autocomplete="new-password">
                        </div>

                        <div class="rxn-form-span-4">
                            <label for="smtp_encryption" class="form-label fw-medium text-dark">Cifrado</label>
                            <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                <?php $enc = $smtpConfig['encryption'] ?? 'tls'; ?>
                                <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                                <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL/TLS (465)</option>
                                <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>Ninguno</option>
                            </select>
                        </div>

                        <div class="rxn-form-span-4">
                            <label for="smtp_from_email" class="form-label fw-medium text-dark">Email remitente</label>
                            <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email"
                                   value="<?= htmlspecialchars($smtpConfig['from_email'] ?? '') ?>"
                                   placeholder="envios@tudominio.com">
                        </div>

                        <div class="rxn-form-span-4">
                            <label for="smtp_from_name" class="form-label fw-medium text-dark">Nombre remitente</label>
                            <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name"
                                   value="<?= htmlspecialchars($smtpConfig['from_name'] ?? '') ?>"
                                   placeholder="ej. Equipo Reaxion">
                        </div>

                        <div class="rxn-form-span-4">
                            <label for="smtp_max_per_batch" class="form-label fw-medium text-dark">Máx. por lote</label>
                            <input type="number" class="form-control" id="smtp_max_per_batch" name="smtp_max_per_batch" min="1" max="1000"
                                   value="<?= htmlspecialchars((string) ($smtpConfig['max_per_batch'] ?? 50)) ?>">
                            <div class="form-text">Cantidad antes de pausar.</div>
                        </div>

                        <div class="rxn-form-span-4">
                            <label for="smtp_pause_seconds" class="form-label fw-medium text-dark">Pausa entre lotes (seg)</label>
                            <input type="number" class="form-control" id="smtp_pause_seconds" name="smtp_pause_seconds" min="0" max="300"
                                   value="<?= htmlspecialchars((string) ($smtpConfig['pause_seconds'] ?? 5)) ?>">
                        </div>

                        <div class="rxn-form-span-4">
                            <label class="form-label fw-medium text-dark">Estado</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="smtp_activo" name="smtp_activo" <?= $smtpActivo ? 'checked' : '' ?>>
                                <label class="form-check-label" for="smtp_activo">Activo</label>
                            </div>
                        </div>

                        <div class="rxn-form-span-12">
                            <button type="button" id="btn-test-smtp" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-broadcast"></i> Probar conexión
                            </button>
                            <span id="smtp-test-result" class="ms-2 small text-muted"></span>

                            <?php if (!empty($smtpConfig['last_test_at'])): ?>
                                <div class="mt-2 small text-muted">
                                    Último test:
                                    <?= htmlspecialchars((string) $smtpConfig['last_test_at']) ?>
                                    <?php if ($smtpConfig['last_test_status'] === 'ok'): ?>
                                        <span class="badge bg-success">OK</span>
                                    <?php elseif ($smtpConfig['last_test_status'] === 'fail'): ?>
                                        <span class="badge bg-danger">FAIL</span>
                                        <?php if (!empty($smtpConfig['last_test_error'])): ?>
                                            <div class="text-danger small mt-1"><?= htmlspecialchars((string) $smtpConfig['last_test_error']) ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="rxn-form-actions">
                    <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary fw-bold py-2 px-4">💾 Guardar Configuración</button>
                </div>
            </form>
            </div>
        </div>

        <?php /* ====== HORARIO LABORAL (orientativo, alimenta notificaciones) ====== */ ?>
        <div class="card rxn-form-card mt-4" id="horario-laboral">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h5 fw-bold mb-1"><i class="bi bi-calendar2-week text-info"></i> Horario laboral</h2>
                <p class="text-muted small mb-4">
                    Es <strong>orientativo</strong>: no bloquea nada, solo se usa para que el sistema te avise si te olvidaste de iniciar o cerrar el turno (módulo Horas).
                    Podés cargar varios bloques por día (ej: turno mañana 9-13 + tarde 14-18).
                </p>

                <form method="POST" action="/mi-perfil/horario" class="rxn-horario-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfHelper::generateToken()) ?>">

                    <div class="table-responsive">
                        <table class="table table-sm align-middle rxn-horario-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 130px;">Día</th>
                                    <th>Bloques (HH:MM)</th>
                                    <th style="width: 110px;" class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($diasSemana as $diaNum => $diaLabel): ?>
                                <?php $bloques = $horarioPorDia[$diaNum] ?? []; ?>
                                <tr data-dia="<?= (int) $diaNum ?>">
                                    <td><strong><?= htmlspecialchars($diaLabel) ?></strong></td>
                                    <td>
                                        <div class="d-flex flex-column gap-2 rxn-bloques-wrapper">
                                            <?php if (empty($bloques)): ?>
                                                <div class="text-muted small fst-italic rxn-bloques-empty">— sin bloques —</div>
                                            <?php else: ?>
                                                <?php foreach ($bloques as $b): ?>
                                                <div class="d-flex gap-2 align-items-center rxn-bloque-row">
                                                    <input type="time" name="bloques[<?= (int) $diaNum ?>][<?= uniqid() ?>][inicio]" value="<?= htmlspecialchars($b['bloque_inicio']) ?>" class="form-control form-control-sm" style="max-width: 130px;">
                                                    <span>→</span>
                                                    <input type="time" name="bloques[<?= (int) $diaNum ?>][<?= uniqid() ?>][fin]" value="<?= htmlspecialchars($b['bloque_fin']) ?>" class="form-control form-control-sm" style="max-width: 130px;">
                                                    <input type="hidden" name="bloques[<?= (int) $diaNum ?>][<?= uniqid() ?>][activo]" value="1">
                                                    <button type="button" class="btn btn-sm btn-outline-danger rxn-bloque-remove" title="Quitar bloque"><i class="bi bi-x"></i></button>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-info rxn-bloque-add" data-dia="<?= (int) $diaNum ?>"><i class="bi bi-plus-lg"></i> Bloque</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-3">

                    <h3 class="h6 fw-bold mb-2"><i class="bi bi-bell text-warning"></i> Avisos del módulo Horas</h3>
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="notif_no_iniciaste" name="notif_no_iniciaste_activa" <?= !empty($usuario['notif_no_iniciaste_activa']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="notif_no_iniciaste">
                                    Avisarme si <strong>no abrí turno</strong> al inicio de un bloque
                                </label>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small mb-1">Tolerancia para "olvidaste cerrar" (minutos después del fin)</label>
                            <input type="number" name="minutos_tolerancia_olvido" class="form-control form-control-sm" min="5" max="240" step="5" value="<?= htmlspecialchars((string) ($usuario['minutos_tolerancia_olvido'] ?? 30)) ?>" style="max-width: 130px;">
                        </div>
                    </div>

                    <div class="rxn-form-actions mt-4">
                        <button type="submit" class="btn btn-primary">💾 Guardar horario laboral</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4" id="card-web-push">
        <div class="card-header bg-light d-flex align-items-center justify-content-between">
            <div>
                <h2 class="h5 mb-0"><i class="bi bi-bell-fill text-primary"></i> Notificaciones del navegador</h2>
                <p class="small text-muted mb-0">Recibí avisos de la suite incluso con la pestaña cerrada (recordatorios, asignaciones, alertas).</p>
            </div>
            <span id="webpush-badge" class="badge bg-secondary">Cargando...</span>
        </div>
        <div class="card-body">
            <div id="webpush-state-loading" class="text-muted small"><i class="bi bi-hourglass-split"></i> Verificando soporte del navegador...</div>

            <div id="webpush-state-unsupported" class="alert alert-warning small d-none mb-0">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Este navegador <strong>no soporta notificaciones push</strong>. Probá con Chrome, Firefox o Edge actualizados.
            </div>

            <div id="webpush-state-ios" class="alert alert-info small d-none mb-0">
                <i class="bi bi-info-circle-fill"></i>
                <strong>iPhone / iPad:</strong> las notificaciones push solo funcionan instalando la suite como app desde el browser (próximamente). Por ahora, usá un navegador desktop o Android.
            </div>

            <div id="webpush-state-blocked" class="alert alert-danger small d-none mb-0">
                <i class="bi bi-x-octagon-fill"></i>
                Bloqueaste las notificaciones para este sitio. Para reactivarlas, abrí los <strong>permisos del sitio</strong> en tu navegador (candado al lado de la URL → Notificaciones → Permitir) y volvé a entrar a esta página.
            </div>

            <div id="webpush-state-disabled" class="d-none">
                <p class="small text-muted">Hoy las notificaciones están <strong>desactivadas</strong> en este navegador. Activalas para que la campanita te suene como notificación nativa del sistema operativo.</p>
                <button type="button" class="btn btn-primary" id="webpush-btn-enable">
                    <i class="bi bi-bell-fill"></i> Activar notificaciones del navegador
                </button>
            </div>

            <div id="webpush-state-enabled" class="d-none">
                <p class="small text-success mb-2"><i class="bi bi-check-circle-fill"></i> Notificaciones del navegador <strong>activadas</strong> en este dispositivo.</p>
                <p class="small text-muted">Vas a recibir un aviso emergente cada vez que la campanita registre una notificación nueva (recordatorios de notas, asignaciones, etc.) — incluso con la pestaña cerrada.</p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="webpush-btn-test">
                        <i class="bi bi-send"></i> Mandar push de prueba
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="webpush-btn-disable">
                        <i class="bi bi-bell-slash"></i> Desactivar en este dispositivo
                    </button>
                </div>
                <div id="webpush-test-result" class="alert small d-none mt-3 mb-0"></div>
            </div>

            <div id="webpush-error" class="alert alert-danger small d-none mt-3 mb-0"></div>
        </div>
    </div>

    <template id="rxn-bloque-template">
        <div class="d-flex gap-2 align-items-center rxn-bloque-row">
            <input type="time" name="bloques[__DIA__][__UID__][inicio]" value="" class="form-control form-control-sm" style="max-width: 130px;">
            <span>→</span>
            <input type="time" name="bloques[__DIA__][__UID__][fin]" value="" class="form-control form-control-sm" style="max-width: 130px;">
            <input type="hidden" name="bloques[__DIA__][__UID__][activo]" value="1">
            <button type="button" class="btn btn-sm btn-outline-danger rxn-bloque-remove" title="Quitar bloque"><i class="bi bi-x"></i></button>
        </div>
    </template>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/rxn-web-push.js"></script>
<script>
    (function () {
        const picker = document.querySelector('input[name="color_calendario"]');
        const preview = document.getElementById('color-preview');
        if (picker && preview) {
            picker.addEventListener('input', (e) => { preview.style.background = e.target.value; });
        }
    })();

    // Horario laboral: agregar/quitar bloques inline.
    (function () {
        const tpl = document.getElementById('rxn-bloque-template');
        if (!tpl) return;

        function uniq() {
            return 'b' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
        }

        document.querySelectorAll('.rxn-bloque-add').forEach(btn => {
            btn.addEventListener('click', function () {
                const dia = btn.dataset.dia;
                const tr = btn.closest('tr');
                const wrapper = tr.querySelector('.rxn-bloques-wrapper');
                const empty = wrapper.querySelector('.rxn-bloques-empty');
                if (empty) empty.remove();

                const html = tpl.innerHTML.replaceAll('__DIA__', dia).replaceAll('__UID__', uniq());
                const div = document.createElement('div');
                div.innerHTML = html.trim();
                wrapper.appendChild(div.firstChild);
            });
        });

        // Delegación para los botones X (incluso los nuevos)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.rxn-bloque-remove');
            if (!btn) return;
            const row = btn.closest('.rxn-bloque-row');
            const wrapper = row?.parentElement;
            row?.remove();
            if (wrapper && !wrapper.querySelector('.rxn-bloque-row')) {
                const empty = document.createElement('div');
                empty.className = 'text-muted small fst-italic rxn-bloques-empty';
                empty.textContent = '— sin bloques —';
                wrapper.appendChild(empty);
            }
        });
    })();

    // Botón "Probar conexión" SMTP Masivo
    (function () {
        const btn = document.getElementById('btn-test-smtp');
        const out = document.getElementById('smtp-test-result');
        if (!btn || !out) return;

        btn.addEventListener('click', async () => {
            const payload = {
                smtp_host: document.getElementById('smtp_host').value.trim(),
                smtp_port: parseInt(document.getElementById('smtp_port').value || '587', 10),
                smtp_username: document.getElementById('smtp_username').value.trim(),
                smtp_password: document.getElementById('smtp_password').value,
                smtp_encryption: document.getElementById('smtp_encryption').value,
                smtp_from_email: document.getElementById('smtp_from_email').value.trim(),
                smtp_from_name: document.getElementById('smtp_from_name').value.trim(),
            };

            if (!payload.smtp_host) {
                out.className = 'ms-2 small text-warning';
                out.textContent = 'Falta el host SMTP';
                return;
            }

            btn.disabled = true;
            out.className = 'ms-2 small text-info';
            out.textContent = '⏳ Probando conexión...';

            try {
                const res = await fetch('/mi-perfil/smtp/test', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (data.success) {
                    out.className = 'ms-2 small text-success';
                    out.textContent = '✓ ' + (data.message || 'Conexión exitosa');
                } else {
                    out.className = 'ms-2 small text-danger';
                    out.textContent = '✗ ' + (data.message || 'Error desconocido');
                }
            } catch (e) {
                out.className = 'ms-2 small text-danger';
                out.textContent = '✗ Error de red: ' + e.message;
            } finally {
                btn.disabled = false;
            }
        });
    })();

    // Web Push — gestión del card de notificaciones del navegador
    (function () {
        const card = document.getElementById('card-web-push');
        if (!card || typeof window.RxnWebPush === 'undefined') return;

        const $ = (id) => document.getElementById(id);
        const states = {
            loading:     $('webpush-state-loading'),
            unsupported: $('webpush-state-unsupported'),
            ios:         $('webpush-state-ios'),
            blocked:     $('webpush-state-blocked'),
            disabled:    $('webpush-state-disabled'),
            enabled:     $('webpush-state-enabled'),
        };
        const badge = $('webpush-badge');
        const errBox = $('webpush-error');

        function show(stateName, badgeText, badgeClass) {
            Object.keys(states).forEach((k) => states[k].classList.add('d-none'));
            if (states[stateName]) states[stateName].classList.remove('d-none');
            if (badgeText) {
                badge.textContent = badgeText;
                badge.className = 'badge ' + (badgeClass || 'bg-secondary');
            }
        }

        function showError(msg) {
            errBox.textContent = msg;
            errBox.classList.remove('d-none');
            setTimeout(() => errBox.classList.add('d-none'), 6000);
        }

        async function refresh() {
            if (!RxnWebPush.isSupported()) {
                if (RxnWebPush.isIos()) {
                    show('ios', 'Sin soporte iOS', 'bg-info');
                } else {
                    show('unsupported', 'No soportado', 'bg-warning text-dark');
                }
                return;
            }

            const perm = RxnWebPush.permissionState();
            if (perm === 'denied') {
                show('blocked', 'Bloqueado', 'bg-danger');
                return;
            }

            try {
                const status = await RxnWebPush.getStatus();
                if (!status.configured) {
                    show('unsupported', 'No configurado', 'bg-secondary');
                    states.unsupported.textContent = 'El servidor todavía no tiene configuradas las claves VAPID. Contactá a soporte.';
                    return;
                }
                if (status.active > 0 && perm === 'granted') {
                    show('enabled', 'Activadas (' + status.active + ')', 'bg-success');
                } else {
                    show('disabled', 'Desactivadas', 'bg-secondary');
                }
            } catch (e) {
                show('disabled', 'Error', 'bg-warning text-dark');
                showError('No se pudo verificar el estado: ' + e.message);
            }
        }

        $('webpush-btn-enable').addEventListener('click', async function () {
            this.disabled = true;
            try {
                await RxnWebPush.enable();
                await refresh();
            } catch (e) {
                showError('No se pudo activar: ' + e.message);
                await refresh();
            } finally {
                this.disabled = false;
            }
        });

        $('webpush-btn-test').addEventListener('click', async function () {
            const out = $('webpush-test-result');
            out.className = 'alert alert-info small mt-3 mb-0';
            out.classList.remove('d-none');
            out.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando push de prueba...';
            this.disabled = true;
            try {
                const r = await RxnWebPush.sendTest();
                if (!r.ok) {
                    out.className = 'alert alert-warning small mt-3 mb-0';
                    out.innerHTML = '<strong>No se pudo enviar:</strong> ' + (r.error || 'desconocido') +
                        (r.hint ? '<br><small>' + r.hint + '</small>' : '');
                    return;
                }
                let cls = 'alert-success';
                let msg = '';
                if (r.sent > 0) {
                    msg = '<strong>✓ Push enviado desde el server (' + r.sent + '/' + r.subs_total + ' subs en ' + r.elapsed_ms + 'ms).</strong><br>';
                    msg += 'Si NO viste el toast del SO en los próximos segundos, el problema NO está en RxnSuite — está en el browser o el SO.<br>';
                    msg += '<small class="d-block mt-2">Revisá: Windows Focus Assist (Asistente de concentración), permisos de notificación de Chrome para este sitio, "Continuar ejecutando aplicaciones en segundo plano" en Chrome.</small>';
                } else if (r.removed > 0) {
                    cls = 'alert-warning';
                    msg = '<strong>La suscripción está vencida</strong> (' + r.removed + ' borradas). Tocá <em>Desactivar</em> y volvé a <em>Activar</em>.';
                } else if (r.failed > 0) {
                    cls = 'alert-danger';
                    msg = '<strong>El push server rechazó el envío</strong> (' + r.failed + ' fallos). Causa más probable: claves VAPID no coinciden con las del browser, o problema de red.';
                } else {
                    cls = 'alert-warning';
                    msg = 'Server respondió OK pero no envió nada. Verificá que tengas suscripciones activas (badge arriba a la derecha).';
                }
                out.className = 'alert ' + cls + ' small mt-3 mb-0';
                out.innerHTML = msg;
            } catch (e) {
                out.className = 'alert alert-danger small mt-3 mb-0';
                out.innerHTML = '<strong>Error de red:</strong> ' + e.message;
            } finally {
                this.disabled = false;
            }
        });

        $('webpush-btn-disable').addEventListener('click', async function () {
            if (!confirm('¿Desactivar las notificaciones del navegador en este dispositivo?')) return;
            this.disabled = true;
            try {
                await RxnWebPush.disable();
                await refresh();
            } catch (e) {
                showError('No se pudo desactivar: ' + e.message);
            } finally {
                this.disabled = false;
            }
        });

        refresh();
    })();
</script>
<script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
