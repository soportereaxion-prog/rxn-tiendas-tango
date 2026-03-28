<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Master RXN Global</title>
    <!-- CSS Bootstrap 5 CDN para pruebas estructurales orgánicas -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #1a1e23; color: #f8f9fa; }
        .card { background-color: #2c3138; border-color: #3e444b; color: white;}
        .form-control, .form-select { background-color: #3b424b; border-color: #4f5761; color: #cdd4db; }
        .form-control:focus, .form-select:focus { background-color: #3b424b; border-color: #e5a93d; color: white; box-shadow: 0 0 0 .25rem rgba(229,169,61,.25); }
        .form-label.text-secondary { color: #8d97a5 !important; }
        .btn-warning { background-color: #e5a93d; border-color: #e5a93d; }
        .rxn-form-section-text { color: #8d97a5; }
        .rxn-form-actions { border-top-color: #3e444b; }
    </style>
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell" style="max-width: 900px;">
        <div class="mb-4 rxn-module-header">
            <div>
                <h2 class="text-warning mb-1">⚙️ SMTP Master RXN</h2>
                <p class="text-muted mb-0">Esta conexión asume las responsabilidades transaccionales cada vez que sub-empresas carezcan de configuraciones propias.</p>
            </div>
            <a href="/rxnTiendasIA/public/admin/dashboard" class="btn btn-outline-light">Volver al Backoffice</a>
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
                <form action="/rxnTiendasIA/public/admin/smtp-global" method="POST">
                
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
    <script>
        document.getElementById('btn-test-smtp').addEventListener('click', async (e) => {
            const btn = e.target;
            const originalText = btn.innerText;
            btn.innerText = '⏳ Probando...';
            btn.disabled = true;

            try {
                const formData = new FormData(document.querySelector('form'));
                const res = await fetch('/rxnTiendasIA/public/admin/smtp-global/test', {
                    method: 'POST',
                    body: formData
                });
                
                const json = await res.json();
                if (json.success) {
                    alert('✅ ÉXITO:\n' + json.message);
                } else {
                    alert('❌ FALLÓ:\n' + json.message);
                }
            } catch (error) {
                alert('Ocurrió un error de red intentando contactar al validador.');
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
