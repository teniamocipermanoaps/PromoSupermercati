<?php
use App\Core\Csrf;
use App\Core\View;

$euro = static fn (mixed $v): string => $v === null ? '—' : '€ ' . number_format((float) $v, 2, ',', '.');
?>
<h1><?= View::e($punto['name']) ?></h1>
<p class="sottotitolo">
  <?= View::e($punto['insegna']) ?> · <span class="pill"><?= View::e($punto['tipologia']) ?></span>
  · <?= View::e($punto['city']) ?> (<?= View::e($punto['province']) ?>)
</p>

<?php $esito = $_GET['esito'] ?? ''; ?>
<?php if ($esito === 'dati-non-validi'): ?>
  <div class="avviso errore">Richiesta non salvata: controlla nome, date e che la fine non preceda l'inizio.</div>
<?php elseif ($esito !== ''): ?>
  <div class="avviso">Richiesta salvata.</div>
<?php endif; ?>

<div class="griglia-due">
  <div class="riquadro">
    <h2>Scheda</h2>
    <dl class="dati">
      <dt>Indirizzo</dt><dd><?= View::e($punto['address']) ?: '—' ?></dd>
      <dt>CAP</dt><dd><?= View::e($punto['postal_code']) ?: '—' ?></dd>
      <dt>Telefono</dt><dd><?php $numero = $punto['phone']; require __DIR__ . '/../partials/telefono.php'; ?></dd>
      <dt>Orari</dt><dd><?= View::e($punto['opening_hours']) ?: '—' ?></dd>
      <dt>Parcheggio</dt><dd><?= $punto['has_parking'] === null ? '—' : ((int) $punto['has_parking'] === 1 ? 'sì' : 'no') ?></dd>
      <dt>Spazio esterno</dt><dd><?= View::e($punto['outdoor_space_notes']) ?: '—' ?></dd>
    </dl>

    <h2 style="margin-top:20px">Referenti</h2>
    <?php if ($contatti === []): ?>
      <p class="vuoto">Nessun referente registrato.</p>
    <?php else: ?>
      <dl class="dati">
        <?php foreach ($contatti as $c): ?>
          <dt><?= View::e($c['role']) ?: 'referente' ?></dt>
          <dd><?= View::e($c['full_name']) ?><?php if ($c['phone']): ?> · <?php $numero = $c['phone']; require __DIR__ . '/../partials/telefono.php'; ?><?php endif; ?></dd>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>
  </div>

  <div class="riquadro">
    <h2>Resa dei banchetti</h2>
    <?php if ($resa === null): ?>
      <p class="vuoto">Nessun banchetto ancora svolto qui.</p>
    <?php else: ?>
      <dl class="dati">
        <dt>Banchetti svolti</dt><dd><?= (int) $resa['banchetti_svolti'] ?></dd>
        <dt>Raccolto medio</dt><dd><strong><?= $euro($resa['raccolto_medio']) ?></strong></dd>
        <dt>Con promozione</dt><dd><?= $euro($resa['medio_con_promo']) ?></dd>
        <dt>Senza promozione</dt><dd><?= $euro($resa['medio_senza_promo']) ?></dd>
        <dt>Affluenza media</dt><dd><?= $resa['affluenza_media'] ?? '—' ?> / 5</dd>
      </dl>
    <?php endif; ?>
  </div>
</div>

