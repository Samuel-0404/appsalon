<?php /* Nueva contraseña (llega desde el enlace del correo) */
$inv = fn(string $c) => isset($errores[$c]) ? ' is-invalid' : '';
$err = fn(string $c) => isset($errores[$c]) ? '<div class="invalid-feedback">' . e($errores[$c]) . '</div>' : '';
?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-7 col-lg-5">
    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <h1 class="h3 fw-bold mb-4">Nueva contraseña</h1>

        <form method="POST" action="<?= BASE_URL ?>/index.php?url=auth/restablecer">
          <?= csrfCampo() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" id="password" name="password" class="form-control<?= $inv('password') ?>"
                   minlength="6" maxlength="72" autocomplete="new-password" required autofocus>
            <?= $err('password') ?>
            <div class="form-text">Mínimo 6 caracteres.</div>
          </div>
          <div class="mb-4">
            <label for="password2" class="form-label">Confirmar contraseña</label>
            <input type="password" id="password2" name="password2" class="form-control<?= $inv('password2') ?>"
                   minlength="6" maxlength="72" autocomplete="new-password" required>
            <?= $err('password2') ?>
          </div>
          <button type="submit" class="btn btn-dark w-100">Guardar contraseña</button>
        </form>
      </div>
    </div>
  </div>
</div>
