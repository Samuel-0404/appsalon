<?php /* Responsable: B - Listado público de servicios */ ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1 text-dark fw-bold">Nuestros Servicios</h1>
        <p class="text-muted mb-0">Descubre nuestros tratamientos y agenda tu cita con los mejores profesionales.</p>
    </div>
    <?php if (esAdmin()): ?>
        <a href="<?= BASE_URL ?>/index.php?url=servicios/admin" class="btn btn-outline-dark btn-sm">
            ⚙️ Panel de Administración
        </a>
    <?php endif; ?>
</div>

<?php if (empty($servicios)): ?>
    <div class="alert alert-info text-center py-4">
        <h4 class="alert-heading">No hay servicios disponibles</h4>
        <p class="mb-0">Pronto añadiremos nuevos servicios para ti. ¡Vuelve pronto!</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($servicios as $servicio): ?>
            <div class="col">
                <div class="card h-100 shadow-sm border-0 service-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title fw-bold text-dark mb-0">
                                <?= e($servicio['nombre']) ?>
                            </h5>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis border">
                                ⏱ <?= e($servicio['duracion'] ?? 60) ?> min
                            </span>
                        </div>

                        <p class="card-text text-muted small flex-grow-1">
                            <?= !empty($servicio['descripcion']) ? e($servicio['descripcion']) : 'Tratamiento profesional personalizado para ti.' ?>
                        </p>

                        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                            <div>
                                <span class="text-muted small d-block">Precio</span>
                                <span class="fs-5 fw-bold text-primary">
                                    $<?= number_format((float)$servicio['precio'], 0, ',', '.') ?>
                                </span>
                            </div>
                            <?php if (estaLogueado()): ?>
                                <span class="badge bg-success-subtle text-success border">Disponible</span>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/index.php?url=auth/login" class="btn btn-sm btn-outline-primary">
                                    Reservar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

