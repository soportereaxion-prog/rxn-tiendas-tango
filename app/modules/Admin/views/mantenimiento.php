<?php
/**
 * Vista de Mantenimiento y Actualizaciones
 * Variables recibidas:
 * - $appVersion
 * - $pendingMigrations (array)
 * - $executedCount (int)
 * - $errorMigrations (array)
 * - $backupsHistory (array)
 * - $success (string|null)
 * - $error (string|null)
 */
ob_start();
?>
<div class="container-xl mt-5 mb-5 rxn-responsive-container rxn-form-shell" style="max-width: 1280px;">
    <div class="mb-4 rxn-module-header">
        <div>
            <h2 class="fw-bold mb-1">Mantenimiento y Actualizaciones</h2>
        </div>
        <div>
            <h2 class="mb-1">Central de Operaciones (Admin)</h2>
        </div>
        <div class="rxn-module-actions">
            <a href="/mi-empresa/configuracion" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-arrow-left"></i> Volver a Configuración
            </a>
        </div>
    </div>
<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- INFO DEL SISTEMA -->
    <div class="col-12 col-xl-4">
        <div class="card rxn-form-card shadow-sm h-100 border-0">
            <div class="card-header border-bottom-0 pt-4 pb-0">
                <h5 class="card-title fw-bold text-dark mb-0">
                    <i class="bi bi-info-circle text-info me-2"></i>Estado del Sistema
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush mt-2">
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center border-0 border-bottom">
                        <span class="text-muted">Versión App</span>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($appVersion) ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center border-0 border-bottom">
                        <span class="text-muted">Migraciones Exitosas</span>
                        <span class="badge bg-success rounded-pill px-3 py-2"><?= $executedCount ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center border-0">
                        <span class="text-muted">Motor BD</span>
                        <span class="text-dark"><i class="bi bi-database me-1"></i>MySQL PDO</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- MIGRACIONES -->
    <div class="col-12 col-xl-8">
        <div class="card rxn-form-card shadow-sm border-0">
            <div class="card-header border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title fw-bold text-dark mb-0">
                    <i class="bi bi-server text-primary me-2"></i>Actualizaciones de BD (Migraciones)
                </h5>
                <?php if (!empty($pendingMigrations)): ?>
                    <form action="/admin/mantenimiento/migrar" method="POST" class="d-inline" onsubmit="return confirm('¿Confirma que desea ejecutar las <?= count($pendingMigrations) ?> migraciones pendientes?');">
                        <button type="submit" class="btn btn-warning fw-bold shadow-sm">
                            <i class="bi bi-play-fill me-1"></i> Ejecutar Pendientes (<?= count($pendingMigrations) ?>)
                        </button>
                    </form>
                <?php else: ?>
                    <span class="badge bg-light text-success border border-success px-3 py-2">
                        <i class="bi bi-check-circle me-1"></i> Sistema al día
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($pendingMigrations)): ?>
                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 py-2 px-3 mb-3">
                        <small><i class="bi bi-exclamation-circle me-1"></i> Se detectaron archivos de migración no aplicados. Ejecute para nivelar la BD.</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 border-top">
                            <thead class="table-light">
                                <tr>
                                    <th>Archivo Pendiente</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingMigrations as $mig): ?>
                                <tr>
                                    <td><code class="text-primary bg-light px-2 py-1 rounded"><?= htmlspecialchars($mig) ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 60px; height: 60px;">
                            <i class="bi bi-shield-check fs-2"></i>
                        </div>
                        <h6 class="fw-bold mb-1">Base de datos actualizada</h6>
                        <p class="text-muted small mb-0">No hay esquemas pendientes por aplicar.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- LINEA BASE (BASELINE) -->
        <div class="card rxn-form-card shadow-sm mt-4 border-warning border-opacity-50">
            <div class="card-header border-bottom-0 pt-4 pb-0 bg-warning bg-opacity-10 text-dark">
                <h5 class="card-title fw-bold mb-0">
                    <i class="bi bi-geo-alt-fill text-warning me-2"></i>Línea Base del Entorno (Baseline)
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted small"><strong>Uso crítico:</strong> Utilice esta función <strong>sólo cuando la instancia es un entorno pre-existente vivo</strong> que acaba de recibir el sistema de migraciones. Esto registrará todas las migraciones de código listadas como "Ejecutadas en origen" sin alterar las tablas de la BD actuales, previniendo colisiones de datos.</p>
                <form action="/admin/mantenimiento/baseline" method="POST" onsubmit="return confirm('ATENCIÓN: ¿Está seguro que desea asentar el Baseline? Todas las migraciones mostradas arriba quedarán marcadas como ejecutadas y no se procesarán.');">
                    <button type="submit" class="btn btn-outline-warning fw-bold shadow-sm">
                        <i class="bi bi-pin-angle-fill me-1"></i> Establecer Línea Base (Ignorar Históricas)
                    </button>
                </form>
            </div>
        </div>
        <!-- ACTUALIZADOR OVER THE AIR (OTA) -->
        <div class="card rxn-form-card shadow-sm mt-4 border-primary border-opacity-25">
            <div class="card-header border-bottom-0 pt-4 pb-0 bg-primary bg-opacity-10 text-dark d-flex align-items-center">
                <h5 class="card-title fw-bold mb-0">
                    <i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Actualización del Sistema (OTA Release)
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <p class="text-muted small mb-2"><strong>Despliegue directo:</strong> Suba el paquete ZIP de release generado en desarrollo. El sistema aislará automáticamente los `.env`, carpetas `storage/` y `uploads/` para proteger la instalación viva.</p>
                        <p class="text-muted small mb-0"><i class="bi bi-shield-check text-success"></i> Realizará un Backup previo completo antes de extraer.</p>
                    </div>
                    <div class="col-12 mt-3 pt-3 border-top">
                        <form action="/admin/mantenimiento/upload-update" method="POST" enctype="multipart/form-data" onsubmit="return confirm('ATENCIÓN: Se iniciará la actualización Integral del Sistema (Archivos y BdD).\nConsidere un posible tiempo de downtime de unos segundos.\n\n¿Proceder con la instalación de la Release?');">
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" name="update_zip" accept=".zip" required id="updateFileField">
                                <button type="submit" class="btn btn-primary fw-bold" id="btnUploadUpdate">
                                    <i class="bi bi-upload me-1"></i> Instalar
                                </button>
                            </div>
                            
                            <div class="form-check form-switch mt-2 bg-light p-2 rounded border">
                                <input class="form-check-input ms-1 me-2" type="checkbox" role="switch" id="autoMigrateCheck" name="auto_migrate" value="1" checked>
                                <label class="form-check-label text-dark fw-bold" for="autoMigrateCheck">
                                    <i class="bi bi-magic text-primary me-1"></i> Auto-aplicar Migraciones de BD incluidas
                                </label>
                                <div class="form-text ms-5 small mt-0">Detección y ejecución de los SQL/Archivos adjuntos para unificar el proceso.</div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">

    <!-- GENERADOR DE BACKUPS -->
    <div class="col-12 col-md-5">
        <div class="card rxn-form-card shadow-sm h-100 border-0 bg-dark text-white">
            <div class="card-body p-4 text-center d-flex flex-column justify-content-center h-100">
                <i class="bi bi-safe2 text-secondary opacity-50 mb-3" style="font-size: 3rem;"></i>
                <h4 class="fw-bold">Generación de Backups</h4>
                <p class="text-white-50 small mb-4">Los respaldos se almacenan de forma segura en <code>/storage/backups</code>. Puede demorar varios segundos según el tamaño del sistema.</p>
                
                <div class="d-grid gap-3">
                    <form action="/admin/mantenimiento/backup-db" method="POST" onsubmit="return confirm('¿Iniciar dump completo de la Base de Datos?');">
                        <button type="submit" class="btn btn-outline-light w-100 rounded-pill py-2">
                            <i class="bi bi-database-down me-2"></i> Respaldo SQL de BD
                        </button>
                    </form>

                    <form action="/admin/mantenimiento/backup-files" method="POST" onsubmit="return confirm('¿Crear archivo ZIP del sistema? Se excluirán logs y vendors.');">
                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2">
                            <i class="bi bi-file-zip me-2"></i> Respaldo de Archivos
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- HISTORIAL -->
    <div class="col-12 col-md-7">
        <div class="card rxn-form-card shadow-sm h-100 border-0">
            <div class="card-header border-bottom-0 pt-4 pb-0">
                <h5 class="card-title fw-bold text-dark mb-0">
                    <i class="bi bi-clock-history text-secondary me-2"></i>Historial de Backups
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($backupsHistory)): ?>
                    <p class="text-muted text-center my-4 py-4 bg-light rounded"><i class="bi bi-inbox fs-3 d-block text-secondary opacity-50 mb-2"></i>No hay registros de copias de seguridad.</p>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top shadow-sm">
                                <tr>
                                    <th>Archivo</th>
                                    <th>Tipo</th>
                                    <th>Tamaño</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backupsHistory as $bkp): ?>
                                <tr>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($bkp['file']) ?>">
                                            <strong><i class="<?= $bkp['type'] === 'Base de Datos' ? 'bi-database' : 'bi-file-zip' ?> text-muted me-2"></i><?= htmlspecialchars($bkp['file']) ?></strong>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= $bkp['type'] ?></span></td>
                                    <td><small class="text-muted"><?= $bkp['size'] ?></small></td>
                                    <td><small class="text-muted"><?= $bkp['date'] ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errorMigrations)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm border-danger border-opacity-25">
            <div class="card-header bg-danger bg-opacity-10 text-danger border-0 pt-3 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-bug me-2"></i>Errores Históricos de Migración</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($errorMigrations as $err): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start bg-light">
                            <div class="ms-2 me-auto text-danger">
                                <div class="fw-bold"><?= htmlspecialchars($err['migracion']) ?></div>
                                <small class="text-dark"><?= htmlspecialchars($err['observaciones'] ?? '') ?></small>
                            </div>
                            <span class="badge bg-danger rounded-pill"><?= htmlspecialchars($err['fecha_hora']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<?php if (in_array(getenv('APP_ENV'), ['local', 'dev', 'development'])): ?>
<div class="row g-4 mt-4">
    <!-- FACTORY OTA -->
    <div class="col-12">
        <div class="card rxn-form-card shadow-sm border-success border-opacity-50" style="background-color: #f6fcf8;">
            <div class="card-header border-bottom-0 pt-4 pb-0 bg-transparent text-dark d-flex align-items-center">
                <h5 class="card-title fw-bold mb-0">
                    <i class="bi bi-box-seam text-success me-2"></i>Fábrica de Empaquetados OTA (Local)
                </h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <p class="text-muted small mb-2"><strong>Entorno de Desarrollo:</strong> Esta herramienta empaquetará el código actual omitiendo vendors e imágenes, generando el `.zip` de actualización listo para subir a producción.</p>
                        <?php if (isset($_SESSION['last_ota_release'])): ?>
                            <div class="mt-3 p-3 bg-success bg-opacity-10 rounded border border-success border-opacity-25 d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold text-success mb-1">Última Compilación Exitosa</h6>
                                    <small class="text-muted"><?= htmlspecialchars($_SESSION['last_ota_release']) ?></small>
                                </div>
                                <a href="/admin/mantenimiento/download-release?file=<?= urlencode($_SESSION['last_ota_release']) ?>" class="btn btn-success fw-bold shadow-sm">
                                    <i class="bi bi-download me-1"></i> Bajar ZIP
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-5 mt-3 mt-md-0 text-md-end">
                        <form action="/admin/mantenimiento/build-release" method="POST" onsubmit="return confirm('¿Construir un nuevo paquete OTA? Se ignorará temporalmente cualquier compilación anterior.');">
                            <button type="submit" class="btn btn-outline-success fw-bold p-3 w-100 rounded-3 shadow-sm">
                                <i class="bi bi-cpu-fill fs-4 d-block mb-1"></i> Generar Paquete (.zip) Ahora
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div>

<?php
$content = ob_get_clean();
// Asegurar que use el layout global
require BASE_PATH . '/app/shared/views/admin_layout.php';
