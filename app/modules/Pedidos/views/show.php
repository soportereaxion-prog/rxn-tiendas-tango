<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>
                    Orden Web #<?= $pedido['pedido_id'] ?>
                    <?php if($pedido['estado_tango'] === 'enviado_tango'): ?>
                        <span class="badge bg-success align-middle ms-2 fs-6 pb-2">✔ Enviado a Tango</span>
                    <?php elseif($pedido['estado_tango'] === 'error_envio_tango'): ?>
                        <span class="badge bg-danger align-middle ms-2 fs-6 pb-2">❌ Error Integración</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark align-middle ms-2 fs-6 pb-2">⏳ Pendiente</span>
                    <?php endif; ?>
                </h2>
                
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span class="badge <?= $pedido['estado_tango'] === 'enviado_tango' ? 'bg-success' : ($pedido['estado_tango'] === 'error_envio_tango' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                        <?= $pedido['estado_tango'] === 'enviado_tango' ? 'Enviado a Tango' : ($pedido['estado_tango'] === 'error_envio_tango' ? 'Error de integración' : 'Pendiente') ?>
                    </span>
                    <span class="badge <?= ((int)$pedido['intentos_envio_tango'] > 0 ? ($pedido['estado_tango'] === 'enviado_tango' ? 'bg-success' : ($pedido['estado_tango'] === 'error_envio_tango' ? 'bg-danger' : 'bg-secondary')) : 'bg-secondary') ?>">
                        Envíos a Tango: <?= (int)$pedido['intentos_envio_tango'] ?>
                    </span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if(empty($pedido['id_gva14_tango'])): ?>
                    <a href="/mi-empresa/clientes/<?= $pedido['cliente_web_id'] ?>/editar" class="btn btn-danger text-white border-0 shadow-sm" title="Falta resolución comercial del cliente">⚠️ Vincular Cliente en Tango Módulo</a>
                <?php else: ?>
                    <form action="/mi-empresa/pedidos/<?= $pedido['pedido_id'] ?>/reprocesar" method="POST">
                        <button type="submit" class="btn <?= $pedido['estado_tango'] === 'error_envio_tango' ? 'btn-warning' : ($pedido['estado_tango'] === 'enviado_tango' ? 'btn-outline-success shadow-sm' : 'btn-success shadow-sm') ?> text-dark " data-rxn-confirm="¿Reintentar envío a Tango con los datos comerciales resueltos?" data-confirm-type="warning"><i class="bi bi-arrow-repeat"></i> Enviar a Tango</button>
                    </form>
                <?php endif; ?>
                <a href="/mi-empresa/pedidos" class="btn btn-outline-secondary">← Volver al Listado</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'pedidos_web';
        $moduleNotesLabel = 'Pedidos Web';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <div class="row g-4">
            <!-- Bloque Izquierdo: Datos del Cliente y Resumen -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header  border-bottom py-3">
                        <h5 class="mb-0 fw-bold">👤 Datos del Comprador</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="data-label">Nombre y Apellido</div>
                            <div class="fs-6 fw-medium text-dark"><?= htmlspecialchars((string)$pedido['nombre']) ?> <?= htmlspecialchars((string)$pedido['apellido']) ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="data-label">Documento/CUIT</div>
                            <div class="fs-6 text-dark"><?= htmlspecialchars((string)($pedido['documento'] ?: '-')) ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="data-label">Razón Social</div>
                            <div class="fs-6 text-dark"><?= htmlspecialchars((string)($pedido['razon_social'] ?: '-')) ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="data-label">Email</div>
                            <div class="fs-6 text-dark"><a href="mailto:<?= htmlspecialchars((string)$pedido['email']) ?>"><?= htmlspecialchars((string)$pedido['email']) ?></a></div>
                        </div>
                        <div class="mb-3">
                            <div class="data-label">Teléfono</div>
                            <div class="fs-6 text-dark"><?= htmlspecialchars((string)($pedido['telefono'] ?: '-')) ?></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header  border-bottom py-3">
                        <h5 class="mb-0 fw-bold">📍 Envío / Facturación</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="data-label">Dirección</div>
                            <div class="fs-6 text-dark"><?= htmlspecialchars((string)($pedido['direccion'] ?: '-')) ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="data-label">Localidad y Provincia</div>
                            <div class="fs-6 text-dark"><?= htmlspecialchars((string)($pedido['localidad'] ?: '-')) ?> - <?= htmlspecialchars((string)($pedido['provincia'] ?: '-')) ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="data-label">Código Postal</div>
                            <div class="fs-6 text-dark"><?= htmlspecialchars((string)($pedido['codigo_postal'] ?: '-')) ?></div>
                        </div>
                    </div>
                </div>

                <div class="card bg-light border-0">
                    <div class="card-body">
                        <div class="data-label mb-2">Observaciones del Pedido (Admin)</div>
                        <p class="mb-0 text-dark" style="font-size: 0.9rem;">
                            <?= nl2br(htmlspecialchars((string)($pedido['pedido_observaciones'] ?: 'Sin observaciones extra.'))) ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Bloque Derecho: Renglones y Logs de Tango -->
            <div class="col-lg-8">
                <!-- Tarjeta de Renglones -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-medium">🛒 Detalle de Artículos</h5>
                        <span class="badge bg-light text-dark fs-6 font-monospace">Total: $<?= number_format((float)$pedido['total'], 2, ',', '.') ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light text-muted" style="font-size: 0.85rem;">
                                <tr>
                                    <th class="ps-4">ID / Ref</th>
                                    <th>Artículo</th>
                                    <th class="text-center">Cant.</th>
                                    <th class="text-end">P. Unit.</th>
                                    <th class="text-end pe-4">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pedido['renglones'] as $r): ?>
                                    <tr>
                                        <td class="ps-4 text-muted"><small>#<?= $r['articulo_id'] ?></small></td>
                                        <td class="fw-medium text-dark"><?= htmlspecialchars((string)$r['nombre_articulo']) ?></td>
                                        <td class="text-center fw-bold"><?= $r['cantidad'] ?></td>
                                        <td class="text-end text-muted">$<?= number_format((float)$r['precio_unitario'], 2, ',', '.') ?></td>
                                        <td class="text-end pe-4 fw-bold text-success">$<?= number_format($r['cantidad'] * $r['precio_unitario'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tarjeta de Log Tango -->
                <div class="card border-info">
                    <div class="card-header bg-info bg-opacity-10 py-3 border-info">
                        <h5 class="mb-0 fw-bold text-info-emphasis">🔌 Integración API Tango Connect</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="data-label text-info-emphasis">Cód. Cliente Usado</div>
                                <div class="fs-5 fw-bold text-dark font-monospace"><?= htmlspecialchars((string)($pedido['codigo_cliente_tango_usado'] ?: 'N/A')) ?></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="data-label text-info-emphasis">Tango Order Number</div>
                                <div class="fs-5 fw-bold text-dark font-monospace"><?= htmlspecialchars((string)($pedido['tango_pedido_numero'] ?: '---')) ?></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="data-label text-info-emphasis">Estado Ext.</div>
                                <div class="fs-5 fw-bold text-dark font-monospace"><?= htmlspecialchars((string)$pedido['estado_tango']) ?></div>
                            </div>
                        </div>

                        <?php if($pedido['estado_tango'] === 'error_envio_tango' && !empty($pedido['mensaje_error'])): ?>
                            <div class="rxn-flash-banner rxn-flash-banner-danger mt-2 shadow-sm">
                                <div class="rxn-flash-icon"><i class="bi bi-x-circle-fill"></i></div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold mb-1">Error detectado</div>
                                    <div>
                                        <?php if (!$isGlobalAdmin): ?>
                                            <span class="font-monospace"><?= htmlspecialchars((string)($cleanMessage ?? 'Error interno.')) ?></span>
                                        <?php else: ?>
                                            <?= nl2br(htmlspecialchars((string)$pedido['mensaje_error'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($isGlobalAdmin): ?>
                            <ul class="nav nav-tabs mt-4" id="logTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="payload-tab" data-bs-toggle="tab" data-bs-target="#payload" type="button" role="tab" aria-controls="payload" aria-selected="true">Payload Enviado</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="response-tab" data-bs-toggle="tab" data-bs-target="#response" type="button" role="tab" aria-controls="response" aria-selected="false">Respuesta API</button>
                                </li>
                            </ul>
                            <div class="tab-content py-3 border border-top-0 rounded-bottom px-3 " id="logTabsContent">
                                <div class="tab-pane fade show active" id="payload" role="tabpanel" aria-labelledby="payload-tab">
                                    <?php if($pedido['payload_enviado']): ?>
                                        <?php 
                                            $decodedPayload = json_decode((string)$pedido['payload_enviado'], true);
                                            $prettyPayload = $decodedPayload ? json_encode($decodedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : htmlspecialchars((string)$pedido['payload_enviado']);
                                        ?>
                                        <pre class="json-view mb-0"><code><?= htmlspecialchars((string)$prettyPayload, ENT_QUOTES, 'UTF-8') ?></code></pre>
                                    <?php else: ?>
                                        
                                    <?php endif; ?>
                                </div>
                                <div class="tab-pane fade" id="response" role="tabpanel" aria-labelledby="response-tab">
                                    <?php if($pedido['respuesta_tango']): ?>
                                        <?php 
                                            $decodedResponse = json_decode((string)$pedido['respuesta_tango'], true);
                                            $prettyResponse = $decodedResponse ? json_encode($decodedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : htmlspecialchars((string)$pedido['respuesta_tango']);
                                        ?>
                                        <pre class="json-view mb-0"><code><?= htmlspecialchars((string)$prettyResponse, ENT_QUOTES, 'UTF-8') ?></code></pre>
                                    <?php else: ?>
                                        
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php if($pedido['estado_tango'] !== 'pendiente_envio_tango'): ?>
                                <div class="mt-4 p-3 border rounded shadow-sm ">
                                    <h6 class="fw-bold mb-2">Estado de Transacción Servidor Tango:</h6>
                                    <p class="mb-0 text-dark">
                                        <?php if ($pedido['estado_tango'] === 'enviado_tango'): ?>
                                            <span class="text-success fw-bold">✔ Operación Exitosa.</span> La orden fue asimilada por el sistema central.
                                        <?php else: ?>
                                            <span class="text-danger fw-bold">❌ Operación Rechazada.</span> Revisar corrección comercial o de configuración.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/rxn-confirm-modal.js"></script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
