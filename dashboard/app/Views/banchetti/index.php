<?php
use App\Core\Csrf;
use App\Core\Percorsi;
use App\Core\View;

$euro = static fn (mixed $v): string => $v === null ? '—' : '€ ' . number_format((float) $v, 2, ',', '.');
$conPromo = $confronto['medio_con_promo'] ?? null;
$senzaPromo = $confronto['medio_senza_promo'] ?? null;
?>
<h1>Banchetti svolti</h1>
<p class="sottotitolo">Il registro della raccolta: è qui che si verifica se la promozione sposta davvero le donazioni.</p>

<div class="riquadro">
  <h2>La promozione fa differenza?</h2>
  <?php if ($conPromo === null || $senzaPromo === null): ?>
    <p class="vuoto">
      Servono banchetti registrati sia con promozione attiva sia senza, per poter confrontare.
    </p>
  <?php else: ?>
    <div class="tessere">
      <div class="tessera">
        <div class="numero"><?= $euro($conPromo) ?></div>
        <div class="etichetta">media con promozione (<?= (int) $confronto['banchetti_con_promo'] ?> banchetti)</div>
      </div>
      <div class="tessera">
        <div class="numero"><?= $euro($senzaPromo) ?></div>
        <div class="etichetta">media senza promozione (<?= (int) $confronto['banchetti_senza_promo'] ?> banchetti)</div>
      </div>
      <div class="tessera">
        <div class="numero"><?= $senzaPromo > 0 ? '+' . round(((float) $conPromo / (float) $senzaPromo - 1) * 100) . '%' : '—' ?></div>
        <div class="etichetta">differenza</div>
      </div>
    </div>
    <p style="color:var(--testo-tenue);font-size:13px;margin:0">
      Con pochi banchetti la differenza può essere casuale: va riletta man mano che i dati crescono.
    </p>
  <?php endif; ?>
</div>

<div class="riquadro">
  <h2>Registra un banchetto</h2>
  <form method="post" action="<?= Percorsi::base() ?>/banchetti">
    <?= Csrf::campo() ?>
    <div class="campi">
      <div>
        <label for="b-pdv">Punto vendita</label>
        <select id="b-pdv" name="store_id" required>
          <?php foreach ($punti as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= View::e($p['name']) ?> — <?= View::e($p['city']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="b-data">Data</label>
        <input type="date" id="b-data" name="event_date" required>
      </div>
      <div>
        <label for="b-vol">Volontari</label>
        <input type="number" id="b-vol" name="volunteers_count" min="1" max="50">
      </div>
      <div>
        <label for="b-racc">Raccolto (€)</label>
        <input type="text" id="b-racc" name="donations_eur" inputmode="decimal" placeholder="es. 480,00">
      </div>
      <div>
        <label for="b-aff">Affluenza percepita (1-5)</label>
        <input type="number" id="b-aff" name="footfall_rating" min="1" max="5">
      </div>
      <div>
        <label for="b-promo">Promozione attiva</label>
        <input type="checkbox" id="b-promo" name="promo_active" value="1" style="min-width:auto">
      </div>
      <div class="campo-largo">
        <label for="b-note">Note</label>
        <textarea id="b-note" name="notes"></textarea>
      </div>
    </div>
    <button type="submit">Registra</button>
  </form>
</div>

<div class="riquadro">
  <h2>Resa per punto vendita</h2>
  <table>
    <thead><tr><th>Punto vendita</th><th>Tipologia</th><th class="numerico">Banchetti</th>
      <th class="numerico">Medio</th><th class="numerico">Con promo</th><th class="numerico">Senza promo</th></tr></thead>
    <tbody>
    <?php foreach ($resa as $r): ?>
      <tr>
        <td><a href="<?= Percorsi::base() ?>/punti-vendita/<?= (int) $r['store_id'] ?>"><?= View::e($r['punto_vendita']) ?></a></td>
        <td><span class="pill"><?= View::e($r['tipologia']) ?></span></td>
        <td class="numerico"><?= (int) $r['banchetti_svolti'] ?></td>
        <td class="numerico"><strong><?= $euro($r['raccolto_medio']) ?></strong></td>
        <td class="numerico"><?= $euro($r['medio_con_promo']) ?></td>
        <td class="numerico"><?= $euro($r['medio_senza_promo']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="riquadro">
  <h2>Ultimi banchetti</h2>
  <table>
    <thead><tr><th>Data</th><th>Punto vendita</th><th>Città</th><th class="numerico">Raccolto</th><th>Promo</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach ($banchetti as $b): ?>
      <tr>
        <td><?= date('d/m/Y', strtotime((string) $b['event_date'])) ?></td>
        <td><a href="<?= Percorsi::base() ?>/punti-vendita/<?= (int) $b['store_id'] ?>"><?= View::e($b['punto_vendita']) ?></a></td>
        <td><?= View::e($b['city']) ?></td>
        <td class="numerico"><?= $euro($b['donations_eur']) ?></td>
        <td><?= (int) $b['promo_active'] === 1 ? 'sì' : 'no' ?></td>
        <td><?= View::e($b['notes']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
