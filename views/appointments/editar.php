<?php?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1">Modificar Cita #<?= e($cita['id']) ?></h1>
        <p class="text-muted mb-0">Cambia los servicios, la fecha o el horario de tu cita reservada.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?url=citas/mis-citas" class="btn btn-outline-secondary btn-sm">
        ← Volver a Mis Citas
    </a>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger shadow-sm">
        <h6 class="fw-bold mb-1">Por favor revisa los siguientes puntos:</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errores as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?= BASE_URL ?>/index.php?url=citas/editar&id=<?= e($cita['id']) ?>" method="POST" id="form-cita">
    <?= csrfCampo() ?>

    <div class="row g-4">
        <!-- Columna Izquierda: Servicios -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold">1. Modifica tus servicios</h5>
                    <small class="text-muted">Marca o desmarca los servicios que deseas incluir</small>
                </div>
                <div class="card-body p-3">
                    <?php if (empty($servicios)): ?>
                        <p class="text-muted text-center py-3">No hay servicios disponibles.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($servicios as $s): 
                                $marcado = in_array((int)$s['id'], array_map('intval', $cita['servicios'] ?? [])) ? 'checked' : '';
                            ?>
                                <label class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3 rounded-3 mb-2 border service-item" style="cursor: pointer;">
                                    <div class="d-flex align-items-center gap-3">
                                        <input class="form-check-input flex-shrink-0 check-servicio" 
                                               type="checkbox" 
                                               name="servicios[]" 
                                               value="<?= e($s['id']) ?>" 
                                               data-precio="<?= (float)$s['precio'] ?>"
                                               data-nombre="<?= e($s['nombre']) ?>"
                                               data-duracion="<?= (int)($s['duracion'] ?? 60) ?>"
                                               <?= $marcado ?>>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= e($s['nombre']) ?></div>
                                            <small class="text-muted"><?= e($s['descripcion'] ?? 'Tratamiento profesional') ?></small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-light text-dark border mb-1">⏱ <?= e($s['duracion'] ?? 60) ?> min</span>
                                        <div class="fw-bold text-primary">$<?= number_format((float)$s['precio'], 0, ',', '.') ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Nueva Fecha y Horario -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold">2. Nueva Fecha y Horario</h5>
                </div>
                <div class="card-body p-4">
                    <!-- Fecha -->
                    <div class="mb-3">
                        <label for="fecha" class="form-label fw-semibold">Fecha de la cita <span class="text-danger">*</span></label>
                        <input type="date" 
                               class="form-control" 
                               id="fecha" 
                               name="fecha" 
                               min="<?= date('Y-m-d') ?>" 
                               value="<?= e($cita['fecha'] ?? '') ?>" 
                               required>
                    </div>

                    <!-- Hora -->
                    <div class="mb-4">
                        <label for="hora" class="form-label fw-semibold">Hora de atención <span class="text-danger">*</span></label>
                        <select class="form-select" id="hora" name="hora" required>
                            <option value="">-- Selecciona una hora --</option>
                            <?php
                            $horas = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
                            foreach ($horas as $h):
                                $sel = (($cita['hora'] ?? '') === $h) ? 'selected' : '';
                            ?>
                                <option value="<?= $h ?>" <?= $sel ?>><?= $h ?> (<?= ((int)$h < 12) ? $h.' AM' : (((int)$h === 12) ? '12:00 PM' : ((int)$h - 12).':00 PM') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Horario de 09:00 AM a 06:00 PM.</div>
                    </div>

                    <!-- Resumen en vivo -->
                    <div class="border rounded-3 p-3 bg-light mb-4">
                        <h6 class="fw-bold text-dark mb-2">Nuevo resumen</h6>
                        <div id="lista-resumen" class="small text-muted mb-2">
                            <em>Calculando servicios...</em>
                        </div>
                        <div class="d-flex justify-content-between small text-muted pt-2 border-top">
                            <span>Duración estimada:</span>
                            <strong id="duracion-total" class="text-dark">0 min</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
                            <span class="fw-bold text-dark">Nuevo Total:</span>
                            <span id="precio-total" class="fs-4 fw-bold text-primary">$0</span>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/index.php?url=citas/mis-citas" class="btn btn-outline-secondary w-50 py-2">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary w-50 py-2 fw-semibold" id="btn-guardar">
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const checks = document.querySelectorAll('.check-servicio');
    const precioTotalEl = document.getElementById('precio-total');
    const duracionTotalEl = document.getElementById('duracion-total');
    const listaResumenEl = document.getElementById('lista-resumen');

    function actualizarResumen() {
        let total = 0;
        let duracion = 0;
        const seleccionados = [];

        checks.forEach(check => {
            if (check.checked) {
                const precio = parseFloat(check.dataset.precio) || 0;
                const dur = parseInt(check.dataset.duracion) || 0;
                total += precio;
                duracion += dur;
                seleccionados.push(check.dataset.nombre);
            }
        });

        precioTotalEl.textContent = '$' + total.toLocaleString('es-CO');
        duracionTotalEl.textContent = duracion + ' min';

        if (seleccionados.length === 0) {
            listaResumenEl.innerHTML = '<em class="text-danger">Debes tener al menos un servicio seleccionado.</em>';
        } else {
            listaResumenEl.innerHTML = '<ul class="mb-0 ps-3">' + 
                seleccionados.map(s => `<li>${s}</li>`).join('') + 
                '</ul>';
        }
    }

    checks.forEach(check => {
        check.addEventListener('change', actualizarResumen);
    });

    actualizarResumen();
});
</script>