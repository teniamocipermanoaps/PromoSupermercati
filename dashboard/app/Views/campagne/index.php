<?php
use App\Core\Csrf;
use App\Core\Percorsi;
use App\Core\View;

$messaggiErrore = [
    'campi-mancanti' => 'Compila insegna, titolo e le due date.',
    'date-invertite' => 'La data di fine precede quella di inizio.',
    'gia-presente'   => 'Questa campagna è già stata inserita.',
    'errore-db'      => 'Salvataggio non riuscito.',
];
$esito = $_GET['esito'] ?? null;
?>
<h1>Campagne promozionali</h1>
<p class="sottotitolo">
  Finché il crawler non è attivo le campagne si inseriscono a mano: bastano
  insegna, titolo e le due date perché l'agenda funzioni.
</p>

<?php if ($errore !== null && isset($messaggiErrore[$errore])): ?>
  <div class="avviso errore"><?= View::e($messaggiErrore[$errore]) ?></div>
<?php elseif ($esito === 'creata' && $negozi === 0): ?>
  <div class="avviso errore">
    Campagna creata ma <strong>collegata a nessun punto vendita</strong>: nelle
    province scelte non ci sono negozi di quell'insegna. Non comparirà
    nell'agenda finché non la ricolleghi.
  </div>
<?php elseif ($esito === 'creata'): ?>
  <div class="avviso">Campagna creata e collegata a <?= (int) $negozi ?> punti vendita.</div>
<?php endif; ?>

<div class="riquadro">
  <h2>Nuova campagna</h2>
  <form method="post" action="<?= Percorsi::base() ?>/campagne">
    <?= Csrf::campo() ?>
    <div class="campi">
      <div>
        <label for="c-catena">Insegna</label>
        <select id="c-catena" name="chain_id" required>
          <?php foreach ($catene as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>"><?= View::e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="c-titolo">Titolo</label>
        <input type="text" id="c-titolo" name="title" required placeholder="es. Sottocosto d'autunno">
      </div>
      <div>
        <label for="c-dal">Dal</label>
        <input type="date" id="c-dal" name="valid_from" required>
      </div>
      <div>
        <label for="c-al">Al</label>
        <input type="date" id="c-al" name="valid_to" required>
      </div>
    </div>
    <div class="campi">
      <div class="campo-largo">
        <label for="c-province">Dove vale questa campagna</label>
        <?php /* Caselle e non un elenco a scelta multipla: la dashboard si usa
                 dal telefono, e li' il Ctrl+clic non esiste. */ ?>
        <div class="province" id="c-province">
          <?php foreach ($province as $prov): ?>
            <label class="provincia">
              <input type="checkbox" name="province[]" value="<?= View::e($prov['sigla']) ?>">
              <span><?= View::e($prov['sigla']) ?></span>
              <small><?= (int) $prov['quanti'] ?></small>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="nota-campo">
          <?php if ($province === []): ?>
            <strong>L'archivio dei punti vendita è vuoto</strong>: importalo con
            <code>ops/scripts/importa_negozi_osm.py</code>, altrimenti la
            campagna nasce senza negozi collegati.<br>
          <?php endif; ?>
          Non spuntare niente = tutta Italia. Il numero accanto alla sigla dice
          quanti punti vendita ci sono in quella provincia, di tutte le insegne.<br>
          Serve perché le promozioni non sono uguali ovunque: Conad è una
          federazione di cooperative regionali, e lo stesso Sottocosto a Bari e
          a Torino può cadere in settimane diverse. Collegando una campagna a
          tutta Italia quando vale solo per una zona, l'agenda proporrebbe alle
          segretarie giorni sbagliati.
        </p>
      </div>
    </div>
    <button type="submit">Aggiungi campagna</button>
  </form>
</div>

<div class="riquadro">
  <h2><?= count($campagne) ?> campagne registrate</h2>
  <table>
    <thead><tr><th>Campagna</th><th>Insegna</th><th>Periodo</th><th class="numerico">Giorni</th><th class="numerico">PDV</th><th>Dove</th><th>Origine</th></tr></thead>
    <tbody>
    <?php foreach ($campagne as $c): ?>
      <tr>
        <td><?= View::e($c['title']) ?></td>
        <td><?= View::e($c['insegna']) ?></td>
        <td style="white-space:nowrap">
          <?= date('d/m/Y', strtotime((string) $c['valid_from'])) ?>
          &ndash; <?= date('d/m/Y', strtotime((string) $c['valid_to'])) ?>
        </td>
        <td class="numerico"><?= (int) $c['durata'] ?></td>
        <td class="numerico"><?= (int) $c['punti_vendita'] ?></td>
        <td>
          <?php
            $sigle = $c['province'] === null || $c['province'] === ''
                ? []
                : explode(' ', (string) $c['province']);
          ?>
          <?php if ($sigle === []): ?>
            <span class="pill">nessun punto vendita</span>
          <?php elseif (count($sigle) > 6): ?>
            <?= count($sigle) ?> province
          <?php else: ?>
            <?= View::e(implode(' ', $sigle)) ?>
          <?php endif; ?>
        </td>
        <td><span class="pill"><?= View::e($c['source_kind']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
