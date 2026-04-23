<?php
$pageTitle = 'Monitoreo de Usuarios CRM - rxn_suite';
$usePageHeader = true;
$pageHeaderTitle = 'Monitoreo de Operadores';
$pageHeaderSubtitle = 'Supervisa al equipo de ventas y operadores del sistema.';
$pageHeaderIcon = 'bi-activity';
$pageHeaderBackUrl = '/mi-empresa/crm/dashboard';

ob_start();
?>
    <style>
        .crm-monitoreo-shell {
            max-width: 100%;
        }

        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.2rem;
            margin-top: 1.5rem;
        }

        .user-card {
            border: 1px solid var(--bs-border-color);
            background-color: var(--bs-body-bg);
            border-radius: 16px;
            padding: 1.2rem;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }

        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
            border-color: var(--bs-primary);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .user-status-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            position: absolute;
            bottom: -2px;
            right: -2px;
            border: 2px solid var(--bs-body-bg);
        }

        .user-status-dot.online {
            background-color: var(--bs-success);
            box-shadow: 0 0 0 0 rgba(25, 135, 84, 1);
            animation: pulse-green 2s infinite;
        }

        .user-status-dot.offline {
            background-color: var(--bs-secondary);
            opacity: 0.6;
        }

        .user-status-dot.suspended {
            background-color: var(--bs-danger);
            opacity: 0.7;
        }

        .user-card.is-online {
            border-color: rgba(25, 135, 84, 0.35);
            box-shadow: 0 0 0 1px rgba(25, 135, 84, 0.15);
        }

        .user-last-seen {
            font-size: 0.75rem;
            color: var(--bs-secondary);
            font-style: italic;
            margin-top: 0.15rem;
        }

        .user-last-seen.online {
            color: var(--bs-success);
            font-weight: 600;
            font-style: normal;
        }

        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(25, 135, 84, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
        }

        .user-info-wrapper {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .user-meta-strip {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--bs-secondary);
            border-top: 1px dashed var(--bs-border-color);
            padding-top: 1rem;
        }

        .user-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-meta-item i {
            width: 16px;
            text-align: center;
            opacity: 0.7;
        }
    </style>
<?php
$extraHead = ob_get_clean();

ob_start();
?>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-primary rounded-pill shadow-sm" onclick="window.location.reload();">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
        </button>
    </div>
<?php
$pageHeaderActions = ob_get_clean();

