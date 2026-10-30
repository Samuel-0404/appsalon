<?php /* Responsable: B - Formulario para nuevo servicio */ ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h4 mb-0 fw-bold text-dark">Nuevo Servicio</h1>
                        <small class="text-muted">Ingresa los datos para registrar un nuevo servicio</small>
                    </div>
                    <a href="<?= BASE_URL ?>/index.php?url=servicios/admin" class="btn btn-sm btn-outline-secondary">
                        ← Volver a la lista
                    </a>
                </div>
            </div>

            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>/index.php?url=servicios/crear" method="POST" novalidate>
                    <?= csrfCampo() ?>

                    <!-- Nombre -->
                    <div class="mb-3">
                        <label for="nombre" class="form-label fw-semibold">Nombre del Servicio <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control <?= isset($errores['nombre']) ? 'is-invalid' : '' ?>" 
                               id="nombre" 
                               name="nombre" 
                               value="<?= e($servicio['nombre'] ?? '') ?>" 
                               placeholder="Ej: Corte y peinado"
                               required>
                        <?php if (isset($errores['nombre'])): ?>
                            <div class="invalid-feedback"><?= e($errores['nombre']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <!-- Precio -->
                        <div class="col-md-6 mb-3">
                            <label for="precio" class="form-label fw-semibold">Precio ($ COP) <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">$</span>
                                <input type="number" 
                                       step="100" 
                                       class="form-control <?= isset($errores['precio']) ? 'is-invalid' : '' ?>" 
                                       id="precio" 
                                       name="precio" 
                                       value="<?= e($servicio['precio'] ?? '') ?>" 
                                       placeholder="Ej: 35000"
                                       required>
                                <?php if (isset($errores['precio'])): ?>
                                    <div class="invalid-feedback"><?= e($errores['precio']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Duración -->
                        <div class="col-md-6 mb-3">
                            <label for="duracion" class="form-label fw-semibold">Duración estimada (minutos)</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="duracion" 
                                   name="duracion" 
                                   value="<?= e($servicio['duracion'] ?? 60) ?>" 
                                   min="5" 
                                   step="5">
                            <div class="form-text">Ejemplo: 30, 45, 60, 90 minutos.</div>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div class="mb-4">
                        <label for="descripcion" class="form-label fw-semibold">Descripción</label>
                        <textarea class="form-control" 
                                  id="descripcion" 
                                  name="descripcion" 
                                  rows="3" 
                                  placeholder="Describe brevemente lo que incluye este servicio..."><?= e($servicio['descripcion'] ?? '') ?></textarea>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= BASE_URL ?>/index.php?url=servicios/admin" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            Guardar Servicio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
