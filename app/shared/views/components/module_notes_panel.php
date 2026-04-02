<?php

if (!\App\Modules\Auth\AuthService::hasAdminPrivileges()) {
    return;
}

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$empresa = \App\Modules\Empresas\EmpresaAccessService::current();

if ($empresa) {
    if (str_contains($currentUri, '/crm/') && empty($empresa->crm_modulo_notas)) {
        return;
    }
    
    // Si esta en mi-empresa pero no es CRM ni configuracion/backoffice general, asumimos tienda
    if (str_contains($currentUri, '/mi-empresa/') && !str_contains($currentUri, '/crm/') && empty($empresa->tiendas_modulo_notas)) {
        // Excepcion para configuracion que es compartida
        if (!str_contains($currentUri, '/configuracion')) {
            return;
        }
    }
}

$moduleNotesKey = isset($moduleNotesKey) ? (string) $moduleNotesKey : '';
$moduleNotesLabel = isset($moduleNotesLabel) ? (string) $moduleNotesLabel : 'Modulo';

if ($moduleNotesKey === '') {
    return;
}

$moduleNotes = \App\Shared\Services\ModuleNoteService::notesForModule($moduleNotesKey, 4);
$moduleNotesCount = count(\App\Shared\Services\ModuleNoteService::notesForModule($moduleNotesKey, 0));
$moduleNotesFlash = $_SESSION['module_notes_flash'] ?? null;
$moduleNotesReturnTo = $_SERVER['REQUEST_URI'] ?? '/rxnTiendasIA/public/admin/notas-modulos';
$moduleNotesShouldOpen = $moduleNotesFlash !== null || $moduleNotesCount === 0;
$moduleNotesDomId = preg_replace('/[^a-z0-9_-]/i', '-', $moduleNotesKey) ?: 'module-notes';

unset($_SESSION['module_notes_flash']);

if (empty($GLOBALS['rxn_module_notes_assets_printed'])) {
    $GLOBALS['rxn_module_notes_assets_printed'] = true;
    ?>
    <style>
        .rxn-module-notes-widget {
            position: fixed;
            right: 1rem;
            bottom: 1rem;
            z-index: 1080;
            width: min(430px, calc(100vw - 1.5rem));
            min-width: 320px;
            max-width: calc(100vw - 1.5rem);
            height: min(68vh, 680px);
            min-height: 360px;
            max-height: calc(100vh - 1.5rem);
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, 0.1);
            border-radius: 20px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16);
            backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.96);
        }

        .rxn-module-notes-widget.is-collapsed {
            width: 88px !important;
            min-width: 0;
            max-width: 88px;
            height: 30px !important;
            min-height: 0;
            max-height: 30px;
            border-radius: 12px;
            border-color: rgba(148, 163, 184, 0.16);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.22);
            backdrop-filter: none;
            background: rgba(15, 23, 42, 0.94);
        }

        .rxn-module-notes-widget__header {
            padding: 1rem 1rem 0.85rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.06), rgba(15, 23, 42, 0.02));
            flex-shrink: 0;
            cursor: grab;
            user-select: none;
        }

        .rxn-module-notes-widget.is-dragging .rxn-module-notes-widget__header {
            cursor: grabbing;
        }

        .rxn-module-notes-widget__body {
            padding: 1rem;
            overflow: auto;
            flex: 1 1 auto;
        }

        .rxn-module-notes-widget.is-collapsed .rxn-module-notes-widget__header,
        .rxn-module-notes-widget.is-collapsed .rxn-module-notes-widget__body,
        .rxn-module-notes-widget.is-collapsed .rxn-module-notes-widget__resize {
            display: none;
        }

        .rxn-module-notes-widget__minimized-copy {
            display: none;
        }

        .rxn-module-notes-widget.is-collapsed .rxn-module-notes-widget__minimized-copy {
            display: block;
        }

        .rxn-module-notes-widget__window-state {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.06);
            color: #475569;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            text-transform: uppercase;
        }

        .rxn-module-notes-widget__launcher {
            display: none;
            width: 100%;
            height: 100%;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            border-radius: inherit;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(30, 41, 59, 0.95));
            color: #cbd5e1;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }

        .rxn-module-notes-widget.is-collapsed .rxn-module-notes-widget__launcher {
            display: inline-flex;
        }

        .rxn-module-notes-widget__launcher:hover {
            transform: translateY(-1px);
            box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.18);
            color: #e2e8f0;
        }

        .rxn-module-notes-widget__launcher:focus-visible {
            outline: 2px solid rgba(96, 165, 250, 0.85);
            outline-offset: 2px;
        }

        .rxn-module-notes-widget__launcher-line {
            display: block;
            width: 22px;
            height: 3px;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.92;
        }

        .rxn-module-notes-widget__resize {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 20px;
            height: 20px;
            cursor: nwse-resize;
            background:
                linear-gradient(135deg, transparent 0 48%, rgba(15, 23, 42, 0.2) 48% 52%, transparent 52% 100%),
                linear-gradient(135deg, transparent 0 65%, rgba(15, 23, 42, 0.14) 65% 69%, transparent 69% 100%);
        }

        .rxn-module-notes-dropzone {
            border: 1px dashed rgba(15, 23, 42, 0.18);
            border-radius: 14px;
            padding: 0.85rem;
            background: rgba(248, 250, 252, 0.85);
            transition: border-color 0.2s ease, background-color 0.2s ease;
        }

        .rxn-module-notes-dropzone:focus-within,
        .rxn-module-notes-dropzone.is-active {
            border-color: rgba(37, 99, 235, 0.55);
            background: rgba(219, 234, 254, 0.4);
        }

        .rxn-module-notes-preview-grid,
        .rxn-module-notes-attachments {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 0.75rem;
        }

        .rxn-module-notes-preview-card,
        .rxn-module-notes-attachment-card {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 14px;
            padding: 0.55rem;
            background: rgba(255, 255, 255, 0.95);
        }

        .rxn-module-notes-preview-card img,
        .rxn-module-notes-attachment-card img {
            width: 100%;
            height: 96px;
            border-radius: 10px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            object-fit: cover;
            background: #fff;
        }

        .rxn-module-notes-history {
            display: grid;
            gap: 0.85rem;
        }

        .rxn-module-notes-entry {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 14px;
            padding: 0.85rem;
            background: rgba(248, 250, 252, 0.9);
        }

        .rxn-module-notes-entry-text {
            white-space: pre-wrap;
        }

        .rxn-module-notes-grip {
            width: 48px;
            height: 4px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.18);
            margin: 0 auto 0.8rem;
        }

        .rxn-module-notes-preview-card .btn,
        .rxn-module-notes-attachment-card .btn {
            --bs-btn-padding-y: 0.15rem;
            --bs-btn-padding-x: 0.45rem;
            --bs-btn-font-size: 0.75rem;
        }

        body.rxn-module-notes-no-select {
            user-select: none;
        }

        @media (max-width: 767.98px) {
            .rxn-module-notes-widget {
                left: 0.75rem;
                right: 0.75rem;
                bottom: 0.75rem;
                width: auto;
                min-width: 0;
                min-height: 320px;
                height: min(72vh, 620px);
            }

            .rxn-module-notes-widget__header {
                cursor: default;
            }

            .rxn-module-notes-widget__resize {
                display: none;
            }

            .rxn-module-notes-widget.is-collapsed {
                width: 76px !important;
                max-width: 76px;
                height: 28px !important;
                max-height: 28px;
            }
        }
    </style>
    <script src="/rxnTiendasIA/public/js/rxn-module-notes.js"></script>
    <?php
}
?>
<aside
    class="card rxn-form-card border-0 shadow-lg rxn-module-notes-widget<?= $moduleNotesShouldOpen ? '' : ' is-collapsed' ?>"
    data-module-notes-widget
    data-default-open="<?= $moduleNotesShouldOpen ? '1' : '0' ?>"
    data-widget-id="<?= htmlspecialchars($moduleNotesKey) ?>"
