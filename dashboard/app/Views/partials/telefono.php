<?php
use App\Core\View;
/**
 * Numero di telefono come collegamento chiamabile.
 * Richiede $numero gia' valorizzato da chi include il partial.
 * @var string|null $numero
 */
$pulito = preg_replace('/[^0-9+]/', '', (string) ($numero ?? ''));
?>
<?php if ($pulito === ''): ?>
  —
<?php else: ?>
  <a href="tel:<?= View::e($pulito) ?>"><?= View::e($numero) ?></a>
<?php endif; ?>
