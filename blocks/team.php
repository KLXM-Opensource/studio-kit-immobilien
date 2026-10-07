<?php
/**
 * Ansprechpartner (Tabelle „Team“): grid (Karten mit Foto) · list (kompakte Zeilen). Optional nur ein Bereich
 * (Verkauf & Vermietung, Hausverwaltung, Geschäftsführung). Telefon und E-Mail als Links.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;

$v = $b->variant() ?: 'grid';
$t = immo_table('team');
$rows = $t ? Entries::query($t, array_filter(['where' => ($area = (string) ($d['area'] ?? '')) !== '' ? [['bereich', '=', $area]] : []])) : [];
$tag = immobilien_htag($d);
?>
<div class="wrap">
  <?= immobilien_head($b) ?>
  <?php if ($rows): ?>
  <ul class="pteam pteam--<?= e($v) ?><?= $v === 'grid' ? ' grid ' . e(immobilien_min($d)) : '' ?>" role="list">
    <?php foreach ($rows as $pi => $p): $tel = trim((string) ($p['telefon'] ?? '')); $mail = trim((string) ($p['email'] ?? '')); ?>
    <li class="pperson card" data-reveal style="--i:<?= $pi % 6 ?>">
      <?= immobilien_image(($f = (int) ($p['foto'] ?? 0)) ?: null, $v === 'grid' ? '(min-width: 1080px) 300px, 50vw' : '96px', $v === 'grid' ? '4:5' : '1:1', 'pperson__img') ?>
      <div class="pperson__body">
        <<?= $tag ?> class="pperson__name"<?= entry_edit_attr($t, $p, 'name') ?>><?= e((string) ($p['name'] ?? '')) ?></<?= $tag ?>>
        <?php if (trim((string) ($p['position'] ?? '')) !== ''): ?><p class="pperson__role"><?= e((string) $p['position']) ?></p><?php endif; ?>
        <?php if (!empty($d['show_text']) && trim((string) ($p['kurztext'] ?? '')) !== ''): ?><p class="pperson__text"><?= e((string) $p['kurztext']) ?></p><?php endif; ?>
        <?php if ($tel !== '' || $mail !== ''): ?>
        <p class="pperson__links">
          <?php if ($tel !== ''): ?><a href="<?= e(immo_tel_href($tel)) ?>"><?= icon('phone') ?><span><?= e($tel) ?></span></a><?php endif; ?>
          <?php if ($mail !== ''): ?><a href="mailto:<?= e($mail) ?>"><?= icon('envelope-simple') ?><span><?= e($mail) ?></span></a><?php endif; ?>
        </p>
        <?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Einträge in der Tabelle „Team“.</p><?php endif; ?>
</div>
