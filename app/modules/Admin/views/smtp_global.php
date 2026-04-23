<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<div class="container-fluid mt-2 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="mb-4 rxn-module-header">
            <div>
                <h2 class="text-warning mb-1">⚙️ SMTP Master RXN</h2>
                
            </div>
            <a href="/admin/dashboard" class="btn btn-outline-secondary btn-sm" title="Volver al Backoffice"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>

        <?php
        $moduleNotesKey = 'smtp_global';
        $moduleNotesLabel = 'SMTP Global';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success border-0 bg-success text-white">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger border-0 bg-danger text-white">❗ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card rxn-form-card shadow-lg">
            <div class="card-body p-4 p-lg-5">
                <form action="/admin/smtp-global" method="POST">
                
                    <div class="alert alert-dark border-secondary mb-4">
                        <small>🛡️ <strong>Alerta de Seguridad:</strong> Estás alterando estructuralmente el superglobal <code>.env</code> desde el Front. Estos cambios impactan diametralmente el transporte final de todas las entidades huérfanas o invitadas.</small>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title text-warning">Credenciales maestras</div>
                        <div class="rxn-form-section-text">Configuración heredada por las empresas que no usan SMTP propio.</div>
                        <div class="rxn-form-grid">
                        <div class="rxn-form-span-8">
                            <label for="host" class="form-label text-secondary small fw-medium">Servidor Master (Host)</label>
                            <input type="text" class="form-control" id="host" name="host" required
                                    value="<?= htmlspecialchars($smtp['host']) ?>" placeholder="Ej: smtp.mailgun.org">
                        </div>
                        <div class="rxn-form-span-4">
                            <label for="port" class="form-label text-secondary small fw-medium">Puerto</label>
                            <input type="number" class="form-control" id="port" name="port" required
                                    value="<?= htmlspecialchars((string)$smtp['port']) ?>">
                        </div>

                        <div class="rxn-form-span-12">
                            <label for="user" class="form-label text-secondary small fw-medium">Usuario SMTP</label>
                            <input type="text" class="form-control" id="user" name="user" required
                                    value="<?= htmlspecialchars($smtp['user']) ?>" placeholder="postmaster@rxntiendas.com">
                        </div>

                        <div class="rxn-form-span-8">
                            <label for="pass" class="form-label text-secondary small fw-medium">Muted Password</label>
                            <input type="password" class="form-control" id="pass" name="pass" autocomplete="new-password">
                            <div class="form-text text-muted"><small>Se asume cargada en memoria RAM. Modificala si necesitás cambiarla.</small></div>
                        </div>

                        <div class="rxn-form-span-4">
                            <label for="secure" class="form-label text-secondary small fw-medium">Túnel</label>
                            <select class="form-select" id="secure" name="secure">
                                <option value="" <?= empty($smtp['secure']) ? 'selected' : '' ?>>Non-Secure</option>
                                <option value="tls" <?= ($smtp['secure'] === 'tls') ? 'selected' : '' ?>>TLS (Moderno)</option>
                                <option value="ssl" <?= ($smtp['secure'] === 'ssl') ? 'selected' : '' ?>>SSL (Heredado)</option>
                            </select>
                        </div>
                        
                        <div class="rxn-form-span-6">
                            <label for="from_name" class="form-label text-secondary small fw-medium">Global From Alias</label>
                            <input type="text" class="form-control" id="from_name" name="from_name" required
                                    value="<?= htmlspecialchars($smtp['from_name']) ?>" placeholder="RXN Transmisiones">
                        </div>

                        <div class="rxn-form-span-6">
                            <label for="from_email" class="form-label text-secondary small fw-medium">Global From Address</label>
                            <input type="email" class="form-control" id="from_email" name="from_email" required
                                    value="<?= htmlspecialchars($smtp['from_email']) ?>" placeholder="no-reply@rxntiendas.com">
                        </div>
                        </div>
                    </div>

                    <div class="rxn-form-actions">
                        <button type="button" id="btn-test-smtp" class="btn btn-outline-info px-3 fw-bold">✔️ Probar Conexión</button>
                        <button type="submit" class="btn btn-warning px-4 fw-bold">Actualizar Fallback RXN</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Validator AJAX -->
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
        document.getElementById('btn-test-smtp').addEventListener('click', async (e) => {
            const btn = e.target;
            const originalText = btn.innerText;
            btn.innerText = '⏳ Probando...';
            btn.disabled = true;

            try {
                const formData = new FormData(btn.closest('form'));
                const res = await fetch('/admin/smtp-global/test', {
                    method: 'POST',
                    body: formData
                });
                
                const json = await res.json();
                if (json.success) {
                    (window.rxnAlert || alert)(json.message, 'success', 'ÉXITO');
                } else {
                    (window.rxnAlert || alert)(json.message, 'danger', 'FALLÓ');
                }
            } catch (error) {
                (window.rxnAlert || alert)('Ocurrió un error de red intentando contactar al validador.', 'danger', 'Error Interno');
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        });
    </script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
