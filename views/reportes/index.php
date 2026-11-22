<?php /* Responsable: A  -- Reportes y exportación (entrega final) */
$cop = fn($v) => '$' . number_format((float)$v, 0, ',', '.');
$qs  = fn(array $extra = []) => http_build_query(array_filter([
    'inicio' => $filtros['inicio'], 'fin' => $filtros['fin'], 'estado' => $filtros['estado'],
] + $extra));
$badge = ['pendiente' => 'warning text-dark', 'confirmada' => 'primary', 'completada' => 'success', 'cancelada' => 'secondary'];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1">Reportes</h1>
        <p class="text-muted mb-0">Citas e ingresos del <?= e($filtros['inicio']) ?> al <?= e($filtros['fin']) ?>.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-dark btn-sm" href="<?= BASE_URL ?>/index.php?url=reportes/csv&<?= e($qs(['tipo' => 'citas'])) ?>">⬇️ CSV citas</a>
        <a class="btn btn-outline-dark btn-sm" href="<?= BASE_URL ?>/index.php?url=reportes/csv&<?= e($qs(['tipo' => 'servicios'])) ?>">⬇️ CSV servicios</a>
        <a class="btn btn-outline-dark btn-sm" href="<?= BASE_URL ?>/index.php?url=reportes/csv&<?= e($qs(['tipo' => 'dias'])) ?>">⬇️ CSV por día</a>
        <a class="btn btn-dark btn-sm" href="<?= BASE_URL ?>/index.php?url=reportes/pdf&<?= e($qs()) ?>">📄 PDF</a>
    </div>
</div>

<?php foreach ($errores as $msg): ?>
    <div class="alert alert-warning py-2"><?= e($msg) ?></div>
<?php endforeach; ?>

