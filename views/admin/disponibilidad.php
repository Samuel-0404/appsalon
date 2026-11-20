<?php /* Gestión de Disponibilidad Horaria - Entrega Final */ ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1">Disponibilidad Horaria</h1>
        <p class="text-muted mb-0">Bloquea o libera franjas horarias del salón para mantenimiento o eventos especiales.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?url=admin/dashboard" class="btn btn-outline-secondary btn-sm">
        ← Volver al Dashboard
    </a>
</div>

<!-- Selector de Fecha -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form action="<?= BASE_URL ?>/index.php" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="url" value="admin/disponibilidad">
            <div class="col-auto">
                <label for="fecha" class="col-form-label fw-semibold">Seleccionar Fecha:</label>
            </div>
            <div class="col-auto">
                <input type="date" 
                       id="fecha" 
                       name="fecha" 
                       class="form-control" 
                       value="<?= e($fecha) ?>" 
                       onchange="this.form.submit()">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Consultar Horarios</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Franjas Horarias del Día -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Horarios para el día: <?= date('d/m/Y', strtotime($fecha)) ?></h5>
        <small class="text-muted">Horario comercial (09:00 AM a 06:00 PM)</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 140px;">Hora</th>
                    <th style="width: 180px;">Estado del Turno</th>
                    <th>Detalle</th>
                    <th class="text-center" style="width: 200px;">Acción de Disponibilidad</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horarios as $h): 
                    $hNum = (int)substr($h['hora'], 0, 2);
                    $label = ($hNum < 12) ? $h['hora'].' AM' : (($hNum === 12) ? '12:00 PM' : ($hNum-12).':00 PM');
                ?>
                    <tr>
                        <td class="fw-bold text-dark fs-6">
                            ⏱ <?= $label ?>
                        </td>
                        <td>
                            <?php if ($h['estado'] === 'libre'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                    🟢 Disponible
                                </span>
                            <?php elseif ($h['estado'] === 'bloqueado'): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                    🔒 Bloqueado por Admin
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                    🟡 Cita Agendada
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted">
                            <?php if ($h['estado'] === 'libre'): ?>
                                <span class="text-success small">Espacio libre para reservas de clientes.</span>
                            <?php elseif ($h['estado'] === 'bloqueado'): ?>
                                <span class="text-danger small fw-semibold">Horario cerrado administrativamente.</span>
                            <?php else: ?>
                                <span class="text-dark fw-medium"><?= e($h['detalle']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($h['estado'] === 'libre'): ?>
                                <!-- Formulario para Bloquear -->
                                <form action="<?= BASE_URL ?>/index.php?url=admin/bloquear-hora" method="POST">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
                                    <input type="hidden" name="hora" value="<?= e($h['hora']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Cerrar este horario para reservas">
                                        🔒 Bloquear Horario
                                    </button>
                                </form>
                            <?php elseif ($h['estado'] === 'bloqueado'): ?>
                                <!-- Formulario para Liberar -->
                                <form action="<?= BASE_URL ?>/index.php?url=admin/liberar-hora" method="POST">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
                                    <input type="hidden" name="hora" value="<?= e($h['hora']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Volver a habilitar este horario">
                                        🔓 Desbloquear
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/index.php?url=admin/dashboard&fecha=<?= urlencode($fecha) ?>" class="btn btn-sm btn-outline-secondary">
                                    Ver en Agenda
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>