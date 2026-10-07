<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

/**
 * Demo-Inhalte des Kits „immobilien“: fiktives Büro „Musterhaus Immobilien (Demo)“ in Musterstadt.
 * Tabellen aus tools/schema.php (OpenImmo-konform), Fotos von Pexels (demo/*.jpg, Nachweise demo/credits.json – Medien-Schlagwort
 * „immobilien-demo“), neun Musterobjekte, drei Ansprechpartner, vier verschlüsselte Eingänge und alle Seiten inkl. Detailseite.
 * Alle Namen, Adressen, Preise und Kennwerte sind frei erfunden.
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=… php kits/immobilien/tools/demo.php [--force|--remove]
 */

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

require_once __DIR__ . '/schema.php';

const IMMO_DEMO_TAG = 'immobilien-demo';

/** Demo anlegen (Tabellen, Fotos, Einträge, Seiten). $force: vorhandene Demo vorher entfernen. */
function immo_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    if (Tables::find('immobilien') && !$force) { $log('Die Tabelle „immobilien“ gibt es schon – mit --force neu anlegen.'); return false; }
    immo_demo_remove($log);
    $img = immo_demo_photos($log);
    $tables = immo_demo_tables($log);
    $team = immo_demo_team($tables['team'] ?? null);
    immo_demo_objects($tables['immobilien'] ?? null, $img, $team, $log);
    immo_demo_pages($img, $log);
    \Core\PageCache::clear();
    $log('Fertig: ' . count($tables) . ' Tabellen, ' . count($img) . ' Fotos, Seiten und Detailseite.');
    return true;
}

/** Demo entfernen: Tabellen, Detailseite, Demo-Fotos (Seiten bleiben, außer $pages) */
function immo_demo_remove(?callable $log = null, bool $pages = false): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $slugs = ['_vorlage-immobilien'];
    if ($pages) $slugs = array_merge($slugs, ['vertrag-widerrufen', 'immobilien', 'verkaufen', 'hausverwaltung', 'suchauftrag', 'ueber-uns', 'kontakt']);
    foreach ($slugs as $s) foreach ($db->fetchAll('SELECT id FROM pages WHERE slug = ? AND parent_id IS NULL', [$s]) as $p) $db->query('DELETE FROM pages WHERE id = ?', [(int) $p['id']]);
    Pages::rebuildPaths();
    foreach (['widerrufe', 'expose_anfragen', 'suchauftraege', 'bewertungen', 'schadensmeldungen', 'immobilien', 'team'] as $h) if ($t = Tables::find($h)) Tables::delete($t);
    Tables::flush();
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . IMMO_DEMO_TAG . '%']) as $m) Media::delete((int) $m['id']);
}

/** Fotos aus demo/ in die Mediathek (Alt-Text und Nachweis aus demo/credits.json). @return array<string,int> Dateiname ohne .jpg → Medien-ID */
function immo_demo_photos(callable $log): array
{
    $dir = dirname(__DIR__) . '/demo';
    $credits = json_decode((string) @file_get_contents($dir . '/credits.json'), true) ?: [];
    $out = [];
    foreach ($credits as $c) {
        $src = $dir . '/' . $c['file'];
        if (!is_file($src)) continue;
        $tmp = tempnam(sys_get_temp_dir(), 'im') . '.jpg';
        copy($src, $tmp);   // Media::import verarbeitet die Datei – das Original im Kit bleibt
        [$m, $err] = Media::import($tmp, 'immobilien-' . $c['file'], (string) $c['alt_de'], [
            'title' => (string) $c['alt_de'], 'tags' => IMMO_DEMO_TAG,
            'credit' => 'Foto: ' . $c['photographer'] . ' / Pexels – ' . $c['photo_url'],
        ]);
        @unlink($tmp);
        if ($m) $out[basename($c['file'], '.jpg')] = (int) $m['id'];
        else $log('Foto ' . $c['file'] . ': ' . $err);
    }
    $log(count($out) . ' Fotos übernommen.');
    return $out;
}

/** Tabellen anlegen: immobilien, team und die Eingänge. @return array<string,array> */
function immo_demo_tables(callable $log): array
{
    $out = [];
    $make = function (array $def) use ($log, &$out): void {
        [$clean, $errors] = Tables::validate($def);
        if ($errors) { $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors)); return; }
        if ($t = Tables::find(Tables::create($clean))) $out[$def['handle']] = $t;
    };
    $s = immobilien_schema();
    $make($s['team']);   // zuerst: „immobilien“ verknüpft auf „team“
    $make($s['immobilien']);
    foreach (immobilien_inboxes() as $def) $make($def);
    $log(count($out) . ' Datentabellen angelegt (' . implode(', ', array_keys($out)) . ').');
    return $out;
}

