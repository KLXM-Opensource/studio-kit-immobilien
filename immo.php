<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

/**
 * Immobilien-Funktionen des Kits (Präfix immo_): Tabellen, Abfrage mit Filtern, Preise und Kennzahlen, Energieausweis
 * (Pflichtangaben § 87 GEG), Adresse und Karte, Bilder, Ansprechpartner, Exposé-Anfrage, strukturierte Daten.
 * Datenmodell und Auswahlwerte: tools/schema.php (OpenImmo-konform). Blöcke: properties, property_detail, property_search, team.
 */

use Core\Data\DataForms;
use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;

require_once __DIR__ . '/tools/schema.php';

/** Tabelle einer Rolle (Website → Immobilien → Datentabellen; Standard: immobilien, team, expose_anfragen) */
function immo_table(string $role = 'objects'): ?array
{
    static $defaults = ['objects' => 'immobilien', 'team' => 'team', 'expose' => 'expose_anfragen'];
    $h = trim((string) setting('immo_' . $role . '_table')) ?: ($defaults[$role] ?? '');
    if ($h === '') return null;
    return $role === 'expose' ? Tables::find($h) : Tables::findContent($h);
}

/** Feld vorhanden? (eigene Tabellen dürfen Felder weglassen – die Blöcke zeigen dann weniger) */
function immo_has(?array $t, string $field): bool
{
    return $t !== null && Tables::field($t, $field) !== null;
}

/** Objekt der aktuellen Detailseite (Vorlage „_vorlage-immobilien“) – in der Bearbeitung das erste Objekt als Vorschau */
function immo_current(): ?array
{
    $t = immo_table();
    if (!$t) return null;
    $ctx = app()->entry;
    if ($ctx && $ctx['table']['handle'] === $t['handle']) return $ctx['entry'];
    return is_editing() ? (Entries::query($t, ['limit' => 1])[0] ?? null) : null;
}

// ------------------------------------------------------------------ Zahlen und Bezeichnungen

/** Zahl deutsch formatiert (1.234,5), ohne überflüssige Nachkommastellen */
function immo_num(mixed $v, int $dec = 2): string
{
    if (!is_numeric($v)) return '';
    $f = (float) $v;
    $d = abs($f - round($f)) < 0.005 ? 0 : $dec;
    return \Core\I18n::locale() === 'en' ? number_format($f, $d, '.', ',') : number_format($f, $d, ',', '.');
}

/** Betrag in Euro: „489.000 €“ */
function immo_money(mixed $v): string
{
    return is_numeric($v) && (float) $v > 0 ? immo_num($v, 2) . ' €' : '';
}

/** Bezeichnung eines Auswahlwerts (aus tools/schema.php, übersetzbar) */
function immo_label(string $field, ?string $value): string
{
    $value = (string) $value;
    $o = immobilien_options($field);
    return isset($o[$value]) && $value !== '' ? lt($o[$value]) : '';
}

/** Werte einer Mehrfachauswahl als Liste [wert, …] */
function immo_multi(mixed $v): array
{
    if (is_array($v)) return array_values(array_filter(array_map('strval', $v)));
    $s = trim((string) $v);
    if ($s === '') return [];
    $j = json_decode($s, true);
    return is_array($j) ? array_values(array_filter(array_map('strval', $j))) : array_values(array_filter(array_map('trim', explode(',', $s))));
}

/** Kauf oder Miete? */
function immo_is_rent(array $e): bool
{
    return ($e['vermarktung'] ?? '') === 'miete';
}

/** Hauptpreis: [Bezeichnung, Betrag] – z. B. ['Kaufpreis', '489.000 €'], ['Kaltmiete', '1.150 €'], ['Kaufpreis', 'auf Anfrage'] */
function immo_price(array $e): array
{
    if (immo_is_rent($e)) {
        if (($m = immo_money($e['kaltmiete'] ?? null)) !== '') return [lt('Kaltmiete'), $m];
        if (($m = immo_money($e['warmmiete'] ?? null)) !== '') return [lt('Warmmiete'), $m];
        return [lt('Miete'), lt('auf Anfrage')];
    }
    if (!empty($e['preis_auf_anfrage'])) return [lt('Kaufpreis'), lt('auf Anfrage')];
    return [lt('Kaufpreis'), immo_money($e['kaufpreis'] ?? null) ?: lt('auf Anfrage')];
}

