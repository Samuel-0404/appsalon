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
                    <small class="text-muted">Los días cerrados y festivos aparecen bloqueados en el calendario</small>
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
                        <!-- Los botones se generan con JS según el horario del día y las citas existentes -->
                        <div class="row row-cols-3 g-2" id="grid-horas"></div>
                        <p class="small text-muted mt-2 mb-0" id="nota-horas"></p>
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
    const BASE = '<?= BASE_URL ?>/index.php?url=';
    const inputFecha = document.getElementById('fecha');
    const inputHora = document.getElementById('hora');
    const fechaLabel = document.getElementById('fecha-label');
    const notaHoras = document.getElementById('nota-horas');
    const gridHoras = document.getElementById('grid-horas');
    const resumenFechaHora = document.getElementById('resumen-fecha-hora');
    const checks = document.querySelectorAll('.check-servicio');
    const precioTotalEl = document.getElementById('precio-total');
    const duracionTotalEl = document.getElementById('duracion-total');
    const listaResumenEl = document.getElementById('lista-resumen');

    // Días de la semana cerrados según el horario configurado por el administrador (0 = domingo)
    const DIAS_CERRADOS = <?= json_encode(array_keys(array_filter(Horario::semana(), fn($h) => !$h['abierto']))) ?>;
    const DURACION_MINIMA = 60;   // sin servicios marcados, se valida una franja de 1 hora

    let fechaActual = inputFecha.value || '<?= date('Y-m-d') ?>';
    let disponibilidad = null;    // última respuesta de citas/horas-ocupadas

    // ---- Utilidades de tiempo ('HH:MM' <-> minutos) ----
    const aMin = h => parseInt(h.slice(0, 2), 10) * 60 + parseInt(h.slice(3, 5), 10);
    const etiqueta = h => {
        const n = parseInt(h.slice(0, 2), 10);
        return n < 12 ? `${h} AM` : (n === 12 ? '12:00 PM' : `${n - 12}:00 PM`);
    };
    const ymd = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    // ---- 1. Calendario: días cerrados + festivos (se llenan vía fetch) ----
    const festivos = {};
    const esCerrado = d => DIAS_CERRADOS.includes(d.getDay());
    const esFestivo = d => ymd(d) in festivos;

    const fp = flatpickr("#calendario-inline", {
        inline: true,
        locale: 'es',
        minDate: 'today',
        defaultDate: fechaActual,
        disable: [esCerrado],
        onDayCreate: (dObj, dStr, instancia, dayElem) => {
            const nombre = festivos[ymd(dayElem.dateObj)];
            if (nombre) {
                dayElem.classList.add('festivo');
                dayElem.title = 'Festivo: ' + nombre;
            }
        },
        onChange: function(selectedDates, dateStr) {
            fechaActual = dateStr;
            inputFecha.value = dateStr;
            inputHora.value = '';
            actualizarTextoFechaHora();
            consultarDisponibilidad(dateStr);
        }
    });

    function cargarFestivos(anio) {
        return fetch(BASE + 'citas/festivos&anio=' + anio)
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);   // fetch NO falla con 4xx/5xx; hay que revisarlo
                return res.json();
            })
            .then(data => data.festivos.forEach(f => { festivos[f.fecha] = f.nombre; }));
    }

    // Año actual + siguiente (en noviembre/diciembre ya se agenda para enero)
    const anioActual = new Date().getFullYear();
    Promise.all([cargarFestivos(anioActual), cargarFestivos(anioActual + 1)])
        .then(() => fp.set('disable', [esCerrado, esFestivo]))   // set() redibuja → corre onDayCreate
        .catch(err => console.error('No se pudieron cargar los festivos:', err));

    // ---- 2. Disponibilidad del día (horario + citas existentes con su duración) ----
    function consultarDisponibilidad(fecha) {
        fechaLabel.textContent = 'Consultando disponibilidad...';
        fetch(BASE + 'citas/horas-ocupadas&fecha=' + encodeURIComponent(fecha))
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                disponibilidad = data;
                fechaLabel.textContent = data.abierto
                    ? `Horario del ${fecha}: ${data.apertura} a ${data.cierre}`
                    : 'El salón no atiende ese día';
                pintarHoras();
            })
            .catch(err => {
                console.error(err);
                fechaLabel.textContent = 'No se pudo consultar la disponibilidad';
            });
    }

    function duracionSeleccionada() {
        let min = 0;
        checks.forEach(c => { if (c.checked) min += parseInt(c.dataset.duracion, 10) || 0; });
        return min || DURACION_MINIMA;
    }

    /**
     * Una hora H está disponible si [H, H + duración) cabe antes del cierre y no se cruza con
     * ninguna cita: A.inicio < B.fin && B.inicio < A.fin (la misma regla que ValidadorCita en el servidor).
     */
    function motivoNoDisponible(h, duracion) {
        const ini = aMin(h), fin = ini + duracion;
        if (fin > aMin(disponibilidad.cierre)) return 'no alcanza';
        if (fechaActual === ymd(new Date())) {
            const ahora = new Date();
            if (ini <= ahora.getHours() * 60 + ahora.getMinutes()) return 'ya pasó';
        }
        const choca = disponibilidad.intervalos.some(iv => ini < aMin(iv.fin) && aMin(iv.inicio) < fin);
        return choca ? 'ocupado' : null;
    }

    function pintarHoras() {
        gridHoras.replaceChildren();
        if (!disponibilidad || !disponibilidad.abierto) {
            notaHoras.textContent = 'Elige otro día en el calendario.';
            inputHora.value = '';
            actualizarTextoFechaHora();
            return;
        }

        const duracion = duracionSeleccionada();
        let libres = 0;
        disponibilidad.franjas.forEach(h => {
            const motivo = motivoNoDisponible(h, duracion);
            const col = document.createElement('div');
            col.className = 'col';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.hora = h;
            btn.className = 'btn btn-sm w-100 py-2 hora-btn ' + (motivo ? 'btn-outline-secondary' : 'btn-outline-primary');
            btn.disabled = motivo !== null;
            btn.textContent = motivo ? `${h} (${motivo})` : etiqueta(h);
            if (!motivo && inputHora.value === h) btn.classList.add('selected');
            if (!motivo) libres++;
            col.appendChild(btn);
            gridHoras.appendChild(col);
        });

        // Si la hora elegida dejó de caber (p. ej. se agregó un servicio largo), se descarta
        if (inputHora.value && !gridHoras.querySelector(`.hora-btn.selected`)) {
            inputHora.value = '';
            actualizarTextoFechaHora();
        }
        notaHoras.textContent = libres
            ? `Horas calculadas para una duración de ${duracion} min.`
            : `No hay horas libres para ${duracion} min ese día. Prueba otra fecha o menos servicios.`;
    }

    // ---- 3. Selección de hora (delegación: los botones se recrean en cada consulta) ----
    gridHoras.addEventListener('click', e => {
        const btn = e.target.closest('.hora-btn');
        if (!btn || btn.disabled) return;
        gridHoras.querySelectorAll('.hora-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        inputHora.value = btn.dataset.hora;
        actualizarTextoFechaHora();
    });

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

    // ---- 4. Cálculo en vivo de servicios, precio y duración ----
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
            // textContent (no innerHTML): el nombre del servicio nunca se interpreta como HTML
            const ul = document.createElement('ul');
            ul.className = 'mb-0 ps-3';
            seleccionados.forEach(nombre => {
                const li = document.createElement('li');
                li.textContent = nombre;
                ul.appendChild(li);
            });
            listaResumenEl.replaceChildren(ul);
        }
    }

    checks.forEach(check => check.addEventListener('change', () => {
        actualizarResumenServicios();
        if (disponibilidad) pintarHoras();   // la duración cambió: recalcular qué horas caben
    }));

    // Inicializar al cargar la página
    actualizarResumenServicios();
    inputFecha.value = fechaActual;
    consultarDisponibilidad(fechaActual);
    actualizarTextoFechaHora();

    // ---- 5. Validación antes de enviar el formulario ----
    document.getElementById('form-cita').addEventListener('submit', (e) => {
        if (!inputHora.value) {
            e.preventDefault();
            alert('Por favor selecciona una hora disponible para tu cita.');
        }
    });
});
</script>
