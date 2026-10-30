<?php /* Responsable: B - Panel de administración de servicios */ ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1 fw-bold text-dark">Panel de Servicios</h1>
        <p class="text-muted mb-0">Gestiona el catálogo de servicios de AppSalon</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?url=servicios/crear" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <span>+</span> Nuevo Servicio
    </a>
</div>

<?php if (empty($servicios)): ?>
    <div class="alert alert-warning text-center py-4">
        <p class="mb-0">No hay servicios registrados en la base de datos.</p>
    </div>
<?php else: ?>
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th style="width: 120px;">Duración</th>
                        <th style="width: 130px;">Precio</th>
                        <th style="width: 160px;" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servicios as $s): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?= e($s['id']) ?></td>
                            <td class="fw-semibold text-dark"><?= e($s['nombre']) ?></td>
                            <td class="text-muted small">
                                <?= !empty($s['descripcion']) ? e($s['descripcion']) : '<span class="text-secondary fst-italic">Sin descripción</span>' ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    ⏱ <?= e($s['duracion'] ?? 60) ?> min
                                </span>
                            </td>
                            <td class="fw-bold text-primary">
                                $<?= number_format((float)$s['precio'], 0, ',', '.') ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <!-- Botón Editar -->
                                    <a href="<?= BASE_URL ?>/index.php?url=servicios/editar&id=<?= e($s['id']) ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Editar servicio">
                                        Editar
                                    </a>

                                    <!-- Formulario Eliminar con confirmación y CSRF -->
                                    <form action="<?= BASE_URL ?>/index.php?url=servicios/eliminar" 
                                          method="POST" 
                                          onsubmit="return confirm('¿Estás seguro de que deseas eliminar el servicio &quot;<?= e($s['nombre']) ?>&quot;? Esta acción no se puede deshacer.');">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar servicio">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