/** Status-Etikett (nur reserviert/verkauft – verfügbar ist der Normalfall) */
function immo_badge(array $e): string
{
    $st = (string) ($e['stand'] ?? 'offen');
    if ($st === 'reserviert') return lt('Reserviert');
    if ($st === 'verkauft') return immo_is_rent($e) ? lt('Vermietet') : lt('Verkauft');
    return '';
}

/** Objektart als Text: „Etagenwohnung“ bzw. „Wohnung“ */
function immo_type(array $e): string
{
    return immo_label('objekttyp', $e['objekttyp'] ?? '') ?: immo_label('objektart', $e['objektart'] ?? '');
}

/**
 * Kennzahlen für Karten und Kopf der Detailseite: [[icon, wert, bezeichnung], …] – Zimmer, Wohnfläche (bzw. Nutzfläche),
 * Grundstück, Etage. $max begrenzt die Anzahl.
 */
function immo_facts(array $e, int $max = 4): array
{
    $out = [];
    if (is_numeric($e['zimmer'] ?? null) && (float) $e['zimmer'] > 0) $out[] = ['door', immo_num($e['zimmer'], 1), lt('Zimmer')];
    if (is_numeric($e['wohnflaeche'] ?? null) && (float) $e['wohnflaeche'] > 0) $out[] = ['ruler', immo_num($e['wohnflaeche'], 1) . ' m²', lt('Wohnfläche')];
    elseif (is_numeric($e['nutzflaeche'] ?? null) && (float) $e['nutzflaeche'] > 0) $out[] = ['ruler', immo_num($e['nutzflaeche'], 1) . ' m²', lt('Nutzfläche')];
    if (is_numeric($e['grundstuecksflaeche'] ?? null) && (float) $e['grundstuecksflaeche'] > 0) $out[] = ['tree', immo_num($e['grundstuecksflaeche'], 0) . ' m²', lt('Grundstück')];
    if (($e['objektart'] ?? '') === 'wohnung' && is_numeric($e['etage'] ?? null)) $out[] = ['stairs', (int) $e['etage'] === 0 ? lt('EG') : lt('{n}. OG', ['n' => (int) $e['etage']]), lt('Etage')];
    if (is_numeric($e['schlafzimmer'] ?? null) && (float) $e['schlafzimmer'] > 0) $out[] = ['bed', immo_num($e['schlafzimmer'], 0), lt('Schlafzimmer')];
    return array_slice($out, 0, $max);
}

/** Ort für Listen: „Musterstadt-Altstadt“ bzw. „12345 Musterstadt“ */
function immo_place(array $e, bool $zip = false): string
{
    $ort = trim((string) ($e['ort'] ?? ''));
    $teil = trim((string) ($e['stadtteil'] ?? ''));
    $p = $zip && trim((string) ($e['plz'] ?? '')) !== '' ? trim((string) $e['plz']) . ' ' . $ort : $ort;
    return $teil !== '' ? ($p !== '' ? $p . ' – ' . $teil : $teil) : $p;
}

/** Adresse für die Detailseite – Straße nur, wenn „Genaue Adresse öffentlich zeigen“ an ist */
function immo_address(array $e): array
{
    $lines = [];
    if (!empty($e['adresse_zeigen'])) {
        $str = trim(trim((string) ($e['strasse'] ?? '')) . ' ' . trim((string) ($e['hausnummer'] ?? '')));
        if ($str !== '') $lines[] = $str;
    }
    $lines[] = immo_place($e, true);
    return array_values(array_filter($lines, fn($l) => $l !== ''));
}

/** Koordinaten [lat, lng] aus dem Feld „geo“ (Kern-Format: JSON {lat,lng} oder „lat,lng“) – null ohne Standort */
function immo_geo(array $e): ?array
{
    $v = $e['geo'] ?? null;
    if (is_string($v)) {
        $j = json_decode($v, true);
        $v = is_array($j) ? $j : (preg_match('~^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)~', $v, $m) ? ['lat' => $m[1], 'lng' => $m[2]] : null);
    }
    if (!is_array($v)) return null;
    $lat = $v['lat'] ?? $v[0] ?? null;
    $lng = $v['lng'] ?? $v['lon'] ?? $v[1] ?? null;
    return is_numeric($lat) && is_numeric($lng) ? [(float) $lat, (float) $lng] : null;
}

