<?php /* Gestión de usuarios: activar/desactivar y asignar rol (solo administradores) */ ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1">Gestión de Usuarios</h1>
        <p class="text-muted mb-0">Activa o desactiva cuentas y asigna el rol de cada persona: cliente, empleado o administrador.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php?url=admin/dashboard" class="btn btn-outline-secondary btn-sm">
        ← Volver al Dashboard
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Listado de Usuarios Registrados</h5>
        <span class="badge bg-secondary-subtle text-secondary-emphasis border">
            Total: <?= count($usuarios) ?> usuarios
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Nombre Completo</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Fecha de Registro</th>
                    <th class="text-center" style="width: 150px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td class="text-muted fw-bold">#<?= e($u['id']) ?></td>
                        <td class="fw-semibold text-dark">
                            <?= e($u['nombre'] . ' ' . $u['apellido']) ?>
                        </td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['telefono'] ?? 'Sin registrar') ?></td>
                        <td>
                            <?php $esYo = (int)$u['id'] === (int)$_SESSION['usuario_id']; ?>
                            <?php if ($esYo): ?>
                                <span class="badge bg-dark">👑 Administrador (tú)</span>
                            <?php else: ?>
                                <!-- Cambiar rol: se envía al elegir una opción -->
                                <form action="<?= BASE_URL ?>/index.php?url=admin/usuario-rol" method="POST" class="d-flex">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <select name="rol" class="form-select form-select-sm" style="min-width: 130px;"
                                            aria-label="Rol de <?= e($u['nombre']) ?>"
                                            onchange="if (confirm('¿Cambiar el rol de este usuario?')) this.form.submit(); else this.value = this.dataset.actual;"
                                            data-actual="<?= e($u['rol']) ?>">
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?= e($r) ?>" <?= $u['rol'] === $r ? 'selected' : '' ?>>
                                                <?= ['cliente' => '🙂 Cliente', 'empleado' => '💇 Empleado', 'admin' => '👑 Administrador'][$r] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($u['confirmado'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td class="text-center">
                            <?php if ($u['rol'] === 'admin'): ?>
                                <span class="text-muted small fst-italic">Protegido</span>
                            <?php else: ?>
                                <form action="<?= BASE_URL ?>/index.php?url=admin/usuario-toggle" method="POST">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <?php if (!empty($u['confirmado'])): ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar cuenta">
                                            Desactivar
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Activar cuenta">
                                            Activar
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>