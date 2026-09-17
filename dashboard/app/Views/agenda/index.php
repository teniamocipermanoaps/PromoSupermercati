<?php
use App\Core\View;

$classeStato = static fn (string $stato): string => match ($stato) {
    'autorizzato' => 'alto',
    'rifiutato' => 'negativo',
    'da_contattare' => 'medio',
    default => 'basso',
};
?>
<h1>Agenda contatti</h1>
<p class="sottotitolo">
  Campagne promozionali in arrivo, con i giorni migliori da proporre al punto vendita
  e lo stato della richiesta di autorizzazione.
</p>

<div class="tessere">
  <?php foreach (['da_contattare' => 'Da contattare', 'contattato' => 'Contattati',
                  'in_attesa' => 'In attesa', 'autorizzato' => 'Autorizzati',
                  'rifiutato' => 'Rifiutati'] as $chiave => $etichetta): ?>
    <div class="tessera">
      <div class="numero"><?= (int) ($riepilogo[$chiave] ?? 0) ?></div>
      <div class="etichetta"><?= View::e($etichetta) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($daRichiamare !== []): ?>
  <div class="riquadro">
    <h2>Da richiamare entro sette giorni</h2>
    <table class="impilabile">
      <thead><tr><th>Quando</th><th>Punto vendita</th><th>Città</th><th>Telefono</th><th>In carico a</th></tr></thead>
      <tbody>
      <?php foreach ($daRichiamare as $r): ?>
        <tr>
          <td data-etichetta="Quando"><strong><?= View::e(date('d/m', strtotime((string) $r['next_follow_up']))) ?></strong></td>
          <td data-etichetta="Negozio"><a href="/punti-vendita/<?= (int) $r['store_id'] ?>"><?= View::e($r['punto_vendita']) ?></a></td>
          <td data-etichetta="Città"><?= View::e($r['city']) ?></td>
          <td data-etichetta="Telefono"><?php $numero = $r['phone']; require dirname(__DIR__) . '/partials/telefono.php'; ?></td>
          <td data-etichetta="In carico a"><?= View::e($r['requested_by']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<div class="riquadro">
  <form class="filtri" method="get" action="/">
    <?php foreach (['citta' => 'Città', 'catena' => 'Insegna', 'tipologia' => 'Tipologia', 'stato' => 'Stato'] as $nome => $etichetta): ?>
      <div>
        <label for="f-<?= View::e($nome) ?>"><?= View::e($etichetta) ?></label>
        <select name="<?= View::e($nome) ?>" id="f-<?= View::e($nome) ?>">
          <option value="">Tutte</option>
          <?php foreach ($valoriFiltri[$nome] as $valore): ?>
            <option value="<?= View::e($valore) ?>" <?= ($filtri[$nome] ?? '') === $valore ? 'selected' : '' ?>>
              <?= View::e(str_replace('_', ' ', (string) $valore)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endforeach; ?>
    <button type="submit">Filtra</button>
    <a class="bottone tenue" href="/">Azzera</a>
  </form>
</div>

<div class="riquadro">
  <h2><?= count($righe) ?> finestre promozionali</h2>
  <?php if ($righe === []): ?>
    <p class="vuoto">Nessuna campagna in arrivo con questi filtri. Inseriscine una dalla pagina Campagne.</p>
  <?php else: ?>
    <table class="impilabile">
      <thead>
        <tr>
          <th>Punto vendita</th><th>Campagna</th><th>Periodo</th>
          <th>Giorni consigliati</th><th>Stato</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($righe as $riga): ?>
        <tr>
          <td data-etichetta="Negozio">
            <a href="/punti-vendita/<?= (int) $riga['store_id'] ?>"><strong><?= View::e($riga['punto_vendita']) ?></strong></a><br>
            <span class="pill"><?= View::e($riga['tipologia']) ?></span>
            <span style="color:var(--testo-tenue);font-size:13px"><?= View::e($riga['city']) ?></span>
          </td>
          <td data-etichetta="Campagna"><?= View::e($riga['campagna']) ?><br>
              <span style="color:var(--testo-tenue);font-size:13px"><?= View::e($riga['insegna']) ?></span></td>
          <td data-etichetta="Periodo" style="white-space:nowrap">
            <?= View::e(date('d/m', strtotime((string) $riga['inizio']))) ?>
            &ndash; <?= View::e(date('d/m', strtotime((string) $riga['fine']))) ?>
          </td>
          <td data-etichetta="Giorni"><?php $giorni = $riga['giorni']; require dirname(__DIR__) . '/partials/giorni.php'; ?></td>
          <td data-etichetta="Stato">
            <span class="pill <?= View::e($classeStato((string) $riga['stato_richiesta'])) ?>">
              <?= View::e(str_replace('_', ' ', (string) $riga['stato_richiesta'])) ?>
            </span>
            <?php if (!empty($riga['richiamare_il'])): ?>
              <div style="font-size:12px;color:var(--testo-tenue);margin-top:4px">
                richiamare <?= View::e(date('d/m', strtotime((string) $riga['richiamare_il']))) ?>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
