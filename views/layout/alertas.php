<?php /* Responsable: B */ foreach (getAlertas() as $a): ?>
  <div class="alert alert-<?= e($a['tipo']) ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
    <?= e($a['mensaje']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endforeach; ?>
