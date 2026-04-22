<?php
$pageTitle = 'RXN Suite';
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
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell" style="max-width: 720px;">
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

                <div class="rxn-form-actions">
                    <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary fw-bold py-2 px-4">💾 Guardar Configuración</button>
                </div>
            </form>
            </div>
        </div>
    </div>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
    (function () {
        const picker = document.querySelector('input[name="color_calendario"]');
        const preview = document.getElementById('color-preview');
        if (picker && preview) {
            picker.addEventListener('input', (e) => { preview.style.background = e.target.value; });
        }
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
</script>
<script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
