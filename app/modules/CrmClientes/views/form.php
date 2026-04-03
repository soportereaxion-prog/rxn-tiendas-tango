<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<?php $basePath = $basePath ?? '/mi-empresa/crm/clientes'; ?>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="fw-bold mb-1"><?= htmlspecialchars((string) ($editTitle ?? 'Modificar Cliente CRM')) ?></h2>
                
            </div>
            <div class="d-flex gap-2">
                <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al listado
                </a>
                <?php if (!empty($cliente['id'])): ?>
                    <form action="<?= htmlspecialchars($basePath) ?>/<?= (int) $cliente['id'] ?>/copiar" method="POST" class="d-inline">
                        <button type="submit" class="btn btn-outline-success" title="Duplicar">
                            <i class="bi bi-copy"></i>
                        </button>
                    </form>
                    <form action="<?= htmlspecialchars($basePath) ?>/eliminar-masivo" method="POST" class="d-inline" onsubmit="return confirm('¿Enviar cliente a la papelera?');">
                        <input type="hidden" name="ids[]" value="<?= (int) $cliente['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger" title="Enviar a papelera">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                <strong><?= $flash['type'] === 'success' ? 'OK' : 'Atención' ?></strong> <?= htmlspecialchars((string) $flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card rxn-form-card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <form action="<?= htmlspecialchars($basePath) ?>/editar?id=<?= (int) ($cliente['id'] ?? 0) ?>" method="POST">
                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Identificadores Tango</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-4">
                                <label class="form-label text-muted">ID local</label>
                                <input type="text" class="form-control" value="<?= (int) ($cliente['id'] ?? 0) ?>" disabled>
                            </div>
                            <div class="rxn-form-span-4">
                                <label class="form-label text-muted">ID GVA14 Tango</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string) ($cliente['id_gva14_tango'] ?? '')) ?>" disabled>
                            </div>
                            <div class="rxn-form-span-4">
                                <label class="form-label text-muted">Codigo Tango</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string) ($cliente['codigo_tango'] ?? '')) ?>" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Ficha local</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-8">
                                <label for="razon_social" class="form-label">Razon social</label>
                                <input type="text" class="form-control" id="razon_social" name="razon_social" value="<?= htmlspecialchars((string) ($cliente['razon_social'] ?? '')) ?>" required>
                            </div>
                            <div class="rxn-form-span-4">
                                <label for="documento" class="form-label">CUIT / Documento</label>
                                <input type="text" class="form-control" id="documento" name="documento" value="<?= htmlspecialchars((string) ($cliente['documento'] ?? '')) ?>">
                            </div>
                            <div class="rxn-form-span-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars((string) ($cliente['email'] ?? '')) ?>">
                            </div>
                            <div class="rxn-form-span-6">
                                <label for="telefono" class="form-label">Telefono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars((string) ($cliente['telefono'] ?? '')) ?>">
                            </div>
                            <div class="rxn-form-span-12">
                                <label for="direccion" class="form-label">Direccion</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars((string) ($cliente['direccion'] ?? '')) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section bg-light p-3 p-lg-4 rounded border border-warning border-opacity-25">
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-8">
                                <label class="form-label text-warning fw-bold mb-1">Ultima sincronizacion</label>
                                <input type="text" class="form-control " value="<?= htmlspecialchars((string) ($cliente['fecha_ultima_sync'] ?? '')) ?>" disabled>
                                <div class="form-text text-muted mt-2"><small>El origen maestro sigue siendo Tango/Connect. Esta ficha solo permite ajustes locales sobre la cache operativa.</small></div>
                            </div>
                            <div class="rxn-form-span-4 d-flex align-items-end">
                                <div class="rxn-form-switch-card w-100">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= !empty($cliente['activo']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="activo">Cliente activo</label>
                                        <div class="form-text mb-0">Controla si el cliente queda disponible para operar en CRM.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-actions mt-4">
                        <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Guardar Modificaciones Locales</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
