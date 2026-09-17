<?php

use App\Core\View;

$percorsoCorrente = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$voci = [
    '/' => 'Agenda',
    '/punti-vendita' => 'Punti vendita',
    '/campagne' => 'Campagne',
    '/banchetti' => 'Banchetti',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($titoloPagina ?? 'OsservatorioPromo') ?> · OsservatorioPromo</title>
<link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="barra">
  <div class="barra-interna">
    <div class="marchio">OsservatorioPromo<span>Teniamoci per Mano APS</span></div>
    <nav class="menu">
      <?php foreach ($voci as $percorso => $etichetta): ?>
        <a href="<?= View::e($percorso) ?>"
           class="<?= $percorsoCorrente === $percorso ? 'attivo' : '' ?>"><?= View::e($etichetta) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<main>
<?= $contenuto ?>
</main>
</body>
</html>
