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

<?php
$retorno = http_build_query(array_filter($filtros, fn($v) => $v !== ''));
$hayFiltros = $retorno !== '';
?>
<form action="<?= BASE_URL ?>/index.php" method="GET" class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3 row g-2 align-items-center">
        <input type="hidden" name="url" value="admin/usuarios">
        <div class="col-md">
            <input type="search" name="q" class="form-control form-control-sm" maxlength="60"
                   value="<?= e($filtros['q']) ?>" placeholder="🔎 Buscar por nombre, email o teléfono" aria-label="Buscar usuarios">
        </div>
        <div class="col-sm-auto">
            <select name="rol" class="form-select form-select-sm" aria-label="Filtrar por rol">
                <option value="">Todos los roles</option>
                <?php foreach (['cliente' => 'Clientes', 'empleado' => 'Empleados', 'admin' => 'Administradores'] as $valor => $texto): ?>
                    <option value="<?= $valor ?>" <?= $filtros['rol'] === $valor ? 'selected' : '' ?>><?= $texto ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-auto">
            <select name="estado" class="form-select form-select-sm" aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                <?php foreach (['activo' => 'Activos', 'inactivo' => 'Inactivos', 'sin_confirmar' => 'Sin confirmar'] as $valor => $texto): ?>
                    <option value="<?= $valor ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>><?= $texto ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-auto d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary px-3">Filtrar</button>
            <?php if ($hayFiltros): ?>
                <a href="<?= BASE_URL ?>/index.php?url=admin/usuarios" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><?= $hayFiltros ? 'Resultados' : 'Listado de Usuarios Registrados' ?></h5>
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
                <?php if (!$usuarios): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Ningún usuario coincide con los filtros.</td></tr>
                <?php endif; ?>
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
                                    <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
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
                            <?php if (empty($u['activo'])): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Inactivo</span>
                            <?php elseif (empty($u['confirmado'])): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Sin confirmar</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Activo</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td class="text-center">
                            <?php if (empty($u['confirmado'])): ?>
                                <form action="<?= BASE_URL ?>/index.php?url=admin/usuario-confirmar" method="POST" class="mb-1">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Confirmar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($u['rol'] === 'admin'): ?>
                                <span class="text-muted small fst-italic">Protegido</span>
                            <?php else: ?>
                                <form action="<?= BASE_URL ?>/index.php?url=admin/usuario-toggle" method="POST">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
                                    <?php if (!empty($u['activo'])): ?>
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