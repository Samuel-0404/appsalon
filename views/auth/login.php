<?php /* Responsable: A - Formulario de inicio de sesión */
$inv = fn(string $c) => isset($errores[$c]) ? ' is-invalid' : '';
$err = fn(string $c) => isset($errores[$c]) ? '<div class="invalid-feedback">' . e($errores[$c]) . '</div>' : '';
?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-7 col-lg-5">
    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <h1 class="h3 fw-bold mb-1">Iniciar sesión</h1>
        <p class="text-muted mb-4">Ingresa para reservar tus citas.</p>

        <?php if (isset($errores['general'])): ?>
          <div class="alert alert-danger"><?= e($errores['general']) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/index.php?url=auth/login">
          <?= csrfCampo() ?>

          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control<?= $inv('email') ?>"
                   value="<?= e($usuario['email']) ?>" maxlength="60" autocomplete="email" required autofocus>
            <?= $err('email') ?>
          </div>

          <div class="mb-4">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" id="password" name="password" class="form-control<?= $inv('password') ?>"
                   autocomplete="current-password" required>
            <?= $err('password') ?>
          </div>

          <button type="submit" class="btn btn-dark w-100">Ingresar</button>
        </form>

        <p class="text-center small mt-3 mb-0">
          ¿No tienes cuenta? <a href="<?= BASE_URL ?>/index.php?url=auth/registro">Regístrate</a>
        </p>
      </div>
    </div>
  </div>
</div>