/** Drei fiktive Ansprechpartner (ohne Fotos – keine echten Gesichter). @return array<string,int> */
function immo_demo_team(?array $t): array
{
    if (!$t) return [];
    $ids = [];
    foreach ([
        'julia' => ['Julia Beispiel', 'Immobilienberaterin, Verkauf', 'makler', '0123 456789-11', 'j.beispiel@example.com', 'Begleitet Eigentümer vom ersten Wertgespräch bis zum Notartermin.'],
        'murat' => ['Murat Muster', 'Vermietung und Gewerbe', 'makler', '0123 456789-12', 'm.muster@example.com', 'Findet Mieter, die passen – für Wohnungen, Büros und Läden.'],
        'anna' => ['Anna Probe', 'Hausverwaltung, WEG und Miete', 'verwaltung', '0123 456789-20', 'verwaltung@example.com', 'Ansprechpartnerin für Eigentümergemeinschaften, Abrechnungen und Technik.'],
    ] as $k => [$name, $pos, $area, $tel, $mail, $text]) {
        [$id] = Entries::save($t, null, ['name' => $name, 'position' => $pos, 'bereich' => $area, 'telefon' => $tel, 'email' => $mail, 'kurztext' => $text, 'status' => 'published']);
        $ids[$k] = (int) $id;
    }
    return $ids;
}

