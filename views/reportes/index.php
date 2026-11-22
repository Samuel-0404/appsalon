<?php /* Responsable: A  -- Reportes y exportación (entrega final) */
$cop = fn($v) => '$' . number_format((float)$v, 0, ',', '.');
$qs  = fn(array $extra = []) => http_build_query(array_filter([
    'inicio' => $filtros['inicio'], 'fin' => $filtros['fin'], 'estado' => $filtros['estado'],
] + $extra));
$estados = ['pendiente', 'confirmada', 'completada', 'cancelada'];
$urlRep  = BASE_URL . '/index.php?url=reportes/';
?>
<link href="<?= BASE_URL ?>/css/reportes.css" rel="stylesheet">

<div class="rep-page">

    <!-- Encabezado + exportaciones -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
        <div>
            <h1 class="rep-title">Reportes</h1>
            <p class="rep-subtitle">Citas e ingresos del <?= e($filtros['inicio']) ?> al <?= e($filtros['fin']) ?>.</p>
        </div>
        <div class="rep-actions" role="group" aria-label="Exportar reporte">
            <a class="rep-btn" href="<?= $urlRep ?>csv&<?= e($qs(['tipo' => 'citas'])) ?>"><i class="bi bi-download" aria-hidden="true"></i>CSV citas</a>
            <a class="rep-btn" href="<?= $urlRep ?>csv&<?= e($qs(['tipo' => 'servicios'])) ?>"><i class="bi bi-download" aria-hidden="true"></i>CSV servicios</a>
            <a class="rep-btn" href="<?= $urlRep ?>csv&<?= e($qs(['tipo' => 'dias'])) ?>"><i class="bi bi-download" aria-hidden="true"></i>CSV por día</a>
            <a class="rep-btn" href="<?= $urlRep ?>pdf&<?= e($qs()) ?>"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>PDF</a>
        </div>
    </div>

    <?php foreach ($errores as $msg): ?>
        <div class="alert alert-warning py-2"><?= e($msg) ?></div>
    <?php endforeach; ?>

    <!-- Filtros (GET: la URL del reporte se puede compartir o guardar) -->
    <form method="get" action="<?= BASE_URL ?>/index.php" class="rep-card mb-3">
        <input type="hidden" name="url" value="reportes/index">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-lg-3">
                <label for="inicio" class="form-label">Desde</label>
                <input type="date" id="inicio" name="inicio" class="form-control" value="<?= e($filtros['inicio']) ?>" required>
            </div>
            <div class="col-6 col-lg-3">
                <label for="fin" class="form-label">Hasta</label>
                <input type="date" id="fin" name="fin" class="form-control" value="<?= e($filtros['fin']) ?>" required>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <label for="estado" class="form-label">Estado (detalle de citas)</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $est): ?>
                        <option value="<?= e($est) ?>" <?= $filtros['estado'] === $est ? 'selected' : '' ?>><?= e(ucfirst($est)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-3 d-flex gap-2">
                <button class="btn rep-btn-primary flex-fill">Aplicar</button>
                <a class="btn btn-outline-secondary flex-fill text-dark" href="<?= $urlRep ?>index">Mes actual</a>
            </div>
        </div>
    </form>

    <!-- KPIs: 2 columnas en móvil y tablet, 4 en escritorio -->
    <div class="row row-cols-2 row-cols-lg-4 g-2 g-sm-3 mb-3">
        <div class="col"><div class="rep-card rep-kpi">
            <span class="rep-kpi-icon"><i class="bi bi-cash-stack ok" aria-hidden="true"></i></span>
            <div class="rep-kpi-label">Ingresos realizados</div>
            <div class="rep-kpi-value"><?= e($cop($resumen['ingresos_realizados'])) ?></div>
            <div class="rep-kpi-hint"><?= (int)$resumen['completadas'] ?> citas completadas</div>
        </div></div>
        <div class="col"><div class="rep-card rep-kpi">
            <span class="rep-kpi-icon" aria-hidden="true"><span class="rep-coin azul">$</span><span class="rep-coin naranja"><?= (int)$resumen['pendientes'] + (int)$resumen['confirmadas'] ?></span></span>
            <div class="rep-kpi-label">Por cobrar</div>
            <div class="rep-kpi-value"><?= e($cop($resumen['ingresos_por_cobrar'])) ?></div>
            <div class="rep-kpi-hint"><?= (int)$resumen['pendientes'] + (int)$resumen['confirmadas'] ?> pendientes o confirmadas</div>
        </div></div>
        <div class="col"><div class="rep-card rep-kpi">
            <span class="rep-kpi-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
            <div class="rep-kpi-label">Ticket promedio</div>
            <div class="rep-kpi-value"><?= e($cop($resumen['ticket_promedio'])) ?></div>
            <div class="rep-kpi-hint"><?= (int)$resumen['clientes_unicos'] ?> clientes atendidos o agendados</div>
        </div></div>
        <div class="col"><div class="rep-card rep-kpi">
            <span class="rep-kpi-icon"><i class="bi bi-percent" aria-hidden="true"></i></span>
            <div class="rep-kpi-label">Citas del período</div>
            <div class="rep-kpi-value"><?= (int)$resumen['total_citas'] ?></div>
            <div class="rep-kpi-hint"><?= number_format((float)$resumen['tasa_cancelacion'], 1, ',', '.') ?> % canceladas</div>
        </div></div>
    </div>

    <!-- Gráficas: apiladas hasta lg -->
    <div class="row g-3 mb-3">
        <div class="col-lg-7"><div class="rep-card h-100">
            <h2 class="rep-card-title">Citas e ingresos por día</h2>
            <div class="rep-chart"><canvas id="graficaDias" role="img" aria-label="Citas e ingresos realizados por día"></canvas></div>
        </div></div>
        <div class="col-lg-5"><div class="rep-card h-100">
            <h2 class="rep-card-title">Ingresos por servicio</h2>
            <?php if ($servicios): ?>
                <div class="rep-chart-small"><canvas id="graficaServicios" role="img" aria-label="Ingresos realizados y por cobrar por servicio"></canvas></div>
            <?php else: ?>
                <p class="text-muted mb-0">No hay servicios reservados en el período.</p>
            <?php endif; ?>
        </div></div>
    </div>

    <!-- Detalle por servicio (en móvil se apila como tarjetas) -->
    <div class="rep-card mb-3">
        <h2 class="rep-card-title">Detalle por servicio</h2>
        <table class="table rep-table rep-stack">
            <thead><tr>
                <th>Servicio</th><th class="text-end">Reservas</th><th class="text-end">Realizados</th>
                <th class="text-end">Ingresos realizados</th><th class="text-end">Por cobrar</th>
            </tr></thead>
            <tbody>
            <?php foreach ($servicios as $s): ?>
                <tr>
                    <td class="rep-stack-head"><?= e($s['nombre']) ?></td>
                    <td class="text-end" data-label="Reservas"><?= (int)$s['reservas'] ?></td>
                    <td class="text-end" data-label="Realizados"><?= (int)$s['realizados'] ?></td>
                    <td class="text-end" data-label="Ingresos realizados"><?= e($cop($s['ingresos_realizados'])) ?></td>
                    <td class="text-end" data-label="Por cobrar"><?= e($cop($s['ingresos_por_cobrar'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$servicios): ?><tr><td colspan="5" class="text-muted text-center rep-empty">Sin datos</td></tr><?php endif; ?>
            </tbody>
        </table>
        <p class="rep-note">El desglose por servicio usa el precio actual de cada servicio.</p>
    </div>

    <!-- Citas del período -->
    <div class="rep-card">
        <h2 class="rep-card-title">Citas del período<?= $filtros['estado'] ? ' (' . e($filtros['estado']) . ')' : '' ?> · <?= count($citas) ?></h2>
        <table class="table rep-table rep-table-head rep-stack">
            <thead><tr>
                <th>#</th><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Servicios</th><th>Estado</th><th class="text-end">Total</th>
            </tr></thead>
            <tbody>
            <?php foreach ($citas as $c): ?>
                <tr>
                    <td class="rep-stack-head">
                        <span class="d-sm-none">Cita #</span><?= (int)$c['id'] ?>
                    </td>
                    <td data-label="Fecha" class="text-nowrap"><?= e($c['fecha']) ?></td>
                    <td data-label="Hora"><?= e($c['hora']) ?></td>
                    <td data-label="Cliente"><?= e($c['cliente'] ?: 'Usuario eliminado') ?></td>
                    <td data-label="Servicios"><?= e($c['servicios'] ?? '—') ?></td>
                    <td data-label="Estado"><span class="rep-pill <?= e($c['estado']) ?>"><?= e($c['estado']) ?></span></td>
                    <td data-label="Total" class="text-end"><?= e($cop($c['total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$citas): ?><tr><td colspan="7" class="text-muted text-center rep-empty">No hay citas en el período</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
    // JSON_HEX_TAG escapa < y > de los datos: un valor malicioso no puede cerrar este bloque (XSS)
    const dias      = <?= json_encode($porDia, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const servicios = <?= json_encode(array_map(fn($s) => ['nombre' => $s['nombre'], 'ingresos' => (float)$s['ingresos_realizados'], 'porCobrar' => (float)$s['ingresos_por_cobrar']], $servicios), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const cop    = v => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v);
    const copEje = v => v >= 1e6 ? '$ ' + (v / 1e6).toLocaleString('es-CO') + ' M' : '$ ' + v.toLocaleString('es-CO');
    const movil  = () => window.matchMedia('(max-width: 575.98px)').matches;

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size   = 11;
    Chart.defaults.color       = '#475569';
    const rejilla = { color: '#e5e7eb', borderDash: [4, 4] };
    const leyenda = { position: 'top', labels: { boxWidth: movil() ? 18 : 26, boxHeight: 10, padding: movil() ? 8 : 12, sort: (a, b) => a.datasetIndex - b.datasetIndex } };

    const MORADO = '#5b54b8', MORADO_SUAVE = '#a5a2f0', VERDE = '#16a34a';

    new Chart(document.getElementById('graficaDias'), {
        data: {
            labels: dias.map(d => d.fecha.slice(5)),
            datasets: [
                { type: 'bar', label: 'Citas', data: dias.map(d => d.citas), yAxisID: 'yCitas',
                  backgroundColor: MORADO, maxBarThickness: 14, order: 2 },
                { type: 'line', label: 'Ingresos realizados', data: dias.map(d => d.ingresos), yAxisID: 'yCop',
                  borderColor: VERDE, backgroundColor: '#fff', borderWidth: 2, tension: .35,
                  pointRadius: movil() ? 2 : 3, pointBorderWidth: 1.5, pointBackgroundColor: '#fff', pointBorderColor: VERDE, order: 1 }
            ]
        },
        options: {
            maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            scales: {
                x:      { grid: rejilla, ticks: { maxRotation: 50, minRotation: 50, autoSkip: true, autoSkipPadding: 4 } },
                yCitas: { position: 'left', beginAtZero: true, grid: rejilla, border: { dash: [4, 4] },
                          ticks: { precision: 0 }, title: { display: !movil(), text: 'Citas' } },
                yCop:   { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: copEje } }
            },
            plugins: {
                legend: leyenda,
                tooltip: { callbacks: { label: c => c.dataset.yAxisID === 'yCop' ? ` ${c.dataset.label}: ${cop(c.parsed.y)}` : ` ${c.dataset.label}: ${c.parsed.y}` } }
            }
        }
    });

    const el = document.getElementById('graficaServicios');
    if (el) new Chart(el, {
        type: 'bar',
        data: {
            labels: servicios.map(s => s.nombre),
            datasets: [
                { label: 'Realizados', data: servicios.map(s => s.ingresos),  backgroundColor: VERDE,        maxBarThickness: 28 },
                { label: 'Por cobrar', data: servicios.map(s => s.porCobrar), backgroundColor: MORADO_SUAVE, maxBarThickness: 28 }
            ]
        },
        options: {
            indexAxis: 'y', maintainAspectRatio: false,
            scales: {
                x: { stacked: true, beginAtZero: true, grid: rejilla, ticks: { callback: copEje, maxTicksLimit: movil() ? 4 : 6 } },
                y: { stacked: true, grid: rejilla }
            },
            plugins: { legend: leyenda, tooltip: { callbacks: { label: c => ` ${c.dataset.label}: ${cop(c.parsed.x)}` } } }
        }
    });
})();
</script>
