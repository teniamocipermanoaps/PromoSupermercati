<?php
use App\Core\View;
use App\Support\Footfall;
/**
 * Richiede $giorni gia' valorizzato da chi include il partial.
 * @var list<array{data: DateTimeImmutable, punteggio: float, fase: string, giorno: string}> $giorni
 */
if (!isset($giorni) || !is_array($giorni)) {
    throw new LogicException('Il partial giorni.php richiede la variabile $giorni.');
}
?>
<div class="giorni">
  <?php foreach ($giorni as $indice => $g): ?>
    <div class="giorno <?= $indice === 0 ? 'principale' : '' ?>">
      <span class="data"><?= View::e($g['giorno']) ?> <?= $g['data']->format('d/m') ?></span>
      <span class="pill <?= View::e(Footfall::livello($g['punteggio'])) ?>"><?= View::e((string) $g['punteggio']) ?></span>
      <span class="fase"><?= View::e($g['fase']) ?></span>
    </div>
  <?php endforeach; ?>
</div>
