<?php use App\Core\View; ?>
<h1>Punti vendita</h1>
<p class="sottotitolo"><?= count($punti) ?> negozi monitorati.</p>
<div class="riquadro">
  <table>
    <thead><tr><th>Punto vendita</th><th>Insegna</th><th>Tipologia</th><th>Città</th><th>Prov.</th></tr></thead>
    <tbody>
    <?php foreach ($punti as $p): ?>
      <tr>
        <td><a href="/punti-vendita/<?= (int) $p['id'] ?>"><?= View::e($p['name']) ?></a></td>
        <td><?= View::e($p['insegna']) ?></td>
        <td><span class="pill"><?= View::e($p['tipologia']) ?></span></td>
        <td><?= View::e($p['city']) ?></td>
        <td><?= View::e($p['province']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
