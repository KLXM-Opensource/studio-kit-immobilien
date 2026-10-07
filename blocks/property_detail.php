<?php
/**
 * Objekt (Detailseite der Tabelle „Immobilien“, Vorlage „_vorlage-immobilien“): Kopf (Typ, Ort, Titel als H1, Preis,
 * Kennzahlen), Bilder als Mosaik (Lightbox des Kerns), Beschreibung, Ausstattung, Lage mit Karte (ungefähr, solange die
 * Adresse nicht freigegeben ist), Grundriss, Video/Rundgang, Eckdaten, Energieausweis (§ 87 GEG) mit Skala, mitlaufender
 * Kasten mit Preis und Ansprechpartner, Formular „Exposé anfordern“ (verschlüsselt) und ähnliche Objekte.
 * Strukturierte Daten: RealEstateListing + Offer.
 * @var \Core\Block $b  @var array $d
 */
use Core\Media;
use Core\MediaBlocks;

$t = immo_table();
$e = immo_current();
if (!$t || !$e) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Vorschau erscheint, sobald es einen Eintrag in „Immobilien“ gibt. Dieser Block gehört auf die Detailseite der Tabelle (Vorlage „_vorlage-immobilien“).</p></div>';
    return;
}
immo_jsonld($e);
$uid = $b->domId();
$imgs = immo_images($e);
[$pl, $pv] = immo_price($e);
$badge = immo_badge($e);
$facts = immo_facts($e, 5);
$contact = immo_contact($e);
$energy = immo_energy($e);
$geo = immo_geo($e);
$exact = !empty($e['adresse_zeigen']);
$label = immo_ref($e);
$rich = fn(string $f) => trim(strip_tags((string) ($e[$f] ?? ''))) !== '' ? (string) $e[$f] : '';
$features = array_values(array_filter(array_map(fn($k) => immo_label('ausstattung', $k), immo_multi($e['ausstattung'] ?? []))));
$open = ($e['stand'] ?? 'offen') !== 'verkauft';
$back = trim((string) ($d['back_label'] ?? ''));
$similar = !empty($d['show_similar']) ? immo_query(['vermarktung' => (string) ($e['vermarktung'] ?? ''), 'exclude' => (int) $e['id'], 'sold' => false, 'limit' => 3]) : [];
$m2 = fn(mixed $v, int $dec = 1) => is_numeric($v) && (float) $v > 0 ? immo_num($v, $dec) . ' m²' : '';
$money = fn(string $f) => immo_money($e[$f] ?? null);

