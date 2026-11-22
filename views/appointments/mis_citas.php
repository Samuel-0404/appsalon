<?php /* Historial de citas del cliente - Entrega Final */ ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1">Mis Citas</h1>
        <p class="text-muted mb-0">Revisa el estado de tus citas reservadas y gestiona tus horarios.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?url=citas/crear" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <span>+</span> Nueva Cita
    </a>
</div>

<?php if (empty($citas)): ?>
    <div class="card shadow-sm border-0 text-center py-5">
        <div class="card-body">
            <div class="display-6 text-muted mb-3">📅</div>
            <h4 class="fw-bold text-dark">Aún no tienes citas agendadas</h4>
            <p class="text-muted mb-4">Elige tus tratamientos favoritos y agenda un espacio con nuestros especialistas.</p>
            <a href="<?= BASE_URL ?>/index.php?url=citas/crear" class="btn btn-primary px-4">
                Reservar mi primera cita
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;"># Cita</th>
                        <th>Fecha y Hora</th>
                        <th>Servicios Incluidos</th>
                        <th>Duración</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th style="width: 250px;" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($citas as $c): 
                        $badgeClass = match($c['estado']) {
                            'confirmada' => 'bg-success-subtle text-success border border-success-subtle',
                            'completada' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                            'cancelada'  => 'bg-danger-subtle text-danger border border-danger-subtle',
                            default      => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
                        };
                    ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?= e($c['id']) ?></td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    🗓 <?= date('d/m/Y', strtotime($c['fecha'])) ?>
                                </div>
                                <small class="text-muted">
                                    ⏱ <?= date('h:i A', strtotime($c['hora'])) ?>
                                </small>
                            </td>
                            <td>
                                <span class="fw-medium text-dark"><?= e($c['servicios'] ?? 'Sin servicios') ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= e($c['duracion_total'] ?? 60) ?> min
                                </span>
                            </td>
                            <td class="fw-bold text-primary">
                                $<?= number_format((float)$c['total'], 0, ',', '.') ?>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?> text-capitalize px-2 py-1">
                                    <?= e($c['estado']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <!-- Botón para que el cliente vea el correo de su cita -->
                                    <a href="<?= BASE_URL ?>/index.php?url=citas/ver-correo&id=<?= e($c['id']) ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-info" 
                                       title="Abrir el correo de confirmación enviado a tu email">
                                        📧 Correo
                                    </a>

                                    <?php if ($c['estado'] === 'pendiente'): ?>
                                        <a href="<?= BASE_URL ?>/index.php?url=citas/editar&id=<?= e($c['id']) ?>" 
                                           class="btn btn-sm btn-outline-primary"
                                           title="Modificar fecha, hora o servicios">
                                            Editar
                                        </a>

                                        <form action="<?= BASE_URL ?>/index.php?url=citas/cancelar" 
                                              method="POST" 
                                              onsubmit="return confirm('¿Seguro que deseas cancelar tu cita del <?= date('d/m/Y', strtotime($c['fecha'])) ?>?');">
                                            <?= csrfCampo() ?>
                                            <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar cita">
                                                ✕
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>