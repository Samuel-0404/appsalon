<?php /* Responsable: B */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AppSalon</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/css/styles.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">AppSalon</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=servicios/index">Servicios</a></li>
        <?php if (esAdmin()): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=servicios/admin">Administrar servicios</a></li>
        <?php endif; ?>
        <?php if (estaLogueado()): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=auth/logout">Salir (<?= e($_SESSION['nombre'] ?? '') ?>)</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=auth/login">Ingresar</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=auth/registro">Registrarse</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="container my-4">
<?php include __DIR__ . '/alertas.php'; ?>
