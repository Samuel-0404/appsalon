<?php 

/**
 * Responsable: A -- Reportes y exportación (entrega final).
 * Sigue el estilo del panel de B (admin/index.php).
 *
 * Variables que inyecta ReportController::index() mediante render()/extract():
 *
 * @var array{inicio: string, fin: string, estado: ?string, servicio: ?int} $filtros
 * @var array<string, string> $errores
 * @var array<string, mixed> $resumen
 * @var array<int, array<string, mixed>> $servicios
 * @var array<int, array{fecha: string, citas: int, ingresos: float}> $porDia
 * @var array<int, array<string, mixed>> $citas
 * @var array<int, array<string, mixed>> $catalogo  servicios activos para el filtro
 */

$cop = fn($v) => '$' . number_format((float)$v, 0, ',', '.');
$qs  = fn(array $extra = []) => http_build_query(array_filter([
    'inicio' => $filtros['inicio'], 'fin' => $filtros['fin'], 'estado' => $filtros['estado'], 'servicio' => $filtros['servicio'],
] + $extra));
$urlRep  = BASE_URL . '/index.php?url=reportes/';
$estados = ['pendiente', 'confirmada', 'completada', 'cancelada'];
$badgeDe = fn(string $estado) => match ($estado) {
    'confirmada' => 'bg-success-subtle text-success border border-success-subtle',
    'completada' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
    'cancelada'  => 'bg-danger-subtle text-danger border border-danger-subtle',
    default      => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
};
?>

<!-- Encabezado + exportaciones (los botones bajan y se reparten en pantallas pequeñas) -->
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1">Reportes</h1>
        <p class="text-muted mb-0">Citas e ingresos del <?= date('d/m/Y', strtotime($filtros['inicio'])) ?> al <?= date('d/m/Y', strtotime($filtros['fin'])) ?><?php
            $nombreSrv = $filtros['servicio'] ? (array_column($catalogo, 'nombre', 'id')[$filtros['servicio']] ?? null) : null;
            echo $nombreSrv ? ' · solo <strong>' . e($nombreSrv) . '</strong> (ingresos = precio del servicio)' : '';
        ?>.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= $urlRep ?>csv&<?= e($qs(['tipo' => 'citas'])) ?>" class="btn btn-outline-dark btn-sm flex-fill flex-lg-grow-0">📋 CSV Citas</a>
        <a href="<?= $urlRep ?>csv&<?= e($qs(['tipo' => 'servicios'])) ?>" class="btn btn-outline-dark btn-sm flex-fill flex-lg-grow-0">✂️ CSV Servicios</a>
        <a href="<?= $urlRep ?>csv&<?= e($qs(['tipo' => 'dias'])) ?>" class="btn btn-outline-dark btn-sm flex-fill flex-lg-grow-0">📅 CSV por Día</a>
        <a href="<?= $urlRep ?>pdf&<?= e($qs()) ?>" class="btn btn-outline-dark btn-sm flex-fill flex-lg-grow-0">📄 Exportar PDF</a>
    </div>
</div>

<?php foreach ($errores as $msg): ?>
    <div class="alert alert-warning py-2"><?= e($msg) ?></div>
<?php endforeach; ?>

<!-- 1. Tarjetas de métricas -->
<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
    <div class="col">
        <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Ingresos Realizados</span>
                <h3 class="fw-bold text-success mt-1 mb-0"><?= e($cop($resumen['ingresos_realizados'])) ?></h3>
                <small class="text-muted"><?= (int)$resumen['completadas'] ?> citas completadas</small>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Por Cobrar</span>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= e($cop($resumen['ingresos_por_cobrar'])) ?></h3>
                <small class="text-muted"><?= (int)$resumen['pendientes'] + (int)$resumen['confirmadas'] ?> pendientes o confirmadas</small>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Ticket Promedio</span>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= e($cop($resumen['ticket_promedio'])) ?></h3>
                <small class="text-muted"><?= (int)$resumen['clientes_unicos'] ?> clientes atendidos o agendados</small>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm border-0 border-start border-warning border-4 h-100">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-bold">Citas del Período</span>
                <h3 class="fw-bold text-warning-emphasis mt-1 mb-0"><?= (int)$resumen['total_citas'] ?></h3>
                <small class="text-muted"><?= number_format((float)$resumen['tasa_cancelacion'], 1, ',', '.') ?> % canceladas</small>
            </div>
        </div>
    </div>