/** Neun Musterobjekte (Kauf und Miete, verschiedene Objektarten, eines reserviert, eines verkauft) */
function immo_demo_objects(?array $t, array $img, array $team, callable $log): void
{
    if (!$t) return;
    $I = fn(string $k) => $img[$k] ?? null;
    $p = fn(string ...$s) => implode('', array_map(fn($x) => '<p>' . $x . '</p>', $s));
    $base = ['status' => 'published', 'nutzungsart' => 'wohnen', 'stand' => 'offen', 'ort' => 'Musterstadt', 'plz' => '12345', 'adresse_zeigen' => false, 'provisionspflichtig' => true];
    $objects = [
        ['objektnr' => 'W-101', 'titel' => 'Lichtdurchflutete 3-Zimmer-Wohnung mit Südbalkon', 'vermarktung' => 'kauf', 'objektart' => 'wohnung', 'objekttyp' => 'etage', 'featured' => true,
            'stadtteil' => 'Altstadt', 'strasse' => 'Lindenallee', 'hausnummer' => '12', 'kurztext' => 'Ruhige Lage, offener Wohn- und Essbereich, Balkon nach Süden – fünf Minuten zum Marktplatz.',
            'kaufpreis' => 389000, 'hausgeld' => 310, 'provision' => '3,57 % inkl. MwSt. vom Kaufpreis', 'wohnflaeche' => 89, 'zimmer' => 3, 'schlafzimmer' => 2, 'badezimmer' => 1, 'etage' => 2, 'etagen' => 4, 'stellplaetze' => 1,
            'ausstattung' => ['balkon', 'aufzug', 'keller', 'einbaukueche', 'stellplatz'], 'ausstatt_kategorie' => 'gehoben', 'zustand' => 'modernisiert', 'baujahr' => '1998', 'modernisierung' => '2021 (Bad, Böden)',
            'verfuegbar_ab' => 'nach Vereinbarung', 'heizungsart' => ['zentral'], 'befeuerung' => ['gas'],
            'energieausweis' => 'verbrauch', 'energie_kennwert' => 96.5, 'energie_klasse' => 'c', 'energietraeger' => 'Gas', 'energie_baujahr' => '1998', 'energie_gueltig_bis' => date('Y-m-d', strtotime('+6 years')),
            'beschreibung' => $p('Die Wohnung liegt im zweiten Obergeschoss eines gepflegten Hauses mit Aufzug. Der offene Wohn- und Essbereich mit Zugang zum Südbalkon ist das Herz der Wohnung; zwei Schlafzimmer liegen ruhig zur Hofseite.', 'Bad und Böden wurden 2021 erneuert. Ein Kellerraum und ein Stellplatz im Hof gehören dazu. (Demo-Objekt – alle Angaben frei erfunden.)'),
            'ausstattung_text' => $p('Eichenparkett im Wohnbereich, Fliesen in Küche und Bad, bodengleiche Dusche, Einbauküche mit Elektrogeräten, Rollläden, Video-Gegensprechanlage.'),
            'lage_text' => $p('Die Altstadt mit Wochenmarkt, Cafés und Ärzten ist zu Fuß erreichbar, Grundschule und Kita liegen um die Ecke. Bus und Bahnhof: wenige Gehminuten.'),
            'bild' => $I('wohnung-wohnen'), 'bild_1' => $I('wohnung-kueche'), 'bild_2' => $I('wohnung-balkon'), 'bild_3' => $I('wohnung-schlafen'), 'bild_4' => $I('wohnung-bad'), 'bild_5' => $I('stadt'),
            'ansprechpartner' => $team['julia'] ?? null],
        ['objektnr' => 'H-204', 'titel' => 'Familienhaus mit großem Garten in ruhiger Lage', 'vermarktung' => 'kauf', 'objektart' => 'haus', 'objekttyp' => 'einfamilienhaus', 'featured' => true,
            'stadtteil' => 'Gartenstadt', 'kurztext' => 'Energieeffizienter Neubau von 2016 mit Wärmepumpe, fünf Zimmern und 520 m² Grundstück.',
            'kaufpreis' => 689000, 'provision' => '3,57 % inkl. MwSt. vom Kaufpreis', 'wohnflaeche' => 148, 'grundstuecksflaeche' => 520, 'zimmer' => 5, 'schlafzimmer' => 4, 'badezimmer' => 2, 'etagen' => 2, 'stellplaetze' => 2,
            'ausstattung' => ['terrasse', 'garten', 'gaeste_wc', 'einbaukueche', 'garage', 'glasfaser'], 'ausstatt_kategorie' => 'gehoben', 'zustand' => 'neuwertig', 'baujahr' => '2016',
            'verfuegbar_ab' => 'ab Frühjahr', 'heizungsart' => ['fussboden'], 'befeuerung' => ['luftwp'],
            'energieausweis' => 'bedarf', 'energie_kennwert' => 42.3, 'energie_klasse' => 'a', 'energietraeger' => 'Strom (Luft-Wärmepumpe)', 'energie_baujahr' => '2016', 'energie_gueltig_bis' => date('Y-m-d', strtotime('+3 years')),
            'beschreibung' => $p('Viel Platz für Familien: Im Erdgeschoss ein offener Wohn- und Essbereich mit Zugang zur Terrasse, Küche und Gäste-WC; oben vier Schlafzimmer und zwei Bäder.', 'Der Garten ist eingewachsen und nach Westen ausgerichtet. (Demo-Objekt.)'),
            'lage_text' => $p('Ruhige Anliegerstraße in der Gartenstadt, Kita und Grundschule fußläufig, Spielplatz am Ende der Straße.'),
            'bild' => $I('hero-haus'), 'bild_1' => $I('garten'), 'bild_2' => $I('wohnung-wohnen'), 'bild_3' => $I('wohnung-kueche'), 'bild_4' => $I('wohnung-bad'),
            'ansprechpartner' => $team['julia'] ?? null],
        ['objektnr' => 'H-207', 'titel' => 'Moderne Doppelhaushälfte nahe dem Zentrum', 'vermarktung' => 'kauf', 'objektart' => 'haus', 'objekttyp' => 'doppelhaushaelfte', 'stand' => 'reserviert',
            'stadtteil' => 'Nordviertel', 'kurztext' => 'Vier Zimmer, Terrasse, Carport – bezugsfertig und gut angebunden.',
            'kaufpreis' => 519000, 'provision' => '3,57 % inkl. MwSt. vom Kaufpreis', 'wohnflaeche' => 126, 'grundstuecksflaeche' => 310, 'zimmer' => 4, 'schlafzimmer' => 3, 'badezimmer' => 2, 'stellplaetze' => 1,
            'ausstattung' => ['terrasse', 'garten', 'keller', 'stellplatz'], 'zustand' => 'gepflegt', 'baujahr' => '2009', 'heizungsart' => ['zentral'], 'befeuerung' => ['gas', 'solar'],
            'energieausweis' => 'bedarf', 'energie_kennwert' => 68.0, 'energie_klasse' => 'b', 'energietraeger' => 'Gas, Solarthermie', 'energie_baujahr' => '2009',
            'beschreibung' => $p('Helle Doppelhaushälfte mit klarer Aufteilung und kleinem, pflegeleichtem Garten. (Demo-Objekt.)'),
            'bild' => $I('haus-2'), 'bild_1' => $I('garten'), 'ansprechpartner' => $team['julia'] ?? null],
        ['objektnr' => 'H-311', 'titel' => 'Architektenhaus mit Weitblick', 'vermarktung' => 'kauf', 'objektart' => 'haus', 'objekttyp' => 'villa', 'featured' => true, 'preis_auf_anfrage' => true,
            'stadtteil' => 'Am Sonnenhang', 'kurztext' => 'Großzügige Räume, viel Glas und ein Grundstück mit Fernsicht – für Menschen, die Architektur lieben.',
            'provision' => 'auf Anfrage', 'wohnflaeche' => 214, 'grundstuecksflaeche' => 980, 'zimmer' => 6, 'schlafzimmer' => 4, 'badezimmer' => 3, 'stellplaetze' => 2,
            'ausstattung' => ['terrasse', 'garten', 'kamin', 'klimatisiert', 'garage', 'barrierefrei'], 'ausstatt_kategorie' => 'luxus', 'zustand' => 'neuwertig', 'baujahr' => '2019',
            'heizungsart' => ['fussboden'], 'befeuerung' => ['erdwaerme'],
            'energieausweis' => 'bedarf', 'energie_kennwert' => 27.9, 'energie_klasse' => 'a+', 'energietraeger' => 'Strom (Erdwärme)', 'energie_baujahr' => '2019',
            'beschreibung' => $p('Ein Haus aus einem Guss: bodentiefe Fenster, fließende Übergänge nach draußen, hochwertige Materialien. Diskrete Vermarktung – Exposé nur nach Anfrage. (Demo-Objekt.)'),
            'bild' => $I('haus-3'), 'bild_1' => $I('wohnung-wohnen'), 'bild_2' => $I('wohnung-schlafen'), 'bild_3' => $I('wohnung-bad'), 'ansprechpartner' => $team['julia'] ?? null],
        ['objektnr' => 'M-018', 'titel' => 'Altbauwohnung mit hohen Decken und Parkett', 'vermarktung' => 'miete', 'objektart' => 'wohnung', 'objekttyp' => 'etage',
            'stadtteil' => 'Bahnhofsviertel', 'kurztext' => 'Vier Zimmer, 3,40 m Deckenhöhe, Fischgrätparkett – im 3. Obergeschoss eines Gründerzeithauses.',
            'kaltmiete' => 1390, 'nebenkosten' => 260, 'heizkosten_enthalten' => true, 'warmmiete' => 1650, 'kaution' => '3 Nettokaltmieten', 'provisionspflichtig' => false,
            'wohnflaeche' => 112, 'zimmer' => 4, 'schlafzimmer' => 2, 'badezimmer' => 1, 'etage' => 3, 'etagen' => 4,
            'ausstattung' => ['keller', 'kamin', 'denkmalschutz'], 'zustand' => 'gepflegt', 'baujahr' => '1906', 'verfuegbar_ab' => 'sofort', 'heizungsart' => ['zentral'], 'befeuerung' => ['fern'],
            'energieausweis' => 'verbrauch', 'energie_kennwert' => 148.2, 'energie_klasse' => 'e', 'energietraeger' => 'Fernwärme', 'energie_baujahr' => '1906',
            'beschreibung' => $p('Klassischer Altbau mit großzügigem Flur, Wohnzimmer mit Kamin und hohen Sprossenfenstern. (Demo-Objekt.)'),
            'bild' => $I('altbau'), 'bild_1' => $I('wohnung-schlafen'), 'bild_2' => $I('stadt'), 'ansprechpartner' => $team['murat'] ?? null],
        ['objektnr' => 'M-024', 'titel' => '2-Zimmer-Wohnung mit Einbauküche, ideal für Paare', 'vermarktung' => 'miete', 'objektart' => 'wohnung', 'objekttyp' => 'etage',
            'stadtteil' => 'Weststadt', 'kurztext' => 'Kompakt, hell und frisch renoviert – mit Balkon und Einbauküche.',
            'kaltmiete' => 780, 'nebenkosten' => 190, 'warmmiete' => 970, 'kaution' => '3 Nettokaltmieten', 'provisionspflichtig' => false,
            'wohnflaeche' => 64, 'zimmer' => 2, 'schlafzimmer' => 1, 'badezimmer' => 1, 'etage' => 1, 'etagen' => 3,
            'ausstattung' => ['balkon', 'einbaukueche', 'keller', 'haustiere'], 'zustand' => 'vollstaendig_renoviert', 'baujahr' => '1972', 'verfuegbar_ab' => 'zum 1. des übernächsten Monats',
            'heizungsart' => ['zentral'], 'befeuerung' => ['gas'],
            'energieausweis' => 'verbrauch', 'energie_kennwert' => 112.0, 'energie_klasse' => 'd', 'energietraeger' => 'Gas', 'energie_baujahr' => '1972',
            'beschreibung' => $p('Durchdachter Grundriss mit Wohnküche, Schlafzimmer und Tageslichtbad. (Demo-Objekt.)'),
            'bild' => $I('wohnung-kueche'), 'bild_1' => $I('wohnung-balkon'), 'bild_2' => $I('wohnung-bad'), 'ansprechpartner' => $team['murat'] ?? null],
        ['objektnr' => 'G-005', 'titel' => 'Helle Bürofläche in der Innenstadt', 'vermarktung' => 'miete', 'nutzungsart' => 'gewerbe', 'objektart' => 'buero_praxen',
            'stadtteil' => 'Innenstadt', 'kurztext' => '185 m² Büro mit Besprechungsraum, Teeküche und Glasfaser – teilbar ab 90 m².',
            'kaltmiete' => 2450, 'nebenkosten' => 520, 'kaution' => '3 Monatsmieten', 'provisionspflichtig' => true, 'provision' => '2,38 Monatsmieten inkl. MwSt.',
            'nutzflaeche' => 185, 'zimmer' => 6, 'etage' => 2, 'etagen' => 5, 'stellplaetze' => 3,
            'ausstattung' => ['aufzug', 'klimatisiert', 'barrierefrei', 'glasfaser', 'tiefgarage'], 'zustand' => 'modernisiert', 'baujahr' => '1994', 'verfuegbar_ab' => 'nach Vereinbarung',
            'energieausweis' => 'folgt',
            'beschreibung' => $p('Repräsentative Fläche mit großen Fenstern, Doppelboden und Klimaanlage. (Demo-Objekt.)'),
            'bild' => $I('buero'), 'ansprechpartner' => $team['murat'] ?? null],
        ['objektnr' => 'B-002', 'titel' => 'Sonniges Baugrundstück am Ortsrand', 'vermarktung' => 'kauf', 'nutzungsart' => 'wohnen', 'objektart' => 'grundstueck',
            'stadtteil' => 'Ortsteil Feldheim', 'kurztext' => '640 m² voll erschlossen, Bebauungsplan für Einzel- und Doppelhäuser.',
            'kaufpreis' => 189000, 'provision' => '3,57 % inkl. MwSt. vom Kaufpreis', 'grundstuecksflaeche' => 640, 'energieausweis' => 'ohne',
            'beschreibung' => $p('Ebenes, voll erschlossenes Grundstück mit freiem Blick nach Süden. Bauträgerfrei. (Demo-Objekt.)'),
            'bild' => $I('grundstueck'), 'ansprechpartner' => $team['julia'] ?? null],
        ['objektnr' => 'K-001', 'titel' => 'Gepflegtes Mehrfamilienhaus als Kapitalanlage', 'vermarktung' => 'kauf', 'nutzungsart' => 'anlage', 'objektart' => 'zinshaus_renditeobjekt', 'objekttyp' => 'mehrfamilienhaus', 'stand' => 'verkauft',
            'stadtteil' => 'Südstadt', 'kurztext' => 'Sechs Wohnungen, voll vermietet – erfolgreich vermittelt.',
            'kaufpreis' => 1290000, 'wohnflaeche' => 420, 'grundstuecksflaeche' => 760, 'zimmer' => 18, 'baujahr' => '1962', 'zustand' => 'gepflegt',
            'energieausweis' => 'verbrauch', 'energie_kennwert' => 131.4, 'energie_klasse' => 'e', 'energietraeger' => 'Gas', 'energie_baujahr' => '1962',
            'beschreibung' => $p('Referenzobjekt: Verkauf an einen regionalen Bestandshalter, die Verwaltung liegt weiterhin bei uns. (Demo-Objekt.)'),
            'bild' => $I('mehrfamilienhaus'), 'ansprechpartner' => $team['anna'] ?? null],
    ];
    $n = 0;
    foreach ($objects as $o) {
        [$id, $err] = Entries::save($t, null, $o + $base);
        if ($err) $log('Objekt „' . $o['titel'] . '“: ' . implode(' ', $err)); else $n++;
    }
    $log($n . ' Musterobjekte angelegt.');
}

