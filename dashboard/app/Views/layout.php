<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Percorsi;
use App\Core\View;

// Percorso interno, per sapere quale voce del menu e' quella attiva.
$percorsoCorrente = Percorsi::interno($_SERVER['REQUEST_URI'] ?? '/');
$voci = [
    '/' => 'Agenda',
    '/punti-vendita' => 'Punti vendita',
    '/campagne' => 'Campagne',
    '/banchetti' => 'Banchetti',
];

// Sulla pagina di accesso non c'e' niente da navigare.
$utenteCollegato = Auth::utente();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($titoloPagina ?? 'OsservatorioPromo') ?> · OsservatorioPromo</title>
<link rel="stylesheet" href="<?= Percorsi::base() ?>/assets/app.css">
</head>
<body>
<header class="barra">
  <div class="barra-interna">
    <div class="marchio">OsservatorioPromo<span>Teniamoci per Mano APS</span></div>
    <?php if ($utenteCollegato !== null): ?>
      <nav class="menu">
        <?php foreach ($voci as $percorso => $etichetta): ?>
          <a href="<?= Percorsi::base() ?><?= View::e($percorso) ?>"
             class="<?= $percorsoCorrente === $percorso ? 'attivo' : '' ?>"><?= View::e($etichetta) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="utente">
        <span class="nome"><?= View::e($utenteCollegato['nome']) ?></span>
        <!-- POST, cosi' l'uscita non parte da un'immagine messa in una pagina altrui. -->
        <form method="post" action="<?= Percorsi::base() ?>/esci">
          <?= Csrf::campo() ?>
          <button type="submit" class="tenue">Esci</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</header>
<main>
<?= $contenuto ?>
</main>
</body>
</html>
