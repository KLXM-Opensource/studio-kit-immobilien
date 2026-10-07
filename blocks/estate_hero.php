<?php
/**
 * Einstieg Immobilien (H1): großes Foto, Ortsetikett, Überschrift, Text – statt zweier Buttons bis zu vier Einstiegs-Kacheln
 * (Kaufen · Mieten mit Anzahl verfügbarer Angebote, Verkaufen/Bewerten, Hausverwaltung) und optional eine Karte mit einer Top-Immobilie.
 * Kacheln und Karte liegen als Glas über dem Bild; ordnet sich nach verfügbarem Platz (Container-Queries, keine Breakpoints).
 * @var \Core\Block $b  @var array $d
 */
$img = (int) ($d['image'] ?? 0) ?: null;
$list = trim((string) ($d['list_link'] ?? '')) ?: '/immobilien';
$listHref = immobilien_link($list);
$sep = str_contains($listHref, '?') ? '&' : '?';
$count = fn(string $v) => count(immo_query(['vermarktung' => $v, 'sold' => false]));
$tiles = [
    ['house-line', trim((string) ($d['buy_label'] ?? '')) ?: lt('Kaufen'), ($n = $count('kauf')) === 1 ? lt('1 Angebot') : lt('{n} Angebote', ['n' => $n]), $listHref . $sep . 'vermarktung=kauf'],
    ['key', trim((string) ($d['rent_label'] ?? '')) ?: lt('Mieten'), ($n = $count('miete')) === 1 ? lt('1 Angebot') : lt('{n} Angebote', ['n' => $n]), $listHref . $sep . 'vermarktung=miete'],
];
if (trim((string) ($d['sell_link'] ?? '')) !== '') {
    $tiles[] = ['chart-line-up', trim((string) ($d['sell_label'] ?? '')) ?: lt('Verkaufen'), trim((string) ($d['sell_sub'] ?? '')) ?: lt('Kostenlose Bewertung'), immobilien_link((string) $d['sell_link'])];
}
if (trim((string) ($d['manage_link'] ?? '')) !== '') {
    $tiles[] = ['buildings', trim((string) ($d['manage_label'] ?? '')) ?: lt('Hausverwaltung'), trim((string) ($d['manage_sub'] ?? '')) ?: lt('WEG- und Mietverwaltung'), immobilien_link((string) $d['manage_link'])];
}
$top = !empty($d['show_top']) ? (immo_query(['featured' => true, 'sold' => false, 'limit' => 1])[0] ?? immo_query(['sold' => false, 'limit' => 1])[0] ?? null) : null;
$title = immobilien_title((string) ($d['title'] ?? ''));
$overlay = in_array($d['overlay'] ?? '', ['soft', 'strong'], true) ? $d['overlay'] : 'medium';
?>
<div class="ehero ehero--<?= e($overlay) ?>">
  <div class="ehero__bg" aria-hidden="true"><?= $img ? img($img, '100vw', ['eager' => true, 'fetchpriority' => 'high']) : '' ?></div>
  <div class="wrap ehero__in">
    <div class="ehero__main">
      <?php if (trim((string) ($d['eyebrow'] ?? '')) !== ''): ?><p class="ehero__loc"><?= icon('map-pin') ?><span<?= $b->edit('eyebrow') ?>><?= e((string) $d['eyebrow']) ?></span></p><?php endif; ?>
      <h1 class="ehero__title" id="<?= e($b->titleId()) ?>"<?= $b->edit('title') ?>><?= $title ?></h1>
      <?php if (trim((string) ($d['text'] ?? '')) !== ''): ?><p class="ehero__text"<?= $b->edit('text') ?>><?= e((string) $d['text']) ?></p><?php endif; ?>
    </div>
    <?php if ($top && ($th = immo_href($top))): [$pl, $pv] = immo_price($top); ?>
    <a class="ehero__top" href="<?= e($th) ?>">
      <?= immobilien_image(immo_images($top)[0] ?? null, '240px', '4:3', 'ehero__thumb') ?>
      <span class="ehero__topbody">
        <span class="ehero__toplabel"><?= e(!empty($top['featured']) ? lt('Top-Immobilie') : lt('Neu im Angebot')) ?></span>
        <span class="ehero__toptitle"><?= e((string) ($top['titel'] ?? '')) ?></span>
        <span class="ehero__topmeta"><?= e(immo_place($top)) ?></span><b class="ehero__topprice"><?= e($pv) ?></b>
      </span>
    </a>
    <?php endif; ?>
    <nav class="ehero__tiles" aria-label="<?= e(lt('Einstieg')) ?>">
      <?php foreach ($tiles as [$ic, $label, $sub, $href]): ?>
      <a class="etile" href="<?= e($href) ?>">
        <span class="etile__ico" aria-hidden="true"><?= icon($ic) ?></span>
        <span class="etile__txt"><b><?= e($label) ?></b><span><?= e($sub) ?></span></span>
        <span class="etile__go" aria-hidden="true"><?= icon('arrow-right') ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