// ------------------------------------------------------------------ Bilder

/** Bilder eines Objekts in Reihenfolge: Titelbild, Bild 1 … N (nur vorhandene Bilder, ohne Doppelte) */
function immo_images(array $e): array
{
    $ids = [];
    foreach (array_merge(['bild'], array_map(fn($i) => 'bild_' . $i, range(1, IMMOBILIEN_SLOTS))) as $f) {
        $id = (int) ($e[$f] ?? 0);
        if ($id > 0 && !in_array($id, $ids, true) && ($m = Media::find($id)) && str_starts_with((string) $m['mime'], 'image/')) $ids[] = $id;
    }
    return $ids;
}

// ------------------------------------------------------------------ Abfrage und Filter

/** Erlaubte Filter aus der Adresse (?vermarktung=kauf&objektart=wohnung&ort=…&zimmer=3&preis=500000&flaeche=80&sort=preis) */
function immo_filters_from_query(): array
{
    $q = $_GET;
    $f = [];
    if (isset(immobilien_options('vermarktung')[$q['vermarktung'] ?? ''])) $f['vermarktung'] = (string) $q['vermarktung'];
    if (isset(immobilien_options('objektart')[$q['objektart'] ?? ''])) $f['objektart'] = (string) $q['objektart'];
    $ort = trim(mb_substr((string) ($q['ort'] ?? ''), 0, 60));
    if ($ort !== '') $f['ort'] = $ort;
    foreach (['zimmer', 'preis', 'flaeche'] as $k) if (isset($q[$k]) && is_numeric($q[$k]) && (float) $q[$k] > 0) $f[$k] = (float) $q[$k];
    if (in_array($q['sort'] ?? '', ['neu', 'preis_auf', 'preis_ab', 'flaeche'], true)) $f['sort'] = (string) $q['sort'];
    return $f;
}

/**
 * Objekte abfragen. $o: vermarktung, objektart, ort, zimmer (min), preis (max: Kaufpreis bzw. Kaltmiete), flaeche (min),
 * featured (nur Top), sold (reserviert/verkauft zeigen, Standard ja – verkaufte stehen hinten), sort (manuell|neu|preis_auf|preis_ab|flaeche),
 * limit, exclude (ID), team (Ansprechpartner-ID)
 */
