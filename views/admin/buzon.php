<?php /* Buzón Simulador de Correos - Entrega Final */ ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1">📬 Buzón de Correos (Simulador)</h1>
        <p class="text-muted mb-0">Visualizador de notificaciones automáticas enviadas a los clientes.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?url=admin/dashboard" class="btn btn-outline-secondary btn-sm">
        ← Volver al Dashboard
    </a>
</div>

<?php if (empty($correos)): ?>
    <div class="card shadow-sm border-0 text-center py-5">
        <div class="card-body">
            <div class="display-6 text-muted mb-3">📭</div>
            <h4 class="fw-bold text-dark">La bandeja está vacía</h4>
            <p class="text-muted mb-0">Cuando un cliente agende una cita, aquí aparecerá el correo de confirmación generado.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <!-- Columna Izquierda: Lista de Correos -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Mensajes Enviados</h6>
                    <span class="badge bg-primary-subtle text-primary border">Total: <?= count($correos) ?></span>
                </div>
                <div class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
                    <?php foreach ($correos as $index => $c): ?>
                        <a href="javascript:void(0);" 
                           class="list-group-item list-group-item-action p-3 correo-item <?= $index === 0 ? 'active' : '' ?>"
                           data-archivo="<?= e($c['archivo']) ?>"
                           onclick="cargarCorreo('<?= e($c['archivo']) ?>', this)">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border">
                                    Cita #<?= e($c['cita_id']) ?>
                                </span>
                                <small class="text-muted"><?= e($c['fecha']) ?></small>
                            </div>
                            <div class="fw-semibold text-dark text-truncate"><?= e($c['asunto']) ?></div>
                            <small class="text-muted d-block text-truncate">Remitente: citas@appsalon.com</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Visor del Correo -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Previsualización del Correo</h6>
                    <small class="text-success fw-semibold">● Formato HTML</small>
                </div>
                <div class="card-body p-0">
                    <iframe id="visor-correo" 
                            src="<?= BASE_URL ?>/index.php?url=admin/buzon-ver&archivo=<?= urlencode($correos[0]['archivo']) ?>" 
                            style="width: 100%; height: 600px; border: none; border-radius: 0 0 12px 12px;">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function cargarCorreo(archivo, elemento) {
    document.querySelectorAll('.correo-item').forEach(item => item.classList.remove('active'));
    elemento.classList.add('active');
    const visor = document.getElementById('visor-correo');
    visor.src = '<?= BASE_URL ?>/index.php?url=admin/buzon-ver&archivo=' + encodeURIComponent(archivo);
}
</script>