<?php
/**
 * Schnellsuche: Kaufen/Mieten, Objektart, Ort, Preis bis → Zielseite mit dem Block „Immobilien“ (Filter an).
 * Varianten: bar (eigener Abschnitt) · overlap (rückt in den Abschnitt davor, z. B. unter ein vollflächiges Hero-Bild).
 * Reines GET-Formular – ohne JavaScript, teilbare Adresse.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'bar';
$target = trim((string) ($d['target'] ?? ''));
$action = $target !== '' ? immobilien_link($target) : '';
$uid = $b->domId();
$places = immo_places();
?>
<div class="wrap">
  <div class="psearch psearch--<?= e($v) ?> card">
    <?php if (trim((string) ($d['title'] ?? '')) !== '' || is_editing()): ?><h2 class="psearch__title" id="<?= e($b->titleId()) ?>"<?= $b->edit('title') ?>><?= e((string) ($d['title'] ?? '')) ?></h2><?php endif; ?>
    <form class="psearch__form" method="get" action="<?= e($action) ?>" role="search" aria-label="<?= e(lt('Immobilien suchen')) ?>">
      <fieldset class="pfilter__seg">
        <legend class="sr-only"><?= e(lt('Kaufen oder mieten')) ?></legend>
        <?php foreach (['kauf' => lt('Kaufen'), 'miete' => lt('Mieten')] as $k => $l): ?>
        <label><input type="radio" name="vermarktung" value="<?= e($k) ?>"<?= $k === ((string) ($d['default'] ?? 'kauf')) ? ' checked' : '' ?>><span><?= e($l) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <label class="pfilter__f"><span><?= e(lt('Objektart')) ?></span>
        <select name="objektart"><option value=""><?= e(lt('Alle')) ?></option>
          <?php foreach (['wohnung', 'haus', 'grundstueck', 'buero_praxen', 'einzelhandel', 'zinshaus_renditeobjekt'] as $k): ?><option value="<?= e($k) ?>"><?= e(immo_label('objektart', $k)) ?></option><?php endforeach; ?>
        </select></label>
      <label class="pfilter__f"><span><?= e(lt('Ort')) ?></span>
        <?php if ($places): ?>
        <select name="ort"><option value=""><?= e(lt('Alle Orte')) ?></option><?php foreach ($places as $o): ?><option value="<?= e($o) ?>"><?= e($o) ?></option><?php endforeach; ?></select>
        <?php else: ?><input type="text" name="ort" autocomplete="address-level2"><?php endif; ?>
      </label>
      <label class="pfilter__f"><span><?= e(lt('Preis bis (€)')) ?></span><input type="number" name="preis" min="0" step="1000" inputmode="numeric" placeholder="<?= e(lt('Beliebig')) ?>"></label>
      <button class="btn btn--primary psearch__go" type="submit"><?= icon('magnifying-glass') ?><span><?= e(trim((string) ($d['button_label'] ?? '')) ?: lt('Objekte finden')) ?></span></button>
    </form>
    <?php if ($target === '' && is_editing()): ?><p class="empty-hint">Zielseite wählen (Seite mit dem Block „Immobilien“ und eingeschaltetem Filter).</p><?php endif; ?>
  </div>
</div>