ob_start();
?>
<div class="container-fluid crm-monitoreo-shell mb-5">
    
    <!-- Barra de Búsqueda Integrada + Conteo de Conectados -->
    <div class="row mb-3 align-items-center g-2">
        <div class="col-md-6 col-lg-4">
            <div class="input-group input-group-sm shadow-sm rounded-pill overflow-hidden border">
                <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-secondary"></i></span>
                <input type="text" class="form-control border-0 bg-white" placeholder="Buscar operador... (Presiona '/' o 'F3')" data-search-input id="buscadorUsuarios">
            </div>
        </div>
        <div class="col-md-6 col-lg-8 text-md-end">
            <span class="badge rounded-pill <?= $onlineCount > 0 ? 'bg-success' : 'bg-secondary' ?> px-3 py-2 shadow-sm" title="Usuarios con actividad en los últimos 5 minutos">
                <span class="d-inline-block me-1" style="width:8px;height:8px;border-radius:50%;background:#fff;"></span>
                <?= (int)$onlineCount ?> en línea de <?= (int)$totalCount ?>
            </span>
        </div>
    </div>

    <!-- Grilla de Usuarios -->
    <div class="user-grid" id="userGrid">
        <?php if (empty($usuarios)): ?>
            <div class="col-12">
                <div class="alert alert-info border shadow-sm">
                    <i class="bi bi-info-circle me-2"></i> No se encontraron usuarios para monitorear en este entorno.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($usuarios as $u):
                // Clase del dot: online (verde pulsante) > suspended (cuenta inactiva) > offline
                if (!$u['activo']) {
                    $dotClass = 'suspended';
                    $dotTitle = 'Cuenta suspendida';
                } elseif ($u['online']) {
                    $dotClass = 'online';
                    $dotTitle = $u['is_current_user']
                        ? 'Vos (en línea ahora)'
                        : 'En línea · ' . $u['ultimo_acceso_desde'];
                } else {
                    $dotClass = 'offline';
                    $dotTitle = 'Offline · ' . $u['ultimo_acceso_desde'];
                }
            ?>
                <div class="user-card rxn-searchable-item <?= $u['online'] ? 'is-online' : '' ?>" style="cursor: grab;" data-id="<?= htmlspecialchars((string)$u['id']) ?>" data-search-content="<?= htmlspecialchars(strtolower($u['nombre'] . ' ' . $u['email'] . ' ' . $u['rol'])) ?>">
                    <div class="user-info-wrapper">
                        <div style="position: relative;">
                            <div class="user-avatar <?= htmlspecialchars($u['avatar_color']) ?>">
                                <?= htmlspecialchars($u['iniciales']) ?>
                            </div>
                            <div class="user-status-dot <?= htmlspecialchars($dotClass) ?>" title="<?= htmlspecialchars($dotTitle) ?>" data-bs-toggle="tooltip"></div>
                        </div>
                        <div class="overflow-hidden">
                            <h5 class="mb-0 text-truncate fw-bold" style="font-size: 1.1rem; color: var(--bs-heading-color);"><?= htmlspecialchars($u['nombre']) ?></h5>
                            <div class="text-truncate mt-1">
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($u['rol']) ?></span>
                            </div>
                            <div class="user-last-seen <?= $u['online'] ? 'online' : '' ?>">
                                <i class="bi <?= $u['online'] ? 'bi-circle-fill' : 'bi-clock-history' ?>" style="font-size:0.7rem;"></i>
                                <?= $u['online']
                                    ? ($u['is_current_user'] ? 'Vos, ahora' : 'En línea')
                                    : htmlspecialchars($u['ultimo_acceso_desde']) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-meta-strip">
                        <div class="user-meta-item">
                            <i class="bi bi-envelope"></i>
                            <span class="text-truncate"><?= htmlspecialchars($u['email']) ?></span>
                        </div>
                        <div class="user-meta-item">
                            <i class="bi bi-telephone"></i>
                            <span>Interno Anura: <strong><?= htmlspecialchars($u['anura_interno']) ?></strong></span>
                        </div>
                        <div class="user-meta-item">
                            <i class="bi bi-diagram-3"></i>
                            <span class="text-truncate">Tango: <strong><?= htmlspecialchars($u['tango_perfil']) ?></strong></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Integración Buscador
        const buscador = document.getElementById('buscadorUsuarios');
        const items = document.querySelectorAll('.rxn-searchable-item');

        if (buscador) {
            buscador.addEventListener('input', function(e) {
                const query = e.target.value.toLowerCase().trim();
                
                items.forEach(function(item) {
                    const content = item.getAttribute('data-search-content') || '';
                    if (query === '' || content.includes(query)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }

        // Integración SortableJS + LocalStorage
        const userGrid = document.getElementById('userGrid');
        if (userGrid && typeof Sortable !== 'undefined') {
            const savedOrder = JSON.parse(localStorage.getItem('crm_monitoreo_order') || '[]');
            
            // Si hay orden guardado, reposicionar tarjetas en el DOM
            if (savedOrder.length > 0) {
                const itemsArray = Array.from(userGrid.querySelectorAll('.rxn-searchable-item'));
                itemsArray.sort(function(a, b) {
                    const idA = a.getAttribute('data-id');
                    const idB = b.getAttribute('data-id');
                    let indexA = savedOrder.indexOf(idA);
                    let indexB = savedOrder.indexOf(idB);
                    // Si un item nuevo no está en el localstorage, lo mandamos al final
                    if (indexA === -1) indexA = 99999;
                    if (indexB === -1) indexB = 99999;
                    return indexA - indexB;
                });
                
                // Re-append forzará el orden en el DOM
                itemsArray.forEach(function(item) { userGrid.appendChild(item); });
            }

            // Inicializar drag & drop
            new Sortable(userGrid, {
                animation: 250,
                ghostClass: 'opacity-50',
                handle: '.user-card',
                forceFallback: true, // Forzar drag custom para evitar problemas con grid y drag nativo de HTML5
                onEnd: function () {
                    const currentOrder = [];
                    userGrid.querySelectorAll('.rxn-searchable-item').forEach(function(item) {
                        currentOrder.push(item.getAttribute('data-id'));
                    });
                    localStorage.setItem('crm_monitoreo_order', JSON.stringify(currentOrder));
                }
            });
        }
    });
</script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
