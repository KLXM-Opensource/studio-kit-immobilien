<?php
/**
 * Immobilien (Liste): grid (Karten im Raster) · list (Zeilen: Bild links, Angaben rechts – jede Zeile ist ein Container)
 * · feature (erstes Objekt groß, weitere daneben). Quelle: alle / nur Kauf / nur Miete, optional nur Top-Immobilien.
 * Filter (optional): GET-Formular – funktioniert ohne JavaScript; js/immo.js sendet bei Änderung automatisch ab.
 * Die Adresse (?vermarktung=…&objektart=…&ort=…&zimmer=…&preis=…&flaeche=…&sort=…) ist teilbar und kommt auch von der Schnellsuche.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'grid';
$t = immo_table();
if (!$t) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Die Tabelle „Immobilien“ fehlt – Startinhalte einspielen oder unter Website → Immobilien eine Tabelle wählen.</p></div>';
    return;
}
$filterOn = !empty($d['show_filter']);
$f = $filterOn ? immo_filters_from_query() : [];
$src = (string) ($d['source'] ?? 'all');
$q = [
    'vermarktung' => $f['vermarktung'] ?? (in_array($src, ['kauf', 'miete'], true) ? $src : ''),
    'objektart' => $f['objektart'] ?? (string) ($d['objektart'] ?? ''),
    'ort' => $f['ort'] ?? '', 'zimmer' => $f['zimmer'] ?? 0, 'preis' => $f['preis'] ?? 0, 'flaeche' => $f['flaeche'] ?? 0,
    'featured' => !empty($d['only_featured']), 'sold' => !empty($d['show_sold']),
    'sort' => $f['sort'] ?? (string) ($d['sort'] ?? ''),
];
$all = immo_query($q);
$limit = max(0, (int) ($d['limit'] ?? 0));
$items = $limit ? array_slice($all, 0, $limit) : $all;
$tag = immobilien_htag($d);
$uid = $b->domId();
$energy = !empty($d['show_energy']);
$fixed = in_array($src, ['kauf', 'miete'], true);   // Quelle fest → kein Kauf/Miete-Filter
$self = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: url('/');
$sel = fn(string $k, string $val) => ($f[$k] ?? '') === $val ? ' selected' : '';
?>
<div class="wrap">
  <?= immobilien_head($b) ?>
  <?php if ($filterOn): ?>
  <form class="pfilter" method="get" action="<?= e($self) ?>#<?= e($uid) ?>-res" role="search" aria-label="<?= e(lt('Immobilien filtern')) ?>" data-immo-filter>
    <?php if (!$fixed): ?>
    <fieldset class="pfilter__seg">
      <legend class="sr-only"><?= e(lt('Kaufen oder mieten')) ?></legend>
      <?php foreach (['' => lt('Alle'), 'kauf' => lt('Kaufen'), 'miete' => lt('Mieten')] as $k => $l): ?>
      <label><input type="radio" name="vermarktung" value="<?= e($k) ?>"<?= ($f['vermarktung'] ?? '') === $k ? ' checked' : '' ?>><span><?= e($l) ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <?php endif; ?>
    <label class="pfilter__f"><span><?= e(lt('Objektart')) ?></span>
      <select name="objektart"><option value=""><?= e(lt('Alle')) ?></option>
        <?php foreach (immobilien_options('objektart') as $k => $l): ?><option value="<?= e($k) ?>"<?= $sel('objektart', $k) ?>><?= e(lt($l)) ?></option><?php endforeach; ?>
      </select></label>
    <label class="pfilter__f"><span><?= e(lt('Ort')) ?></span>
      <select name="ort"><option value=""><?= e(lt('Alle Orte')) ?></option>
        <?php foreach (immo_places() as $o): ?><option value="<?= e($o) ?>"<?= $sel('ort', $o) ?>><?= e($o) ?></option><?php endforeach; ?>
      </select></label>
    <label class="pfilter__f"><span><?= e(lt('Zimmer ab')) ?></span>
      <select name="zimmer"><option value=""><?= e(lt('Beliebig')) ?></option>
        <?php foreach ([1, 2, 3, 4, 5] as $n): ?><option value="<?= $n ?>"<?= (float) ($f['zimmer'] ?? 0) === (float) $n ? ' selected' : '' ?>><?= $n ?>+</option><?php endforeach; ?>
      </select></label>
    <label class="pfilter__f"><span><?= e(lt('Preis bis (€)')) ?></span>
      <input type="number" name="preis" min="0" step="1000" inputmode="numeric" value="<?= isset($f['preis']) ? e((string) (int) $f['preis']) : '' ?>" placeholder="<?= e(lt('Beliebig')) ?>"></label>
    <label class="pfilter__f"><span><?= e(lt('Fläche ab (m²)')) ?></span>
      <input type="number" name="flaeche" min="0" step="5" inputmode="numeric" value="<?= isset($f['flaeche']) ? e((string) (int) $f['flaeche']) : '' ?>" placeholder="<?= e(lt('Beliebig')) ?>"></label>
    <label class="pfilter__f"><span><?= e(lt('Sortierung')) ?></span>
      <select name="sort">
        <?php foreach (['' => lt('Empfohlen'), 'neu' => lt('Neueste zuerst'), 'preis_auf' => lt('Preis aufsteigend'), 'preis_ab' => lt('Preis absteigend'), 'flaeche' => lt('Größte Fläche')] as $k => $l): ?>
        <option value="<?= e($k) ?>"<?= $sel('sort', $k) ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select></label>
    <div class="pfilter__go">
      <button class="btn btn--primary" type="submit"><?= icon('funnel') ?><span><?= e(lt('Filtern')) ?></span></button>
      <?php if ($f): ?><a class="pfilter__reset" href="<?= e($self) ?>#<?= e($uid) ?>-res"><?= icon('x') ?><span><?= e(lt('Zurücksetzen')) ?></span></a><?php endif; ?>
    </div>
  </form>
  <?php endif; ?>
  <div id="<?= e($uid) ?>-res" class="pres">
    <?php if ($filterOn): ?><p class="pres__count" role="status"><?= e(count($all) === 1 ? lt('1 Objekt') : lt('{n} Objekte', ['n' => count($all)])) ?></p><?php endif; ?>
    <?php if ($items): ?>
    <ul class="plist plist--<?= e($v) ?><?= $v === 'grid' ? ' grid ' . e(immobilien_min($d)) : '' ?>" role="list">
      <?php foreach ($items as $i => $e): ?>
      <?= immo_card($e, ['htag' => $tag, 'energy' => $energy, 'i' => $i, 'class' => $v === 'feature' && $i === 0 ? 'pcard--lead' : '',
          'sizes' => $v === 'feature' && $i === 0 ? '(min-width: 1080px) 760px, 100vw' : ($v === 'list' ? '(min-width: 800px) 380px, 100vw' : '(min-width: 1080px) 380px, (min-width: 640px) 45vw, 100vw')]) ?>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="pempty">
      <p><b><?= e(lt('Gerade kein passendes Objekt.')) ?></b> <?= e(lt('Neue Angebote gehen oft zuerst an unsere Suchkunden.')) ?></p>
      <?php if (trim((string) ($d['empty_link'] ?? '')) !== ''): ?><p><a class="btn btn--secondary" <?= immobilien_link_attrs($d['empty_link']) ?>><?= e(trim((string) ($d['empty_label'] ?? '')) ?: lt('Suchauftrag anlegen')) ?></a></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (trim((string) ($d['more_label'] ?? '')) !== '' && trim((string) ($d['more_link'] ?? '')) !== '' && (!$limit || count($all) > $limit || !$filterOn)): ?>
    <p class="more-row"><a class="btn btn--secondary" <?= immobilien_link_attrs($d['more_link']) ?>><span<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></span><?= icon('arrow-right') ?></a></p>
    <?php endif; ?>
  </div>
</div>
