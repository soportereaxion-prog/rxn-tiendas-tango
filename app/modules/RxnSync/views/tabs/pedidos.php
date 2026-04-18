<?php
/** @var array $registros */

use App\Modules\CrmPedidosServicio\TangoPedidoEstado;

$totalConEstado = 0;
$counts = [2 => 0, 3 => 0, 4 => 0, 5 => 0, 'sin' => 0];
foreach ($registros as $r) {
    $estado = $r['tango_estado'] !== null ? (int) $r['tango_estado'] : null;
    if (TangoPedidoEstado::isValid($estado)) {
        $counts[$estado] = ($counts[$estado] ?? 0) + 1;
        $totalConEstado++;
    } else {
        $counts['sin']++;
    }
}
?>
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
    <input type="text" id="rxnsync-search-ped" class="form-control form-control-sm border-info"
           placeholder="🔎 Buscar PDS, cliente o NRO_PEDIDO..." style="max-width:280px;" autocomplete="off">
    <select id="rxnsync-filter-estado-ped" class="form-select form-select-sm" style="width:190px;">
        <option value="">🔽 Todos los estados</option>
        <option value="2">✅ Aprobado (<?= (int) $counts[2] ?>)</option>
        <option value="3">🧾 Cumplido (<?= (int) $counts[3] ?>)</option>
        <option value="4">🔒 Cerrado (<?= (int) $counts[4] ?>)</option>
        <option value="5">🚫 Anulado (<?= (int) $counts[5] ?>)</option>
        <option value="sin">❓ Sin sync (<?= (int) $counts['sin'] ?>)</option>
    </select>
    <small class="text-muted ms-auto" id="rxnsync-ped-count"><?= count($registros) ?> PDS con ID Tango</small>
</div>

<div class="table-responsive text-start">
    <table class="table table-hover table-sm align-middle view-table" id="rxnsync-table-ped">
        <thead class="table-light">
            <tr>
                <th style="width:90px;">PDS #</th>
                <th>Cliente</th>
                <th style="width:180px;">Nro. Pedido Tango</th>
                <th style="width:150px;">ID_GVA21</th>
                <th style="width:140px;">Estado</th>
                <th style="width:170px;">Última sync</th>
                <th class="text-end" style="width:80px;">Acciones</th>
            </tr>
        </thead>
        <tbody id="rxnsync-tbody-ped">
            <?php if (empty($registros)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        No hay PDS enviados a Tango todavía. Cuando los PDS se envían a Tango aparecen acá para poder sincronizar su estado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($registros as $row): ?>
                    <?php
                    $estado    = $row['tango_estado'] !== null ? (int) $row['tango_estado'] : null;
                    $meta      = TangoPedidoEstado::meta($estado);
                    $estadoKey = TangoPedidoEstado::isValid($estado) ? (string) $estado : 'sin';
                    $nombre    = strtolower((string) ($row['cliente_nombre'] ?? ''));
                    $nroPed    = strtolower((string) ($row['tango_nro_pedido'] ?? ''));
                    ?>
                    <tr data-pedido-id="<?= (int) $row['id'] ?>"
                        data-estado="<?= htmlspecialchars($estadoKey) ?>"
                        data-nombre="<?= htmlspecialchars($nombre) ?>"
                        data-nro-pedido="<?= htmlspecialchars($nroPed) ?>"
                        data-numero="<?= (int) $row['numero'] ?>">
                        <td class="fw-medium">#<?= (int) $row['numero'] ?></td>
                        <td class="text-truncate" style="max-width:260px;" title="<?= htmlspecialchars((string) $row['cliente_nombre']) ?>">
                            <?= htmlspecialchars((string) $row['cliente_nombre']) ?>
                        </td>
                        <td class="font-monospace small">
                            <?= $row['tango_nro_pedido'] ? htmlspecialchars((string) $row['tango_nro_pedido']) : '<span class="text-muted">–</span>' ?>
                        </td>
                        <td class="font-monospace text-muted small">#<?= (int) $row['tango_id_gva21'] ?></td>
                        <td>
                            <span class="badge bg-<?= htmlspecialchars($meta['color']) ?> rounded-pill px-2">
                                <i class="bi <?= htmlspecialchars($meta['icon']) ?> me-1"></i><?= htmlspecialchars($meta['label']) ?>
                            </span>
                        </td>
                        <td class="text-muted small">
                            <?= $row['tango_estado_sync_at'] ? date('d/m/Y H:i', strtotime($row['tango_estado_sync_at'])) : '<span class="text-muted">Nunca</span>' ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info btn-sync-pedido-row"
                                    data-id="<?= (int) $row['id'] ?>"
                                    data-numero="<?= (int) $row['numero'] ?>"
                                    title="Re-sincronizar estado desde Tango">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
(function() {
    var tbody   = document.getElementById('rxnsync-tbody-ped');
    var search  = document.getElementById('rxnsync-search-ped');
    var filter  = document.getElementById('rxnsync-filter-estado-ped');
    var countEl = document.getElementById('rxnsync-ped-count');

    if (!tbody) return;

    var rows = Array.from(tbody.querySelectorAll('tr[data-pedido-id]'));

    function applyFilter() {
        var term   = (search ? search.value : '').toLowerCase().trim();
        var estado = filter ? filter.value : '';
        var visible = 0;
        rows.forEach(function(row) {
            var matchText  = !term
                || (row.dataset.nombre || '').includes(term)
                || (row.dataset.nroPedido || '').includes(term)
                || String(row.dataset.numero || '').includes(term);
            var matchState = !estado || row.dataset.estado === estado;
            var show = matchText && matchState;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (countEl) countEl.textContent = visible + ' PDS con ID Tango';
    }

    if (search) search.addEventListener('input', applyFilter);
    if (filter) filter.addEventListener('change', applyFilter);
})();
</script>