/** Seiten: Start, Immobilien, Verkaufen, Hausverwaltung, Suchauftrag, Über uns, Kontakt + Detailseite */
function immo_demo_pages(array $img, callable $log): void
{
    $B = fn(string $type, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    $hero = fn(string $title, string $text, string $eyebrow = '') => $B('hero', ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text], ['background' => 'muted', 'spaceBottom' => 'small']);
    $page = fn(array $f, array $blocks) => Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));
    $I = fn(string $k) => $img[$k] ?? null;
    $btn = fn(string $l1 = '', string $h1 = '', string $l2 = '', string $h2 = '') => ['button_label' => $l1, 'button_link' => $h1, 'button2_label' => $l2, 'button2_link' => $h2];
    $form = fn(string $table, string $eyebrow, string $title, string $intro, string $note = '', array $tunes = []) => $B('contact', ['eyebrow' => $eyebrow, 'title' => $title, 'intro' => $intro, 'show_hours' => false, 'show_map' => false,
        'form_table' => $table, 'form_title' => '', 'submit_label' => '', 'note' => $note], $tunes);
    $db = app()->db;
    $sort = (int) $db->fetchValue('SELECT COALESCE(MAX(sort), 0) + 1 FROM pages WHERE parent_id IS NULL');

    // ---------------------------------------------------------------- Start (Inhalt der Startseite aus seed.php ersetzen)
    $home = [
        $B('estate_hero', ['eyebrow' => 'Musterstadt & Umgebung', 'title' => 'Zuhause finden. *Werte erhalten.*',
            'text' => 'Wir verkaufen, vermieten und verwalten Immobilien in der Region – persönlich, mit Marktkenntnis und klaren Abläufen.',
            'image' => $I('hero-haus'), 'overlay' => 'medium', 'list_link' => '/immobilien', 'show_top' => true,
            'buy_label' => '', 'rent_label' => '', 'sell_label' => 'Verkaufen', 'sell_sub' => 'Kostenlose Bewertung', 'sell_link' => '/verkaufen#bewertung',
            'manage_label' => 'Hausverwaltung', 'manage_sub' => 'WEG- und Mietverwaltung', 'manage_link' => '/hausverwaltung']),
        $B('properties', ['variant' => 'feature', 'eyebrow' => 'Aktuelle Angebote', 'title' => 'Top-Immobilien', 'intro' => '', 'source' => 'all', 'objektart' => '', 'only_featured' => true, 'show_sold' => false,
            'show_filter' => false, 'show_energy' => true, 'sort' => '', 'limit' => 4, 'size' => 'm', 'more_label' => 'Alle Immobilien', 'more_link' => '/immobilien', 'empty_label' => '', 'empty_link' => '']),
        $B('features', ['variant' => 'icons', 'eyebrow' => 'Leistungen', 'title' => 'Alles aus einer Hand.', 'intro' => '', 'size' => 'm', 'items' => [
            ['icon' => 'house-line', 'image' => null, 'title' => 'Verkaufen', 'text' => 'Marktgerechte Bewertung, starkes Exposé, geprüfte Käufer – bis zum Notartermin.', 'link_label' => 'Mehr', 'link' => '/verkaufen'],
            ['icon' => 'key', 'image' => null, 'title' => 'Vermieten', 'text' => 'Mieterauswahl mit Bonitätsprüfung, rechtssichere Verträge, Übergabe mit Protokoll.', 'link_label' => 'Mehr', 'link' => '/verkaufen'],
            ['icon' => 'buildings', 'image' => null, 'title' => 'Hausverwaltung', 'text' => 'WEG- und Mietverwaltung mit transparenter Abrechnung und schnellem Draht zur Technik.', 'link_label' => 'Mehr', 'link' => '/hausverwaltung'],
        ]], ['background' => 'muted']),
        $B('media_text', ['variant' => 'right', 'eyebrow' => 'Für Eigentümer', 'title' => 'Was ist Ihre Immobilie wert?',
            'text' => 'Wir ermitteln den Marktwert vor Ort – kostenlos und unverbindlich. Mit Vergleichsdaten aus der Region und einer ehrlichen Einschätzung, wie schnell ein Verkauf gelingen kann.',
            'list' => "Bewertung innerhalb einer Woche\nPersönlich vor Ort statt Online-Schätzung\nKostenlos und unverbindlich", 'image' => $I('beratung'), 'ratio' => '4:3'] + $btn('Bewertung anfragen', '/verkaufen#bewertung')),
        $B('team', ['variant' => 'grid', 'eyebrow' => 'Ihre Ansprechpartner', 'title' => 'Persönlich für Sie da.', 'intro' => '', 'area' => '', 'show_text' => true, 'size' => 'm'], ['background' => 'muted']),
        $B('cta', ['variant' => 'band', 'eyebrow' => '', 'title' => 'Nicht das Richtige dabei?', 'text' => 'Legen Sie einen Suchauftrag an – viele Objekte vermitteln wir, bevor sie online gehen.', 'image' => null]
            + $btn('Suchauftrag anlegen', '/suchauftrag', 'Anrufen', 'phone'), ['background' => 'accent']),
    ];
    $hid = (int) $db->fetchValue("SELECT id FROM pages WHERE is_home = 1 ORDER BY id LIMIT 1");
    if ($hid) {
        $enc = json_encode(['blocks' => Pages::sanitizeBlocks($home)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $db->query('UPDATE pages SET content_published = ?, content_draft = ? WHERE id = ?', [$enc, $enc, $hid]);
    } else {
        $page(['slug' => 'start', 'title' => 'Start', 'is_home' => 1, 'menu' => 0, 'sort' => $sort], $home);
    }

    // ---------------------------------------------------------------- Immobilien (Liste mit Filter)
    $page(['slug' => 'immobilien', 'title' => 'Immobilien', 'sort' => $sort + 1,
        'meta_description' => 'Aktuelle Immobilien zum Kaufen und Mieten in Musterstadt und Umgebung – Wohnungen, Häuser, Grundstücke, Gewerbe (Demo).'], [
        $hero('Immobilien', 'Wohnungen, Häuser, Grundstücke und Gewerbe zum Kaufen und Mieten. Das ausführliche Exposé senden wir Ihnen auf Anfrage.', 'Aktuelle Angebote'),
        $B('properties', ['variant' => 'grid', 'eyebrow' => '', 'title' => '', 'intro' => '', 'source' => 'all', 'objektart' => '', 'only_featured' => false, 'show_sold' => true,
            'show_filter' => true, 'show_energy' => true, 'sort' => '', 'limit' => 0, 'size' => 'm', 'more_label' => '', 'more_link' => '', 'empty_label' => 'Suchauftrag anlegen', 'empty_link' => '/suchauftrag'], ['spaceTop' => 'small']),
        $B('cta', ['variant' => 'box', 'eyebrow' => 'Suchauftrag', 'title' => 'Wir suchen für Sie weiter.', 'text' => 'Sagen Sie uns, was Sie suchen – wir melden uns, sobald ein passendes Objekt hereinkommt.', 'image' => null]
            + $btn('Suchauftrag anlegen', '/suchauftrag'), ['background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Verkaufen (Eigentümer, Bewertung)
    $page(['slug' => 'verkaufen', 'title' => 'Verkaufen & Vermieten', 'nav_title' => 'Verkaufen', 'sort' => $sort + 2,
        'meta_description' => 'Immobilie verkaufen oder vermieten in Musterstadt: kostenlose Bewertung, Exposé, Besichtigungen und Begleitung bis zum Notar (Demo).'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Für Eigentümer', 'title' => 'Verkaufen mit *Ruhe* und Plan.', 'text' => 'Vom ersten Wertgespräch bis zur Schlüsselübergabe: Sie haben einen festen Ansprechpartner und wissen jederzeit, wie es steht.',
            'points' => "Kostenlose Bewertung vor Ort\nProfessionelle Fotos und Exposé\nNur geprüfte Interessenten", 'image' => $I('schluessel'), 'ratio' => '4:5'] + $btn('Bewertung anfragen', '#bewertung')),
        $B('steps', ['variant' => 'process', 'eyebrow' => 'Ablauf', 'title' => 'So verkaufen wir Ihre Immobilie', 'intro' => '', 'items' => [
            ['meta' => '1', 'title' => 'Bewertung', 'text' => 'Besichtigung, Unterlagen, marktgerechter Angebotspreis.', 'icon' => 'chart-line-up'],
            ['meta' => '2', 'title' => 'Exposé', 'text' => 'Fotos, Grundrisse, Energieausweis, Texte – alles aus einer Hand.', 'icon' => 'file-text'],
            ['meta' => '3', 'title' => 'Besichtigungen', 'text' => 'Nur mit vorab geprüften Interessenten, auch abends und samstags.', 'icon' => 'key'],
            ['meta' => '4', 'title' => 'Notartermin', 'text' => 'Kaufvertrag, Finanzierungsnachweis, Übergabe mit Protokoll.', 'icon' => 'handshake'],
        ]], ['background' => 'muted']),
        $B('faq', ['variant' => 'split', 'eyebrow' => 'Fragen', 'title' => 'Gut zu wissen', 'intro' => '', 'items' => [
            ['q' => 'Was kostet die Bewertung?', 'a' => '<p>Nichts. Die Bewertung ist kostenlos und unverbindlich.</p>'],
            ['q' => 'Welche Unterlagen brauche ich?', 'a' => '<p>Grundbuchauszug, Grundrisse, Wohnflächenberechnung, Energieausweis, bei Eigentumswohnungen Teilungserklärung und Protokolle der letzten Eigentümerversammlungen. Fehlendes besorgen wir gern.</p>'],
            ['q' => 'Wer zahlt die Provision?', 'a' => '<p>Bei Einfamilienhäusern und Wohnungen teilen sich Käufer und Verkäufer die Provision zu gleichen Teilen (§ 656c BGB). [Ihre Konditionen ergänzen.]</p>'],
            ['q' => 'Vermieten Sie auch?', 'a' => '<p>Ja – mit Mieterauswahl, Bonitätsprüfung, Mietvertrag und Übergabe. Auf Wunsch übernimmt danach unsere Hausverwaltung.</p>'],
        ]]),
        $form('bewertungen', 'Kostenlose Bewertung', 'Was ist Ihre Immobilie wert?', 'Ein paar Angaben genügen – wir melden uns innerhalb von zwei Werktagen für einen Termin.', '', ['anchor' => 'bewertung', 'background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Hausverwaltung (Schaden melden)
    $page(['slug' => 'hausverwaltung', 'title' => 'Hausverwaltung', 'sort' => $sort + 3,
        'meta_description' => 'WEG- und Mietverwaltung in Musterstadt: transparente Abrechnung, Technik, Schaden online melden (Demo).'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Hausverwaltung', 'title' => 'Gut *verwaltet* – ohne Überraschungen.', 'text' => 'Wir verwalten Eigentümergemeinschaften und Mietshäuser: kaufmännisch, technisch und mit einem festen Ansprechpartner.',
            'points' => "Abrechnungen pünktlich und nachvollziehbar\nTechnik-Notdienst rund um die Uhr\nDigitale Belegeinsicht für Eigentümer", 'image' => $I('mehrfamilienhaus'), 'ratio' => '4:3'] + $btn('Angebot anfragen', '/verkaufen#bewertung', 'Schaden melden', '#schaden')),
        $B('features', ['variant' => 'cards', 'eyebrow' => 'Leistungen', 'title' => 'Was wir für Sie übernehmen', 'intro' => '', 'size' => 'm', 'items' => [
            ['icon' => 'users-three', 'image' => null, 'title' => 'WEG-Verwaltung', 'text' => 'Eigentümerversammlungen, Beschlüsse, Wirtschaftsplan und Jahresabrechnung nach WEG.', 'link_label' => '', 'link' => ''],
            ['icon' => 'key', 'image' => null, 'title' => 'Mietverwaltung', 'text' => 'Mieten, Nebenkostenabrechnung, Mieterwechsel und Korrespondenz.', 'link_label' => '', 'link' => ''],
            ['icon' => 'wrench', 'image' => null, 'title' => 'Technik', 'text' => 'Wartungen, Reparaturen, Angebote einholen, Handwerker koordinieren.', 'link_label' => '', 'link' => ''],
            ['icon' => 'clipboard-text', 'image' => null, 'title' => 'Sondereigentum', 'text' => 'Verwaltung einzelner Wohnungen für Kapitalanleger – auch überregional.', 'link_label' => '', 'link' => ''],
        ]], ['background' => 'muted']),
        $B('team', ['variant' => 'list', 'eyebrow' => '', 'title' => 'Ihr Ansprechpartner', 'intro' => '', 'area' => 'verwaltung', 'show_text' => true, 'size' => 'm']),
        $form('schadensmeldungen', 'Für Mieter und Eigentümer', 'Schaden melden', 'Beschreiben Sie kurz, was passiert ist. Bei Gefahr (Gas, Wasser, Strom) rufen Sie bitte zusätzlich den Notdienst an.',
            'Notdienst: [Telefonnummer]', ['anchor' => 'schaden', 'background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Suchauftrag
    $page(['slug' => 'suchauftrag', 'title' => 'Suchauftrag', 'menu' => 0, 'sort' => $sort + 4,
        'meta_description' => 'Suchauftrag anlegen: Wir melden uns, sobald eine passende Immobilie verfügbar ist (Demo).'], [
        $hero('Suchauftrag', 'Viele Objekte vermitteln wir, bevor sie online gehen – an Interessenten mit Suchauftrag.'),
        $form('suchauftraege', '', 'Was suchen Sie?', 'Je genauer, desto besser passen unsere Vorschläge.'),
    ]);

    // ---------------------------------------------------------------- Über uns
    $page(['slug' => 'ueber-uns', 'title' => 'Über uns', 'sort' => $sort + 5,
        'meta_description' => 'Musterhaus Immobilien (Demo): Team, Haltung und Region.'], [
        $hero('Über uns', 'Ein kleines Team mit großer Ortskenntnis.'),
        $B('media_text', ['variant' => 'left', 'eyebrow' => 'Region', 'title' => 'Wir kennen Musterstadt.', 'text' => 'Seit [Jahr] vermitteln und verwalten wir Immobilien in Musterstadt und Umgebung. Wir wissen, welche Straße ruhig ist, wo gebaut wird und was eine Wohnung wirklich wert ist.',
            'list' => '', 'image' => $I('stadt'), 'ratio' => '4:3'] + $btn()),
        $B('team', ['variant' => 'grid', 'eyebrow' => 'Team', 'title' => 'Ansprechpartner', 'intro' => '', 'area' => '', 'show_text' => true, 'size' => 'm'], ['background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Kontakt
    $page(['slug' => 'kontakt', 'title' => 'Kontakt', 'sort' => $sort + 6,
        'meta_description' => 'Kontakt zu Musterhaus Immobilien (Demo): Adresse, Öffnungszeiten, Anfahrt.'], [
        $hero('Kontakt', 'Rufen Sie an, schreiben Sie uns oder kommen Sie vorbei.'),
        $B('contact', ['eyebrow' => '', 'title' => '', 'intro' => '', 'show_hours' => true, 'show_map' => true, 'form_table' => 'suchauftraege', 'form_title' => 'Suchauftrag', 'submit_label' => '', 'note' => '']),
    ]);

    // ---------------------------------------------------------------- „Vertrag widerrufen“ (aus seed.php): Formular zuordnen – beim Seed gab es die Tabelle noch nicht
    foreach ($db->fetchAll("SELECT id, content_published FROM pages WHERE slug = 'vertrag-widerrufen'") as $wp) {
        $json = json_decode((string) $wp['content_published'], true);
        if (!is_array($json['blocks'] ?? null)) continue;
        foreach ($json['blocks'] as &$bl) if (($bl['type'] ?? '') === 'contact') $bl['data']['form_table'] = 'widerrufe';
        unset($bl);
        $enc = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $db->query('UPDATE pages SET content_published = ?, content_draft = ? WHERE id = ?', [$enc, $enc, (int) $wp['id']]);
    }

    // ---------------------------------------------------------------- Detailseite
    $tpl = Pages::create(['slug' => '_vorlage-immobilien', 'title' => 'Immobilie – Detailseite', 'type' => 'template', 'template_for' => 'immobilien', 'status' => 'published', 'menu' => 0],
        Pages::sanitizeBlocks([$B('property_detail', ['back_label' => 'Alle Immobilien', 'back_link' => '/immobilien', 'cta_label' => '', 'show_similar' => true, 'expose_title' => '', 'expose_intro' => ''])]));
    if ($t = Tables::find('immobilien')) Tables::setDetailPage($t, $tpl);
    $log('Seiten angelegt: Start, Immobilien, Verkaufen, Hausverwaltung, Suchauftrag, Über uns, Kontakt + Detailseite.');
}
