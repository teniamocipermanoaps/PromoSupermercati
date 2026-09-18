<?php

use App\Core\Percorsi;
use App\Core\View;

$messaggi = [
    'scheda-salvata' => 'Scheda del punto vendita salvata.',
    'referente-aggiunto' => 'Referente registrato.',
    'referente-senza-nome' => 'Il referente non e stato salvato: manca il nome.',
];
$attivi = array_filter($filtri);
?>
<h1>Punti vendita</h1>
<p class="sottotitolo">
  <?= (int) $totale ?> negozi in archivio<?= $attivi ? ' con questi filtri' : '' ?>.
</p>

<?php if ($esito !== null && isset($messaggi[$esito])): ?>
  <div class="avviso<?= $esito === 'referente-senza-nome' ? ' errore' : '' ?>">
    <?= View::e($messaggi[$esito]) ?>
  </div>
<?php endif; ?>

<div class="riquadro">
  <form class="filtri" method="get" action="<?= Percorsi::base() ?>/punti-vendita">
    <div class="campo-largo">
      <label for="f-cerca">Cerca nel nome o nell'indirizzo</label>
      <input type="search" id="f-cerca" name="cerca" value="<?= View::e($filtri['cerca']) ?>"
             placeholder="es. Vomero, via Toledo, Fuorigrotta">
    </div>
    <?php foreach (['provincia' => 'Provincia', 'citta' => 'Città', 'catena' => 'Insegna', 'tipologia' => 'Tipologia'] as $nome => $etichetta): ?>
      <div>
        <label for="f-<?= View::e($nome) ?>"><?= View::e($etichetta) ?></label>
        <select name="<?= View::e($nome) ?>" id="f-<?= View::e($nome) ?>">
          <option value="">Tutte</option>
          <?php foreach ($valoriFiltri[$nome] as $valore): ?>
            <option value="<?= View::e($valore) ?>" <?= $filtri[$nome] === (string) $valore ? 'selected' : '' ?>>
              <?= View::e(str_replace('_', ' ', (string) $valore)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endforeach; ?>
    <button type="submit">Filtra</button>
    <a class="bottone tenue" href="<?= Percorsi::base() ?>/punti-vendita">Azzera</a>
  </form>
</div>

<?php if ($punti === []): ?>
  <div class="riquadro">
    <p class="vuoto">Nessun punto vendita con questi filtri.</p>
  </div>
<?php else: ?>
  <?php if ($totale > $limite): ?>
    <div class="avviso">
      Mostrati i primi <?= (int) $limite ?> di <?= (int) $totale ?>. Restringi con i
      filtri o con la ricerca per vedere quelli che ti servono.
    </div>
  <?php endif; ?>

  <div class="riquadro">
    <table>
      <thead>
        <tr>
          <th>Punto vendita</th><th>Insegna</th><th>Tipologia</th>
          <th>Città</th><th>Prov.</th><th>Telefono</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($punti as $p): ?>
        <tr>
          <td data-etichetta="Punto vendita">
            <a href="<?= Percorsi::base() ?>/punti-vendita/<?= (int) $p['id'] ?>"><?= View::e($p['name']) ?></a>
            <?php if ($p['address']): ?>
              <br><span class="indirizzo"><?= View::e($p['address']) ?></span>
            <?php endif; ?>
          </td>
          <td data-etichetta="Insegna"><?= View::e($p['insegna']) ?></td>
          <td data-etichetta="Tipologia"><span class="pill"><?= View::e($p['tipologia']) ?></span></td>
          <td data-etichetta="Città">
            <?= View::e($p['city']) ?>
            <?php if ($p['postal_code']): ?>
              <span class="indirizzo"><?= View::e($p['postal_code']) ?></span>
            <?php endif; ?>
          </td>
          <td data-etichetta="Prov."><?= View::e($p['province']) ?></td>
          <td data-etichetta="Telefono">
            <?php $numero = $p['phone']; require __DIR__ . '/../partials/telefono.php'; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