// Eckdaten in Gruppen: [Überschrift => [[Bezeichnung, Wert], …]] – leere Werte fallen weg
$groups = [
    lt('Preise') => immo_is_rent($e) ? [
        [lt('Kaltmiete'), $money('kaltmiete')], [lt('Nebenkosten'), $money('nebenkosten') . (!empty($e['heizkosten_enthalten']) && $money('nebenkosten') !== '' ? ' ' . lt('(inkl. Heizkosten)') : '')],
        [lt('Warmmiete'), $money('warmmiete')], [lt('Kaution'), trim((string) ($e['kaution'] ?? ''))],
    ] : [
        [lt('Kaufpreis'), !empty($e['preis_auf_anfrage']) ? lt('auf Anfrage') : $money('kaufpreis')], [lt('Hausgeld'), $money('hausgeld')],
    ],
    lt('Flächen und Räume') => [
        [lt('Wohnfläche'), $m2($e['wohnflaeche'] ?? null)], [lt('Nutzfläche'), $m2($e['nutzflaeche'] ?? null)], [lt('Grundstück'), $m2($e['grundstuecksflaeche'] ?? null, 0)],
        [lt('Zimmer'), is_numeric($e['zimmer'] ?? null) && (float) $e['zimmer'] > 0 ? immo_num($e['zimmer'], 1) : ''],
        [lt('Schlafzimmer'), is_numeric($e['schlafzimmer'] ?? null) && (float) $e['schlafzimmer'] > 0 ? immo_num($e['schlafzimmer'], 0) : ''],
        [lt('Badezimmer'), is_numeric($e['badezimmer'] ?? null) && (float) $e['badezimmer'] > 0 ? immo_num($e['badezimmer'], 0) : ''],
        [lt('Etage'), is_numeric($e['etage'] ?? null) ? ((int) $e['etage'] === 0 ? lt('Erdgeschoss') : lt('{n}. Obergeschoss', ['n' => (int) $e['etage']])) . (is_numeric($e['etagen'] ?? null) && (int) $e['etagen'] > 0 ? ' ' . lt('von {n}', ['n' => (int) $e['etagen']]) : '') : ''],
        [lt('Stellplätze'), is_numeric($e['stellplaetze'] ?? null) && (int) $e['stellplaetze'] > 0 ? (string) (int) $e['stellplaetze'] : ''],
    ],
    lt('Objekt') => [
        [lt('Objektart'), immo_type($e)], [lt('Objektnummer'), trim((string) ($e['objektnr'] ?? ''))], [lt('Baujahr'), trim((string) ($e['baujahr'] ?? ''))],
        [lt('Zustand'), immo_label('zustand', $e['zustand'] ?? '')], [lt('Letzte Modernisierung'), trim((string) ($e['modernisierung'] ?? ''))],
        [lt('Ausstattung'), immo_label('ausstatt_kategorie', $e['ausstatt_kategorie'] ?? '')],
        [lt('Heizung'), implode(', ', array_filter(array_map(fn($k) => immo_label('heizungsart', $k), immo_multi($e['heizungsart'] ?? []))))],
        [lt('Befeuerung'), implode(', ', array_filter(array_map(fn($k) => immo_label('befeuerung', $k), immo_multi($e['befeuerung'] ?? []))))],
        [lt('Verfügbar ab'), trim((string) ($e['verfuegbar_ab'] ?? ''))],
    ],
];
$groups = array_filter(array_map(fn($rows) => array_values(array_filter($rows, fn($r) => trim((string) $r[1]) !== '')), $groups));
$provision = !empty($e['provisionspflichtig']) ? trim((string) ($e['provision'] ?? '')) : '';
$noProv = empty($e['provisionspflichtig']) && trim((string) ($e['provision'] ?? '')) === '';
$texts = array_filter(['beschreibung' => lt('Objektbeschreibung'), 'ausstattung_text' => lt('Ausstattung'), 'lage_text' => lt('Lage'), 'sonstiges' => lt('Sonstiges')], fn($f) => $rich($f) !== '', ARRAY_FILTER_USE_KEY);
$video = trim((string) ($e['video_url'] ?? ''));
$tour = trim((string) ($e['rundgang_url'] ?? ''));
$plan = (int) ($e['grundriss'] ?? 0);
?>
<article class="pd">
  <div class="wrap">
    <?php if ($back !== '' && trim((string) ($d['back_link'] ?? '')) !== ''): ?><p class="pd__back"><a href="<?= e(immobilien_link((string) $d['back_link'])) ?>"><?= icon('arrow-right', ['class' => 'flip-x']) ?><span<?= $b->edit('back_label') ?>><?= e($back) ?></span></a></p><?php endif; ?>
    <header class="pd__head">
      <p class="pd__kicker"><span class="ptag"><?= e(immo_is_rent($e) ? lt('Zur Miete') : lt('Zum Kauf')) ?></span><?php if ($badge !== ''): ?><span class="ptag ptag--state"><?= e($badge) ?></span><?php endif; ?><span><?= e(implode(' · ', array_filter([immo_type($e), immo_place($e)]))) ?></span></p>
      <h1 class="pd__title"<?= entry_edit_attr($t, $e, 'titel') ?>><?= e((string) ($e['titel'] ?? '')) ?></h1>
      <div class="pd__keys">
        <p class="pd__price"><span><?= e($pl) ?></span> <b><?= e($pv) ?></b></p>
        <?php if ($facts): ?><ul class="pd__facts" role="list"><?php foreach ($facts as [$ic, $v, $l]): ?><li><?= icon($ic) ?><b><?= e($v) ?></b><span><?= e($l) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
      </div>
    </header>

    <?php if ($imgs): ?>
    <div class="pd__gallery pd__gallery--<?= min(count($imgs), 5) ?>"<?= is_editing() ? '' : ' data-cms-lightbox' ?>>
      <?php foreach (array_slice($imgs, 0, 5) as $n => $mid): $m = Media::find($mid); if (!$m) continue; ?>
      <a class="pd__shot<?= $n === 0 ? ' pd__shot--main' : '' ?>"<?= is_editing() ? '' : MediaBlocks::lightboxLink($m, (string) ($e['titel'] ?? '')) ?>>
        <?= img($mid, $n === 0 ? '(min-width: 1080px) 760px, 100vw' : '(min-width: 1080px) 380px, 50vw', ['ratio' => $n === 0 ? '3:2' : '4:3', 'eager' => $n === 0]) ?>
        <?php if ($n === 4 && count($imgs) > 5): ?><span class="pd__more"><?= icon('images') ?> <?= e(lt('+{n} Fotos', ['n' => count($imgs) - 5])) ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
      <?php if (!is_editing()): foreach (array_slice($imgs, 5) as $mid): $m = Media::find($mid); if (!$m) continue; ?>
      <a class="pd__hidden"<?= MediaBlocks::lightboxLink($m, (string) ($e['titel'] ?? '')) ?> tabindex="-1" aria-hidden="true"></a>
      <?php endforeach; endif; ?>
      <?php if (count($imgs) > 1 && !is_editing()): ?><button type="button" class="pd__all btn btn--secondary btn--small" data-immo-gallery><?= icon('images') ?><span><?= e(lt('Alle {n} Fotos', ['n' => count($imgs)])) ?></span></button><?php endif; ?>
    </div>
    <?php if (!is_editing()): ?><?= MediaBlocks::lightbox() . MediaBlocks::script() ?><?php endif; ?>
    <?php endif; ?>

    <div class="pd__grid">
      <div class="pd__main">
        <?php foreach ($texts as $f => $h): ?>
        <section class="pd__sec" aria-labelledby="<?= e($uid . '-' . $f) ?>" data-reveal>
          <h2 class="pd__h" id="<?= e($uid . '-' . $f) ?>"><?= e($h) ?></h2>
          <?php if ($f === 'ausstattung_text' && $features): ?><ul class="pchips" role="list"><?php foreach ($features as $ft): ?><li><?= icon('check') ?><?= e($ft) ?></li><?php endforeach; ?></ul><?php endif; ?>
          <div class="prose"<?= entry_edit_attr($t, $e, $f, 'rich') ?>><?= rich($rich($f)) ?></div>
          <?php if ($f === 'lage_text' && $geo): ?>
          <div class="pd__map"><?= \Core\Maps::render(['point' => $exact ? ['lat' => $geo[0], 'lng' => $geo[1]] : ['lat' => round($geo[0], 2), 'lng' => round($geo[1], 2)],
              'label' => $exact ? (string) ($e['titel'] ?? '') : lt('Ungefähre Lage'), 'address' => implode(', ', immo_address($e)), 'zoom' => $exact ? 15 : 13, 'height' => 'm', 'route' => $exact]) ?></div>
          <?php if (!$exact): ?><p class="pd__note"><?= e(lt('Die genaue Adresse erhalten Sie mit dem Exposé.')) ?></p><?php endif; ?>
          <?php endif; ?>
        </section>
        <?php endforeach; ?>
        <?php if (!isset($texts['ausstattung_text']) && $features): ?>
        <section class="pd__sec"><h2 class="pd__h"><?= e(lt('Ausstattung')) ?></h2><ul class="pchips" role="list"><?php foreach ($features as $ft): ?><li><?= icon('check') ?><?= e($ft) ?></li><?php endforeach; ?></ul></section>
        <?php endif; ?>

        <?php if ($plan || $video !== '' || $tour !== ''): ?>
        <section class="pd__sec" aria-labelledby="<?= e($uid) ?>-plan" data-reveal>
          <h2 class="pd__h" id="<?= e($uid) ?>-plan"><?= e($plan ? lt('Grundriss und Rundgang') : lt('Video und Rundgang')) ?></h2>
          <?php if ($plan && ($pm = Media::find($plan))): ?><a class="pd__plan"<?= is_editing() ? '' : MediaBlocks::lightboxLink($pm, lt('Grundriss')) ?>><?= img($plan, '(min-width: 1080px) 700px, 100vw') ?></a><?php endif; ?>
          <?php if ($video !== ''): ?><div class="pd__video"><figure class="video"><?= app()->theme->partial('video-embed', ['url' => $video, 'file' => null, 'poster' => null, 'ratio' => '16-9', 'label' => (string) ($e['titel'] ?? '')]) ?></figure></div><?php endif; ?>
          <?php if ($tour !== ''): ?><p><a class="btn btn--secondary" href="<?= e($tour) ?>" target="_blank" rel="noopener"><?= icon('cube') ?><span><?= e(lt('360°-Rundgang öffnen')) ?></span><span class="sr-only"> <?= e(lt('(öffnet in neuem Fenster)')) ?></span></a></p><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($groups): ?>
        <section class="pd__sec" aria-labelledby="<?= e($uid) ?>-facts" data-reveal>
          <h2 class="pd__h" id="<?= e($uid) ?>-facts"><?= e(lt('Eckdaten')) ?></h2>
          <div class="pd__tables">
            <?php foreach ($groups as $gh => $rows): ?>
            <div class="pd__table">
              <h3 class="pd__h3"><?= e($gh) ?></h3>
              <dl class="pdl"><?php foreach ($rows as [$k, $val]): ?><div><dt><?= e($k) ?></dt><dd><?= e($val) ?></dd></div><?php endforeach; ?></dl>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if ($provision !== ''): ?><p class="pd__note"><b><?= e(lt('Provision:')) ?></b> <?= e($provision) ?></p>
          <?php elseif ($noProv && !immo_is_rent($e)): ?><p class="pd__note"><?= e(lt('Für Käufer provisionsfrei.')) ?></p><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($energy['rows']): ?>
        <section class="pd__sec pd__energy" aria-labelledby="<?= e($uid) ?>-energy" data-reveal>
          <h2 class="pd__h" id="<?= e($uid) ?>-energy"><?= e(lt('Energieausweis')) ?></h2>
          <?= immo_energy_scale($energy['class']) ?>
          <dl class="pdl"><?php foreach ($energy['rows'] as [$k, $val]): ?><div><dt><?= e($k) ?></dt><dd><?= e($val) ?></dd></div><?php endforeach; ?></dl>
        </section>
        <?php endif; ?>
      </div>

      <aside class="pd__aside" aria-label="<?= e(lt('Kontakt zu diesem Objekt')) ?>">
        <div class="pd__box card" data-reveal>
          <p class="pd__boxprice"><span><?= e($pl) ?></span><b><?= e($pv) ?></b></p>
          <?php if ($open): ?><a class="btn btn--primary pd__cta" href="#<?= e($uid) ?>-expose"><?= icon('file-text') ?><span><?= e(trim((string) ($d['cta_label'] ?? '')) ?: lt('Exposé anfordern')) ?></span></a><?php endif; ?>
          <?php if ($contact): ?>
          <div class="pd__contact">
            <?= immobilien_image(($cf = (int) ($contact['foto'] ?? 0)) ?: null, '96px', '1:1', 'pd__avatar') ?>
            <div>
              <p class="pd__cname"><?= e((string) ($contact['name'] ?? '')) ?></p>
              <?php if (trim((string) ($contact['position'] ?? '')) !== ''): ?><p class="pd__crole"><?= e((string) $contact['position']) ?></p><?php endif; ?>
              <?php if (($tel = trim((string) ($contact['telefon'] ?? ''))) !== ''): ?><p><a href="<?= e(immo_tel_href($tel)) ?>"><?= icon('phone') ?><?= e($tel) ?></a></p><?php endif; ?>
              <?php if (($mail = trim((string) ($contact['email'] ?? ''))) !== ''): ?><p><a href="mailto:<?= e($mail) ?>?subject=<?= e(rawurlencode($label)) ?>"><?= icon('envelope-simple') ?><?= e($mail) ?></a></p><?php endif; ?>
            </div>
          </div>
          <?php elseif (immobilien_phone() !== ''): ?><p class="pd__call"><a href="<?= e((string) immobilien_phone_href()) ?>"><?= icon('phone') ?><?= e(immobilien_phone()) ?></a></p><?php endif; ?>
          <?php if (trim((string) ($e['objektnr'] ?? '')) !== ''): ?><p class="pd__nr"><?= e(lt('Objektnummer {nr}', ['nr' => trim((string) $e['objektnr'])])) ?></p><?php endif; ?>
        </div>
      </aside>
    </div>
  </div>

  <?php if ($open): ?>
  <section class="pd__expose" id="<?= e($uid) ?>-expose" aria-labelledby="<?= e($uid) ?>-xh">
    <div class="wrap pd__xwrap">
      <div class="pd__xtext">
        <p class="eyebrow"><?= e(lt('Exposé auf Anfrage')) ?></p>
        <h2 class="h2" id="<?= e($uid) ?>-xh"><?= e(trim((string) ($d['expose_title'] ?? '')) ?: lt('Exposé anfordern')) ?></h2>
        <p class="lead"><?= e(trim((string) ($d['expose_intro'] ?? '')) ?: lt('Das ausführliche Exposé mit Grundrissen, Adresse und allen Unterlagen senden wir Ihnen nach Ihrer Anfrage per E-Mail – meist am selben Werktag.')) ?></p>
        <ul class="pd__xlist" role="list">
          <li><?= icon('check') ?><?= e(lt('Persönliche Rückmeldung, keine Weitergabe Ihrer Daten')) ?></li>
          <li><?= icon('check') ?><?= e(lt('Besichtigungstermin auf Wunsch')) ?></li>
          <li><?= icon('check') ?><?= e(lt('Verschlüsselte Übertragung')) ?></li>
        </ul>
        <?php $wl = array_filter([[(string) setting('immo_withdrawal_page'), lt('Widerrufsbelehrung')], [(string) setting('immo_withdraw_form_page'), lt('Vertrag widerrufen')]], fn($x) => $x[0] !== '');
        if ($wl): ?><p class="pd__note pd__legal"><?php foreach ($wl as [$pid, $lbl]): ?><a href="<?= e(immobilien_link('page:' . (int) $pid)) ?>"><?= e($lbl) ?></a> <?php endforeach; ?></p><?php endif; ?>
      </div>
      <div class="pd__form card" data-reveal><?= immo_expose_form($e, $uid . '-xf') ?></div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($similar): ?>
  <section class="wrap pd__similar" aria-labelledby="<?= e($uid) ?>-sim">
    <h2 class="h2" id="<?= e($uid) ?>-sim"><?= e(lt('Ähnliche Objekte')) ?></h2>
    <ul class="plist plist--grid grid min-m" role="list"><?php foreach ($similar as $si => $s): ?><?= immo_card($s, ['htag' => 'h3', 'i' => $si]) ?><?php endforeach; ?></ul>
  </section>
  <?php endif; ?>
</article>
