<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold">Gestión de Usuarios</h2>
                <p class="text-muted mb-0">Entorno Operativo ID: <?= htmlspecialchars((string)\App\Core\Context::getEmpresaId()) ?></p>
            </div>
            <div class="d-flex align-items-center">
                <a href="/rxnTiendasIA/public/mi-empresa/dashboard" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Volver al Entorno</a>
                <a href="/rxnTiendasIA/public/mi-empresa/usuarios/crear" class="btn btn-primary fw-bold">+ Nuevo Usuario</a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron usuarios en la estructura de la empresa.</td></tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td class="align-middle"><?= $u->id ?></td>
                                <td class="align-middle fw-bold"><?= htmlspecialchars($u->nombre) ?></td>
                                <td class="align-middle"><?= htmlspecialchars($u->email) ?></td>
                                <td class="align-middle">
                                    <?php if ($u->es_admin == 1): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Operador</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <?php if ($u->activo == 1): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end align-middle">
                                    <a href="/rxnTiendasIA/public/mi-empresa/usuarios/<?= $u->id ?>/editar" class="btn btn-sm btn-outline-primary">Editar</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
