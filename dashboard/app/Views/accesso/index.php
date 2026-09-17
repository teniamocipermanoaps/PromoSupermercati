<?php

use App\Core\Csrf;
use App\Core\Percorsi;
use App\Core\View;

$messaggiErrore = [
    // Uno solo per tutti i casi in cui le credenziali non tornano: l'elenco di
    // chi ha un account non si ricava dalla pagina di accesso.
    'credenziali' => 'Email o password non corrette.',
    'bloccato' => 'Troppi tentativi falliti: riprova fra ' . (int) ($minutiBlocco ?? 15) . ' minuti.',
    'revocato' => 'Questo accesso è stato revocato. Chiedi a chi cura il sistema.',
];
?>
<div class="accesso">
  <h1>Accesso</h1>
  <p class="sottotitolo">
    La dashboard contiene i recapiti dei referenti dei punti vendita: serve
    l'accesso per consultarla.
  </p>

  <?php if ($errore !== null && isset($messaggiErrore[$errore])): ?>
    <div class="avviso errore"><?= View::e($messaggiErrore[$errore]) ?></div>
  <?php elseif (!empty($uscita)): ?>
    <div class="avviso">Sei uscita dalla dashboard.</div>
  <?php endif; ?>

  <div class="riquadro">
    <form method="post" action="<?= Percorsi::base() ?>/accesso">
      <?= Csrf::campo() ?>
      <input type="hidden" name="ritorno" value="<?= View::e($ritorno ?? '/') ?>">
      <div>
        <label for="a-email">Email</label>
        <input type="email" id="a-email" name="email" required autocomplete="username"
               autocapitalize="none" autofocus>
      </div>
      <div style="margin-top:12px">
        <label for="a-password">Password</label>
        <input type="password" id="a-password" name="password" required
               autocomplete="current-password">
      </div>
      <button type="submit" style="margin-top:16px">Entra</button>
    </form>
  </div>

  <p class="nota-accesso">
    Gli accessi li crea chi cura il sistema con
    <code>php ops/scripts/crea_utente.php</code>. Non c'è il recupero della
    password via email: se non entri più, fattela reimpostare.
  </p>
</div>