function immo_query(array $o = []): array
{
    $t = immo_table();
    if (!$t) return [];
    $where = [];
    foreach (['vermarktung', 'objektart'] as $k) if (!empty($o[$k]) && immo_has($t, $k)) $where[] = [$k, '=', (string) $o[$k]];
    if (!empty($o['featured']) && immo_has($t, 'featured')) $where[] = ['featured', '=', 1];
    if (!empty($o['team']) && immo_has($t, 'ansprechpartner')) $where[] = ['ansprechpartner', '=', (int) $o['team']];
    if (isset($o['sold']) && !$o['sold'] && immo_has($t, 'stand')) $where[] = ['stand', '=', 'offen'];
    $sort = (string) ($o['sort'] ?? '');
    $q = ['where' => $where, 'limit' => 500] + match ($sort) {
        'neu' => ['sort' => 'published_at', 'dir' => 'desc'],
        default => [],
    };
    $rows = Entries::query($t, $q);
    // Weitere Filter in PHP (Ort als Teiltext über Ort/Stadtteil/PLZ, Preis je nach Vermarktung, Mindestwerte)
    $ort = mb_strtolower(trim((string) ($o['ort'] ?? '')));
    $rows = array_values(array_filter($rows, function (array $e) use ($o, $ort): bool {
        if ($ort !== '' && !str_contains(mb_strtolower(($e['ort'] ?? '') . ' ' . ($e['stadtteil'] ?? '') . ' ' . ($e['plz'] ?? '')), $ort)) return false;
        if (!empty($o['zimmer']) && (float) ($e['zimmer'] ?? 0) < (float) $o['zimmer']) return false;
        if (!empty($o['flaeche']) && (float) (($e['wohnflaeche'] ?? 0) ?: ($e['nutzflaeche'] ?? 0)) < (float) $o['flaeche']) return false;
        if (!empty($o['preis'])) {
            $p = immo_is_rent($e) ? (float) ($e['kaltmiete'] ?? 0) : (float) ($e['kaufpreis'] ?? 0);
            if ($p <= 0 || $p > (float) $o['preis']) return false;
        }
        if (!empty($o['exclude']) && (int) $e['id'] === (int) $o['exclude']) return false;
        return true;
    }));
    $price = fn(array $e) => immo_is_rent($e) ? (float) ($e['kaltmiete'] ?? 0) : (float) ($e['kaufpreis'] ?? 0);
    if ($sort === 'preis_auf') usort($rows, fn($a, $b) => $price($a) <=> $price($b));
    if ($sort === 'preis_ab') usort($rows, fn($a, $b) => $price($b) <=> $price($a));
    if ($sort === 'flaeche') usort($rows, fn($a, $b) => (float) ($b['wohnflaeche'] ?? 0) <=> (float) ($a['wohnflaeche'] ?? 0));
    // Verfügbare zuerst, reservierte danach, verkaufte/vermietete zuletzt (Referenzen) – Reihenfolge innerhalb bleibt
    $rank = fn(array $e) => ['offen' => 0, 'reserviert' => 1, 'verkauft' => 2][(string) ($e['stand'] ?? 'offen')] ?? 0;
    $i = 0;
    $rows = array_map(fn($e) => [$rank($e), $i++, $e], $rows);
    usort($rows, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
    $rows = array_column($rows, 2);
    return isset($o['limit']) && (int) $o['limit'] > 0 ? array_slice($rows, 0, (int) $o['limit']) : $rows;
}

/** Orte aller veröffentlichten Objekte (für die Auswahl in Suche und Filter) */
function immo_places(): array
{
    $out = [];
    foreach (immo_query(['sold' => false]) as $e) if (($o = trim((string) ($e['ort'] ?? ''))) !== '') $out[$o] = $o;
    ksort($out);
    return array_values($out);
}

/** Link zur Detailseite */
function immo_href(array $e): ?string
{
    $t = immo_table();
    return $t ? Entries::href($t, $e) : null;
}

// ------------------------------------------------------------------ Energieausweis (§ 87 GEG)

/**
 * Pflichtangaben des Energieausweises: [[bezeichnung, wert], …] und Effizienzklasse (a+ … h) – leer ohne Angaben.
 * Bei „Nicht erforderlich“ bzw. „Liegt zur Besichtigung vor“ nur dieser Hinweis.
 */
function immo_energy(array $e): array
{
    $art = (string) ($e['energieausweis'] ?? '');
    if ($art === '') return ['rows' => [], 'class' => ''];
    if (in_array($art, ['ohne', 'folgt'], true)) return ['rows' => [[lt('Energieausweis'), immo_label('energieausweis', $art)]], 'class' => ''];
    $rows = [[lt('Energieausweis'), immo_label('energieausweis', $art)]];
    if (is_numeric($e['energie_kennwert'] ?? null)) $rows[] = [$art === 'bedarf' ? lt('Endenergiebedarf') : lt('Endenergieverbrauch'), immo_num($e['energie_kennwert'], 1) . ' kWh/(m²·a)'];
    if (trim((string) ($e['energietraeger'] ?? '')) !== '') $rows[] = [lt('Wesentlicher Energieträger'), trim((string) $e['energietraeger'])];
    $bj = trim((string) ($e['energie_baujahr'] ?? '')) ?: trim((string) ($e['baujahr'] ?? ''));
    if ($bj !== '') $rows[] = [lt('Baujahr laut Energieausweis'), $bj];
    $cls = (string) ($e['energie_klasse'] ?? '');
    if ($cls !== '') $rows[] = [lt('Energieeffizienzklasse'), strtoupper($cls)];
    if (trim((string) ($e['energie_gueltig_bis'] ?? '')) !== '') $rows[] = [lt('Gültig bis'), date_local((string) $e['energie_gueltig_bis'], 'short')];
    return ['rows' => $rows, 'class' => $cls];
}

/** Kurzform für Karten (Pflichtangaben in Anzeigen): „Verbrauchsausweis · 112,4 kWh/(m²·a) · Gas · Bj. 1994 · D“ */
function immo_energy_short(array $e): string
{
    $en = immo_energy($e);
    return implode(' · ', array_map(fn($r) => $r[1], $en['rows']));
}

/** Effizienzskala A+ … H als HTML (Pfeil an der Klasse) */
function immo_energy_scale(string $cls, ?float $value = null): string
{
    $keys = ['a+', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
    if (!in_array($cls, $keys, true)) return '';
    $h = '<div class="escale" role="img" aria-label="' . e(lt('Energieeffizienzklasse {k}', ['k' => strtoupper($cls)])) . '"><ol class="escale__bar" aria-hidden="true">';
    foreach ($keys as $i => $k) $h .= '<li class="escale__k escale__k--' . $i . ($k === $cls ? ' is-on' : '') . '">' . e(strtoupper($k)) . '</li>';
    return $h . '</ol></div>';
}

// ------------------------------------------------------------------ Ansprechpartner

/** Ansprechpartner eines Objekts (Eintrag der Tabelle „team“) – sonst null */
function immo_contact(array $e): ?array
{
    $tt = immo_table('team');
    $id = (int) ($e['ansprechpartner'] ?? 0);
    return $tt && $id ? (Entries::query($tt, ['ids' => [$id]])[0] ?? null) : null;
}

/** Telefon als Wähl-Link (+49 …) */
function immo_tel_href(string $tel): string
{
    $n = preg_replace('~[^\d+]~', '', $tel) ?? '';
    if (str_starts_with($n, '00')) $n = '+' . substr($n, 2);
    elseif (str_starts_with($n, '0')) $n = '+49' . substr($n, 1);
    return 'tel:' . $n;
}

// ------------------------------------------------------------------ Exposé-Anfrage

/** Bezeichnung des Objekts für Anfragen: „Nr. W-104 · Helle 3-Zimmer-Wohnung …“ */
function immo_ref(array $e): string
{
    $nr = trim((string) ($e['objektnr'] ?? ''));
    $t = trim((string) ($e['titel'] ?? ''));
    return ($nr !== '' ? lt('Nr. {nr}', ['nr' => $nr]) . ' · ' : '') . $t;
}

/** Formular „Exposé anfordern“ (verschlüsselter Eingang) mit vorausgefülltem Objekt – leer, wenn ausgeschaltet */
function immo_expose_form(array $e, string $uid): string
{
    $t = immo_table('expose');
    if (!$t || !DataForms::enabled($t)) {
        return is_editing() ? '<p class="empty-hint">' . e('Das Formular „Exposé anfordern“ fehlt: Daten → „Exposé-Anfragen“ → Öffentliches Formular einschalten (oder Website → Immobilien → Datentabellen).') . '</p>' : '';
    }
    $state = DataForms::$state[$t['handle']] ?? [];
    $values = $state['values'] ?? [];
    if (!$values && Tables::field($t, 'objekt')) $values['objekt'] = immo_ref($e);
    return DataForms::render($t, ['uid' => $uid, 'values' => $values, 'errors' => $state['errors'] ?? [], 'message' => $state['message'] ?? null,
        'sent' => $state['sent'] ?? false, 'challenge' => $state['challenge'] ?? null]);
}

// ------------------------------------------------------------------ Strukturierte Daten

/** schema.org RealEstateListing mit Offer und Wohnobjekt (Apartment/House/Place) */
function immo_jsonld(array $e): void
{
    $t = immo_table();
    if (!$t) return;
    $kind = match ((string) ($e['objektart'] ?? '')) { 'wohnung' => 'Apartment', 'haus' => 'SingleFamilyResidence', default => 'Place' };
    $item = ['@type' => $kind, 'name' => (string) ($e['titel'] ?? '')];
    $addr = array_filter(['@type' => 'PostalAddress', 'postalCode' => trim((string) ($e['plz'] ?? '')), 'addressLocality' => trim((string) ($e['ort'] ?? '')), 'addressCountry' => 'DE',
        'streetAddress' => !empty($e['adresse_zeigen']) ? trim(trim((string) ($e['strasse'] ?? '')) . ' ' . trim((string) ($e['hausnummer'] ?? ''))) : '']);
    if (count($addr) > 2) $item['address'] = $addr;
    if (is_numeric($e['zimmer'] ?? null) && (float) $e['zimmer'] > 0 && $kind !== 'Place') $item['numberOfRooms'] = (float) $e['zimmer'];
    if (is_numeric($e['wohnflaeche'] ?? null) && (float) $e['wohnflaeche'] > 0) $item['floorSize'] = ['@type' => 'QuantitativeValue', 'value' => (float) $e['wohnflaeche'], 'unitCode' => 'MTK'];
    if (($g = immo_geo($e)) && !empty($e['adresse_zeigen'])) $item['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $g[0], 'longitude' => $g[1]];
    $node = ['@type' => 'RealEstateListing', 'name' => (string) ($e['titel'] ?? ''), 'about' => $item];
    if (trim((string) ($e['kurztext'] ?? '')) !== '') $node['description'] = trim((string) $e['kurztext']);
    if (($u = Entries::absUrl($t, $e)) !== null) $node['url'] = $u;
    if (!empty($e['published_at'])) $node['datePosted'] = substr((string) $e['published_at'], 0, 10);
    if ($imgs = immo_images($e)) {
        $node['image'] = array_values(array_filter(array_map(fn($id) => ($m = Media::find($id)) ? site_url() . Media::url($m) : null, array_slice($imgs, 0, 5))));
    }
    $amount = immo_is_rent($e) ? ($e['kaltmiete'] ?? null) : (empty($e['preis_auf_anfrage']) ? ($e['kaufpreis'] ?? null) : null);
    if (is_numeric($amount) && (float) $amount > 0) {
        $node['offers'] = ['@type' => 'Offer', 'price' => (string) (float) $amount, 'priceCurrency' => 'EUR', 'businessFunction' => immo_is_rent($e) ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
            'availability' => ['offen' => 'https://schema.org/InStock', 'reserviert' => 'https://schema.org/LimitedAvailability', 'verkauft' => 'https://schema.org/SoldOut'][(string) ($e['stand'] ?? 'offen')] ?? 'https://schema.org/InStock'];
    }
    \Core\StructuredData::add($node);
}

// ------------------------------------------------------------------ Karte eines Objekts (Liste, Startseite, „Weitere Objekte“)

/**
 * Ein Objekt als Karte: Bild (16:10 bzw. 3:2) mit Status/Vermarktung, Ort, Titel, Kennzahlen, Preis, optional Energie-Kurzform.
 * $o: sizes, htag (h2|h3), energy (Pflichtangaben zeigen), class, i (Position – gestaffeltes Einblenden)
 */
function immo_card(array $e, array $o = []): string
{
    $href = immo_href($e);
    $img = immo_images($e)[0] ?? null;
    [$pl, $pv] = immo_price($e);
    $badge = immo_badge($e);
    $h = $o['htag'] ?? 'h3';
    $facts = immo_facts($e, 3);
    $title = trim((string) ($e['titel'] ?? ''));
    $out = '<li class="pcard' . ($badge !== '' ? ' pcard--off' : '') . (!empty($o['class']) ? ' ' . e((string) $o['class']) : '') . '" data-reveal data-i="' . ((int) ($o['i'] ?? 0) % 6) . '">';
    $out .= '<div class="pcard__media">' . immobilien_image($img, $o['sizes'] ?? '(min-width: 1080px) 380px, (min-width: 640px) 45vw, 100vw', '3:2', 'pcard__img');
    $out .= '<span class="pcard__tags"><span class="ptag">' . e(immo_is_rent($e) ? lt('Miete') : lt('Kauf')) . '</span>'
        . ($badge !== '' ? '<span class="ptag ptag--state">' . e($badge) . '</span>' : '') . (!empty($e['featured']) && $badge === '' ? '<span class="ptag ptag--top">' . e(lt('Top')) . '</span>' : '') . '</span></div>';
    $out .= '<div class="pcard__body">';
    if (($ty = immo_type($e)) !== '') $out .= '<p class="pcard__type">' . e($ty) . '</p>';
    $out .= '<p class="pcard__place">' . icon('map-pin') . '<span>' . e(immo_place($e)) . '</span></p>';
    $out .= '<' . $h . ' class="pcard__title">' . ($href ? '<a class="pcard__link" href="' . e($href) . '">' . e($title) . '</a>' : e($title)) . '</' . $h . '>';
    if ($facts) {
        $out .= '<ul class="pfacts" role="list">';
        foreach ($facts as [$ic, $v, $l]) $out .= '<li><b>' . e($v) . '</b> <span>' . e($l) . '</span></li>';
        $out .= '</ul>';
    }
    $out .= '<p class="pcard__price"><span>' . e($pl) . '</span> <b>' . e($pv) . '</b></p>';
    if (!empty($o['energy']) && ($en = immo_energy_short($e)) !== '') $out .= '<p class="pcard__energy">' . e($en) . '</p>';
    $out .= '</div></li>';
    return $out;
}