<div class="riquadro">
  <h2>Campagne in arrivo e giorni consigliati</h2>
  <?php if ($campagne === []): ?>
    <p class="vuoto">Nessuna campagna attiva o futura per questo punto vendita.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Campagna</th><th>Periodo</th><th>Giorni consigliati</th></tr></thead>
      <tbody>
      <?php foreach ($campagne as $c): ?>
        <tr>
          <td><?= View::e($c['campagna']) ?></td>
          <td style="white-space:nowrap">
            <?= date('d/m', strtotime((string) $c['inizio'])) ?> &ndash; <?= date('d/m', strtotime((string) $c['fine'])) ?>
          </td>
          <td><?php $giorni = $c['giorni']; require dirname(__DIR__) . '/partials/giorni.php'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="riquadro">
  <h2>Richieste di autorizzazione</h2>
  <?php if ($richieste === []): ?>
    <p class="vuoto">Nessuna richiesta registrata.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Periodo richiesto</th><th>Campagna</th><th>In carico a</th><th>Stato</th><th>Aggiorna</th></tr></thead>
      <tbody>
      <?php foreach ($richieste as $r): ?>
        <tr>
          <td style="white-space:nowrap">
            <?= date('d/m/Y', strtotime((string) $r['target_date_from'])) ?><br>
            <?= date('d/m/Y', strtotime((string) $r['target_date_to'])) ?>
          </td>
          <td><?= View::e($r['campagna']) ?: '—' ?>
            <?php if ($r['notes']): ?><br><span style="font-size:12px;color:var(--testo-tenue)"><?= View::e($r['notes']) ?></span><?php endif; ?>
          </td>
          <td><?= View::e($r['requested_by']) ?></td>
          <td>
            <span class="pill <?= $r['status'] === 'autorizzato' ? 'alto' : ($r['status'] === 'rifiutato' ? 'negativo' : 'basso') ?>">
              <?= View::e(str_replace('_', ' ', (string) $r['status'])) ?>
            </span>
            <?php if ($r['refusal_reason']): ?>
              <div style="font-size:12px;color:var(--testo-tenue)"><?= View::e($r['refusal_reason']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="/richieste/<?= (int) $r['id'] ?>" style="display:flex;gap:6px;align-items:center">
              <?= Csrf::campo() ?>
              <select name="status" aria-label="Nuovo stato">
                <?php foreach ($stati as $s): ?>
                  <option value="<?= View::e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= View::e(str_replace('_', ' ', $s)) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="date" name="next_follow_up" value="<?= View::e($r['next_follow_up']) ?>" aria-label="Richiamare il">
              <button type="submit" class="tenue">Salva</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2 style="margin-top:24px">Nuova richiesta</h2>
  <form method="post" action="/richieste">
    <?= Csrf::campo() ?>
    <input type="hidden" name="store_id" value="<?= (int) $punto['id'] ?>">
    <div class="campi">
      <div>
        <label for="r-dal">Dal</label>
        <input type="date" id="r-dal" name="target_date_from" required>
      </div>
      <div>
        <label for="r-al">Al</label>
        <input type="date" id="r-al" name="target_date_to" required>
      </div>
      <div>
        <label for="r-campagna">Campagna</label>
        <select id="r-campagna" name="flyer_id">
          <option value="">nessuna</option>
          <?php foreach ($campagne as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= View::e($c['campagna']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="r-chi">In carico a</label>
        <input type="text" id="r-chi" name="requested_by" required placeholder="nome della segretaria">
      </div>
      <div>
        <label for="r-canale">Canale</label>
        <select id="r-canale" name="channel">
          <?php foreach ($canali as $ca): ?>
            <option value="<?= View::e($ca) ?>"><?= View::e(str_replace('_', ' ', $ca)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="r-richiamo">Richiamare il</label>
        <input type="date" id="r-richiamo" name="next_follow_up">
      </div>
      <div class="campo-largo">
        <label for="r-note">Note</label>
        <textarea id="r-note" name="notes"></textarea>
      </div>
    </div>
    <button type="submit">Registra richiesta</button>
  </form>
</div>

<div class="riquadro">
  <h2>Banchetti svolti</h2>
  <?php if ($banchetti === []): ?>
    <p class="vuoto">Nessun banchetto registrato.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Data</th><th>Volontari</th><th class="numerico">Raccolto</th><th>Promo attiva</th><th>Affluenza</th><th>Note</th></tr></thead>
      <tbody>
      <?php foreach ($banchetti as $b): ?>
        <tr>
          <td><?= date('d/m/Y', strtotime((string) $b['event_date'])) ?></td>
          <td><?= $b['volunteers_count'] ?? '—' ?></td>
          <td class="numerico"><?= $euro($b['donations_eur']) ?></td>
          <td><?= $b['promo_active'] === null ? '—' : ((int) $b['promo_active'] === 1 ? 'sì' : 'no') ?></td>
          <td><?= $b['footfall_rating'] ?? '—' ?> / 5</td>
          <td><?= View::e($b['notes']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
