<?php use App\Core\View; ?>
<h1><?= View::e($titolo ?? 'Errore') ?></h1>
<p class="sottotitolo"><?= View::e($messaggio ?? '') ?></p>
<a class="bottone" href="/">Torna all'agenda</a>
