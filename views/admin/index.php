<?php /* Dashboard de Administración y Agenda - Entrega Final */ ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1">Panel de Control</h1>
        <p class="text-muted mb-0">Monitorea los ingresos, citas y la agenda en tiempo real.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?url=admin/buzon" class="btn btn-outline-dark btn-sm">
            📬 Buzón de Correos
        </a>
        <a href="<?= BASE_URL ?>/index.php?url=admin/disponibilidad" class="btn btn-outline-dark btn-sm">
            ⏰ Disponibilidad Horaria
        </a>
        <a href="<?= BASE_URL ?>/index.php?url=admin/usuarios" class="btn btn-outline-dark btn-sm">
            👥 Gestionar Usuarios
        </a>
        <a href="<?= BASE_URL ?>/index.php?url=servicios/admin" class="btn btn-outline-dark btn-sm">
            ✂️ Gestionar Servicios
        </a>
    </div> 
</div>

<!-- 1. Tarjetas de Estadísticas / Métricas -->
<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
    <!-- Citas de hoy -->
    <div class="col">
        <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Citas para Hoy</span>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= e($metricas['citas_hoy']) ?></h3>
                <small class="text-muted">Agendadas para el día</small>
            </div>
        </div>
    </div>

    <!-- Ingresos del mes -->
    <div class="col">
        <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Ingresos del Mes</span>
                <h3 class="fw-bold text-success mt-1 mb-0">$<?= number_format($metricas['ingresos_mes'], 0, ',', '.') ?></h3>
                <small class="text-muted">Citas confirmadas y activas</small>
            </div>
        </div>
    </div>

    <!-- Citas pendientes -->
    <div class="col">
        <div class="card shadow-sm border-0 border-start border-warning border-4 h-100">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Por Confirmar</span>
                <h3 class="fw-bold text-warning-emphasis mt-1 mb-0"><?= e($metricas['citas_pendientes']) ?></h3>
                <small class="text-muted">Pendientes de revisión</small>
            </div>
        </div>
    </div>

    <!-- Clientes registrados -->
    <div class="col">
        <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Clientes Totales</span>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= e($metricas['total_clientes']) ?></h3>
                <small class="text-muted">Cuentas activas en salón</small>
            </div>
        </div>
    </div>
</div>

<!-- 2. Filtros de Búsqueda de la Agenda -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form action="<?= BASE_URL ?>/index.php" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="url" value="admin/dashboard">
            
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white">📅 Fecha:</span>
                    <input type="date" name="fecha" class="form-control" value="<?= e($filtroFecha) ?>">
                </div>
            </div>

            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white">Estado:</span>
                    <select name="estado" class="form-select">
                        <option value="">-- Todos los estados --</option>
                        <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                        <option value="confirmada" <?= $filtroEstado === 'confirmada' ? 'selected' : '' ?>>Confirmadas</option>
                        <option value="completada" <?= $filtroEstado === 'completada' ? 'selected' : '' ?>>Completadas</option>
                        <option value="cancelada" <?= $filtroEstado === 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
                    </select>
                </div>
            </div>

            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filtrar Agenda</button>
                <?php if (!empty($filtroFecha) || !empty($filtroEstado)): ?>
                    <a href="<?= BASE_URL ?>/index.php?url=admin/dashboard" class="btn btn-sm btn-outline-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- 3. Tabla de Agenda de Citas -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Agenda de Citas</h5>
        <span class="badge bg-secondary-subtle text-secondary-emphasis border">
            Total encontradas: <?= count($citas) ?>
        </span>
    </div>
    <div class="table-responsive">
        <?php if (empty($citas)): ?>
            <div class="text-center py-5 text-muted">
                <p class="mb-0">No se encontraron citas con los filtros seleccionados.</p>
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;"># ID</th>
                        <th>Fecha y Hora</th>
                        <th>Cliente</th>
                        <th>Servicios</th>
                        <th>Total</th>
                        <th>Estado Actual</th>
                        <th class="text-center" style="width: 220px;">Gestionar Estado</th>
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
                                <div class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($c['fecha'])) ?></div>
                                <small class="text-muted">⏱ <?= date('h:i A', strtotime($c['hora'])) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($c['cliente_nombre'] . ' ' . $c['cliente_apellido']) ?></div>
                                <small class="text-muted d-block">✉️ <?= e($c['cliente_email']) ?></small>
                                <small class="text-muted">📞 <?= e($c['cliente_telefono'] ?? 'Sin teléfono') ?></small>
                            </td>
                            <td>
                                <span class="small fw-medium text-dark"><?= e($c['servicios'] ?? 'Sin servicios') ?></span>
                                <div class="text-muted small">Duración: <?= e($c['duracion_total'] ?? 60) ?> min</div>
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
                                <form action="<?= BASE_URL ?>/index.php?url=admin/cita-estado" method="POST" class="d-inline-flex gap-1">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                    <input type="hidden" name="retorno_fecha" value="<?= e($filtroFecha) ?>">

                                    <?php if ($c['estado'] === 'pendiente'): ?>
                                        <button type="submit" name="estado" value="confirmada" class="btn btn-sm btn-outline-success" title="Confirmar Cita">
                                            ✓ Confirmar
                                        </button>
                                        <button type="submit" name="estado" value="cancelada" class="btn btn-sm btn-outline-danger" title="Cancelar Cita" onclick="return confirm('¿Cancelar esta cita?');">
                                            ✕
                                        </button>
                                    <?php elseif ($c['estado'] === 'confirmada'): ?>
                                        <button type="submit" name="estado" value="completada" class="btn btn-sm btn-outline-info" title="Marcar como atendida/completada">
                                            ✓ Completar
                                        </button>
                                        <button type="submit" name="estado" value="cancelada" class="btn btn-sm btn-outline-danger" title="Cancelar Cita" onclick="return confirm('¿Cancelar esta cita?');">
                                            ✕
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Finalizada</span>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>