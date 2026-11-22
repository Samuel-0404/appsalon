<?php
/* Responsable: A  -- Plantilla HTML para TCPDF (subconjunto de HTML: tablas y estilos en línea).
   Variables: $f (filtros), $resumen, $servicios, $citas */
$cop = fn($v) => '$' . number_format((float)$v, 0, ',', '.');
?>
<h1 style="font-size:18pt;color:#1e1e2d;">AppSalon · Reporte de citas e ingresos</h1>
<p style="color:#64748b;">Período: <b><?= e($f['inicio']) ?></b> a <b><?= e($f['fin']) ?></b>
<?= $f['estado'] ? ' · Estado: <b>' . e($f['estado']) . '</b>' : '' ?> · Generado: <?= e(date('Y-m-d H:i')) ?></p>

<table cellpadding="6" border="0">
  <tr style="background-color:#f1f5f9;">
    <td><b>Ingresos realizados</b><br><?= e($cop($resumen['ingresos_realizados'])) ?></td>
    <td><b>Por cobrar</b><br><?= e($cop($resumen['ingresos_por_cobrar'])) ?></td>
    <td><b>Ticket promedio</b><br><?= e($cop($resumen['ticket_promedio'])) ?></td>
    <td><b>Citas</b><br><?= (int)$resumen['total_citas'] ?> (<?= (int)$resumen['canceladas'] ?> canceladas)</td>
  </tr>
</table>

<h2 style="font-size:13pt;">Ingresos por servicio</h2>
<table cellpadding="4" border="1" style="border-color:#cbd5e1;">
  <thead>
    <tr style="background-color:#1e1e2d;color:#ffffff;font-weight:bold;">
      <th width="40%">Servicio</th><th width="15%" align="right">Reservas</th><th width="15%" align="right">Realizados</th>
      <th width="15%" align="right">Ingresos</th><th width="15%" align="right">Por cobrar</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($servicios as $s): ?>
    <tr>
      <td width="40%"><?= e($s['nombre']) ?></td>
      <td width="15%" align="right"><?= (int)$s['reservas'] ?></td>
      <td width="15%" align="right"><?= (int)$s['realizados'] ?></td>
      <td width="15%" align="right"><?= e($cop($s['ingresos_realizados'])) ?></td>
      <td width="15%" align="right"><?= e($cop($s['ingresos_por_cobrar'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$servicios): ?><tr><td colspan="5" align="center">Sin datos en el período</td></tr><?php endif; ?>
  </tbody>
</table>
<p style="font-size:8pt;color:#64748b;">El desglose por servicio usa el precio actual de cada servicio.</p>

<h2 style="font-size:13pt;">Detalle de citas (<?= count($citas) ?>)</h2>
<table cellpadding="3" border="1" style="border-color:#cbd5e1;font-size:8.5pt;">
  <thead>
    <tr style="background-color:#1e1e2d;color:#ffffff;font-weight:bold;">
      <th width="7%">#</th><th width="13%">Fecha</th><th width="8%">Hora</th><th width="20%">Cliente</th>
      <th width="27%">Servicios</th><th width="12%">Estado</th><th width="13%" align="right">Total</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($citas as $i => $c): ?>
    <tr<?= $i % 2 ? ' style="background-color:#f8fafc;"' : '' ?>>
      <td width="7%"><?= (int)$c['id'] ?></td>
      <td width="13%"><?= e($c['fecha']) ?></td>
      <td width="8%"><?= e($c['hora']) ?></td>
      <td width="20%"><?= e($c['cliente'] ?: 'Usuario eliminado') ?></td>
      <td width="27%"><?= e($c['servicios'] ?? '—') ?></td>
      <td width="12%"><?= e($c['estado']) ?></td>
      <td width="13%" align="right"><?= e($cop($c['total'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$citas): ?><tr><td colspan="7" align="center">No hay citas en el período</td></tr><?php endif; ?>
  </tbody>
</table>
