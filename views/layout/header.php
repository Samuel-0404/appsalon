<?php /* Responsable: B */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AppSalon</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/css/styles.css" rel="stylesheet">
</head>
<body>
<?php
  // Marca el ítem activo del menú según el prefijo de la ruta actual (?url=...)
  $__navRuta = $_GET['url'] ?? '';
  $__navActivo = fn(string $prefijo) => str_starts_with($__navRuta, $prefijo) ? ' active" aria-current="page' : '';
?>
<nav class="navbar navbar-expand-md navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">AppSalon</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav ms-auto">
        <?php if (esAdmin()): ?>
          <li class="nav-item"><a class="nav-link<?= $__navActivo('admin/dashboard') ?>" href="<?= BASE_URL ?>/index.php?url=admin/dashboard"><i class="bi bi-house-door me-1" aria-hidden="true"></i>Dashboard</a></li>
          <li class="nav-item"><a class="nav-link<?= $__navActivo('servicios/') ?>" href="<?= BASE_URL ?>/index.php?url=servicios/admin"><i class="bi bi-scissors me-1" aria-hidden="true"></i>Servicios</a></li>
          <li class="nav-item"><a class="nav-link<?= $__navActivo('admin/usuarios') ?>" href="<?= BASE_URL ?>/index.php?url=admin/usuarios"><i class="bi bi-people me-1" aria-hidden="true"></i>Usuarios</a></li>
          <li class="nav-item"><a class="nav-link<?= $__navActivo('reportes/') ?>" href="<?= BASE_URL ?>/index.php?url=reportes/index"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>Reportes</a></li>
          <li class="nav-item"><a class="nav-link text-warning" href="<?= BASE_URL ?>/index.php?url=auth/logout">Salir (<?= e($_SESSION['nombre'] ?? '') ?>)</a></li>
        <?php elseif (estaLogueado()): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=servicios/index">Servicios</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=citas/crear">Reservar Cita</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=citas/mis-citas">Mis Citas</a></li>
          <li class="nav-item"><a class="nav-link text-warning" href="<?= BASE_URL ?>/index.php?url=auth/logout">Salir (<?= e($_SESSION['nombre'] ?? '') ?>)</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=servicios/index">Servicios</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=auth/login">Ingresar</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php?url=auth/registro">Registrarse</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="container my-4">
<?php include __DIR__ . '/alertas.php'; ?>
