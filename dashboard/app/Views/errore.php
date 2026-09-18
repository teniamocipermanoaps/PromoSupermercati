<?php use App\Core\Percorsi;
use App\Core\View; ?>
<h1><?= View::e($titolo ?? 'Errore') ?></h1>
<p class="sottotitolo"><?= View::e($messaggio ?? '') ?></p>
<a class="bottone" href="<?= Percorsi::base() ?>/">Torna all'agenda</a>
