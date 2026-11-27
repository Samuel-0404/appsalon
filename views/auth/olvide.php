<?php /* Solicitud del enlace para recuperar la contraseña */ ?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-7 col-lg-5">
    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <h1 class="h3 fw-bold mb-1">Recuperar contraseña</h1>
        <p class="text-muted mb-4">Escribe tu email y te enviaremos un enlace válido por 1 hora.</p>

        <form method="POST" action="<?= BASE_URL ?>/index.php?url=auth/olvide">
          <?= csrfCampo() ?>
          <div class="mb-4">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control<?= isset($errores['email']) ? ' is-invalid' : '' ?>"
                   value="<?= e($email) ?>" maxlength="60" autocomplete="email" required autofocus>
            <?php if (isset($errores['email'])): ?><div class="invalid-feedback"><?= e($errores['email']) ?></div><?php endif; ?>
          </div>
          <button type="submit" class="btn btn-dark w-100">Enviar enlace</button>
        </form>

        <p class="text-center small mt-3 mb-0">
          <a href="<?= BASE_URL ?>/index.php?url=auth/login">Volver a iniciar sesión</a>
        </p>
      </div>
    </div>
  </div>
</div>