<!-- Filtros (GET: la URL del reporte se puede compartir o guardar) -->
<form method="get" action="<?= BASE_URL ?>/index.php" class="card card-body shadow-sm border-0 mb-4">
    <input type="hidden" name="url" value="reportes/index">
    <div class="row g-3 align-items-end">
        <div class="col-sm-6 col-lg-3">
            <label for="inicio" class="form-label small fw-bold">Desde</label>
            <input type="date" id="inicio" name="inicio" class="form-control" value="<?= e($filtros['inicio']) ?>" required>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label for="fin" class="form-label small fw-bold">Hasta</label>
            <input type="date" id="fin" name="fin" class="form-control" value="<?= e($filtros['fin']) ?>" required>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label for="estado" class="form-label small fw-bold">Estado (detalle de citas)</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">Todos</option>
                <?php foreach (array_keys($badge) as $est): ?>
                    <option value="<?= e($est) ?>" <?= $filtros['estado'] === $est ? 'selected' : '' ?>><?= e(ucfirst($est)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill">Aplicar</button>
            <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/index.php?url=reportes/index">Mes actual</a>
        </div>
    </div>
</form>

<!-- KPIs -->
<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
    <div class="col"><div class="card shadow-sm border-0 border-start border-success border-4 h-100"><div class="card-body">
        <span class="text-muted small text-uppercase fw-bold">Ingresos realizados</span>
        <div class="h3 fw-bold mb-0"><?= e($cop($resumen['ingresos_realizados'])) ?></div>
        <small class="text-muted"><?= (int)$resumen['completadas'] ?> citas completadas</small>
    </div></div></div>
    <div class="col"><div class="card shadow-sm border-0 border-start border-primary border-4 h-100"><div class="card-body">
        <span class="text-muted small text-uppercase fw-bold">Por cobrar</span>
        <div class="h3 fw-bold mb-0"><?= e($cop($resumen['ingresos_por_cobrar'])) ?></div>
        <small class="text-muted"><?= (int)$resumen['pendientes'] + (int)$resumen['confirmadas'] ?> pendientes o confirmadas</small>
    </div></div></div>
    <div class="col"><div class="card shadow-sm border-0 border-start border-info border-4 h-100"><div class="card-body">
        <span class="text-muted small text-uppercase fw-bold">Ticket promedio</span>
        <div class="h3 fw-bold mb-0"><?= e($cop($resumen['ticket_promedio'])) ?></div>
        <small class="text-muted"><?= (int)$resumen['clientes_unicos'] ?> clientes atendidos o agendados</small>
    </div></div></div>
    <div class="col"><div class="card shadow-sm border-0 border-start border-secondary border-4 h-100"><div class="card-body">
        <span class="text-muted small text-uppercase fw-bold">Citas del período</span>
        <div class="h3 fw-bold mb-0"><?= (int)$resumen['total_citas'] ?></div>
        <small class="text-muted"><?= number_format((float)$resumen['tasa_cancelacion'], 1, ',', '.') ?> % canceladas</small>
    </div></div></div>
</div>

<!-- Gráficas -->
<div class="row g-3 mb-4">
    <div class="col-lg-7"><div class="card shadow-sm border-0 h-100"><div class="card-body">
        <h2 class="h6 fw-bold">Citas e ingresos por día</h2>
        <div style="position:relative;height:280px"><canvas id="graficaDias" aria-label="Citas e ingresos por día" role="img"></canvas></div>
    </div></div></div>
    <div class="col-lg-5"><div class="card shadow-sm border-0 h-100"><div class="card-body">
        <h2 class="h6 fw-bold">Ingresos por servicio</h2>
        <?php if ($servicios): ?>
            <div style="position:relative;height:280px"><canvas id="graficaServicios" aria-label="Ingresos por servicio" role="img"></canvas></div>
        <?php else: ?>
            <p class="text-muted mb-0">No hay servicios reservados en el período.</p>
        <?php endif; ?>
    </div></div></div>
</div>

<!-- Tabla por servicio -->
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h2 class="h6 fw-bold">Detalle por servicio</h2>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>Servicio</th><th class="text-end">Reservas</th><th class="text-end">Realizados</th>
                <th class="text-end">Ingresos realizados</th><th class="text-end">Por cobrar</th>
            </tr></thead>
            <tbody>
            <?php foreach ($servicios as $s): ?>
                <tr>
                    <td><?= e($s['nombre']) ?></td>
                    <td class="text-end"><?= (int)$s['reservas'] ?></td>
                    <td class="text-end"><?= (int)$s['realizados'] ?></td>
                    <td class="text-end"><?= e($cop($s['ingresos_realizados'])) ?></td>
                    <td class="text-end"><?= e($cop($s['ingresos_por_cobrar'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$servicios): ?><tr><td colspan="5" class="text-muted text-center">Sin datos</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <small class="text-muted">El desglose por servicio usa el precio actual de cada servicio.</small>
</div></div>

<!-- Tabla de citas -->
<div class="card shadow-sm border-0"><div class="card-body">
    <h2 class="h6 fw-bold">Citas del período <?= $filtros['estado'] ? '(' . e($filtros['estado']) . ')' : '' ?> · <?= count($citas) ?></h2>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>#</th><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Servicios</th><th>Estado</th><th class="text-end">Total</th>
            </tr></thead>
            <tbody>
            <?php foreach ($citas as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><?= e($c['fecha']) ?></td>
                    <td><?= e($c['hora']) ?></td>
                    <td><?= e($c['cliente'] ?: 'Usuario eliminado') ?></td>
                    <td class="small"><?= e($c['servicios'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= e($badge[$c['estado']] ?? 'light') ?>"><?= e($c['estado']) ?></span></td>
                    <td class="text-end"><?= e($cop($c['total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$citas): ?><tr><td colspan="7" class="text-muted text-center">No hay citas en el período</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
    // JSON_HEX_TAG escapa < y > de los datos: un valor malicioso no puede cerrar este bloque (XSS)
    const dias      = <?= json_encode($porDia, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const servicios = <?= json_encode(array_map(fn($s) => ['nombre' => $s['nombre'], 'ingresos' => (float)$s['ingresos_realizados'], 'porCobrar' => (float)$s['ingresos_por_cobrar']], $servicios), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const cop = v => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v);

    new Chart(document.getElementById('graficaDias'), {
        data: {
            labels: dias.map(d => d.fecha.slice(5)),
            datasets: [
                { type: 'bar',  label: 'Citas', data: dias.map(d => d.citas), yAxisID: 'yCitas', backgroundColor: 'rgba(99,102,241,.55)' },
                { type: 'line', label: 'Ingresos realizados', data: dias.map(d => d.ingresos), yAxisID: 'yCop',
                  borderColor: '#16a34a', backgroundColor: '#16a34a', tension: .25, pointRadius: 2 }
            ]
        },
        options: {
            maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            scales: {
                yCitas: { position: 'left', beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Citas' } },
                yCop:   { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: cop } }
            },
            plugins: { tooltip: { callbacks: { label: c => c.dataset.yAxisID === 'yCop' ? `${c.dataset.label}: ${cop(c.parsed.y)}` : `${c.dataset.label}: ${c.parsed.y}` } } }
        }
    });

    const el = document.getElementById('graficaServicios');
    if (el) new Chart(el, {
        type: 'bar',
        data: {
            labels: servicios.map(s => s.nombre),
            datasets: [
                { label: 'Realizados', data: servicios.map(s => s.ingresos),  backgroundColor: '#16a34a' },
                { label: 'Por cobrar', data: servicios.map(s => s.porCobrar), backgroundColor: 'rgba(99,102,241,.55)' }
            ]
        },
        options: {
            indexAxis: 'y', maintainAspectRatio: false,
            scales: { x: { stacked: true, beginAtZero: true, ticks: { callback: cop } }, y: { stacked: true } },
            plugins: { tooltip: { callbacks: { label: c => `${c.dataset.label}: ${cop(c.parsed.x)}` } } }
        }
    });
})();
</script>
