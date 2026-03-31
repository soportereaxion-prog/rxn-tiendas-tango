<?php
use App\Core\Helpers\UIHelper;
use App\Core\View;
$ui = isset($environmentLabel) ? compact('environmentLabel', 'dashboardPath') : [];
?>
<!DOCTYPE html>
<html lang="es" <?= UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Editar' : 'Nueva' ?> Nota CRM | <?= htmlspecialchars($environmentLabel ?? 'App') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 rxn-launcher-shell pt-3">
    <?php View::render('app/shared/views/components/backoffice_user_banner.php', $ui); ?>

    <main class="container-fluid flex-grow-1 px-4 mb-5" style="max-width: 900px; margin: 0 auto;">
        
        <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25">
            <div>
                <h1 class="h3 fw-bold mb-1"><i class="bi <?= $isEdit ? 'bi-pencil-square' : 'bi-journal-plus' ?>"></i> <?= $isEdit ? 'Editar Nota #' . $nota->id : 'Nueva Nota' ?></h1>
                <p class="text-muted mb-0">Añade o modifica una anotación en la bitácora.</p>
            </div>
            <div>
                <a href="<?= htmlspecialchars($indexPath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Formulario (Sábana Style) -->
        <div class="card bg-dark text-light border-0 shadow pt-3 px-2 pb-4">
            <div class="card-body">
                <form action="<?= htmlspecialchars($indexPath) ?><?= $isEdit ? '/' . $nota->id . '/editar' : '/crear' ?>" method="POST" class="needs-validation">
                    
                    <h5 class="fw-bold border-bottom border-secondary pb-2 mb-3 text-info"><i class="bi bi-link"></i> Vínculos</h5>
                    
                    <div class="mb-4 position-relative">
                        <label class="form-label text-muted small">Vincular a Cliente (Opcional)</label>
                        <input type="hidden" name="cliente_id" id="cliente_id" value="<?= htmlspecialchars((string) ($nota->cliente_id ?? $old['cliente_id'] ?? '')) ?>">
                        <input type="text" id="cliente_search" class="form-control bg-dark text-light border-secondary" placeholder="Escribí razón social o código para buscar..." autocomplete="off" value="<?= htmlspecialchars((string) ($nota->cliente_nombre ?? '')) ?>">
                        <ul id="cliente_dropdown" class="dropdown-menu w-100 bg-dark border-secondary shadow-lg overflow-hidden" style="max-height: 250px; overflow-y: auto; display: none; position: absolute; z-index: 1050;"></ul>
                        <div class="form-text text-secondary"><i class="bi bi-search"></i> Busca clientes activos en el CRM.</div>
                    </div>

                    <h5 class="fw-bold border-bottom border-secondary pt-3 pb-2 mb-3 text-info"><i class="bi bi-card-text"></i> Contenido de la Bitácora</h5>

                    <div class="mb-3">
                        <label class="form-label text-muted small">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control bg-dark text-light border-secondary" required value="<?= htmlspecialchars($nota->titulo ?? $old['titulo'] ?? '') ?>" placeholder="Resumen corto de la anotación">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small">Detalle Completo <span class="text-danger">*</span></label>
                        <textarea name="contenido" class="form-control bg-dark text-light border-secondary" rows="8" required placeholder="Conversación, tarea, o seguimiento detallado..."><?= htmlspecialchars($nota->contenido ?? $old['contenido'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-4 position-relative">
                        <label class="form-label text-muted small">Etiquetas (Tags)</label>
                        <input type="text" name="tags" id="tags_input" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($nota->tags ?? $old['tags'] ?? '') ?>" placeholder="Ej: seguimiento, facturación, presencial" autocomplete="off">
                        <ul id="tags_dropdown" class="dropdown-menu w-100 bg-dark border-secondary shadow-lg overflow-hidden" style="max-height: 200px; overflow-y: auto; display: none; position: absolute; z-index: 1050;"></ul>
                        <div class="form-text text-secondary"><i class="bi bi-tags"></i> Separadas por coma. Al escribir la última etiqueta, te sugeriremos las guardadas.</div>
                    </div>

                    <h5 class="fw-bold border-bottom border-secondary pt-3 pb-2 mb-3 text-info"><i class="bi bi-eye"></i> Estado y Visibilidad</h5>

                    <div class="form-check form-switch mb-5">
                        <?php $activo = $nota->activo ?? $old['activo'] ?? 1; ?>
                        <input class="form-check-input bg-dark border-secondary" type="checkbox" role="switch" name="activo" id="switchActivo" <?= $activo ? 'checked' : '' ?>>
                        <label class="form-check-label text-light ms-2" for="switchActivo">Nota Pública / Activa en el timeline</label>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary border-opacity-25">
                        <a href="<?= htmlspecialchars($indexPath) ?>" class="btn btn-outline-secondary px-4"><i class="bi bi-x-circle"></i> Cancelar</a>
                        <button type="submit" class="btn btn-primary px-5 fw-bold text-white shadow-sm">
                            <i class="bi <?= $isEdit ? 'bi-save2' : 'bi-plus-circle' ?>"></i> <?= $isEdit ? 'Actualizar Nota' : 'Guardar Nueva Nota' ?>
                        </button>
                    </div>
                    
                </form>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const basePath = <?= json_encode($indexPath) ?>;
        
        // --- AUTOCOMPLETE CLIENTES ---
        const inputClient = document.getElementById('cliente_search');
        const hiddenClient = document.getElementById('cliente_id');
        const dropClient = document.getElementById('cliente_dropdown');
        let clTimeout = null;
        let currentClientFocus = -1;

        inputClient.addEventListener('keydown', function(e) {
            let items = dropClient.querySelectorAll('li.rxn-selectable');
            if (items.length === 0) return;
            
            if (e.key === 'ArrowDown') {
                currentClientFocus++;
                addActive(items);
            } else if (e.key === 'ArrowUp') {
                currentClientFocus--;
                addActive(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentClientFocus > -1) {
                    if (items[currentClientFocus]) items[currentClientFocus].click();
                }
            } else if (e.key === 'Escape') {
                dropClient.style.display = 'none';
            }
        });

        function addActive(items) {
            if (!items) return false;
            removeActive(items);
            if (currentClientFocus >= items.length) currentClientFocus = 0;
            if (currentClientFocus < 0) currentClientFocus = (items.length - 1);
            items[currentClientFocus].classList.add('bg-secondary', 'bg-opacity-50');
        }

        function removeActive(items) {
            for (let i = 0; i < items.length; i++) {
                items[i].classList.remove('bg-secondary', 'bg-opacity-50');
            }
        }

        inputClient.addEventListener('input', (e) => {
            clearTimeout(clTimeout);
            hiddenClient.value = ''; // Reset ID if user typing
            currentClientFocus = -1;
            
            const term = e.target.value.trim();
            if (term.length < 2) {
                dropClient.style.display = 'none';
                return;
            }
            
            clTimeout = setTimeout(async () => {
                try {
                    const params = new URLSearchParams({ search: term });
                    const res = await fetch(`${basePath}/sugerencias-clientes?${params.toString()}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });
                    
                    if (!res.ok) {
                        throw new Error(`Error de servidor: ${res.status}`);
                    }
                    
                    const json = await res.json();
                    
                    dropClient.innerHTML = '';
                    if (json.data && json.data.length > 0) {
                        json.data.forEach((c, index) => {
                            const li = document.createElement('li');
                            li.className = 'dropdown-item text-light border-bottom border-secondary border-opacity-25 px-3 py-2 rxn-hover-bg rxn-selectable';
                            li.style.cursor = 'pointer';
                            li.innerHTML = `<strong>${c.razon_social}</strong> <span class="text-muted small ms-2">(${c.codigo_tango || 'Sin código'})</span>`;
                            li.onclick = () => {
                                inputClient.value = c.razon_social;
                                hiddenClient.value = c.id;
                                dropClient.style.display = 'none';
                            };
                            li.onmouseover = () => {
                                removeActive(dropClient.querySelectorAll('li.rxn-selectable'));
                                currentClientFocus = index;
                                addActive(dropClient.querySelectorAll('li.rxn-selectable'));
                            };
                            dropClient.appendChild(li);
                        });
                        dropClient.style.display = 'block';
                    } else {
                        dropClient.innerHTML = '<li class="dropdown-item text-muted disabled">No se encontraron clientes para esta empresa...</li>';
                        dropClient.style.display = 'block';
                    }
                } catch (err) {
                    console.error('Error fetching clients:', err);
                    dropClient.innerHTML = `<li class="dropdown-item text-danger disabled"><i class="bi bi-exclamation-triangle"></i> Error buscando clientes: ${err.message}</li>`;
                    dropClient.style.display = 'block';
                }
            }, 350);
        });

        // --- AUTOCOMPLETE TAGS ---
        const inputTags = document.getElementById('tags_input');
        const dropTags = document.getElementById('tags_dropdown');
        let tagsTimeout = null;
        let currentTagFocus = -1;

        inputTags.addEventListener('keydown', function(e) {
            let items = dropTags.querySelectorAll('li.rxn-selectable');
            if (items.length === 0) return;
            
            if (e.key === 'ArrowDown') {
                currentTagFocus++;
                addTagActive(items);
            } else if (e.key === 'ArrowUp') {
                currentTagFocus--;
                addTagActive(items);
            } else if (e.key === 'Enter') {
                if (currentTagFocus > -1) {
                    e.preventDefault();
                    if (items[currentTagFocus]) items[currentTagFocus].click();
                }
            } else if (e.key === 'Escape') {
                dropTags.style.display = 'none';
            }
        });

        function addTagActive(items) {
            if (!items) return false;
            removeTagActive(items);
            if (currentTagFocus >= items.length) currentTagFocus = 0;
            if (currentTagFocus < 0) currentTagFocus = (items.length - 1);
            items[currentTagFocus].classList.add('bg-secondary', 'bg-opacity-50');
        }

        function removeTagActive(items) {
            for (let i = 0; i < items.length; i++) {
                items[i].classList.remove('bg-secondary', 'bg-opacity-50');
            }
        }

        inputTags.addEventListener('input', (e) => {
            clearTimeout(tagsTimeout);
            currentTagFocus = -1;
            const val = e.target.value;
            const parts = val.split(',');
            const currentTerm = parts[parts.length - 1].trim();
            
            if (currentTerm.length < 2) {
                dropTags.style.display = 'none';
                return;
            }

            tagsTimeout = setTimeout(async () => {
                try {
                    const params = new URLSearchParams({ q: currentTerm });
                    const res = await fetch(`${basePath}/sugerencias-tags?${params.toString()}`);
                    if (!res.ok) throw new Error('API fetch error');
                    const json = await res.json();
                    
                    dropTags.innerHTML = '';
                    if (json.success && json.data && json.data.length > 0) {
                        json.data.forEach((tag, index) => {
                            const li = document.createElement('li');
                            li.className = 'dropdown-item text-light border-bottom border-secondary border-opacity-25 px-3 py-2 rxn-hover-bg rxn-selectable';
                            li.style.cursor = 'pointer';
                            li.innerHTML = `<i class="bi bi-tag me-2"></i> ${tag}`;
                            li.onclick = () => {
                                parts[parts.length - 1] = ' ' + tag; // replace current part
                                inputTags.value = parts.join(',') + ', ';
                                dropTags.style.display = 'none';
                                inputTags.focus();
                            };
                            li.onmouseover = () => {
                                removeTagActive(dropTags.querySelectorAll('li.rxn-selectable'));
                                currentTagFocus = index;
                                addTagActive(dropTags.querySelectorAll('li.rxn-selectable'));
                            };
                            dropTags.appendChild(li);
                        });
                        dropTags.style.display = 'block';
                    } else {
                        dropTags.style.display = 'none';
                    }
                } catch (err) {
                    console.error('Error fetching tags:', err);
                }
            }, 300);
        });

        // Ocultar dropdowns
        document.addEventListener('click', (e) => {
            if (!inputClient.contains(e.target) && !dropClient.contains(e.target)) {
                dropClient.style.display = 'none';
            }
            if (!inputTags.contains(e.target) && !dropTags.contains(e.target)) {
                dropTags.style.display = 'none';
            }
        });
    });
    </script>
    <style>
        .rxn-hover-bg:hover { background-color: rgba(255,255,255,0.1) !important; }
    </style>
</body>
</html>
