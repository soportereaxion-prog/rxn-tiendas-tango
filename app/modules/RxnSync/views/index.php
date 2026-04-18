<?php
$title = "RXN Sync - Consola Centralizada";
ob_start();
?>
<div class="container-fluid px-0">
    <?php
    $dashboardPath = strpos($_SERVER['REQUEST_URI'], '/crm/') !== false ? '/mi-empresa/crm/dashboard' : '/mi-empresa/dashboard';
    $basePath = strpos($_SERVER['REQUEST_URI'], '/crm/') !== false ? '/mi-empresa/crm/rxn-sync' : '/mi-empresa/rxn-sync';
    $syncCircuit = $syncCircuit ?? [];
    ?>
    <div class="rxn-module-header mb-4">
        <div>
            <h2><i class="fas fa-sync text-primary me-2"></i>RXN Sync</h2>
            <p class="text-muted">Consola central de sincronización bidireccional RXN ↔ Tango.</p>
        </div>
        <div class="rxn-module-actions d-flex gap-2">
            <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
        </div>
    </div>

    <?php $isCrm = ($syncCircuit['area'] ?? '') === 'crm'; ?>
    <div class="card shadow-sm border-0 mb-3 border-start border-4 border-info-subtle">
        <div class="card-body py-3 px-4">
            <div class="d-flex flex-wrap align-items-center gap-3 justify-content-between">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge text-bg-<?= !empty($syncCircuit['articulos_ready']) ? 'success' : 'secondary' ?>">1. Artículos <?= !empty($syncCircuit['articulos_ready']) ? 'listos' : 'pendientes' ?></span>
                    <?php if ($isCrm): ?>
                        <span class="badge text-bg-<?= (!empty($syncCircuit['listas_ready']) && !empty($syncCircuit['deposito_ready'])) ? 'success' : 'warning' ?>">2. Catálogos <?= (!empty($syncCircuit['listas_ready']) && !empty($syncCircuit['deposito_ready'])) ? 'cargados' : 'pendientes (correr Sync Catálogos)' ?></span>
                        <span class="badge text-bg-<?= !empty($syncCircuit['precios_ready']) ? 'success' : 'warning' ?>">3. Precios <?= !empty($syncCircuit['precios_ready']) ? 'habilitados' : 'requieren artículos + catálogos' ?></span>
                        <span class="badge text-bg-<?= !empty($syncCircuit['stock_ready']) ? 'success' : 'warning' ?>">4. Stock <?= !empty($syncCircuit['stock_ready']) ? 'habilitado' : 'requiere artículos + catálogos' ?></span>
                    <?php else: ?>
                        <span class="badge text-bg-<?= !empty($syncCircuit['precios_ready']) ? 'success' : 'warning' ?>">2. Precios <?= !empty($syncCircuit['precios_ready']) ? 'habilitados' : 'requieren artículos + listas' ?></span>
                        <span class="badge text-bg-<?= !empty($syncCircuit['stock_ready']) ? 'success' : 'warning' ?>">3. Stock <?= !empty($syncCircuit['stock_ready']) ? 'habilitado' : 'requiere artículos + depósito' ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2 small text-muted align-items-center">
                    <span>Vinculados: <strong><?= (int) ($syncCircuit['articulos_vinculados'] ?? 0) ?></strong><?= !empty($syncCircuit['articulos_total']) ? ' / ' . (int) $syncCircuit['articulos_total'] : '' ?></span>
                    <a href="<?= htmlspecialchars((string) ($syncCircuit['config_path'] ?? '/mi-empresa/configuracion')) ?>" class="btn btn-sm btn-outline-secondary">Configuración</a>
                    <?php if ($isCrm): ?>
                        <button type="button" id="btn-sync-catalogos" class="btn btn-sm btn-outline-warning <?= empty($syncCircuit['catalogos_ready']) ? 'disabled' : '' ?>" title="Sincroniza condiciones de venta, listas de precio, vendedores, transportes, depósitos y clasificaciones PDS desde Tango Connect. Prerequisito de Sync Precios y Sync Stock en CRM." <?= empty($syncCircuit['catalogos_ready']) ? 'disabled' : '' ?>><i class="bi bi-arrow-repeat"></i> Sync Catálogos</button>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars((string) ($syncCircuit['sync_precios_path'] ?? '/mi-empresa/sync/precios')) ?>" class="btn btn-sm btn-outline-info <?= empty($syncCircuit['precios_ready']) ? 'disabled' : '' ?>" <?= empty($syncCircuit['precios_ready']) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Sync Precios</a>
                    <a href="<?= htmlspecialchars((string) ($syncCircuit['sync_stock_path'] ?? '/mi-empresa/sync/stock')) ?>" class="btn btn-sm btn-outline-info <?= empty($syncCircuit['stock_ready']) ? 'disabled' : '' ?>" <?= empty($syncCircuit['stock_ready']) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Sync Stock</a>
                </div>
            </div>
        </div>
    </div>

    <?php $flash = \App\Core\Flash::get(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> alert-dismissible fade show shadow-sm mb-3" role="alert">
            <strong><?= $flash['type'] === 'success' ? 'OK' : 'Atención' ?></strong>
            <?= htmlspecialchars((string) $flash['message']) ?>
            <?php if (!empty($flash['stats'])): ?>
                <ul class="mb-0 mt-2 fs-6">
                    <?php if (isset($flash['stats']['recibidos'])): ?>
                        <li>Recibidos en capa de red: <b class="text-primary"><?= (int) $flash['stats']['recibidos'] ?></b></li>
                    <?php endif; ?>
                    <?php if (isset($flash['stats']['insertados'])): ?>
                        <li>Nuevos localmente: <b class="text-success"><?= (int) $flash['stats']['insertados'] ?></b></li>
                    <?php endif; ?>
                    <?php if (isset($flash['stats']['actualizados'])): ?>
                        <li>Actualizados: <b class="text-info"><?= (int) $flash['stats']['actualizados'] ?></b></li>
                    <?php endif; ?>
                    <?php if (isset($flash['stats']['omitidos'])): ?>
                        <li>Omitidos: <b class="text-secondary"><?= (int) $flash['stats']['omitidos'] ?></b></li>
                    <?php endif; ?>
                    <?php if (isset($flash['stats']['sin_match'])): ?>
                        <li>Sin match local: <b class="text-warning"><?= (int) $flash['stats']['sin_match'] ?></b></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Pestañas -->
    <ul class="nav nav-pills mb-0" id="syncTabs" role="tablist" data-base-path="<?= htmlspecialchars($basePath) ?>">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-4 rounded-pill me-2 fw-medium" id="clientes-tab"
                    data-bs-toggle="pill" data-bs-target="#clientes" type="button" role="tab"
                    aria-selected="true"
                    data-url="<?= htmlspecialchars($basePath) ?>/clientes/list"
                    data-entidad="cliente"
                    data-search-id="rxnsync-search-cli"
                    data-filter-id="rxnsync-filter-estado-cli"
                    data-tbody-id="rxnsync-tbody-cli"
                    data-table-id="rxnsync-table-cli"
                    data-count-id="rxnsync-cli-count">
                <i class="fas fa-users me-2"></i>Clientes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 rounded-pill fw-medium" id="articulos-tab"
                    data-bs-toggle="pill" data-bs-target="#articulos" type="button" role="tab"
                    aria-selected="false"
                    data-url="<?= htmlspecialchars($basePath) ?>/articulos/list"
                    data-entidad="articulo"
                    data-search-id="rxnsync-search-art"
                    data-filter-id="rxnsync-filter-estado-art"
                    data-tbody-id="rxnsync-tbody-art"
                    data-table-id="rxnsync-table-art"
                    data-count-id="rxnsync-art-count">
                <i class="fas fa-box me-2"></i>Artículos
            </button>
        </li>
        <?php if ($isCrm): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 rounded-pill fw-medium" id="pedidos-tab"
                    data-bs-toggle="pill" data-bs-target="#pedidos" type="button" role="tab"
                    aria-selected="false"
                    data-url="<?= htmlspecialchars($basePath) ?>/pedidos/list"
                    data-entidad="pedido">
                <i class="fas fa-file-invoice me-2"></i>Pedidos
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Barra de acciones masivas (oculta por defecto) -->
    <div id="rxnsync-bulk-bar" class="d-none border rounded-bottom mb-3 px-3 py-2 d-flex align-items-center gap-3 bg-dark text-light" style="border-top:none!important;">
        <span class="fw-semibold text-warning" id="rxnsync-selected-count">0 seleccionados</span>
        <div class="vr opacity-50"></div>
        <button class="btn btn-sm btn-outline-warning" id="btn-bulk-push" title="Push seleccionados hacia Tango">
            <i class="bi bi-cloud-upload me-1"></i> Push ↑ Seleccionados
        </button>
        <button class="btn btn-sm btn-outline-info" id="btn-bulk-pull" title="Pull: traer datos desde Tango a RXN">
            <i class="bi bi-cloud-download me-1"></i> Pull ↓ Seleccionados
        </button>
        <button class="btn btn-sm btn-outline-secondary ms-auto" id="btn-bulk-audit-tab" title="Auditoría del tab activo">
            <i class="fas fa-bolt me-1"></i> Auditoría Tab Activo
        </button>
        <button class="btn btn-sm btn-outline-danger" id="btn-bulk-clear">
            <i class="bi bi-x"></i> Cancelar
        </button>
    </div>

    <!-- Barra de acciones del tab (visible siempre, colapsa cuando hay selección) -->
    <div id="rxnsync-tab-actions" class="mb-3 d-flex align-items-center justify-content-between border rounded px-3 py-2 bg-dark-subtle">
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button class="btn btn-sm btn-warning fw-semibold" id="btn-audit-tab-main" style="background: linear-gradient(135deg,#f7b733,#fc4a1a);border:0;" title="Importa desde Tango (upsert local) y luego audita el vínculo por código. Flujo completo recomendado para tenants nuevos o sincronización periódica.">
                <i class="fas fa-bolt me-1"></i> <span id="audit-tab-label">Sincronizar desde Tango</span>
            </button>
            <button class="btn btn-sm btn-outline-primary" id="btn-only-import-main" title="Solo trae datos desde Tango (upsert local). No ejecuta la auditoría de vínculo.">
                <i class="bi bi-cloud-download me-1"></i> Solo importar
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btn-only-audit-main" title="Solo match suave por código contra Tango. No trae datos nuevos. Útil para revalidar vínculos existentes.">
                <i class="fas fa-search me-1"></i> Solo auditar
            </button>
            <small class="text-muted ms-2">Import trae datos nuevos • Auditar revalida vínculos • Sincronizar hace ambos.</small>
        </div>
    </div>

    <!-- Barra de progreso global -->
    <div id="rxnsync-progress-bar" class="d-none mb-2">
        <div class="progress" style="height:4px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-info w-100" role="progressbar"></div>
        </div>
    </div>

    <!-- Contenido Pestañas -->
    <div class="tab-content" id="syncTabsContent">
        <div class="tab-pane fade show active" id="clientes" role="tabpanel" aria-labelledby="clientes-tab">
            <div class="card shadow-sm border-0 border-top border-primary border-3">
                <div class="card-body p-4 text-center text-muted" id="clientes-content">
                    <div class="spinner-border text-primary my-4" role="status"></div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="articulos" role="tabpanel" aria-labelledby="articulos-tab">
            <div class="card shadow-sm border-0 border-top border-primary border-3">
                <div class="card-body p-4 text-center text-muted" id="articulos-content">
                    <!-- Se carga via Ajax -->
                </div>
            </div>
        </div>

        <?php if ($isCrm): ?>
        <div class="tab-pane fade" id="pedidos" role="tabpanel" aria-labelledby="pedidos-tab">
            <div class="card shadow-sm border-0 border-top border-primary border-3">
                <div class="card-body p-4 text-center text-muted" id="pedidos-content">
                    <!-- Se carga via Ajax -->
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    var basePath   = document.getElementById('syncTabs').getAttribute('data-base-path');
    var bulkBar    = document.getElementById('rxnsync-bulk-bar');
    var tabBar     = document.getElementById('rxnsync-tab-actions');
    var countEl    = document.getElementById('rxnsync-selected-count');
    var auditLabel = document.getElementById('audit-tab-label');
    var progressBar = document.getElementById('rxnsync-progress-bar');

    // Rows-por-página por tab (estado). Sort persiste en localStorage (sobrevive navegación).
    function loadSortState(key) {
        try {
            return {
                page    : 1,
                perPage : 25,
                sort    : localStorage.getItem('rxnsync_sort_' + key) || null,
                dir     : parseInt(localStorage.getItem('rxnsync_dir_' + key) || '1'),
            };
        } catch(e) { return { page: 1, perPage: 25, sort: null, dir: 1 }; }
    }
    function saveSortState(key, ps) {
        try {
            if (ps.sort) { localStorage.setItem('rxnsync_sort_' + key, ps.sort); localStorage.setItem('rxnsync_dir_' + key, ps.dir); }
            else { localStorage.removeItem('rxnsync_sort_' + key); localStorage.removeItem('rxnsync_dir_' + key); }
        } catch(e) {}
    }
    var pageState = { clientes: loadSortState('clientes'), articulos: loadSortState('articulos'), pedidos: loadSortState('pedidos') };

    // Filtros persistidos por tab — sobreviven cambios de solapa
    var tabColFilters  = { clientes: {}, articulos: {}, pedidos: {} };       // embudos client-side
    var tabSearchState = { clientes: { search: '', estado: '' }, articulos: { search: '', estado: '' }, pedidos: { search: '', estado: '' } }; // búsqueda + estado
    var tabBdParams    = { clientes: null, articulos: null, pedidos: null };    // Motor BD (URLSearchParams)

    // ── Helpers ──────────────────────────────────────────────────────
    function showProgress() { if (progressBar) progressBar.classList.remove('d-none'); }
    function hideProgress() { if (progressBar) progressBar.classList.add('d-none'); }

    function getActiveTabBtn() {
        return document.querySelector('#syncTabs button.active');
    }

    function getActiveEntidad() {
        var btn = getActiveTabBtn();
        return btn ? btn.getAttribute('data-entidad') : 'articulo';
    }

    function getActiveTabKey() {
        var btn = getActiveTabBtn();
        if (!btn) return 'articulos';
        var ent = btn.getAttribute('data-entidad');
        if (ent === 'cliente') return 'clientes';
        if (ent === 'pedido')  return 'pedidos';
        return 'articulos';
    }

    function getActiveContentRoot() {
        var key = getActiveTabKey();
        return document.getElementById(key + '-content') || document;
    }

    function fetchJson(url, options) {
        return fetch(url, options).then(function(r) {
            return r.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    var raw = (text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                    throw new Error(raw || 'La respuesta no vino en JSON válido.');
                }
            });
        });
    }

    function renderJsonBlock(label, data) {
        if (!data) return '';
        var json = JSON.stringify(data, null, 2).replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return '<details style="margin-top:8px;cursor:pointer;">'
            + '<summary style="font-size:.8rem;color:#aaa;">' + label + '</summary>'
            + '<pre style="font-size:.7rem;max-height:200px;overflow:auto;background:#111;color:#0f0;padding:8px;border-radius:4px;margin-top:4px;text-align:left;">'
            + json
            + '</pre></details>';
    }

    function payloadHtml(meta, snapshot, summaryLabel) {
        var lines = [
            '<strong>Estado:</strong> ' + (meta.estado || '—'),
            '<strong>Dirección:</strong> ' + (meta.direccion || '—').toUpperCase()
                + ' <span class="badge bg-' + (meta.resultado === 'ok' ? 'success' : 'danger') + ' ms-1">'
                + (meta.resultado || '—').toUpperCase() + '</span>',
            '<strong>Tango ID:</strong> #' + (meta.tango_id || '?'),
            '<strong>Fecha:</strong> ' + (meta.fecha || '—')
        ];
        if (meta.error) lines.push('<strong class="text-danger">Error:</strong> ' + meta.error);
        var html = '<div style="font-size:.85rem;margin-bottom:8px;">' + lines.join('<br>') + '</div>';
        if (snapshot) {
            html += renderJsonBlock(summaryLabel || 'Snapshot Tango', snapshot);
        }
        return html;
    }

    function getSelectedIds() {
        return Array.from(getActiveContentRoot().querySelectorAll('.rxnsync-row-check:checked'))
                    .map(function(c) { return c.getAttribute('data-id'); });
    }

    function showAlert(msg, type, title) {
        if (typeof window.rxnAlert === 'function') { window.rxnAlert(msg, type, title || 'RXN Sync'); }
        else { alert(msg); }
    }

    function showConfirm(msg, type, cbOk, okText) {
        if (typeof window.rxnConfirm === 'function') {
            window.rxnConfirm({ message: msg, type: type || 'warning', title: 'Confirmar Sync',
                         okText: okText || 'Confirmar', okClass: 'btn-' + (type || 'warning'), onConfirm: cbOk });
        } else {
            if (confirm(msg)) cbOk();
        }
    }

    // ── Cargar tab ───────────────────────────────────────────────────
    function loadTabContent(btn, resetPage) {
        var targetId  = btn.getAttribute('data-bs-target').substring(1);
        var url       = btn.getAttribute('data-url');
        var entidad   = btn.getAttribute('data-entidad');
        var container = document.getElementById(targetId + '-content');
        var tabKey    = entidad === 'cliente' ? 'clientes' : (entidad === 'pedido' ? 'pedidos' : 'articulos');

        // Guardar BD params del tab saliente ANTES de limpiar la URL
        var leavingTabKey = getActiveTabKey();
        if (leavingTabKey !== tabKey && window.location.search && window.location.search.length > 1) {
            tabBdParams[leavingTabKey] = new URLSearchParams(window.location.search);
        } else if (leavingTabKey !== tabKey) {
            tabBdParams[leavingTabKey] = null;
        }

        // Limpiar params de filtro Motor BD de la URL al cambiar/recargar tab
        if (window.location.search) {
            history.replaceState(null, '', location.pathname);
        }

        if (resetPage) pageState[tabKey].page = 1;

        // Mostrar/ocultar botones de acciones según tab activo.
        // Pedidos es read-only (solo pull de estado), no aplica push/import/audit clásico.
        var btnSyncMain    = document.getElementById('btn-audit-tab-main');
        var btnOnlyImport  = document.getElementById('btn-only-import-main');
        var btnOnlyAudit   = document.getElementById('btn-only-audit-main');
        var isPedido = entidad === 'pedido';
        if (btnOnlyImport) btnOnlyImport.style.display = isPedido ? 'none' : '';
        if (btnOnlyAudit)  btnOnlyAudit.style.display  = isPedido ? 'none' : '';

        if (auditLabel) {
            if (entidad === 'articulo') auditLabel.textContent = 'Sincronizar Artículos';
            else if (entidad === 'cliente') auditLabel.textContent = 'Sincronizar Clientes';
            else if (entidad === 'pedido')  auditLabel.textContent = 'Sincronizar Estados de Pedidos';
        }

        container.innerHTML = '<div class="spinner-border text-primary my-4" role="status"></div>';
        showProgress();

        // Si hay BD params guardados para este tab, cargar directamente con ellos
        var savedBdParams = tabBdParams[tabKey];
        var fetchUrl = (savedBdParams && savedBdParams.toString()) ? url + '?' + savedBdParams.toString() : url;

        fetch(fetchUrl)
            .then(function(r) {
                if (!r.ok) throw new Error('Error al cargar el tab (' + r.status + ')');
                return r.text();
            })
            .then(function(html) {
                container.innerHTML = html;
                hideProgress();
                rebindCheckboxes();
                initTabControls(btn);
                // Restaurar la URL con los BD params del tab si había filtro activo
                if (savedBdParams && savedBdParams.toString()) {
                    history.replaceState(null, '', location.pathname + '?' + savedBdParams.toString());
                }
                if (typeof window.rxnFiltersInit === 'function') window.rxnFiltersInit();
            })
            .catch(function(err) {
                hideProgress();
                container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + err.message + '</div>';
            });
    }

    // ── Controles del tab (búsqueda, filtro, sort, paginación) ───────
    // Esta función se ejecuta DESPUÉS de inyectar el HTML via fetch
    function initTabControls(btn) {
        var entidad  = btn.getAttribute('data-entidad');
        // El tab Pedidos es auto-contenido: su HTML ya trae su propio <script>
        // que maneja búsqueda/filtro. No usamos el framework de control del index
        // porque pedidos no tiene checkboxes, push/pull ni filtros Motor BD.
        if (entidad === 'pedido') return;
        var tabKey   = entidad === 'cliente' ? 'clientes' : 'articulos';
        var suffix   = entidad === 'cliente' ? 'cli' : 'art';

        var tbody    = document.getElementById('rxnsync-tbody-' + suffix);
        var searchI  = document.getElementById('rxnsync-search-' + suffix);
        var filterS  = document.getElementById('rxnsync-filter-estado-' + suffix);
        var countEl2 = document.getElementById('rxnsync-' + suffix + '-count');
        var table    = document.getElementById('rxnsync-table-' + suffix);

        if (!tbody) return;

        var ps = pageState[tabKey];
        var allRows = Array.from(tbody.querySelectorAll('tr[data-nombre]'));
        var filteredRows = allRows.slice();

        // ── Conteo y paginación ──────────────────────────────────
        function renderPage() {
            var start = (ps.page - 1) * ps.perPage;
            var end   = start + ps.perPage;
            allRows.forEach(function(r) { r.style.display = 'none'; });
            filteredRows.slice(start, end).forEach(function(r) { r.style.display = ''; });
            if (countEl2) {
                countEl2.textContent = filteredRows.length + ' ' + (entidad === 'cliente' ? 'cliente' : 'artículo') + (filteredRows.length !== 1 ? 's' : '');
            }
            renderPagination(tabKey, filteredRows.length);
        }

        function renderPagination(tabKey, total) {
            var paginationId = 'rxnsync-pagination-' + tabKey;
            var existing = document.getElementById(paginationId);
            if (existing) existing.remove();

            var ps2 = pageState[tabKey];
            var totalPages = Math.ceil(total / ps2.perPage);
            if (totalPages <= 1) return;

            var nav = document.createElement('nav');
            nav.id = paginationId;
            nav.className = 'mt-3 d-flex justify-content-between align-items-center';

            var info = document.createElement('small');
            info.className = 'text-muted';
            info.textContent = 'Pág. ' + ps2.page + ' / ' + totalPages + ' · ' + total + ' registros';

            var ul = document.createElement('ul');
            ul.className = 'pagination pagination-sm mb-0';

            function mkLi(label, page, disabled, active) {
                var li = document.createElement('li');
                li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
                var a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.innerHTML = label;
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!disabled) { ps2.page = page; renderPage(); }
                });
                li.appendChild(a);
                return li;
            }

            ul.appendChild(mkLi('&laquo;', ps2.page - 1, ps2.page === 1));

            var start = Math.max(1, ps2.page - 2);
            var end   = Math.min(totalPages, ps2.page + 2);
            if (start > 1) ul.appendChild(mkLi('1', 1, false, false));
            if (start > 2) ul.appendChild(mkLi('...', null, true));

            for (var p = start; p <= end; p++) {
                ul.appendChild(mkLi(String(p), p, false, p === ps2.page));
            }

            if (end < totalPages - 1) ul.appendChild(mkLi('...', null, true));
            if (end < totalPages) ul.appendChild(mkLi(String(totalPages), totalPages, false, false));
            ul.appendChild(mkLi('&raquo;', ps2.page + 1, ps2.page === totalPages));

            nav.appendChild(info);
            nav.appendChild(ul);

            // Agregar select de items por página
            var perPageSel = document.createElement('div');
            perPageSel.className = 'd-flex align-items-center gap-1';
            perPageSel.innerHTML = '<small class="text-muted">Mostrar</small>';
            var sel = document.createElement('select');
            sel.className = 'form-select form-select-sm';
            sel.style.width = '70px';
            [25, 50, 100, 250].forEach(function(n) {
                var opt = document.createElement('option');
                opt.value = n; opt.textContent = n;
                if (n === ps2.perPage) opt.selected = true;
                sel.appendChild(opt);
            });
            sel.addEventListener('change', function() {
                ps2.perPage = parseInt(this.value);
                ps2.page = 1;
                renderPage();
            });
            perPageSel.appendChild(sel);
            nav.insertBefore(perPageSel, nav.firstChild);

            // Adjuntar debajo de la tabla
            var tableEl = table;
            if (tableEl && tableEl.parentNode) {
                tableEl.parentNode.insertAdjacentElement('afterend', nav);
            }
        }

        // ── Filtro ────────────────────────────────────────────────
        function applyFilter() {
            var term   = searchI ? (searchI.value || '').toLowerCase() : '';
            var estado = filterS ? filterS.value : '';
            filteredRows = allRows.filter(function(row) {
                var matchText  = !term || (row.dataset.nombre || '').includes(term) || (row.dataset.codigo || '').includes(term);
                var matchState = !estado || row.dataset.estado === estado;
                return matchText && matchState;
            });
            // Aplicar sort actual
            if (ps.sort) {
                filteredRows.sort(function(a, b) {
                    var va = a.dataset[ps.sort] || '';
                    var vb = b.dataset[ps.sort] || '';
                    return va.localeCompare(vb, 'es', { numeric: true }) * ps.dir;
                });
            }
            ps.page = 1;
            renderPage();
        }

        // Restaurar valores guardados al recargar el tab
        if (searchI) searchI.value = tabSearchState[tabKey].search;
        if (filterS) filterS.value = tabSearchState[tabKey].estado;

        if (searchI) searchI.addEventListener('input', function() {
            tabSearchState[tabKey].search = searchI.value;
            applyFilter();
        });
        if (filterS) filterS.addEventListener('change', function() {
            tabSearchState[tabKey].estado = filterS.value;
            applyFilter();
        });

        // ── Ordenamiento de columnas ──────────────────────────────
        if (table) {
            table.querySelectorAll('.rxnsync-sortable').forEach(function(th) {
                th.style.cursor = 'pointer';

                // Restaurar icono si hay sort persistido
                if (ps.sort === th.dataset.col) {
                    var icInit = th.querySelector('.rxnsync-sort-icon');
                    if (icInit) icInit.textContent = ps.dir === 1 ? ' ▲' : ' ▼';
                    // Aplicar sort inicial
                    filteredRows.sort(function(a, b) {
                        var va = a.dataset[ps.sort] || '';
                        var vb = b.dataset[ps.sort] || '';
                        return va.localeCompare(vb, 'es', { numeric: true }) * ps.dir;
                    });
                    allRows.sort(function(a, b) {
                        var va = a.dataset[ps.sort] || '';
                        var vb = b.dataset[ps.sort] || '';
                        return va.localeCompare(vb, 'es', { numeric: true }) * ps.dir;
                    });
                }

                th.addEventListener('click', function() {
                    var col = th.dataset.col;
                    ps.dir = (ps.sort === col) ? ps.dir * -1 : 1;
                    ps.sort = col;
                    saveSortState(tabKey, ps);

                    table.querySelectorAll('.rxnsync-sort-icon').forEach(function(ic) { ic.textContent = ''; });
                    var icon = th.querySelector('.rxnsync-sort-icon');
                    if (icon) icon.textContent = ps.dir === 1 ? ' ▲' : ' ▼';

                    filteredRows.sort(function(a, b) {
                        var va = a.dataset[col] || '';
                        var vb = b.dataset[col] || '';
                        return va.localeCompare(vb, 'es', { numeric: true }) * ps.dir;
                    });
                    allRows.sort(function(a, b) {
                        var va = a.dataset[col] || '';
                        var vb = b.dataset[col] || '';
                        return va.localeCompare(vb, 'es', { numeric: true }) * ps.dir;
                    });
                    renderPage();
                });
            });
        }

        // ── Filtros de columna (embudo) ──────────────────────────────────
        var colFilters = tabColFilters[tabKey]; // Referencia al estado persistido por tab

        function applyColFilter() {
            filteredRows = allRows.filter(function(row) {
                // Filtro de búsqueda + estado ya aplicados en applyFilter
                var term   = searchI ? (searchI.value || '').toLowerCase() : '';
                var estado = filterS ? filterS.value : '';
                var matchText  = !term || (row.dataset.nombre || '').includes(term) || (row.dataset.codigo || '').includes(term);
                var matchState = !estado || row.dataset.estado === estado;
                if (!matchText || !matchState) return false;

                // Filtros de columna adicionales
                for (var col in colFilters) {
                    var f = colFilters[col];
                    if (!f.val) continue;
                    var cellVal = (row.dataset[col] || '').toLowerCase();
                    var fVal    = f.val.toLowerCase();
                    var pass = false;
                    if (f.op === 'contiene')      pass = cellVal.includes(fVal);
                    else if (f.op === 'no_cont')  pass = !cellVal.includes(fVal);
                    else if (f.op === 'empieza')  pass = cellVal.startsWith(fVal);
                    else if (f.op === 'termina')  pass = cellVal.endsWith(fVal);
                    else if (f.op === 'igual')    pass = cellVal === fVal;
                    else if (f.op === 'distinto') pass = cellVal !== fVal;
                    if (!pass) return false;
                }
                return true;
            });
            if (ps.sort) {
                filteredRows.sort(function(a, b) {
                    var va = a.dataset[ps.sort] || '';
                    var vb = b.dataset[ps.sort] || '';
                    return va.localeCompare(vb, 'es', { numeric: true }) * ps.dir;
                });
            }
            ps.page = 1;
            renderPage();
        }

        if (table) {
            // CSS para los popovers (inyectar una sola vez)
            if (!document.getElementById('rxnsync-col-filter-css')) {
                var styleEl = document.createElement('style');
                styleEl.id  = 'rxnsync-col-filter-css';
                styleEl.textContent = [
                    '.rxnsync-col-popover { position:absolute; top:100%; right:0; z-index:1055; min-width:220px;',
                    '  background:var(--bs-body-bg,#fff); border:1px solid rgba(0,0,0,.15);',
                    '  box-shadow:0 .5rem 1rem rgba(0,0,0,.15); border-radius:.375rem;',
                    '  padding:.75rem; display:none; font-weight:normal; font-size:.85rem; }',
                    '.rxnsync-col-popover.show { display:block; }',
                    '.rxnsync-funnel { cursor:pointer; opacity:.3; font-size:.8rem; margin-left:4px;',
                    '  transition:opacity .2s; vertical-align:middle; }',
                    '.rxnsync-sortable:hover .rxnsync-funnel { opacity:.7; }',
                    '.rxnsync-funnel.active { opacity:1; color:#0d6efd; }',
                ].join('\n');
                document.head.appendChild(styleEl);
            }

            // Cerrar popovers al click afuera
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.rxnsync-col-popover') && !e.target.closest('.rxnsync-funnel')) {
                    table.querySelectorAll('.rxnsync-col-popover.show').forEach(function(p) { p.classList.remove('show'); });
                }
            });

            table.querySelectorAll('.rxnsync-sortable[data-col]').forEach(function(th) {
                // Columnas con rxn-filter-col usan el sistema Motor BD — saltar el embudo inline
                if (th.classList.contains('rxn-filter-col')) return;
                th.style.position = 'relative';
                var col = th.dataset.col;

                var funnel = document.createElement('i');
                funnel.className = 'bi bi-funnel rxnsync-funnel';
                funnel.title = 'Filtrar columna';

                var popover = document.createElement('div');
                popover.className = 'rxnsync-col-popover text-start';
                popover.onclick = function(e) { e.stopPropagation(); };
                popover.innerHTML = [
                    '<div class="mb-2 fw-bold">Filtrar columna</div>',
                    '<select class="form-select form-select-sm mb-2" id="rxnsync-fop-' + col + '">',
                    '  <option value="contiene">Contiene</option>',
                    '  <option value="no_cont">No contiene</option>',
                    '  <option value="empieza">Empieza con</option>',
                    '  <option value="termina">Termina con</option>',
                    '  <option value="igual">Igual</option>',
                    '  <option value="distinto">Distinto</option>',
                    '</select>',
                    '<input type="text" class="form-control form-control-sm mb-3" id="rxnsync-fval-' + col + '" placeholder="Valor...">',
                    '<div class="d-flex gap-2">',
                    '  <button type="button" class="btn btn-sm btn-outline-danger w-50 rxnsync-fc-clear">Eliminar</button>',
                    '  <button type="button" class="btn btn-sm btn-primary w-50 rxnsync-fc-apply">Aplicar</button>',
                    '</div>',
                ].join('');

                funnel.onclick = function(e) {
                    e.stopPropagation();
                    table.querySelectorAll('.rxnsync-col-popover.show').forEach(function(p) {
                        if (p !== popover) p.classList.remove('show');
                    });
                    popover.classList.toggle('show');
                    if (popover.classList.contains('show')) {
                        // Restaurar valores actuales
                        var f = colFilters[col] || {};
                        var opSel = popover.querySelector('#rxnsync-fop-' + col);
                        var valIn = popover.querySelector('#rxnsync-fval-' + col);
                        if (f.op && opSel) opSel.value = f.op;
                        if (valIn) { valIn.value = f.val || ''; valIn.focus(); }
                    }
                };

                popover.querySelector('.rxnsync-fc-apply').onclick = function() {
                    var op  = popover.querySelector('#rxnsync-fop-' + col).value;
                    var val = popover.querySelector('#rxnsync-fval-' + col).value.trim();
                    if (val) {
                        colFilters[col] = { op: op, val: val };
                        funnel.className = 'bi bi-funnel-fill rxnsync-funnel active';
                    } else {
                        delete colFilters[col];
                        funnel.className = 'bi bi-funnel rxnsync-funnel';
                    }
                    popover.classList.remove('show');
                    applyColFilter();
                };

                popover.querySelector('.rxnsync-fc-clear').onclick = function() {
                    delete colFilters[col];
                    funnel.className = 'bi bi-funnel rxnsync-funnel';
                    popover.classList.remove('show');
                    applyColFilter();
                };

                popover.querySelector('input').addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); popover.querySelector('.rxnsync-fc-apply').click(); }
                });

                // Restaurar ícono activo si hay filtro guardado para esta columna
                if (colFilters[col] && colFilters[col].val) {
                    funnel.className = 'bi bi-funnel-fill rxnsync-funnel active';
                }

                th.appendChild(funnel);
                th.appendChild(popover);
            });
        }

        // Render inicial: aplica todos los filtros persistidos (texto, estado, columna, sort)
        applyColFilter();
    }

    // ── Tab activo cambia ────────────────────────────────────────────
    document.querySelectorAll('#syncTabs button[data-bs-toggle="pill"]').forEach(function(btn) {
        btn.addEventListener('show.bs.tab', function(e) {
            loadTabContent(e.target, true);
            clearSelection();
        });
    });

    // ── Checkboxes y selección ────────────────────────────────────────
    function rebindCheckboxes() {
        var root = getActiveContentRoot();
        var selectAllId = getActiveTabKey() === 'clientes' ? 'rxnsync-select-all-cli' : 'rxnsync-select-all-art';
        var selectAll = document.getElementById(selectAllId);
        if (selectAll) {
            var fresh = selectAll.cloneNode(true);
            selectAll.parentNode.replaceChild(fresh, selectAll);
            fresh.addEventListener('change', function() {
                var visibleChecks = Array.from(root.querySelectorAll('.rxnsync-row-check'))
                    .filter(function(c) {
                        var row = c.closest('tr');
                        return row && row.style.display !== 'none';
                    });
                visibleChecks.forEach(function(c) { c.checked = fresh.checked; });
                updateBulkBar();
            });
        }
        root.querySelectorAll('.rxnsync-row-check').forEach(function(c) {
            c.addEventListener('change', updateBulkBar);
        });
    }

    function updateBulkBar() {
        var ids = getSelectedIds();
        var count = ids.length;
        if (count > 0) {
            bulkBar.classList.remove('d-none');
            bulkBar.classList.add('d-flex');
            tabBar.classList.add('d-none');
            countEl.textContent = count + ' seleccionado' + (count !== 1 ? 's' : '');
        } else {
            clearSelection();
        }
    }

    function clearSelection() {
        document.querySelectorAll('.rxnsync-row-check').forEach(function(c) { c.checked = false; });
        ['rxnsync-select-all-cli', 'rxnsync-select-all-art'].forEach(function(id) {
            var sa = document.getElementById(id);
            if (sa) sa.checked = false;
        });
        bulkBar.classList.add('d-none');
        bulkBar.classList.remove('d-flex');
        tabBar.classList.remove('d-none');
    }

    document.getElementById('btn-bulk-clear').addEventListener('click', clearSelection);

    // ── Helper común: POST AJAX a un endpoint del módulo con feedback visual ──
    function runRxnSyncAjax(opts) {
        // opts: { endpoint, btnEl, busyLabel, confirmMsg, confirmLabel, successTitle, errorTitle }
        var btn      = opts.btnEl;
        var origHtml = btn ? btn.innerHTML : '';

        showConfirm(
            opts.confirmMsg,
            'warning',
            function () {
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> ' + opts.busyLabel;
                }
                showProgress();

                fetch(basePath + '/' + opts.endpoint, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    hideProgress();
                    if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
                    if (data.success) {
                        showAlert(data.message, 'success', opts.successTitle);
                        var activeTab = getActiveTabBtn();
                        if (activeTab) loadTabContent(activeTab, true);
                    } else {
                        showAlert(data.message, 'danger', opts.errorTitle);
                    }
                })
                .catch(function(err) {
                    hideProgress();
                    if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
                    showAlert(err.message || 'Error de red', 'danger', opts.errorTitle);
                });
            },
            opts.confirmLabel
        );
    }

    // ── Sincronización completa (Import + Audit) del tab activo ──────────────
    function runSyncFullTab() {
        var tabBtn  = getActiveTabBtn();
        var entidad = tabBtn ? tabBtn.getAttribute('data-entidad') : 'articulo';

        if (entidad === 'pedido') {
            runRxnSyncAjax({
                endpoint:     'sync-pedidos-estados',
                btnEl:        document.getElementById('btn-audit-tab-main'),
                busyLabel:    'Sincronizando estados...',
                confirmMsg:   '¿Sincronizar estados de todos los PDS enviados a Tango?\n\nPagina el listado de pedidos de Tango y actualiza Aprobado / Cumplido / Cerrado / Anulado. Puede tardar según el volumen de pedidos en Tango.',
                confirmLabel: 'Sincronizar Estados',
                successTitle: 'Estados sincronizados',
                errorTitle:   'Error en Sync de Estados'
            });
            return;
        }

        var label = entidad === 'articulo' ? 'Artículos' : 'Clientes';
        runRxnSyncAjax({
            endpoint:     'sync-full-' + entidad + 's',
            btnEl:        document.getElementById('btn-audit-tab-main'),
            busyLabel:    'Sincronizando...',
            confirmMsg:   '¿Sincronizar ' + label + ' desde Tango?\n\nEsto traerá datos nuevos/actualizados desde Tango y luego auditará los vínculos por código. Puede tardar varios segundos o minutos según el catálogo.',
            confirmLabel: 'Sincronizar Ahora',
            successTitle: 'Sincronización Completa',
            errorTitle:   'Error en Sincronización'
        });
    }

    // ── Solo auditar (match suave contra Tango, sin import) ─────────────────
    function runOnlyAuditTab() {
        var tabBtn  = getActiveTabBtn();
        var entidad = tabBtn ? tabBtn.getAttribute('data-entidad') : 'articulo';
        var label   = entidad === 'articulo' ? 'Artículos' : 'Clientes';

        runRxnSyncAjax({
            endpoint:     'auditar-' + entidad + 's',
            btnEl:        document.getElementById('btn-only-audit-main'),
            busyLabel:    'Auditando...',
            confirmMsg:   '¿Iniciar Auditoría Pull de ' + label + ' desde Tango?\n\nSolo revalida los vínculos existentes por código contra Tango. No trae datos nuevos.',
            confirmLabel: 'Iniciar Auditoría',
            successTitle: 'Auditoría Completada',
            errorTitle:   'Error en Auditoría'
        });
    }

    // ── Solo importar (redirige al TangoSyncController con ?return=) ────────
    // No es AJAX porque el TangoSyncController es screen-based con Flash al volver.
    function runOnlyImportTab() {
        var tabBtn  = getActiveTabBtn();
        var entidad = tabBtn ? tabBtn.getAttribute('data-entidad') : 'articulo';
        var label   = entidad === 'articulo' ? 'Artículos' : 'Clientes';

        // Gate: Tiendas no tiene endpoint de import de clientes (solo existe /mi-empresa/crm/sync/clientes).
        // Si llegamos acá en área Tiendas con tab Clientes, explicamos y abortamos.
        var isCrm = basePath.indexOf('/crm/') !== -1;
        if (!isCrm && entidad === 'cliente') {
            showAlert('El import masivo de clientes desde Tango solo está disponible en el área CRM. Usá "Sincronizar desde Tango" (que también audita) o hacé el Pull individual por fila.', 'warning', 'No disponible en Tiendas');
            return;
        }

        showConfirm(
            '¿Importar ' + label + ' desde Tango?\n\nTrae y upserta los registros sin auditar el vínculo. Al terminar volvés a esta pantalla con un resumen.',
            'warning',
            function () {
                // basePath viene de data-base-path del #syncTabs (ej: /mi-empresa/rxn-sync o /mi-empresa/crm/rxn-sync)
                // Derivamos la ruta del TangoSyncController a partir del basePath:
                //   /mi-empresa/rxn-sync       → /mi-empresa/sync/articulos
                //   /mi-empresa/crm/rxn-sync   → /mi-empresa/crm/sync/articulos|clientes
                var syncBase = basePath.replace(/\/rxn-sync$/, '/sync');
                var returnTo = encodeURIComponent(basePath);
                window.location.href = syncBase + '/' + entidad + 's?return=' + returnTo;
            },
            'Importar Ahora'
        );
    }

    document.getElementById('btn-audit-tab-main').addEventListener('click', runSyncFullTab);
    document.getElementById('btn-bulk-audit-tab').addEventListener('click', runSyncFullTab);
    document.getElementById('btn-only-audit-main').addEventListener('click', runOnlyAuditTab);
    document.getElementById('btn-only-import-main').addEventListener('click', runOnlyImportTab);

    // ── Sync Catálogos (solo CRM) ────────────────────────────────────────────
    // Trae condiciones de venta, listas de precio, vendedores, transportes y depósitos
    // desde Tango Connect y los persiste en crm_catalogo_comercial_items. Prerequisito
    // para que Sync Precios y Sync Stock puedan correr en CRM.
    //
    // Defensivo: (1) URL derivada de basePath (NO hardcoded) para sobrevivir a cambios
    // de prefijo o multi-tenant. (2) try/catch que captura cualquier error sincrónico
    // del handler y lo reporta visualmente en lugar de morir silencioso — el síntoma
    // "el botón no hace nada" de release 1.12.5 se debió a URL hardcoded que rompía
    // el rewrite del server. (3) Console.log de cada paso para diagnóstico persistente.
    var btnSyncCatalogos = document.getElementById('btn-sync-catalogos');
    if (btnSyncCatalogos) {
        btnSyncCatalogos.addEventListener('click', function () {
            console.log('[SyncCatalogos] click detected, opening confirm');
            showConfirm(
                '¿Sincronizar catálogos comerciales CRM desde Tango?\n\nTrae condiciones de venta, listas de precio, vendedores, transportes, depósitos y clasificaciones PDS. Prerequisito para Sync Precios y Sync Stock.',
                'warning',
                function () {
                    console.log('[SyncCatalogos] confirm OK, disparando fetch a ' + basePath + '/sync-catalogos');
                    var orig = btnSyncCatalogos.innerHTML;
                    try {
                        btnSyncCatalogos.disabled = true;
                        btnSyncCatalogos.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sincronizando catálogos...';
                        showProgress();

                        fetch(basePath + '/sync-catalogos', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                        .then(function (r) {
                            console.log('[SyncCatalogos] response recibida, status=' + r.status);
                            return r.text().then(function (text) { return { status: r.status, body: text }; });
                        })
                        .then(function (res) {
                            hideProgress();
                            btnSyncCatalogos.disabled = false;
                            btnSyncCatalogos.innerHTML = orig;

                            var data;
                            try {
                                data = JSON.parse(res.body);
                            } catch (e) {
                                console.error('[SyncCatalogos] respuesta no-JSON', res);
                                showAlert('El servidor devolvió contenido inválido (HTTP ' + res.status + '). Primeros 200 chars: ' + res.body.substring(0, 200), 'danger', 'Error en Sync Catálogos');
                                return;
                            }

                            if (data.success) {
                                console.log('[SyncCatalogos] success', data.stats);
                                showAlert(data.message + '\n\nRecargando circuito para reflejar nuevas precondiciones...', 'success', 'Catálogos sincronizados');
                                setTimeout(function () { window.location.reload(); }, 1500);
                            } else {
                                console.warn('[SyncCatalogos] server reportó fracaso', data);
                                showAlert(data.message || 'Error sin mensaje', 'danger', 'Error en Sync Catálogos');
                            }
                        })
                        .catch(function (err) {
                            console.error('[SyncCatalogos] fetch rejected', err);
                            hideProgress();
                            btnSyncCatalogos.disabled = false;
                            btnSyncCatalogos.innerHTML = orig;
                            showAlert((err && err.message) ? err.message : 'Error de red al contactar el endpoint de sincronización.', 'danger', 'Error en Sync Catálogos');
                        });
                    } catch (syncErr) {
                        console.error('[SyncCatalogos] excepción sincrónica en el handler', syncErr);
                        hideProgress();
                        btnSyncCatalogos.disabled = false;
                        btnSyncCatalogos.innerHTML = orig;
                        showAlert('Excepción en el handler JS: ' + (syncErr && syncErr.message ? syncErr.message : String(syncErr)), 'danger', 'Error interno');
                    }
                },
                'Sincronizar Catálogos'
            );
        });
    }

    // ── Push Masivo ───────────────────────────────────────────────────
    document.getElementById('btn-bulk-push').addEventListener('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) return;
        var entidad = getActiveEntidad();
        var label   = entidad === 'articulo' ? 'artículos' : 'clientes';

        showConfirm(
            '¿Push de ' + ids.length + ' ' + label + ' seleccionados hacia Tango?',
            'warning',
            function() { doBulkRequest(basePath + '/push-masivo', { ids: ids, entidad: entidad }); },
            'Confirmar Push ↑'
        );
    });

    // ── Pull Masivo ───────────────────────────────────────────────────
    document.getElementById('btn-bulk-pull').addEventListener('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) return;
        var entidad = getActiveEntidad();
        var label   = entidad === 'articulo' ? 'artículos' : 'clientes';

        showConfirm(
            '¿Pull de ' + ids.length + ' ' + label + ' seleccionados desde Tango?\n\nSe actualizarán los datos locales con los valores de Tango.',
            'info',
            function() { doBulkRequest(basePath + '/pull-masivo', { ids: ids, entidad: entidad }); },
            'Confirmar Pull ↓'
        );
    });

    function doBulkRequest(url, body) {
        var fd = new FormData();
        fd.append('ids', JSON.stringify(body.ids));
        fd.append('entidad', body.entidad);

        var btnPush = document.getElementById('btn-bulk-push');
        var btnPull = document.getElementById('btn-bulk-pull');
        [btnPush, btnPull].forEach(function(b) { b.disabled = true; });
        showProgress();

        fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            hideProgress();
            showAlert(data.message, data.success ? 'success' : 'danger', data.success ? 'Operación Completada' : 'Error');
            if (data.success) {
                clearSelection();
                var activeTab = getActiveTabBtn();
                if (activeTab) loadTabContent(activeTab, true);
            }
        })
        .catch(function(err) {
            hideProgress();
            showAlert(err.message || 'Error de red', 'danger');
        })
        .finally(function() {
            [btnPush, btnPull].forEach(function(b) { b.disabled = false; });
        });
    }

    // ── Helper sync individual (push y pull) ─────────────────────────
    function doSingleSync(endpoint, id, entidad, nombre, btn, resetIcon) {
        var icon = btn.querySelector('i');
        btn.disabled = true;
        if (icon) { icon.className = 'bi bi-arrow-repeat rxnsync-spin'; }
        showProgress();

        function resultHtml(data) {
            if (!data || !data.payload) return '';
            if (endpoint === 'push') {
                return renderJsonBlock('Payload enviado', data.payload.payload_enviado)
                    + renderJsonBlock('Respuesta API', data.payload.response_tango);
            }

            return renderJsonBlock('Snapshot Tango', data.payload.snapshot_tango)
                + renderJsonBlock('Cache local actualizada', data.payload.local_actualizado);
        }

        var fd = new FormData();
        fd.append('id', id);
        fd.append('entidad', entidad);

        fetchJson(basePath + '/' + endpoint, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(data) {
            hideProgress();
            var tipo    = endpoint === 'push' ? 'Push ↑' : 'Pull ↓';
            var titulo  = data.success ? tipo + ' completado' : 'Error en ' + tipo;
            var msg = data.message || '';
            var details = resultHtml(data);
            if (details !== '') {
                msg += '<br>' + details;
            }
            showAlert(msg, data.success ? 'success' : 'danger', titulo);
            if (data.success) {
                var activeTab = getActiveTabBtn();
                if (activeTab) loadTabContent(activeTab, false);
            } else {
                btn.disabled = false;
                if (icon) icon.className = resetIcon;
            }
        })
        .catch(function(err) {
            hideProgress();
            showAlert(err.message || 'Error de red', 'danger');
            btn.disabled = false;
            if (icon) icon.className = resetIcon;
        });
    }

    // ── Event delegation: Push individual ───────────────────────────
    document.getElementById('syncTabsContent').addEventListener('click', function(e) {
        // PUSH
        var pushBtn = e.target.closest('.btn-push-tango');
        if (pushBtn) {
            e.stopPropagation();
            var id      = pushBtn.getAttribute('data-id');
            var nombre  = pushBtn.getAttribute('data-nombre');
            var entidad = pushBtn.getAttribute('data-entidad');

            showConfirm(
                '¿Forzar Push de "' + nombre + '" hacia Tango?',
                'warning',
                function() { doSingleSync('push', id, entidad, nombre, pushBtn, 'bi bi-cloud-upload'); },
                'Sí, Push ↑'
            );
            return;
        }

        // PULL
        var pullBtn = e.target.closest('.btn-pull-tango');
        if (pullBtn) {
            e.stopPropagation();
            var id      = pullBtn.getAttribute('data-id');
            var nombre  = pullBtn.getAttribute('data-nombre');
            var entidad = pullBtn.getAttribute('data-entidad');

            showConfirm(
                '¿Traer datos de "' + nombre + '" desde Tango? Los datos locales se actualizarán.',
                'info',
                function() { doSingleSync('pull', id, entidad, nombre, pullBtn, 'bi bi-cloud-download'); },
                'Sí, Pull ↓'
            );
            return;
        }

        // Re-sync estado de un PDS individual (tab Pedidos)
        var syncPedBtn = e.target.closest('.btn-sync-pedido-row');
        if (syncPedBtn) {
            e.stopPropagation();
            var pedId   = syncPedBtn.getAttribute('data-id');
            var pedNum  = syncPedBtn.getAttribute('data-numero');
            var icon    = syncPedBtn.querySelector('i');
            var origCls = icon ? icon.className : 'bi bi-arrow-repeat';

            syncPedBtn.disabled = true;
            if (icon) icon.className = 'bi bi-arrow-repeat rxnsync-spin';

            var fd = new FormData();
            fd.append('id', pedId);

            fetchJson(basePath + '/sync-pedido-estado', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(data) {
                syncPedBtn.disabled = false;
                if (icon) icon.className = origCls;
                if (data.success) {
                    showAlert('PDS #' + pedNum + ' — ' + data.message, 'success', 'Estado actualizado');
                    // Recargar el tab para ver el badge/fecha actualizados
                    var activeTab = getActiveTabBtn();
                    if (activeTab) loadTabContent(activeTab, false);
                } else {
                    showAlert(data.message, 'danger', 'Error al sincronizar');
                }
            })
            .catch(function(err) {
                syncPedBtn.disabled = false;
                if (icon) icon.className = origCls;
                showAlert(err.message || 'Error de red', 'danger');
            });
            return;
        }

        var infoBtn = e.target.closest('.btn-payload-info');
        if (infoBtn) {
            e.stopPropagation();
            var id = infoBtn.getAttribute('data-id');
            var entidad = infoBtn.getAttribute('data-entidad');

            fetchJson(basePath + '/payload?entidad=' + entidad + '&id=' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(data) {
                if (!data.success) {
                    showAlert(data.message, 'warning', 'Sin historial');
                    return;
                }
                showAlert(payloadHtml(data.meta, data.snapshot), 'info', 'Historial Tango — ID #' + (data.meta.tango_id || '?'));
            })
            .catch(function(err) {
                showAlert(err.message || 'Error de red', 'danger', 'Error');
            });
        }
    });

    // ── Load initial tab ──────────────────────────────────────────────
    var activeTab = document.querySelector('#syncTabs button.active');
    if (activeTab) loadTabContent(activeTab, true);

    // ── Reinicializar tab controls tras reload por filtro Motor BD (AJAX mode) ──
    document.addEventListener('rxnsync:contentRefreshed', function() {
        rebindCheckboxes();
        var activeBtn = getActiveTabBtn();
        if (activeBtn) initTabControls(activeBtn);
    });
});
</script>

<style>
@keyframes rxnsync-spin { to { transform: rotate(360deg); } }
.rxnsync-spin { display:inline-block; animation: rxnsync-spin .8s linear infinite; }
</style>

<?php
$content = ob_get_clean();
$extraScripts = '<script src="/js/rxn-advanced-filters.js"></script><script src="/js/rxn-confirm-modal.js"></script>';
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
