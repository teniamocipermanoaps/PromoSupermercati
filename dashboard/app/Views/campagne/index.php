<?php
use App\Core\Csrf;
use App\Core\View;

$messaggiErrore = [
    'campi-mancanti' => 'Compila insegna, titolo e le due date.',
    'date-invertite' => 'La data di fine precede quella di inizio.',
    'gia-presente'   => 'Questa campagna è già stata inserita.',
    'errore-db'      => 'Salvataggio non riuscito.',
];
?>
<h1>Campagne promozionali</h1>
<p class="sottotitolo">
  Finché il crawler non è attivo le campagne si inseriscono a mano: bastano
  insegna, titolo e le due date perché l'agenda funzioni.
</p>

<?php if ($errore !== null && isset($messaggiErrore[$errore])): ?>
  <div class="avviso errore"><?= View::e($messaggiErrore[$errore]) ?></div>
<?php endif; ?>

<div class="riquadro">
  <h2>Nuova campagna</h2>
  <form method="post" action="/campagne">
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
    <button type="submit">Aggiungi campagna</button>
    <span style="color:var(--testo-tenue);font-size:13px;margin-left:8px">
      Viene collegata a tutti i punti vendita attivi dell'insegna.
    </span>
  </form>
</div>

<div class="riquadro">
  <h2><?= count($campagne) ?> campagne registrate</h2>
  <table>
    <thead><tr><th>Campagna</th><th>Insegna</th><th>Periodo</th><th class="numerico">Giorni</th><th class="numerico">PDV</th><th>Origine</th></tr></thead>
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
        <td><span class="pill"><?= View::e($c['source_kind']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
