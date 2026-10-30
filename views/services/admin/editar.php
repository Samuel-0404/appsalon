<?php /* Responsable: B - Formulario para editar servicio */ ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h4 mb-0 fw-bold text-dark">Editar Servicio</h1>
                        <small class="text-muted">Modifica los datos del servicio #<?= e($servicio['id']) ?></small>
                    </div>
                    <a href="<?= BASE_URL ?>/index.php?url=servicios/admin" class="btn btn-sm btn-outline-secondary">
                        ← Volver a la lista
                    </a>
                </div>
            </div>

            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>/index.php?url=servicios/editar&id=<?= e($servicio['id']) ?>" method="POST" novalidate>
                    <?= csrfCampo() ?>

                    <!-- Nombre -->
                    <div class="mb-3">
                        <label for="nombre" class="form-label fw-semibold">Nombre del Servicio <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control <?= isset($errores['nombre']) ? 'is-invalid' : '' ?>" 
                               id="nombre" 
                               name="nombre" 
                               value="<?= e($servicio['nombre'] ?? '') ?>" 
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
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div class="mb-4">
                        <label for="descripcion" class="form-label fw-semibold">Descripción</label>
                        <textarea class="form-control" 
                                  id="descripcion" 
                                  name="descripcion" 
                                  rows="3"><?= e($servicio['descripcion'] ?? '') ?></textarea>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= BASE_URL ?>/index.php?url=servicios/admin" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            Actualizar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
