<?php /* Responsable: A - Formulario de registro de clientes */
$inv = fn(string $c) => isset($errores[$c]) ? ' is-invalid' : '';
$err = fn(string $c) => isset($errores[$c]) ? '<div class="invalid-feedback">' . e($errores[$c]) . '</div>' : '';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-9 col-lg-7">
    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <h1 class="h3 fw-bold mb-1">Crear cuenta</h1>
        <p class="text-muted mb-4">Regístrate para agendar tus citas en AppSalon.</p>

        <form method="POST" action="<?= BASE_URL ?>/index.php?url=auth/registro">
          <?= csrfCampo() ?>

          <div class="row g-3">
            <div class="col-sm-6">
              <label for="nombre" class="form-label">Nombre</label>
              <input type="text" id="nombre" name="nombre" class="form-control<?= $inv('nombre') ?>"
                     value="<?= e($usuario['nombre']) ?>" maxlength="60" autocomplete="given-name" required>
              <?= $err('nombre') ?>
            </div>

            <div class="col-sm-6">
              <label for="apellido" class="form-label">Apellido</label>
              <input type="text" id="apellido" name="apellido" class="form-control<?= $inv('apellido') ?>"
                     value="<?= e($usuario['apellido']) ?>" maxlength="60" autocomplete="family-name" required>
              <?= $err('apellido') ?>
            </div>

            <div class="col-sm-7">
              <label for="email" class="form-label">Email</label>
              <input type="email" id="email" name="email" class="form-control<?= $inv('email') ?>"
                     value="<?= e($usuario['email']) ?>" maxlength="60" autocomplete="email" required>
              <?= $err('email') ?>
            </div>

            <div class="col-sm-5">
              <label for="telefono" class="form-label">Teléfono</label>
              <input type="tel" id="telefono" name="telefono" class="form-control<?= $inv('telefono') ?>"
                     value="<?= e($usuario['telefono']) ?>" inputmode="numeric" pattern="\d{10}"
                     maxlength="10" placeholder="3001234567" autocomplete="tel" required>
              <?= $err('telefono') ?>
            </div>

            <div class="col-sm-6">
              <label for="password" class="form-label">Contraseña</label>
              <input type="password" id="password" name="password" class="form-control<?= $inv('password') ?>"
                     minlength="6" maxlength="72" autocomplete="new-password" required>
              <?= $err('password') ?>
              <div class="form-text">Mínimo 6 caracteres.</div>
            </div>

            <div class="col-sm-6">
              <label for="password2" class="form-label">Confirmar contraseña</label>
              <input type="password" id="password2" name="password2" class="form-control<?= $inv('password2') ?>"
                     minlength="6" maxlength="72" autocomplete="new-password" required>
              <?= $err('password2') ?>
            </div>
          </div>

          <button type="submit" class="btn btn-dark w-100 mt-4">Crear cuenta</button>
        </form>

        <p class="text-center small mt-3 mb-0">
          ¿Ya tienes cuenta? <a href="<?= BASE_URL ?>/index.php?url=auth/login">Inicia sesión</a>
        </p>
      </div>
    </div>
  </div>
</div>
