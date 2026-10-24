<?php /* Responsable: B */ foreach (getAlertas() as $a): ?>
  <div class="alert alert-<?= e($a['tipo']) ?>"><?= e($a['mensaje']) ?></div>
<?php endforeach; ?>