</div>

<!-- 2. Filtros del reporte (GET: la URL se puede guardar o compartir) -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form action="<?= BASE_URL ?>/index.php" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="url" value="reportes/index">

            <div class="col-sm-6 col-lg-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white">📅 Desde:</span>
                    <input type="date" name="inicio" class="form-control" value="<?= e($filtros['inicio']) ?>" required>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white">📅 Hasta:</span>
                    <input type="date" name="fin" class="form-control" value="<?= e($filtros['fin']) ?>" required>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white">✂️ Servicio:</span>
                    <select name="servicio" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($catalogo as $srv): ?>
                            <option value="<?= (int)$srv['id'] ?>" <?= (int)$filtros['servicio'] === (int)$srv['id'] ? 'selected' : '' ?>><?= e($srv['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white">📌 Estado:</span>
                    <select name="estado" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($estados as $est): ?>
                            <option value="<?= e($est) ?>" <?= $filtros['estado'] === $est ? 'selected' : '' ?>><?= e(ucfirst($est)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3 flex-fill text-nowrap">Filtrar</button>
                <a href="<?= $urlRep ?>index" class="btn btn-sm btn-outline-secondary flex-fill text-nowrap">Mes Actual</a>
            </div>
        </form>
    </div>
</div>

<!-- 3. Gráficas (una al lado de la otra desde lg, apiladas en móvil y tablet) -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold">Citas e Ingresos por Día</h5>
            </div>
            <div class="card-body">
                <div class="position-relative" style="height: clamp(220px, 40vw, 300px);">
                    <canvas id="graficaDias" role="img" aria-label="Citas e ingresos realizados por día"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold">Ingresos por Servicio</h5>
            </div>
            <div class="card-body">
                <?php if ($servicios): ?>
                    <div class="position-relative" style="height: clamp(220px, 40vw, 300px);">
                        <canvas id="graficaServicios" role="img" aria-label="Ingresos realizados y por cobrar por servicio"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted"><p class="mb-0">No hay servicios reservados en el período.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 4. Detalle por servicio -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Detalle por Servicio</h5>
        <span class="badge bg-secondary-subtle text-secondary-emphasis border">Precio actual del servicio</span>
    </div>
    <div class="table-responsive">
        <?php if (empty($servicios)): ?>
            <div class="text-center py-5 text-muted"><p class="mb-0">No hay servicios reservados en el período.</p></div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Servicio</th>
                        <th class="text-end">Reservas</th>
                        <th class="text-end">Realizados</th>
                        <th class="text-end text-nowrap">Ingresos Realizados</th>
                        <th class="text-end text-nowrap">Por Cobrar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servicios as $s): ?>
                        <tr>
                            <td class="fw-semibold text-dark text-nowrap"><?= e($s['nombre']) ?></td>
                            <td class="text-end"><?= (int)$s['reservas'] ?></td>
                            <td class="text-end"><?= (int)$s['realizados'] ?></td>
                            <td class="text-end fw-bold text-success"><?= e($cop($s['ingresos_realizados'])) ?></td>
                            <td class="text-end"><?= e($cop($s['ingresos_por_cobrar'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- 5. Citas del período -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Citas del Período<?= $filtros['estado'] ? ' · ' . e(ucfirst($filtros['estado'])) : '' ?></h5>
        <span class="badge bg-secondary-subtle text-secondary-emphasis border">Total encontradas: <?= count($citas) ?></span>
    </div>
    <div class="table-responsive">
        <?php if (empty($citas)): ?>
            <div class="text-center py-5 text-muted"><p class="mb-0">No hay citas en el período seleccionado.</p></div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;"># ID</th>
                        <th class="text-nowrap">Fecha y Hora</th>
                        <th>Cliente</th>
                        <th>Servicios</th>
                        <th>Estado</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($citas as $c): ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?= (int)$c['id'] ?></td>
                            <td class="text-nowrap">
                                <div class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($c['fecha'])) ?></div>
                                <small class="text-muted">⏰ <?= e($c['hora']) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark text-nowrap"><?= e($c['cliente'] ?: 'Usuario eliminado') ?></div>
                                <small class="text-muted"><?= e($c['email'] ?? '') ?></small>
                            </td>
                            <td><small><?= e($c['servicios'] ?? '—') ?></small></td>
                            <td>
                                <span class="badge <?= $badgeDe($c['estado']) ?> text-capitalize px-2 py-1"><?= e($c['estado']) ?></span>
                            </td>
                            <td class="text-end fw-bold text-dark"><?= e($cop($c['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
    // JSON_HEX_TAG escapa < y > de los datos: un valor malicioso no puede cerrar este bloque (XSS)
    const dias      = <?= json_encode($porDia, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const servicios = <?= json_encode(array_map(fn($s) => ['nombre' => $s['nombre'], 'ingresos' => (float)$s['ingresos_realizados'], 'porCobrar' => (float)$s['ingresos_por_cobrar']], $servicios), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const cop    = v => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v);
    const movil  = window.matchMedia('(max-width: 575.98px)').matches;

    // Colores de styles.css (--primary-color) y verde "success" de Bootstrap
    const PRIMARIO = '#6366f1', PRIMARIO_SUAVE = 'rgba(99,102,241,.35)', VERDE = '#198754';
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size   = movil ? 10 : 12;
    Chart.defaults.color       = '#64748b';
    const leyenda = { labels: { boxWidth: movil ? 14 : 22, padding: movil ? 8 : 12, sort: (a, b) => a.datasetIndex - b.datasetIndex } };

    new Chart(document.getElementById('graficaDias'), {
        data: {
            labels: dias.map(d => d.fecha.slice(8) + '/' + d.fecha.slice(5, 7)),
            datasets: [
                { type: 'bar', label: 'Citas', data: dias.map(d => d.citas), yAxisID: 'yCitas',
                  backgroundColor: PRIMARIO, borderRadius: 4, maxBarThickness: 16, order: 2 },
                { type: 'line', label: 'Ingresos realizados', data: dias.map(d => d.ingresos), yAxisID: 'yCop',
                  borderColor: VERDE, backgroundColor: VERDE, tension: .3, pointRadius: movil ? 1.5 : 2.5, order: 1 }
            ]
        },
        options: {
            maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            scales: {
                x:      { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 } },
                yCitas: { position: 'left', beginAtZero: true, ticks: { precision: 0 } },
                yCop:   { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false },
                          ticks: { callback: v => movil ? '$' + (v / 1000) + 'k' : cop(v) } }
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
                { label: 'Realizados', data: servicios.map(s => s.ingresos),  backgroundColor: VERDE,          borderRadius: 4, maxBarThickness: 26 },
                { label: 'Por cobrar', data: servicios.map(s => s.porCobrar), backgroundColor: PRIMARIO_SUAVE, borderRadius: 4, maxBarThickness: 26 }
            ]
        },
        options: {
            indexAxis: 'y', maintainAspectRatio: false,
            scales: {
                x: { stacked: true, beginAtZero: true, ticks: { maxTicksLimit: movil ? 4 : 6, callback: v => movil ? '$' + (v / 1000) + 'k' : cop(v) } },
                y: { stacked: true, grid: { display: false } }
            },
            plugins: { legend: leyenda, tooltip: { callbacks: { label: c => ` ${c.dataset.label}: ${cop(c.parsed.x)}` } } }
        }
    });
})();
</script>
