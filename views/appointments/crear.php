<?php /* Vista de Reserva de Citas con Calendario Interactivo - Entrega Final */ ?>

<!-- Estilos del Calendario Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">

<style>
    .flatpickr-calendar {
        box-shadow: none !important;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin: 0 auto;
    }
    .hora-btn {
        transition: all 0.2s ease;
        font-weight: 500;
        border-radius: 8px;
    }
    .hora-btn.selected {
        background-color: var(--primary-color, #6366f1) !important;
        color: #fff !important;
        border-color: var(--primary-color, #6366f1) !important;
    }
</style>

<div class="mb-4">
    <h1 class="h2 fw-bold text-dark mb-1">Reservar Cita</h1>
    <p class="text-muted mb-0">Selecciona tus tratamientos, elige un día en el calendario y escoge un horario disponible.</p>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger shadow-sm">
        <h6 class="fw-bold mb-1">Por favor corrige los siguientes puntos:</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errores as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?= BASE_URL ?>/index.php?url=citas/crear" method="POST" id="form-cita">
    <?= csrfCampo() ?>
    
    <!-- Campos ocultos para fecha y hora -->
    <input type="hidden" id="fecha" name="fecha" value="<?= e($cita['fecha'] ?? '') ?>">
    <input type="hidden" id="hora" name="hora" value="<?= e($cita['hora'] ?? '') ?>">

    <div class="row g-4">
        <!-- Columna Izquierda: Selección de Servicios -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold">1. Selecciona tus servicios</h5>
                    <small class="text-muted">Puedes marcar todos los que necesites</small>
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

        <!-- Columna Derecha: Calendario Interactivo y Horarios -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold">2. Elige Fecha y Horario</h5>
                    <small class="text-muted">Horario continuo de Lunes a Sábado</small>
                </div>
                <div class="card-body p-4">
                    <!-- Calendario Visual Integrado -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Día de la cita:</label>
                        <div id="calendario-inline" class="d-flex justify-content-center"></div>
                    </div>

                    <!-- Bloques de Horas -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-dark d-flex justify-content-between align-items-center">
                            <span>Horarios disponibles:</span>
                            <small class="text-muted fw-normal" id="fecha-label">Selecciona una fecha</small>
                        </label>
                        <div class="row row-cols-3 g-2" id="grid-horas">
                            <?php 
                            $horasDisponibles = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
                            foreach ($horasDisponibles as $h):
                                $hNum = (int)substr($h, 0, 2);
                                $label = ($hNum < 12) ? $h.' AM' : (($hNum === 12) ? '12:00 PM' : ($hNum-12).':00 PM');
                            ?>
                                <div class="col">
                                    <button type="button" 
                                            class="btn btn-outline-primary btn-sm w-100 py-2 hora-btn" 
                                            data-hora="<?= $h ?>">
                                        <?= $label ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-danger d-none mt-2 d-block" id="error-hora">Por favor selecciona un horario disponible.</small>
                    </div>

                    <!-- Resumen en vivo -->
                    <div class="border rounded-3 p-3 bg-light mb-4">
                        <h6 class="fw-bold text-dark mb-2">Resumen de tu cita</h6>
                        <div id="lista-resumen" class="small text-muted mb-2">
                            <em>No has seleccionado servicios.</em>
                        </div>
                        <div class="d-flex justify-content-between small text-muted pt-2 border-top">
                            <span>Fecha y Hora elegida:</span>
                            <strong id="resumen-fecha-hora" class="text-dark">Sin definir</strong>
                        </div>
                        <div class="d-flex justify-content-between small text-muted pt-2 border-top">
                            <span>Duración estimada:</span>
                            <strong id="duracion-total" class="text-dark">0 min</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
                            <span class="fw-bold text-dark">Total a pagar:</span>
                            <span id="precio-total" class="fs-4 fw-bold text-primary">$0</span>
                        </div>
                    </div>

                    <!-- Botón de Confirmación -->
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="btn-confirmar">
                        Confirmar Reserva
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
  .flatpickr-day.festivo { color: #dc3545 !important; text-decoration: line-through; }
</style>
<!-- Scripts de Flatpickr e Interactividad -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputFecha = document.getElementById('fecha');
    const inputHora = document.getElementById('hora');
    const fechaLabel = document.getElementById('fecha-label');
    const resumenFechaHora = document.getElementById('resumen-fecha-hora');
    const botonesHora = document.querySelectorAll('.hora-btn');
    const checks = document.querySelectorAll('.check-servicio');
    const precioTotalEl = document.getElementById('precio-total');
    const duracionTotalEl = document.getElementById('duracion-total');
    const listaResumenEl = document.getElementById('lista-resumen');

    let fechaActual = inputFecha.value || '<?= date('Y-m-d') ?>';

    // 1. Festivos: diccionario 'Y-m-d' => nombre (se llena vía fetch)
    const festivos = {};
    const ymd = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const esDomingo = d => d.getDay() === 0;
    const esFestivo = d => ymd(d) in festivos;

    const fp = flatpickr("#calendario-inline", {
        inline: true,
        locale: 'es',
        minDate: 'today',
        defaultDate: fechaActual,
        disable: [esDomingo],                       // los festivos se agregan cuando llegue el fetch
        onDayCreate: (dObj, dStr, instancia, dayElem) => {
            const nombre = festivos[ymd(dayElem.dateObj)];
            if (nombre) {
                dayElem.classList.add('festivo');
                dayElem.title = 'Festivo: ' + nombre;   // tooltip al pasar el mouse
            }
        },
        onChange: function(selectedDates, dateStr) {
            fechaActual = dateStr;
            inputFecha.value = dateStr;
            inputHora.value = '';
            deseleccionarHoras();
            actualizarTextoFechaHora();
            consultarDisponibilidad(dateStr);
        }
    });

    function cargarFestivos(anio) {
        return fetch('<?= BASE_URL ?>/index.php?url=citas/festivos&anio=' + anio)
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);   // fetch NO falla con 4xx/5xx; hay que revisarlo
                return res.json();
            })
            .then(data => data.festivos.forEach(f => { festivos[f.fecha] = f.nombre; }));
    }

    // Año actual + siguiente (en noviembre/diciembre ya se agenda para enero)
    const anioActual = new Date().getFullYear();
    Promise.all([cargarFestivos(anioActual), cargarFestivos(anioActual + 1)])
        .then(() => fp.set('disable', [esDomingo, esFestivo]))   // set() redibuja → corre onDayCreate
        .catch(err => console.error('No se pudieron cargar los festivos:', err));
    
    // 2. Consulta en tiempo real de horarios ocupados vía AJAX
    function consultarDisponibilidad(fecha) {
        fechaLabel.textContent = 'Consultando disponibilidad...';
        
        fetch('<?= BASE_URL ?>/index.php?url=citas/horas-ocupadas&fecha=' + fecha)
            .then(res => res.json())
            .then(data => {
                const ocupadas = data.ocupadas || [];
                fechaLabel.textContent = 'Horarios para el ' + fecha;

                botonesHora.forEach(btn => {
                    const h = btn.dataset.hora;
                    if (ocupadas.includes(h)) {
                        btn.disabled = true;
                        btn.classList.add('btn-outline-secondary', 'disabled');
                        btn.classList.remove('btn-outline-primary', 'selected');
                        btn.innerText = h + ' (Ocupado)';
                    } else {
                        btn.disabled = false;
                        btn.classList.remove('btn-outline-secondary', 'disabled');
                        btn.classList.add('btn-outline-primary');
                        // Restaurar texto limpio
                        const hNum = parseInt(h.substring(0, 2));
                        const label = (hNum < 12) ? h + ' AM' : ((hNum === 12) ? '12:00 PM' : (hNum - 12) + ':00 PM');
                        btn.innerText = label;
                    }
                });
            })
            .catch(err => {
                console.error(err);
                fechaLabel.textContent = 'Horarios disponibles';
            });
    }

    // 3. Selección interactiva de hora
    botonesHora.forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.disabled) return;
            deseleccionarHoras();
            btn.classList.add('selected');
            inputHora.value = btn.dataset.hora;
            actualizarTextoFechaHora();
        });
    });

    function deseleccionarHoras() {
        botonesHora.forEach(b => b.classList.remove('selected'));
    }

    function actualizarTextoFechaHora() {
        const f = inputFecha.value;
        const h = inputHora.value;
        if (f && h) {
            resumenFechaHora.textContent = f + ' a las ' + h;
            resumenFechaHora.className = 'text-primary fw-bold';
        } else if (f) {
            resumenFechaHora.textContent = f + ' (Elige una hora)';
            resumenFechaHora.className = 'text-warning-emphasis';
        } else {
            resumenFechaHora.textContent = 'Sin definir';
            resumenFechaHora.className = 'text-muted';
        }
    }

    // 4. Cálculo en vivo de servicios y precios
    function actualizarResumenServicios() {
        let total = 0;
        let duracion = 0;
        const seleccionados = [];

        checks.forEach(check => {
            if (check.checked) {
                total += parseFloat(check.dataset.precio) || 0;
                duracion += parseInt(check.dataset.duracion) || 0;
                seleccionados.push(check.dataset.nombre);
            }
        });

        precioTotalEl.textContent = '$' + total.toLocaleString('es-CO');
        duracionTotalEl.textContent = duracion + ' min';

        if (seleccionados.length === 0) {
            listaResumenEl.innerHTML = '<em class="text-danger">Selecciona al menos un servicio para continuar.</em>';
        } else {
            listaResumenEl.innerHTML = '<ul class="mb-0 ps-3">' + 
                seleccionados.map(s => `<li>${s}</li>`).join('') + 
                '</ul>';
        }
    }

    checks.forEach(check => check.addEventListener('change', actualizarResumenServicios));

    // Inicializar al cargar la página
    actualizarResumenServicios();
    inputFecha.value = fechaActual;
    consultarDisponibilidad(fechaActual);
    actualizarTextoFechaHora();

    // 5. Validación antes de enviar el formulario
    document.getElementById('form-cita').addEventListener('submit', (e) => {
        if (!inputHora.value) {
            e.preventDefault();
            alert('Por favor selecciona una hora disponible para tu cita.');
        }
    });
});
</script>