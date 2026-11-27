<?php /* Horario de atención del salón por día de la semana (solo administradores) */
$horas = array_map(fn($h) => sprintf('%02d:00', $h), range(5, 23));
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1">Horario del Salón</h1>
        <p class="text-muted mb-0">Define qué días se atiende y en qué horario. Las reservas, la disponibilidad y la API usan este horario.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?url=admin/disponibilidad" class="btn btn-outline-secondary btn-sm">
        ⏰ Ir a Disponibilidad
    </a>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger shadow-sm">
        <ul class="mb-0 ps-3">
            <?php foreach ($errores as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?= BASE_URL ?>/index.php?url=admin/horario" method="POST">
    <?= csrfCampo() ?>
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Día</th>
                        <th class="text-center">¿Abierto?</th>
                        <th>Apertura</th>
                        <th>Cierre <small class="text-muted fw-normal">(la última cita debe terminar a esta hora)</small></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($semana as $n => $d): ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?= e($d['nombre']) ?></td>
                            <td class="text-center">
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           name="dias[<?= $n ?>][abierto]" value="1" id="abierto-<?= $n ?>"
                                           aria-label="<?= e($d['nombre']) ?> abierto"
                                           <?= $d['abierto'] ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td>
                                <select name="dias[<?= $n ?>][apertura]" class="form-select form-select-sm" aria-label="Apertura <?= e($d['nombre']) ?>">
                                    <?php foreach (array_slice($horas, 0, -1) as $h): ?>
                                        <option value="<?= $h ?>" <?= $d['apertura'] === $h ? 'selected' : '' ?>><?= $h ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="dias[<?= $n ?>][cierre]" class="form-select form-select-sm" aria-label="Cierre <?= e($d['nombre']) ?>">
                                    <?php foreach (array_slice($horas, 1) as $h): ?>
                                        <option value="<?= $h ?>" <?= $d['cierre'] === $h ? 'selected' : '' ?>><?= $h ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
            <small class="text-muted">
                Los festivos de Colombia se bloquean automáticamente. Para cerrar una hora puntual usa <em>Disponibilidad</em>.
                Las citas ya agendadas no se modifican al cambiar el horario.
            </small>
            <button type="submit" class="btn btn-primary px-4">Guardar horario</button>
        </div>
    </div>
</form>