>
    <div class="rxn-module-notes-widget__header" data-module-notes-drag-handle>
        <div class="rxn-module-notes-grip"></div>
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <span class="badge text-bg-dark mb-2">Solo administradores</span>
                <h3 class="h6 mb-1">Bitacora interna - <?= htmlspecialchars($moduleNotesLabel) ?></h3>
                <p class="text-muted small mb-0" data-module-notes-header-copy>Arrastrala, redimensionala, minimizala y pega varias capturas con referencias `#imagenN`.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <span class="rxn-module-notes-widget__window-state">Ventana</span>
                <span class="badge rounded-pill text-bg-secondary"><?= $moduleNotesCount ?> nota<?= $moduleNotesCount === 1 ? '' : 's' ?></span>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-module-notes-toggle><?= $moduleNotesShouldOpen ? 'Minimizar' : 'Restaurar' ?></button>
            </div>
        </div>
    </div>

    <div class="rxn-module-notes-widget__body" data-module-notes-body>
        <?php if (is_array($moduleNotesFlash) && !empty($moduleNotesFlash['message'])): ?>
            <div class="alert alert-<?= htmlspecialchars((string) ($moduleNotesFlash['type'] ?? 'info')) ?> py-2 small mb-3">
                <?= htmlspecialchars((string) $moduleNotesFlash['message']) ?>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <p class="small text-muted mb-0">Cada imagen que agregas inserta su referencia en el texto para que despues se entienda de que estabas hablando.</p>
            <a href="/rxnTiendasIA/public/admin/notas-modulos" class="btn btn-outline-secondary btn-sm">Centro</a>
        </div>

        <form action="/rxnTiendasIA/public/admin/notas-modulos" method="POST" enctype="multipart/form-data" class="row g-3 mb-4" data-module-notes-form>
            <input type="hidden" name="module_key" value="<?= htmlspecialchars($moduleNotesKey) ?>">
            <input type="hidden" name="module_label" value="<?= htmlspecialchars($moduleNotesLabel) ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($moduleNotesReturnTo) ?>">
            <input type="hidden" name="attachment_labels_json" value="[]" data-module-notes-labels>

            <div class="col-md-4">
                <label class="form-label fw-semibold small" for="module-note-type-<?= htmlspecialchars($moduleNotesDomId) ?>">Tipo</label>
                <select class="form-select form-select-sm" id="module-note-type-<?= htmlspecialchars($moduleNotesDomId) ?>" name="type">
                    <option value="idea">Idea</option>
                    <option value="ajuste">Ajuste</option>
                    <option value="bug">Bug</option>
                    <option value="dato">Dato</option>
                </select>
            </div>

            <div class="col-md-8">
                <label class="form-label fw-semibold small" for="module-note-content-<?= htmlspecialchars($moduleNotesDomId) ?>">Anotacion</label>
                <textarea class="form-control form-control-sm" id="module-note-content-<?= htmlspecialchars($moduleNotesDomId) ?>" name="content" rows="3" maxlength="3000" placeholder="Ej: el filtro rompe layout cuando aparece #imagen1, revisar padding en #imagen2..." data-module-notes-content></textarea>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold small mb-2">Capturas</label>
                <div class="rxn-module-notes-dropzone" tabindex="0" data-module-notes-pastezone>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <div class="fw-semibold small">Pega varias capturas con `Ctrl+V` o arrastralas aca</div>
                            <div class="text-muted small">Cada imagen se indexa como `#imagen1`, `#imagen2`, etc. y se inserta en el texto al momento de agregarla.</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-module-notes-choose>Agregar imagenes</button>
                            <button type="button" class="btn btn-outline-danger btn-sm d-none" data-module-notes-clear-all>Vaciar</button>
                        </div>
                    </div>

                    <input type="file" name="attachments[]" accept="image/png,image/jpeg,image/webp,image/gif" class="d-none" data-module-notes-file multiple>

                    <div class="rxn-module-notes-preview-grid mt-3 d-none" data-module-notes-preview-grid></div>
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-dark btn-sm">Guardar anotacion</button>
            </div>
        </form>

        <?php if ($moduleNotes === []): ?>
            <div class="alert alert-light border small mb-0">Todavia no hay notas para este modulo.</div>
        <?php else: ?>
            <div class="rxn-module-notes-history">
                <?php foreach ($moduleNotes as $moduleNote): ?>
                    <?php $noteAttachments = is_array($moduleNote['attachments'] ?? null) ? $moduleNote['attachments'] : []; ?>
                    <article class="rxn-module-notes-entry">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge <?= htmlspecialchars(\App\Shared\Services\ModuleNoteService::typeBadgeClass((string) ($moduleNote['type'] ?? 'idea'))) ?>">
                                    <?= htmlspecialchars(\App\Shared\Services\ModuleNoteService::typeLabel((string) ($moduleNote['type'] ?? 'idea'))) ?>
                                </span>
                                <span class="small text-muted">
                                    <?= htmlspecialchars(\App\Shared\Services\ModuleNoteService::formatTimestamp((string) ($moduleNote['created_at'] ?? ''))) ?>
                                </span>
                            </div>
                            <span class="small text-muted">por <?= htmlspecialchars((string) ($moduleNote['author_name'] ?? 'Administrador')) ?></span>
                        </div>

                        <?php if ((string) ($moduleNote['content'] ?? '') !== ''): ?>
                            <div class="small rxn-module-notes-entry-text mb-3"><?= htmlspecialchars((string) ($moduleNote['content'] ?? '')) ?></div>
                        <?php endif; ?>

                        <?php if ($noteAttachments !== []): ?>
                            <div class="rxn-module-notes-attachments">
                                <?php foreach ($noteAttachments as $attachment): ?>
                                    <?php $noteAttachmentPath = (string) ($attachment['path'] ?? ''); ?>
                                    <?php if ($noteAttachmentPath === '') { continue; } ?>
                                    <div class="rxn-module-notes-attachment-card">
                                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                            <span class="badge text-bg-dark"><?= htmlspecialchars((string) ($attachment['label'] ?? '#imagen')) ?></span>
                                            <?php if (!empty($attachment['name'])): ?>
                                                <span class="small text-muted text-truncate"><?= htmlspecialchars((string) $attachment['name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="/rxnTiendasIA/public<?= htmlspecialchars($noteAttachmentPath) ?>" target="_blank" rel="noopener noreferrer">
                                            <img src="/rxnTiendasIA/public<?= htmlspecialchars($noteAttachmentPath) ?>" alt="Captura adjunta de <?= htmlspecialchars($moduleNotesLabel) ?>">
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="rxn-module-notes-widget__resize" data-module-notes-resize-handle></div>
    <button
        type="button"
        class="rxn-module-notes-widget__launcher"
        data-module-notes-launcher
        title="Abrir bitacora interna de <?= htmlspecialchars($moduleNotesLabel) ?>"
        aria-label="Abrir bitacora interna de <?= htmlspecialchars($moduleNotesLabel) ?>"
    >
        <span class="rxn-module-notes-widget__launcher-line" aria-hidden="true"></span>
    </button>
</aside>
