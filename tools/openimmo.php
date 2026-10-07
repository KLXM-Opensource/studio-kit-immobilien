<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

/**
 * OpenImmo 1.2.7 – Import und Export für das Kit „immobilien“ (optional; Standard ist die Erfassung in der Verwaltung).
 *
 *   immobilien_openimmo_import($pfad, $opt)  .xml oder .zip (XML + Anhänge, wie aus onOffice, FlowFact, Propstack, ImmoScout …)
 *       $opt: dry (nichts schreiben), update (bestehende Objekte ändern), images (Anhänge übernehmen), allow_remote (REMOTE-Anhänge
 *             laden – Zeitlimit, Größenlimit, nur öffentliche Hosts über Core\Sources\Fetcher), delete (bei aktionart="DELETE":
 *             draft | delete | ignore), status (neue Objekte: published | draft), prune (umfang="VOLL": fehlende Objekte → Entwurf),
 *             log (callable für Fortschrittszeilen)
 *       → ['created', 'updated', 'deleted', 'skipped', 'pruned', 'media', 'team_created', 'objects' => […], 'warnings' => […], 'errors' => […]]
 *       Schlüssel für den Abgleich: openimmo_id (openimmo_obid), sonst objektnr (objektnr_extern bzw. objektnr_intern).
 *   immobilien_openimmo_export($opt)          gültiges OpenImmo-1.2.7-XML, als ZIP mit Bildern oder nur XML
 *       $opt: out (Datei, .zip oder .xml), ids (nur diese Einträge), images, xml_only (Bilder als REMOTE-Adressen), include_expose
 *             (Exposé-PDF beilegen – sonst nie), hide_address (Straße/Hausnummer/Koordinaten weglassen, wenn die Adresse nicht
 *             freigegeben ist), anbieternr, anid, base_url (für xml_only, sonst Adresse der Website), log
 *       → ['file', 'objects', 'images', 'warnings']
 *
 * Eine Zuordnungsschicht (Konstanten IMMOBILIEN_OI_*) dient beiden Richtungen: Auswahlwert ↔ OpenImmo-Attribut bzw. -Element.
 * Werte, die OpenImmo nicht genau abbildet (z. B. Zustand „Erstbezug nach Sanierung“), schreibt der Export zusätzlich als
 * <user_defined_simplefield feldname="klxm_…"> – ein Import aus KLXM Studio stellt sie damit verlustfrei wieder her.
 * Freitexte: OpenImmo erwartet reinen Text – Absätze und Aufzählungen werden in beide Richtungen umgesetzt.
 */

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;

if (!function_exists('immobilien_schema')) require_once __DIR__ . '/schema.php';

// ================================================================== Zuordnung (beide Richtungen)

/** Auswahlwert (tools/schema.php) → OpenImmo-Wert; Import nutzt die Umkehrung plus IMMOBILIEN_OI_ALIAS */
const IMMOBILIEN_OI_MAP = [
    // objektkategorie/vermarktungsart @KAUF @MIETE_PACHT (xsd:boolean)
    'vermarktung' => ['kauf' => 'KAUF', 'miete' => 'MIETE_PACHT'],
    // objektkategorie/nutzungsart @WOHNEN @GEWERBE @ANLAGE @WAZ
    'nutzungsart' => ['wohnen' => 'WOHNEN', 'gewerbe' => 'GEWERBE', 'anlage' => 'ANLAGE', 'waz' => 'WAZ'],
    // objektkategorie/objektart/<element> – Elementname = Auswahlwert
    'objektart' => ['wohnung' => 'wohnung', 'haus' => 'haus', 'grundstueck' => 'grundstueck', 'buero_praxen' => 'buero_praxen',
        'einzelhandel' => 'einzelhandel', 'gastgewerbe' => 'gastgewerbe', 'hallen_lager_prod' => 'hallen_lager_prod',
        'zinshaus_renditeobjekt' => 'zinshaus_renditeobjekt', 'parken' => 'parken', 'zimmer' => 'zimmer', 'sonstige' => 'sonstige'],
    // zustand_angaben/verkaufstatus@stand
    'stand' => ['offen' => 'OFFEN', 'reserviert' => 'RESERVIERT', 'verkauft' => 'VERKAUFT'],
    // zustand_angaben/zustand@zustand_art
    'zustand' => ['erstbezug' => 'ERSTBEZUG', 'erstbezug_nach_sanierung' => 'VOLL_SANIERT', 'neuwertig' => 'NEUWERTIG', 'gepflegt' => 'GEPFLEGT',
        'modernisiert' => 'MODERNISIERT', 'teil_vollsaniert' => 'TEIL_VOLLSANIERT', 'vollstaendig_renoviert' => 'TEIL_VOLLRENOVIERT',
        'renovierungsbeduerftig' => 'TEIL_VOLLRENOVIERUNGSBED', 'nach_vereinbarung' => 'NACH_VEREINBARUNG', 'projektiert' => 'PROJEKTIERT', 'rohbau' => 'ROHBAU'],
    // ausstattung/ausstatt_kategorie
    'ausstatt_kategorie' => ['standard' => 'STANDARD', 'gehoben' => 'GEHOBEN', 'luxus' => 'LUXUS'],
    // ausstattung/heizungsart @OFEN @ETAGE @ZENTRAL @FERN @FUSSBODEN
    'heizungsart' => ['zentral' => 'ZENTRAL', 'etage' => 'ETAGE', 'fern' => 'FERN', 'fussboden' => 'FUSSBODEN', 'ofen' => 'OFEN'],
    // ausstattung/befeuerung @… (OpenImmo 1.2.7 kennt keine allgemeine „Wärmepumpe“ – ALTERNATIV)
    'befeuerung' => ['gas' => 'GAS', 'oel' => 'OEL', 'fern' => 'FERN', 'waermepumpe' => 'ALTERNATIV', 'luftwp' => 'LUFTWP', 'erdwaerme' => 'ERDWAERME',
        'solar' => 'SOLAR', 'pellet' => 'PELLET', 'holz' => 'HOLZ', 'elektro' => 'ELEKTRO', 'block' => 'BLOCK'],
    // zustand_angaben/energiepass/epart bzw. jahrgang (ohne Ausweis)
    'energieausweis' => ['bedarf' => 'BEDARF', 'verbrauch' => 'VERBRAUCH', 'ohne' => 'nicht_noetig', 'folgt' => 'bei_besichtigung'],
    // zustand_angaben/energiepass/wertklasse
    'energie_klasse' => ['a+' => 'A+', 'a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E', 'f' => 'F', 'g' => 'G', 'h' => 'H'],
];

/** Untertyp je Objektart: [Attribut, [Auswahlwert → OpenImmo-Wert]] (objektkategorie/objektart/<art>@<attribut>) */
const IMMOBILIEN_OI_OBJEKTTYP = [
    'wohnung' => ['wohnungtyp', ['etage' => 'ETAGE', 'erdgeschoss' => 'ERDGESCHOSS', 'dachgeschoss' => 'DACHGESCHOSS', 'maisonette' => 'MAISONETTE',
        'penthouse' => 'PENTHOUSE', 'loft' => 'LOFT-STUDIO-ATELIER', 'terrassen' => 'TERRASSEN', 'apartment' => 'APARTMENT']],
    'haus' => ['haustyp', ['einfamilienhaus' => 'EINFAMILIENHAUS', 'zweifamilienhaus' => 'ZWEIFAMILIENHAUS', 'mehrfamilienhaus' => 'MEHRFAMILIENHAUS',
        'doppelhaushaelfte' => 'DOPPELHAUSHAELFTE', 'reihenhaus' => 'REIHENHAUS', 'reiheneck' => 'REIHENECK', 'reihenmittel' => 'REIHENMITTEL',
        'stadthaus' => 'STADTHAUS', 'villa' => 'VILLA', 'bungalow' => 'BUNGALOW', 'bauernhaus' => 'BAUERNHAUS']],
    'zinshaus_renditeobjekt' => ['zins_typ', ['mehrfamilienhaus' => 'MEHRFAMILIENHAUS']],
];

/** Nur Import: weitere OpenImmo-Werte → nächster Auswahlwert ('' = verwerfen) */
const IMMOBILIEN_OI_ALIAS = [
    'vermarktung' => ['ERBPACHT' => 'kauf', 'LEASING' => 'miete'],
    'objektart' => ['land_und_forstwirtschaft' => 'sonstige', 'freizeitimmobilie_gewerblich' => 'sonstige'],
    'objekttyp' => ['REIHENEND' => 'reiheneck', 'ATTIKAWOHNUNG' => 'penthouse', 'GALERIE' => 'maisonette', 'SOUTERRAIN' => 'erdgeschoss'],
    'zustand' => ['VOLL_SANIERT' => 'teil_vollsaniert', 'TEIL_SANIERT' => 'teil_vollsaniert', 'SANIERUNGSBEDUERFTIG' => 'renovierungsbeduerftig',
        'BAUFAELLIG' => 'renovierungsbeduerftig', 'ABRISSOBJEKT' => 'renovierungsbeduerftig', 'ENTKERNT' => 'rohbau'],
    'befeuerung' => ['FLUESSIGGAS' => 'gas', 'WASSER-ELEKTRO' => 'elektro'],
    'energieausweis' => ['ohne' => 'ohne'],
    'energie_klasse' => ['A_PLUS' => 'a+', 'APLUS' => 'a+', 'A++' => 'a+'],
];

/**
 * Mehrfachauswahl „ausstattung“: Wert → [Abschnitt, Element, Art, Attribut, Wert]
 *   bool  <element>true</element> · count  Anzahl > 0 (Export: 1) · attr  @ATTRIBUT="true" · enum  @attribut="WERT"
 */
const IMMOBILIEN_OI_AUSSTATTUNG = [
    'balkon' => ['flaechen', 'anzahl_balkone', 'count'],
    'terrasse' => ['flaechen', 'anzahl_terrassen', 'count'],
    'garten' => ['ausstattung', 'gartennutzung', 'bool'],
    'aufzug' => ['ausstattung', 'fahrstuhl', 'attr', 'PERSONEN'],
    'keller' => ['ausstattung', 'unterkellert', 'enum', 'keller', 'JA'],
    'einbaukueche' => ['ausstattung', 'kueche', 'attr', 'EBK'],
    'gaeste_wc' => ['ausstattung', 'gaestewc', 'bool'],
    'barrierefrei' => ['ausstattung', 'barrierefrei', 'bool'],
    'kamin' => ['ausstattung', 'kamin', 'bool'],
    'klimatisiert' => ['ausstattung', 'klimatisiert', 'bool'],
    'garage' => ['ausstattung', 'stellplatzart', 'attr', 'GARAGE'],
    'tiefgarage' => ['ausstattung', 'stellplatzart', 'attr', 'TIEFGARAGE'],
    'stellplatz' => ['ausstattung', 'stellplatzart', 'attr', 'FREIPLATZ'],
    'haustiere' => ['verwaltung_objekt', 'haustiere', 'bool'],
    'denkmalschutz' => ['verwaltung_objekt', 'denkmalgeschuetzt', 'bool'],
    'moebliert' => ['ausstattung', 'moebliert', 'enum', 'moeb', 'VOLL'],
    'wg_geeignet' => ['ausstattung', 'wg_geeignet', 'bool'],
    'glasfaser' => ['ausstattung', 'breitband_zugang', 'enum', 'art', 'GLASFASER'],
];

/** Einfache Felder: Feld → [Abschnitt/Element, Art] (num = xsd:decimal, int = xsd:int, text, bool) – gleiche Tabelle für Import und Export */
const IMMOBILIEN_OI_FIELDS = [
    'plz' => ['geo/plz', 'text'], 'ort' => ['geo/ort', 'text'], 'strasse' => ['geo/strasse', 'text'], 'hausnummer' => ['geo/hausnummer', 'text'],
    'stadtteil' => ['geo/regionaler_zusatz', 'text'], 'etage' => ['geo/etage', 'int'], 'etagen' => ['geo/anzahl_etagen', 'int'],
    'warmmiete' => ['preise/warmmiete', 'num'], 'nebenkosten' => ['preise/nebenkosten', 'num'], 'hausgeld' => ['preise/hausgeld', 'num'],
    'provision' => ['preise/aussen_courtage', 'text'],
    'wohnflaeche' => ['flaechen/wohnflaeche', 'num'], 'nutzflaeche' => ['flaechen/nutzflaeche', 'num'], 'grundstuecksflaeche' => ['flaechen/grundstuecksflaeche', 'num'],
    'zimmer' => ['flaechen/anzahl_zimmer', 'num'], 'schlafzimmer' => ['flaechen/anzahl_schlafzimmer', 'num'], 'badezimmer' => ['flaechen/anzahl_badezimmer', 'num'],
    'stellplaetze' => ['flaechen/anzahl_stellplaetze', 'int'],
    'baujahr' => ['zustand_angaben/baujahr', 'text'], 'modernisierung' => ['zustand_angaben/letztemodernisierung', 'text'],
    'verfuegbar_ab' => ['verwaltung_objekt/verfuegbar_ab', 'text'],
    'energie_baujahr' => ['zustand_angaben/energiepass/baujahr', 'text'], 'energietraeger' => ['zustand_angaben/energiepass/primaerenergietraeger', 'text'],
];

/** Freitexte: Feld → Element unter <freitexte> (richtext ↔ reiner Text; kurztext und titel bleiben einzeilig/ohne HTML) */
const IMMOBILIEN_OI_TEXTS = ['titel' => 'objekttitel', 'kurztext' => 'dreizeiler', 'lage_text' => 'lage', 'ausstattung_text' => 'ausstatt_beschr',
    'beschreibung' => 'objektbeschreibung', 'sonstiges' => 'sonstige_angaben'];

/** Reihenfolge der Kindelemente laut XSD (nur die verwendeten) */
const IMMOBILIEN_OI_ORDER = [
    'immobilie' => ['objektkategorie', 'geo', 'kontaktperson', 'preise', 'flaechen', 'ausstattung', 'zustand_angaben', 'freitexte', 'anhaenge',
        'verwaltung_objekt', 'verwaltung_techn', 'user_defined_simplefield'],
    'objektkategorie' => ['nutzungsart', 'vermarktungsart', 'objektart'],
    'geo' => ['plz', 'ort', 'geokoordinaten', 'strasse', 'hausnummer', 'land', 'etage', 'anzahl_etagen', 'regionaler_zusatz'],
    'kontaktperson' => ['email_zentrale', 'email_direkt', 'tel_zentrale', 'tel_durchw', 'name', 'vorname', 'position', 'firma'],
    'preise' => ['kaufpreis', 'nettokaltmiete', 'kaltmiete', 'warmmiete', 'nebenkosten', 'heizkosten_enthalten', 'hausgeld', 'provisionspflichtig',
        'aussen_courtage', 'waehrung', 'kaution', 'kaution_text'],
    'flaechen' => ['wohnflaeche', 'nutzflaeche', 'grundstuecksflaeche', 'anzahl_zimmer', 'anzahl_schlafzimmer', 'anzahl_badezimmer', 'anzahl_balkone',
        'anzahl_terrassen', 'anzahl_stellplaetze'],
    'ausstattung' => ['ausstatt_kategorie', 'wg_geeignet', 'kueche', 'kamin', 'heizungsart', 'befeuerung', 'klimatisiert', 'fahrstuhl', 'stellplatzart',
        'gartennutzung', 'moebliert', 'barrierefrei', 'breitband_zugang', 'unterkellert', 'gaestewc'],
    'zustand_angaben' => ['baujahr', 'letztemodernisierung', 'zustand', 'energiepass', 'verkaufstatus'],
    'energiepass' => ['epart', 'gueltig_bis', 'energieverbrauchkennwert', 'endenergiebedarf', 'primaerenergietraeger', 'wertklasse', 'baujahr', 'jahrgang', 'gebaeudeart'],
    'freitexte' => ['objekttitel', 'dreizeiler', 'lage', 'ausstatt_beschr', 'objektbeschreibung', 'sonstige_angaben'],
    'verwaltung_objekt' => ['objektadresse_freigeben', 'verfuegbar_ab', 'haustiere', 'denkmalgeschuetzt'],
    'verwaltung_techn' => ['objektnr_extern', 'aktion', 'openimmo_obid', 'kennung_ursprung', 'stand_vom'],
];

/** Bild-Gruppen, die in Titelbild bzw. bild_1 … bild_N landen */
const IMMOBILIEN_OI_IMAGE_GROUPS = ['TITELBILD', 'BILD', 'INNENANSICHTEN', 'AUSSENANSICHTEN', 'PANORAMA', 'KARTEN_LAGEPLAN', ''];

/** Grenzen für ZIP-Pakete und Downloads */
const IMMOBILIEN_OI_LIMITS = ['zip_files' => 2000, 'zip_bytes' => 600 * 1024 * 1024, 'file_bytes' => 50 * 1024 * 1024, 'remote_bytes' => 25 * 1024 * 1024, 'remote_timeout' => 20];

/** Auswahlwert → OpenImmo (null = keine Entsprechung) */
function immobilien_openimmo_out(string $field, string $value): ?string
{
    return IMMOBILIEN_OI_MAP[$field][$value] ?? null;
}

/** OpenImmo → Auswahlwert ('' = keine Entsprechung); Groß-/Kleinschreibung egal */
function immobilien_openimmo_in(string $field, string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    foreach (IMMOBILIEN_OI_MAP[$field] ?? [] as $k => $v) if (strcasecmp($v, $value) === 0) return (string) $k;
    foreach (IMMOBILIEN_OI_ALIAS[$field] ?? [] as $k => $v) if (strcasecmp((string) $k, $value) === 0) return $v;
    return '';
}

/** Objekttyp → [Attribut, OpenImmo-Wert] für die Objektart, sonst null */
function immobilien_openimmo_typ_out(string $art, string $typ): ?array
{
    $def = IMMOBILIEN_OI_OBJEKTTYP[$art] ?? null;
    return $def && isset($def[1][$typ]) ? [$def[0], $def[1][$typ]] : null;
}

/** OpenImmo-Untertyp (Attributwert) → Objekttyp ('' = keiner) */
function immobilien_openimmo_typ_in(string $art, string $value): string
{
    $value = strtoupper(trim($value));
    if ($value === '' || $value === 'KEINE_ANGABE') return '';
    foreach (IMMOBILIEN_OI_OBJEKTTYP[$art][1] ?? [] as $k => $v) if ($v === $value) return $k;
    foreach (IMMOBILIEN_OI_OBJEKTTYP as $def) foreach ($def[1] as $k => $v) if ($v === $value) return $k;
    return IMMOBILIEN_OI_ALIAS['objekttyp'][$value] ?? '';
}

// ================================================================== Hilfen

/** Tabellen [immobilien, team] – wie die Blöcke des Kits (immo_table), sonst Standard-Kurznamen */
function immobilien_openimmo_tables(): array
{
    if (function_exists('immo_table')) return [immo_table('objects'), immo_table('team')];
    return [Tables::findContent('immobilien'), Tables::findContent('team')];
}

/** Kindelement über einen Pfad „a/b/c“ (Namen ohne Namensraum, Kleinschreibung) */
function immobilien_openimmo_el(?DOMElement $el, string $path): ?DOMElement
{
    if (!$el) return null;
    foreach (explode('/', $path) as $name) {
        $found = null;
        foreach ($el->childNodes as $c) {
            if ($c instanceof DOMElement && strtolower($c->localName) === $name) { $found = $c; break; }
        }
        if (!$found) return null;
        $el = $found;
    }
    return $el;
}

/** Alle direkten Kindelemente (optional nur mit diesem Namen) */
function immobilien_openimmo_els(?DOMElement $el, ?string $name = null): array
{
    $out = [];
    if (!$el) return $out;
    foreach ($el->childNodes as $c) {
        if ($c instanceof DOMElement && ($name === null || strtolower($c->localName) === $name)) $out[] = $c;
    }
    return $out;
}

/** Text eines Elements (getrimmt, '' wenn es fehlt) */
function immobilien_openimmo_txt(?DOMElement $el, string $path = ''): string
{
    $e = $path === '' ? $el : immobilien_openimmo_el($el, $path);
    return $e ? trim($e->textContent) : '';
}

/** Attribut (Name ohne Rücksicht auf Groß-/Kleinschreibung), null wenn es fehlt */
function immobilien_openimmo_attr(?DOMElement $el, string $name): ?string
{
    if (!$el) return null;
    foreach ($el->attributes as $a) if (strcasecmp($a->localName, $name) === 0) return trim($a->value);
    return null;
}

/** xsd:boolean (auch 1/0, ja/nein) – null, wenn leer oder unbekannt */
function immobilien_openimmo_bool(?string $v): ?bool
{
    $v = strtolower(trim((string) $v));
    return match ($v) { 'true', '1', 'ja', 'yes', 'j', 'y' => true, 'false', '0', 'nein', 'no', 'n' => false, default => null };
}

/** Zahl aus OpenImmo-Text (Punkt als Dezimalzeichen; deutsche Schreibweise wird erkannt) – null, wenn keine Zahl */
function immobilien_openimmo_number(string $s): ?float
{
    $s = trim(preg_replace('~[^\d,.\-]~', '', $s) ?? '');
    if ($s === '' || $s === '-') return null;
    if (str_contains($s, ',') && str_contains($s, '.')) {
        $s = strrpos($s, ',') > strrpos($s, '.') ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
    } elseif (str_contains($s, ',')) {
        $s = str_replace(',', '.', $s);
    } elseif (substr_count($s, '.') > 1) {
        $s = str_replace('.', '', $s);
    }
    return is_numeric($s) ? (float) $s : null;
}

/** Zahl für das XML: Punkt, ohne überflüssige Nachkommastellen */
function immobilien_openimmo_fmt(mixed $v, bool $int = false): ?string
{
    if ($v === null || $v === '' || !is_numeric($v)) return null;
    if ($int) return (string) (int) round((float) $v);
    $s = rtrim(rtrim(sprintf('%.2F', (float) $v), '0'), '.');
    return $s === '-0' ? '0' : $s;
}

/** Zahl als Text für Textfelder („1500“, „2.5“) */
function immobilien_openimmo_numtext(?float $v): string
{
    return $v === null ? '' : (string) immobilien_openimmo_fmt($v);
}

/** Datum für das Feld „date“ (JJJJ-MM-TT) aus üblichen Schreibweisen, sonst '' */
function immobilien_openimmo_date(string $s): string
{
    $s = trim($s);
    if ($s === '') return '';
    if (preg_match('~^(\d{4})-(\d{2})-(\d{2})~', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return "$m[1]-$m[2]-$m[3]";
    if (preg_match('~^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$~', $s, $m)) {
        $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
        if (checkdate((int) $m[2], (int) $m[1], $y)) return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
    }
    if (preg_match('~^(\d{1,2})[./](\d{4})$~', $s, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
        return date('Y-m-t', mktime(0, 0, 0, (int) $m[1], 1, (int) $m[2]));   // „05/2031“ → Monatsende
    }
    return '';
}

/** Für XML unzulässige Steuerzeichen entfernen */
function immobilien_openimmo_clean(string $s): string
{
    return (string) preg_replace('~[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]~u', '', $s);
}

/** Formatierter Text → reiner Text (Absätze als Leerzeile, Aufzählungen als „- “) */
function immobilien_openimmo_html_text(string $html): string
{
    if (trim($html) === '') return '';
    $h = preg_replace('~<li\b[^>]*>~i', "\n- ", $html);
    $h = preg_replace('~<br\s*/?>~i', "\n", (string) $h);
    $h = preg_replace('~</(p|h[1-6]|ul|ol|div|blockquote)>~i', "\n\n", (string) $h);
    $t = html_entity_decode(strip_tags((string) $h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("\u{00A0}", ' ', $t);
    $t = preg_replace("~[ \t]+\n~", "\n", $t);
    $t = preg_replace("~\n- ?\n~", "\n", (string) $t);
    return trim((string) preg_replace("~\n{3,}~", "\n\n", (string) $t));
}

/** Reiner Text (oder einfaches HTML aus der Quelle) → formatierter Text: Leerzeilen = Absätze, „- “/„• “-Zeilen = Aufzählung */
function immobilien_openimmo_text_html(string $text): string
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') return '';
    if (preg_match('~<(p|br|ul|ol|li|b|strong|em|i|h[1-6])\b~i', $text)) return \Core\Sanitizer::block($text);
    $out = [];
    foreach (preg_split("~\n\s*\n~", $text) as $para) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $para)), fn($l) => $l !== ''));
        if (!$lines) continue;
        $bullets = array_filter($lines, fn($l) => preg_match('~^[-•*–]\s+~u', $l));
        if (count($bullets) === count($lines)) {
            $out[] = '<ul>' . implode('', array_map(fn($l) => '<li>' . htmlspecialchars((string) preg_replace('~^[-•*–]\s+~u', '', $l), ENT_QUOTES, 'UTF-8') . '</li>', $lines)) . '</ul>';
        } else {
            $out[] = '<p>' . implode('<br>', array_map(fn($l) => htmlspecialchars($l, ENT_QUOTES, 'UTF-8'), $lines)) . '</p>';
        }
    }
    return implode('', $out);
}

/** Ordner rekursiv löschen (nur temporäre Arbeitsordner) */
function immobilien_openimmo_rmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

/** Temporärer Arbeitsordner im Datenordner der Website (open_basedir-sicher) */
function immobilien_openimmo_tmpdir(): string
{
    $dir = site()->storage('tmp') . '/openimmo-' . bin2hex(random_bytes(6));
    if (!@mkdir($dir, 0770, true) && !is_dir($dir)) throw new RuntimeException('Temporärer Ordner lässt sich nicht anlegen: ' . $dir);
    return $dir;
}

// ================================================================== Import

/**
 * ZIP sicher entpacken (kein Zip-Slip, keine Links, Größen- und Anzahl-Grenzen). Ordnerstruktur bleibt als Schlüssel erhalten,
 * gespeichert wird flach mit bereinigtem Namen. @return array{xml: list<string>, files: array<string,string>, base: array<string,string>}
 */
function immobilien_openimmo_unzip(string $zipFile, string $dir): array
{
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('Auf dem Server fehlt die PHP-Erweiterung zip.');
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::RDONLY) !== true) throw new RuntimeException('Die ZIP-Datei lässt sich nicht öffnen.');
    $lim = IMMOBILIEN_OI_LIMITS;
    $out = ['xml' => [], 'files' => [], 'base' => []];
    try {
        if ($zip->numFiles > $lim['zip_files']) throw new RuntimeException("Die ZIP-Datei enthält zu viele Dateien (max. {$lim['zip_files']}).");
        $total = 0;
        for ($n = 0; $n < $zip->numFiles; $n++) {
            $st = $zip->statIndex($n);
            if (!$st) continue;
            $name = (string) $st['name'];
            if (str_ends_with($name, '/')) continue;
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\') || preg_match('~^[a-z]:~i', $name) || preg_match('~[\x00-\x1f]~', $name)) {
                throw new RuntimeException('Die ZIP-Datei enthält einen unzulässigen Pfad (' . mb_substr($name, 0, 80) . ').');
            }
            if ($zip->getExternalAttributesIndex($n, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) continue;   // Symlink
            $base = basename($name);
            if ($base === '' || str_starts_with($base, '.') || str_starts_with($name, '__MACOSX/')) continue;
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (!in_array($ext, ['xml', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'], true)) continue;
            $size = (int) $st['size'];
            $total += $size;
            if ($size > $lim['file_bytes'] || $total > $lim['zip_bytes'] || ($st['comp_size'] > 0 && $size > 10 * 1024 * 1024 && $size / max(1, (int) $st['comp_size']) > 200)) {
                throw new RuntimeException('Die ZIP-Datei ist entpackt zu groß (max. ' . (int) ($lim['zip_bytes'] / 1048576) . ' MB, je Datei ' . (int) ($lim['file_bytes'] / 1048576) . ' MB).');
            }
            $dest = $dir . '/' . sprintf('%04d_', $n) . preg_replace('~[^\w.\-]+~u', '_', $base);
            $in = $zip->getStream($name);
            if (!$in) continue;
            $fh = fopen($dest, 'wb');
            $written = 0;
            while (!feof($in)) {
                $chunk = (string) fread($in, 65536);
                $written += strlen($chunk);
                if ($written > $size + 1024 || $written > $lim['file_bytes']) { fclose($in); fclose($fh); @unlink($dest); throw new RuntimeException('Eine Datei in der ZIP-Datei ist zu groß.'); }
                fwrite($fh, $chunk);
            }
            fclose($in);
            fclose($fh);
            if ($ext === 'xml') { $out['xml'][$name] = $dest; continue; }
            $out['files'][mb_strtolower($name)] = $dest;
            $out['base'][mb_strtolower($base)] ??= $dest;
        }
    } finally {
        $zip->close();
    }
    ksort($out['xml']);
    $out['xml'] = array_values($out['xml']);
    if (!$out['xml']) throw new RuntimeException('In der ZIP-Datei fehlt die OpenImmo-XML-Datei.');
    return $out;
}

/** XML sicher laden (keine Entities, kein Netz); falsch deklariertes Latin-1 wird erkannt */
function immobilien_openimmo_load(string $file): DOMDocument
{
    $body = (string) file_get_contents($file);
    if (str_starts_with($body, "\xEF\xBB\xBF")) $body = substr($body, 3);
    $parse = fn(string $b) => \Core\Sources\Parser::xmlDoc($b);
    try {
        $doc = $parse($body);
    } catch (\Core\Sources\SourceException $e) {
        // Häufig: Deklaration UTF-8, Inhalt Windows-1252 → umwandeln und erneut versuchen
        if (mb_check_encoding($body, 'UTF-8')) throw new RuntimeException(basename($file) . ': ' . $e->getMessage());
        $conv = (string) mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
        $conv = (string) preg_replace('~^(<\?xml[^>]*encoding=["\'])[^"\']+~i', '${1}UTF-8', $conv);
        try { $doc = $parse($conv); } catch (\Core\Sources\SourceException $e2) { throw new RuntimeException(basename($file) . ': ' . $e2->getMessage()); }
    }
    if (strtolower($doc->documentElement->localName) !== 'openimmo') {
        throw new RuntimeException(basename($file) . ': keine OpenImmo-Datei (Wurzelelement „' . $doc->documentElement->nodeName . '“ statt „openimmo“).');
    }
    return $doc;
}

/** Zuordnung Quelldatei (MD5) → Medien-ID, damit ein erneuter Import keine Bilder doppelt anlegt */
function immobilien_openimmo_media_map(?array $set = null): array
{
    static $map = null;
    $file = site()->storage('openimmo') . '/media-map.json';
    if ($map === null) $map = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    if ($set !== null) {
        $map = $set + $map;
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0770, true);
        @file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT), LOCK_EX);
    }
    return $map;
}

/** Anhang-Datei finden: ZIP (Pfad, sonst Dateiname) bzw. relativ zur XML-Datei (nie außerhalb ihres Ordners) */
function immobilien_openimmo_locate(string $pfad, array $ctx): ?string
{
    $pfad = str_replace('\\', '/', trim($pfad));
    if ($pfad === '' || str_contains($pfad, "\0")) return null;
    if (isset($ctx['zip'])) {
        $key = mb_strtolower(ltrim($pfad, './'));
        return $ctx['zip']['files'][$key] ?? $ctx['zip']['base'][mb_strtolower(basename($pfad))] ?? null;
    }
    $base = realpath($ctx['dir']);
    if ($base === false) return null;
    foreach ([$pfad, basename($pfad)] as $rel) {
        $real = realpath($base . '/' . ltrim($rel, '/'));
        if ($real !== false && is_file($real) && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) return $real;
    }
    return null;
}

/**
 * Anhang als Medium übernehmen. @return int|null Medien-ID; im Probelauf -1, wenn die Datei verfügbar wäre
 */
function immobilien_openimmo_media(array $a, array $ctx, array $opt, string $alt, string $title, array &$sum): ?int
{
    $label = $a['pfad'] !== '' ? $a['pfad'] : ($a['titel'] ?: 'Anhang');
    $tmp = null;
    $file = null;
    $name = basename((string) (parse_url($a['pfad'], PHP_URL_PATH) ?: $a['pfad'])) ?: 'anhang';
    if ($a['location'] === 'REMOTE' || preg_match('~^https?://~i', $a['pfad'])) {
        if (empty($opt['allow_remote'])) {
            $sum['warnings'][] = "{$ctx['obj']}: Anhang „{$label}“ liegt auf einem fremden Server – nicht geladen (mit --remote erlauben).";
            return null;
        }
        if ($opt['dry']) return -1;
        try {
            $r = \Core\Sources\Fetcher::get($a['pfad'], ['timeout' => IMMOBILIEN_OI_LIMITS['remote_timeout'], 'max' => min(Media::maxBytes(), IMMOBILIEN_OI_LIMITS['remote_bytes']), 'kind' => 'file']);
        } catch (Throwable $e) {
            $sum['warnings'][] = "{$ctx['obj']}: Anhang „{$label}“ nicht geladen – " . $e->getMessage();
            return null;
        }
        $tmp = $file = $ctx['tmp'] . '/remote-' . bin2hex(random_bytes(4)) . '-' . preg_replace('~[^\w.\-]+~', '_', $name);
        file_put_contents($file, $r['body']);
    } elseif ($a['location'] === 'INTERN') {
        $raw = base64_decode(preg_replace('~\s+~', '', $a['inhalt']) ?? '', true);
        if ($raw === false || $raw === '' || strlen($raw) > IMMOBILIEN_OI_LIMITS['file_bytes']) {
            $sum['warnings'][] = "{$ctx['obj']}: eingebetteter Anhang „{$label}“ ist leer oder ungültig.";
            return null;
        }
        if ($opt['dry']) return -1;
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'][strtolower($a['format'])] ?? (strtolower($a['format']) ?: 'bin');
        $tmp = $file = $ctx['tmp'] . '/intern-' . bin2hex(random_bytes(4)) . '.' . preg_replace('~\W~', '', $ext);
        $name = $a['titel'] !== '' ? $a['titel'] . '.' . $ext : basename($file);
        file_put_contents($file, $raw);
    } else {
        $file = immobilien_openimmo_locate($a['pfad'], $ctx);
        if ($file === null) {
            $sum['warnings'][] = "{$ctx['obj']}: Anhang „{$label}“ nicht gefunden – übersprungen.";
            return null;
        }
        if ($opt['dry']) return -1;
    }
    try {
        $hash = md5_file($file) ?: '';
        $known = immobilien_openimmo_media_map()[$hash] ?? null;
        if ($known && Media::find((int) $known)) return (int) $known;
        [$m, $err] = Media::import($file, $name, $alt, ['title' => $title, 'tags' => 'openimmo', 'require_alt' => false]);
        if ($err || !$m) {
            $sum['warnings'][] = "{$ctx['obj']}: Anhang „{$label}“ nicht übernommen – " . ($err ?: 'unbekannter Fehler');
            return null;
        }
        immobilien_openimmo_media_map([$hash => (int) $m['id']]);
        $sum['media']++;
        return (int) $m['id'];
    } finally {
        if ($tmp) @unlink($tmp);
    }
}

/** Ansprechpartner (kontaktperson) in der Tabelle „team“ finden (E-Mail, sonst Name) oder anlegen */
function immobilien_openimmo_team(?array $team, array $k, array $opt, array &$sum, string $obj): ?int
{
    $name = trim($k['vorname'] . ' ' . $k['name']);
    $email = mb_strtolower(trim($k['email']));
    if (!$team || ($name === '' && $email === '')) return null;
    $db = Tables::db($team);
    $id = null;
    if ($email !== '' && Tables::field($team, 'email')) $id = $db->fetchValue("SELECT id FROM {$team['table']} WHERE LOWER(email) = ? ORDER BY id", [$email]);
    $tf = $team['settings']['title_field'] ?: 'name';
    if (!$id && $name !== '') $id = $db->fetchValue("SELECT id FROM {$team['table']} WHERE $tf = ? ORDER BY id", [$name]);
    if ($id) return (int) $id;
    if ($name === '') $name = $k['firma'] ?: explode('@', $email)[0];
    if ($opt['dry']) {
        $sum['warnings'][] = "{$obj}: Ansprechpartner „{$name}“ würde im Team angelegt.";
        return null;
    }
    $in = [$tf => $name, 'status' => 'published'];
    if (Tables::field($team, 'email') && filter_var($email, FILTER_VALIDATE_EMAIL)) $in['email'] = $email;
    if (Tables::field($team, 'telefon') && preg_match('~^[+\d][\d\s/()\-–.]{3,}$~u', $k['tel'])) $in['telefon'] = $k['tel'];
    if (Tables::field($team, 'position') && $k['position'] !== '') $in['position'] = $k['position'];
    if (Tables::field($team, 'bereich')) $in['bereich'] = 'makler';
    [$newId, $errors] = Entries::save($team, null, $in, 'OpenImmo-Import');
    if ($errors) {
        $sum['warnings'][] = "{$obj}: Ansprechpartner „{$name}“ nicht angelegt – " . implode(' ', $errors);
        return null;
    }
    $sum['team_created']++;
    return (int) $newId;
}

/**
 * <immobilie> → Feldwerte (ohne Medien und Ansprechpartner) plus Metadaten (Schlüssel, Löschen, Kontakt, Anhänge).
 * Alle zugeordneten Felder sind gesetzt (fehlende Angaben = leer), damit ein Abgleich auch Entferntes übernimmt.
 */
function immobilien_openimmo_read(DOMElement $im): array
{
    $el = fn(string $p) => immobilien_openimmo_el($im, $p);
    $txt = fn(string $p) => immobilien_openimmo_txt($im, $p);
    $num = fn(string $p) => immobilien_openimmo_number($txt($p));
    $warn = [];
    $d = [];

    // Objektkategorie
    $kat = $el('objektkategorie');
    $d['nutzungsart'] = '';
    foreach (IMMOBILIEN_OI_MAP['nutzungsart'] as $key => $attr) {
        if (immobilien_openimmo_bool(immobilien_openimmo_attr(immobilien_openimmo_el($kat, 'nutzungsart'), $attr))) { $d['nutzungsart'] = $key; break; }
    }
    $vm = immobilien_openimmo_el($kat, 'vermarktungsart');
    $d['vermarktung'] = '';
    foreach (['KAUF', 'MIETE_PACHT', 'ERBPACHT', 'LEASING'] as $attr) {
        if (immobilien_openimmo_bool(immobilien_openimmo_attr($vm, $attr))) { $d['vermarktung'] = immobilien_openimmo_in('vermarktung', $attr); break; }
    }
    if ($d['vermarktung'] === '') {
        $d['vermarktung'] = $txt('preise/kaufpreis') !== '' ? 'kauf' : 'miete';
        $warn[] = 'Vermarktungsart fehlt – angenommen: ' . $d['vermarktung'] . '.';
    }
    $d['objektart'] = $d['objekttyp'] = '';
    foreach (immobilien_openimmo_els(immobilien_openimmo_el($kat, 'objektart')) as $art) {
        $name = strtolower($art->localName);
        if ($name === 'objektart_zusatz') continue;
        $d['objektart'] = immobilien_openimmo_in('objektart', $name) ?: 'sonstige';
        if (!isset(IMMOBILIEN_OI_MAP['objektart'][$name]) && !isset(IMMOBILIEN_OI_ALIAS['objektart'][$name])) $warn[] = "Objektart „{$name}“ unbekannt – als „Sonstiges“ übernommen.";
        foreach ($art->attributes as $a) {
            if (str_ends_with(strtolower($a->localName), 'typ')) { $d['objekttyp'] = immobilien_openimmo_typ_in($d['objektart'], $a->value); break; }
        }
        break;
    }
    if ($d['objektart'] === '') { $d['objektart'] = 'sonstige'; $warn[] = 'Objektart fehlt – als „Sonstiges“ übernommen.'; }

    // Einfache Felder (Lage, Preise, Flächen, Zustand, Verwaltung)
    foreach (IMMOBILIEN_OI_FIELDS as $field => [$path, $kind]) {
        $d[$field] = match ($kind) {
            'num' => $num($path),
            'int' => ($n = $num($path)) === null ? null : (int) round($n),
            default => $txt($path),
        };
    }
    $lat = immobilien_openimmo_attr($el('geo/geokoordinaten'), 'breitengrad');
    $lng = immobilien_openimmo_attr($el('geo/geokoordinaten'), 'laengengrad');
    $p = $lat !== null && $lng !== null ? \Core\Maps::parse(['lat' => immobilien_openimmo_number($lat), 'lng' => immobilien_openimmo_number($lng)]) : null;
    $d['geo'] = $p ? \Core\Maps::format($p) : '';
    if (($lat !== null || $lng !== null) && !$p) $warn[] = 'Geokoordinaten ungültig – nicht übernommen.';
    $d['adresse_zeigen'] = (bool) immobilien_openimmo_bool($txt('verwaltung_objekt/objektadresse_freigeben'));
    if ($d['verfuegbar_ab'] === '' && ($ab = immobilien_openimmo_date($txt('verwaltung_objekt/abdatum'))) !== '') $d['verfuegbar_ab'] = date('d.m.Y', (int) strtotime($ab));

    // Preise
    $kp = $el('preise/kaufpreis');
    $d['preis_auf_anfrage'] = (bool) immobilien_openimmo_bool(immobilien_openimmo_attr($kp, 'auf_anfrage'));
    $d['kaufpreis'] = ($v = $num('preise/kaufpreis')) !== null && $v > 0 ? $v : null;
    $d['kaltmiete'] = $num('preise/nettokaltmiete') ?? $num('preise/kaltmiete');
    $d['heizkosten_enthalten'] = (bool) immobilien_openimmo_bool($txt('preise/heizkosten_enthalten'));
    $d['provisionspflichtig'] = (bool) immobilien_openimmo_bool($txt('preise/provisionspflichtig'));
    $d['kaution'] = $txt('preise/kaution_text') ?: immobilien_openimmo_numtext($num('preise/kaution'));
    if ($d['provision'] !== '' && immobilien_openimmo_bool(immobilien_openimmo_attr($el('preise/aussen_courtage'), 'mit_mwst')) && !preg_match('~mwst|ust~i', $d['provision'])) {
        $d['provision'] .= ' inkl. MwSt.';
    }

    // Ausstattung (Mehrfachauswahl) aus Flächen, Ausstattung und Verwaltung
    $aus = [];
    foreach (IMMOBILIEN_OI_AUSSTATTUNG as $key => $spec) {
        [$sec, $name, $kind] = $spec;
        $hit = false;
        foreach (immobilien_openimmo_els($el($sec), $name) as $node) {
            $hit = $hit || match ($kind) {
                'bool' => (bool) immobilien_openimmo_bool($node->textContent),
                'count' => (immobilien_openimmo_number($node->textContent) ?? 0) > 0,
                'attr' => (bool) immobilien_openimmo_bool(immobilien_openimmo_attr($node, $spec[3])),
                'enum' => $key === 'glasfaser' ? (bool) preg_match('~glas|ftt[hb]~i', (string) immobilien_openimmo_attr($node, $spec[3]))
                    : in_array(strtoupper((string) immobilien_openimmo_attr($node, $spec[3])), [$spec[4], 'TEIL'], true),
                default => false,
            };
        }
        if ($hit) $aus[] = $key;
    }
    $sec = $el('ausstattung');
    if (immobilien_openimmo_bool($txt('ausstattung/rollstuhlgerecht'))) $aus[] = 'barrierefrei';
    foreach (immobilien_openimmo_els($sec, 'fahrstuhl') as $f) {
        if (immobilien_openimmo_bool(immobilien_openimmo_attr($f, 'LASTEN')) || ($f->attributes->length === 0 && immobilien_openimmo_bool($f->textContent))) $aus[] = 'aufzug';
    }
    foreach (immobilien_openimmo_els($sec, 'stellplatzart') as $s) {
        if (immobilien_openimmo_bool(immobilien_openimmo_attr($s, 'DUPLEX'))) $aus[] = 'garage';
        if (immobilien_openimmo_bool(immobilien_openimmo_attr($s, 'CARPORT')) || immobilien_openimmo_bool(immobilien_openimmo_attr($s, 'PARKHAUS'))) $aus[] = 'stellplatz';
    }
    foreach (['stp_garage' => 'garage', 'stp_duplex' => 'garage', 'stp_tiefgarage' => 'tiefgarage', 'stp_freiplatz' => 'stellplatz', 'stp_carport' => 'stellplatz', 'stp_parkhaus' => 'stellplatz'] as $stp => $key) {
        if ($el('preise/' . $stp)) $aus[] = $key;
    }
    $d['ausstattung'] = array_values(array_intersect(array_keys(immobilien_options('ausstattung')), $aus));   // Reihenfolge wie in der Auswahl
    $d['ausstatt_kategorie'] = immobilien_openimmo_in('ausstatt_kategorie', $txt('ausstattung/ausstatt_kategorie'));
    foreach (['heizungsart', 'befeuerung'] as $multi) {
        $vals = [];
        foreach (immobilien_openimmo_els($sec, $multi) as $node) {
            foreach ($node->attributes as $a) if (immobilien_openimmo_bool($a->value) && ($k = immobilien_openimmo_in($multi, $a->localName)) !== '') $vals[] = $k;
        }
        $d[$multi] = array_values(array_unique($vals));
    }

    // Zustand, Status, Energieausweis
    $d['zustand'] = immobilien_openimmo_in('zustand', (string) immobilien_openimmo_attr($el('zustand_angaben/zustand'), 'zustand_art'));
    $d['stand'] = immobilien_openimmo_in('stand', (string) immobilien_openimmo_attr($el('zustand_angaben/verkaufstatus'), 'stand')) ?: 'offen';
    $ep = $el('zustand_angaben/energiepass');
    $epart = strtoupper(immobilien_openimmo_txt($ep, 'epart'));
    $jahrgang = strtolower(immobilien_openimmo_txt($ep, 'jahrgang'));
    $d['energieausweis'] = immobilien_openimmo_in('energieausweis', $epart)
        ?: (in_array($jahrgang, ['ohne', 'nicht_noetig'], true) ? 'ohne' : ($jahrgang === 'bei_besichtigung' ? 'folgt' : ''));
    $bedarf = immobilien_openimmo_number(immobilien_openimmo_txt($ep, 'endenergiebedarf'));
    $verbrauch = immobilien_openimmo_number(immobilien_openimmo_txt($ep, 'energieverbrauchkennwert'));
    $d['energie_kennwert'] = $epart === 'VERBRAUCH' ? ($verbrauch ?? $bedarf) : ($bedarf ?? $verbrauch);
    $d['energie_klasse'] = immobilien_openimmo_in('energie_klasse', immobilien_openimmo_txt($ep, 'wertklasse'));
    if ($d['energietraeger'] !== '' && preg_match('~^[A-Z_\-]+$~', $d['energietraeger'])) {
        $codes = array_flip(IMMOBILIEN_OI_MAP['befeuerung']) + ['KOHLE' => 'Kohle', 'FLUESSIGGAS' => 'Flüssiggas', 'WASSER-ELEKTRO' => 'Strom'];
        $key = $codes[$d['energietraeger']] ?? null;
        if ($key !== null) $d['energietraeger'] = immobilien_options('befeuerung')[$key] ?? $key;
    }
    $gb = immobilien_openimmo_txt($ep, 'gueltig_bis');
    $d['energie_gueltig_bis'] = immobilien_openimmo_date($gb);
    if ($gb !== '' && $d['energie_gueltig_bis'] === '') $warn[] = "Energieausweis „gültig bis“ ({$gb}) nicht lesbar – nicht übernommen.";

    // Freitexte
    foreach (IMMOBILIEN_OI_TEXTS as $field => $node) {
        $t = $txt('freitexte/' . $node);
        $d[$field] = in_array($field, ['titel', 'kurztext'], true) ? trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $t)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : immobilien_openimmo_text_html($t);
    }
    if ($d['titel'] === '') {
        $d['titel'] = trim((immobilien_options('objektart')[$d['objektart']] ?? 'Immobilie') . ($d['ort'] !== '' ? ' in ' . $d['ort'] : ''));
        $warn[] = 'Objekttitel fehlt – „' . $d['titel'] . '“ verwendet.';
    }
    $d['titel'] = mb_substr(preg_replace('~\s+~u', ' ', $d['titel']) ?? '', 0, 250);

    // Verwaltung (technisch)
    $d['objektnr'] = $txt('verwaltung_techn/objektnr_extern') ?: $txt('verwaltung_techn/objektnr_intern');
    $d['openimmo_id'] = $txt('verwaltung_techn/openimmo_obid');
    $delete = strtoupper((string) immobilien_openimmo_attr($el('verwaltung_techn/aktion'), 'aktionart')) === 'DELETE';

    // Anhänge
    $anh = [];
    foreach (immobilien_openimmo_els($el('anhaenge'), 'anhang') as $a) {
        $anh[] = ['location' => strtoupper((string) immobilien_openimmo_attr($a, 'location')), 'gruppe' => strtoupper((string) immobilien_openimmo_attr($a, 'gruppe')),
            'titel' => immobilien_openimmo_txt($a, 'anhangtitel'), 'format' => immobilien_openimmo_txt($a, 'format'),
            'pfad' => immobilien_openimmo_txt($a, 'daten/pfad'), 'inhalt' => immobilien_openimmo_txt($a, 'daten/anhanginhalt')];
    }
    $d['video_url'] = $d['rundgang_url'] = '';
    foreach ($anh as $a) {
        if (!preg_match('~^https?://~i', $a['pfad'])) continue;
        $url = (string) preg_replace('~^http://~i', 'https://', $a['pfad']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
        if (in_array($a['gruppe'], ['FILMLINK', 'FILM'], true) && $d['video_url'] === '' && preg_match('~youtu\.?be|vimeo~i', $url)) $d['video_url'] = $url;
        elseif (in_array($a['gruppe'], ['LINKS', 'PANORAMA', 'FILMLINK'], true) && $d['rundgang_url'] === '' && !preg_match('~\.(jpe?g|png|webp|gif)$~i', $url)
            && preg_match('~rundgang|360|tour|matterport|ogulo|immoviewer|feelestate~i', $a['titel'] . ' ' . $url)) $d['rundgang_url'] = $url;
    }

    // Kontaktperson
    $kp = $el('kontaktperson');
    $kontakt = $kp ? ['name' => immobilien_openimmo_txt($kp, 'name'), 'vorname' => immobilien_openimmo_txt($kp, 'vorname'), 'position' => immobilien_openimmo_txt($kp, 'position'),
        'firma' => immobilien_openimmo_txt($kp, 'firma'), 'email' => immobilien_openimmo_txt($kp, 'email_direkt') ?: immobilien_openimmo_txt($kp, 'email_zentrale'),
        'tel' => immobilien_openimmo_txt($kp, 'tel_durchw') ?: immobilien_openimmo_txt($kp, 'tel_handy') ?: immobilien_openimmo_txt($kp, 'tel_zentrale')] : null;

    // Eigene Angaben aus einem Export von KLXM Studio (verlustfreie Rückübernahme)
    foreach (immobilien_openimmo_els($im, 'user_defined_simplefield') as $u) {
        $f = strtolower((string) immobilien_openimmo_attr($u, 'feldname'));
        $v = trim($u->textContent);
        if ($f === 'klxm_featured') $d['featured'] = (bool) immobilien_openimmo_bool($v);
        elseif ($f === 'klxm_preis_auf_anfrage') $d['preis_auf_anfrage'] = (bool) immobilien_openimmo_bool($v);
        elseif (in_array($f, ['klxm_objekttyp', 'klxm_zustand'], true) && isset(immobilien_options(substr($f, 5))[$v])) $d[substr($f, 5)] = $v;
    }
    return ['data' => $d, 'delete' => $delete, 'kontakt' => $kontakt, 'anhaenge' => $anh, 'warnings' => $warn];
}

/** Anhänge den Medienfeldern zuordnen: Titelbild, bild_1 … bild_N, Grundriss, Exposé. @return array [feld => id|null] (nur gesetzte Bereiche) */
function immobilien_openimmo_assign(array $t, array $anh, string $titel, array $ctx, array $opt, array &$sum): array
{
    $images = $plans = $docs = [];
    foreach ($anh as $i => $a) {
        if ($a['pfad'] === '' && $a['inhalt'] === '') continue;
        $isPdf = stripos($a['format'], 'pdf') !== false || preg_match('~\.pdf$~i', (string) parse_url($a['pfad'], PHP_URL_PATH));
        $isImg = !$isPdf && (preg_match('~jpe?g|png|webp|gif|image~i', $a['format']) || preg_match('~\.(jpe?g|png|webp|gif)$~i', (string) parse_url($a['pfad'], PHP_URL_PATH)));
        if ($a['gruppe'] === 'GRUNDRISS' && $isImg) $plans[] = $a;
        elseif ($isPdf && in_array($a['gruppe'], ['DOKUMENTE', ''], true)) $docs[] = $a;
        elseif ($isImg && in_array($a['gruppe'], IMMOBILIEN_OI_IMAGE_GROUPS, true)) $images[] = $a + ['_i' => $i];
    }
    // Titelbild zuerst; ohne Titelbild das erste Bild
    usort($images, fn($a, $b) => [$a['gruppe'] !== 'TITELBILD', $a['_i']] <=> [$b['gruppe'] !== 'TITELBILD', $b['_i']]);
    $slots = ['bild'];
    for ($i = 1; $i <= IMMOBILIEN_SLOTS; $i++) $slots[] = 'bild_' . $i;
    $slots = array_values(array_filter($slots, fn($s) => Tables::field($t, $s)));
    $out = [];
    if ($images) {
        foreach ($slots as $s) $out[$s] = null;
        $n = 0;
        foreach ($images as $a) {
            if ($n >= count($slots)) { $sum['warnings'][] = "{$ctx['obj']}: " . (count($images) - count($slots)) . ' weitere Bilder ohne freien Platz – übersprungen.'; break; }
            $alt = $a['titel'] !== '' ? $a['titel'] : $titel . ($n ? ' – Bild ' . ($n + 1) : '');
            $id = immobilien_openimmo_media($a, $ctx, $opt, $alt, $a['titel'] ?: $titel, $sum);
            if ($id !== null) $out[$slots[$n++]] = $id;
        }
        if ($n === 0) $out = [];   // nichts übernommen → bestehende Bilder behalten
    }
    if ($plans && Tables::field($t, 'grundriss')) {
        $id = immobilien_openimmo_media($plans[0], $ctx, $opt, $plans[0]['titel'] ?: 'Grundriss: ' . $titel, $plans[0]['titel'] ?: 'Grundriss', $sum);
        if ($id !== null) $out['grundriss'] = $id;
    }
    if ($docs && Tables::field($t, 'expose')) {
        $id = immobilien_openimmo_media($docs[0], $ctx, $opt, '', $docs[0]['titel'] ?: 'Exposé ' . $titel, $sum);
        if ($id !== null) $out['expose'] = $id;
    }
    return $out;
}

/**
 * OpenImmo-Datei (.xml oder .zip) importieren. Siehe Kopfkommentar.
 * @throws RuntimeException bei fehlender Tabelle, unlesbarer Datei oder ungültigem XML
 */
function immobilien_openimmo_import(string $path, array $opt = []): array
{
    $opt += ['dry' => false, 'update' => true, 'images' => true, 'allow_remote' => false, 'delete' => 'draft', 'status' => 'published', 'prune' => false, 'log' => null];
    $opt['dry'] = (bool) $opt['dry'];
    $log = is_callable($opt['log']) ? $opt['log'] : static fn(string $m) => null;
    $sum = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'pruned' => 0, 'media' => 0, 'team_created' => 0, 'objects' => [], 'warnings' => [], 'errors' => []];
    [$t, $team] = immobilien_openimmo_tables();
    if (!$t) throw new RuntimeException('Die Datentabelle „immobilien“ fehlt.');
    if (!is_file($path) || !is_readable($path)) throw new RuntimeException("Datei nicht gefunden: $path");

    $tmp = immobilien_openimmo_tmpdir();
    try {
        $head = (string) file_get_contents($path, false, null, 0, 4);
        if ($head === "PK\x03\x04" || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
            $zip = immobilien_openimmo_unzip($path, $tmp);
            $sources = array_map(fn($x) => ['xml' => $x, 'ctx' => ['zip' => $zip, 'tmp' => $tmp]], $zip['xml']);
        } else {
            $sources = [['xml' => $path, 'ctx' => ['dir' => dirname((string) realpath($path)), 'tmp' => $tmp]]];
        }
        $db = Tables::db($t);
        $seen = [];
        $full = false;
        foreach ($sources as $src) {
            $doc = immobilien_openimmo_load($src['xml']);
            $root = $doc->documentElement;
            $u = immobilien_openimmo_el($root, 'uebertragung');
            $full = $full || strtoupper((string) immobilien_openimmo_attr($u, 'umfang')) === 'VOLL';
            $ver = (string) immobilien_openimmo_attr($u, 'version');
            if ($ver !== '' && !str_starts_with($ver, '1.2')) $sum['warnings'][] = "OpenImmo-Version {$ver} – erwartet 1.2.x; Felder werden so weit wie möglich übernommen.";
            foreach (immobilien_openimmo_els($root, 'anbieter') as $anbieter) {
                foreach (immobilien_openimmo_els($anbieter, 'immobilie') as $im) {
                    $r = immobilien_openimmo_read($im);
                    $d = $r['data'];
                    $obj = ($d['objektnr'] !== '' ? $d['objektnr'] . ' ' : '') . '„' . mb_strimwidth($d['titel'], 0, 60, '…') . '“';
                    foreach ($r['warnings'] as $w) $sum['warnings'][] = "$obj: $w";
                    $ctx = $src['ctx'] + ['obj' => $obj];

                    // Vorhandenes Objekt: openimmo_id, sonst Objektnummer
                    $id = null;
                    if ($d['openimmo_id'] !== '' && Tables::field($t, 'openimmo_id')) $id = $db->fetchValue("SELECT id FROM {$t['table']} WHERE openimmo_id = ? ORDER BY id", [$d['openimmo_id']]);
                    if (!$id && $d['objektnr'] !== '' && Tables::field($t, 'objektnr')) $id = $db->fetchValue("SELECT id FROM {$t['table']} WHERE objektnr = ? ORDER BY id", [$d['objektnr']]);
                    $id = $id ? (int) $id : null;
                    if ($id) $seen[] = $id;
                    $report = function (string $action, ?int $eid = null) use (&$sum, $d): void {
                        $sum['objects'][] = ['action' => $action, 'id' => $eid, 'objektnr' => $d['objektnr'], 'titel' => $d['titel']];
                    };

                    if ($r['delete']) {
                        if (!$id || $opt['delete'] === 'ignore') { $sum['skipped']++; $report($id ? 'ignoriert (Löschung)' : 'übersprungen (Löschung, nicht vorhanden)', $id); continue; }
                        if (!$opt['dry']) $opt['delete'] === 'delete' ? Entries::delete($t, $id) : Entries::setStatus($t, [$id], 'draft');
                        $sum['deleted']++;
                        $report($opt['delete'] === 'delete' ? 'gelöscht' : 'auf Entwurf gesetzt', $id);
                        $log(($opt['dry'] ? '[Probelauf] ' : '') . "− $obj " . ($opt['delete'] === 'delete' ? 'gelöscht' : 'auf Entwurf gesetzt'));
                        continue;
                    }
                    if ($id && !$opt['update']) { $sum['skipped']++; $report('übersprungen (vorhanden)', $id); continue; }

                    if ($r['kontakt'] && Tables::field($t, 'ansprechpartner')) $d['ansprechpartner'] = immobilien_openimmo_team($team, $r['kontakt'], $opt, $sum, $obj);
                    if ($opt['images']) $d = immobilien_openimmo_assign($t, $r['anhaenge'], $d['titel'], $ctx, $opt, $sum) + $d;
                    // Nur Felder, die die Tabelle hat (eigene Tabellen dürfen Felder weglassen)
                    $d = array_filter($d, fn($k) => Tables::field($t, (string) $k) !== null, ARRAY_FILTER_USE_KEY);
                    if (!$id) $d['status'] = $opt['status'] === 'draft' ? 'draft' : 'published';

                    if ($opt['dry']) {
                        $check = array_map(fn($v) => $v === -1 ? null : $v, $d);
                        [, $errors] = \Core\Fields::sanitize(Entries::schema($t), $check);
                        if ($errors) { $sum['errors'][] = "$obj: " . implode(' ', $errors); $sum['skipped']++; $report('fehlerhaft'); continue; }
                        $id ? $sum['updated']++ : $sum['created']++;
                        $report($id ? 'würde aktualisiert' : 'würde angelegt', $id);
                        $log('[Probelauf] ' . ($id ? '~' : '+') . " $obj");
                        continue;
                    }
                    [$saved, $errors] = Entries::save($t, $id, $d, 'OpenImmo-Import');
                    if ($errors) {
                        $sum['errors'][] = "$obj: " . implode(' ', array_map(fn($k, $v) => "$k: $v", array_keys($errors), $errors));
                        $sum['skipped']++;
                        $report('fehlerhaft', $id);
                        continue;
                    }
                    $seen[] = (int) $saved;
                    $id ? $sum['updated']++ : $sum['created']++;
                    $report($id ? 'aktualisiert' : 'angelegt', (int) $saved);
                    $log(($id ? '~' : '+') . " $obj (#$saved)");
                }
            }
        }
        // Vollabgleich: veröffentlichte Objekte aus früheren Importen, die in der Datei fehlen → Entwurf
        if ($full && $opt['prune'] && Tables::field($t, 'openimmo_id')) {
            $seen = array_values(array_unique(array_map('intval', $seen)));
            $tf = $t['settings']['title_field'] ?: 'id';
            $rows = $db->fetchAll("SELECT id, $tf AS titel FROM {$t['table']} WHERE openimmo_id IS NOT NULL AND openimmo_id != '' AND status = 'published'"
                . ($seen ? ' AND id NOT IN (' . implode(',', $seen) . ')' : ''));
            foreach ($rows as $row) {
                if (!$opt['dry']) Entries::setStatus($t, [(int) $row['id']], 'draft');
                $sum['pruned']++;
                $sum['objects'][] = ['action' => 'nicht mehr im Bestand → Entwurf', 'id' => (int) $row['id'], 'objektnr' => '', 'titel' => (string) ($row['titel'] ?? '')];
            }
        }
    } finally {
        immobilien_openimmo_rmdir($tmp);
    }
    return $sum;
}

// ================================================================== Export

/** Element mit Text und Attributen (Text wird korrekt maskiert) */
function immobilien_openimmo_node(DOMDocument $d, string $name, ?string $text = null, array $attrs = []): DOMElement
{
    $e = $d->createElement($name);
    foreach ($attrs as $k => $v) $e->setAttribute((string) $k, immobilien_openimmo_clean((string) $v));
    if ($text !== null && $text !== '') $e->appendChild($d->createTextNode(immobilien_openimmo_clean($text)));
    return $e;
}

/** Abschnitt in XSD-Reihenfolge zusammensetzen: $kids [name => DOMElement|list<DOMElement>]; leer → null (außer $keep) */
function immobilien_openimmo_section(DOMDocument $d, string $name, array $kids, bool $keep = false, array $attrs = []): ?DOMElement
{
    $kids = array_filter($kids, fn($v) => $v !== null && $v !== []);
    if (!$kids && !$keep) return null;
    $e = immobilien_openimmo_node($d, $name, null, $attrs);
    $order = IMMOBILIEN_OI_ORDER[$name] ?? array_keys($kids);
    foreach (array_merge($order, array_diff(array_keys($kids), $order)) as $k) {
        foreach (is_array($kids[$k] ?? null) ? $kids[$k] : (isset($kids[$k]) ? [$kids[$k]] : []) as $node) $e->appendChild($node);
    }
    return $e;
}

/** Stabile OpenImmo-Objekt-ID für Einträge ohne openimmo_id */
function immobilien_openimmo_obid(array $e): string
{
    $ts = strtotime((string) ($e['created_at'] ?? '')) ?: 0;
    return 'OKLX' . date('YmdHis', $ts) . sprintf('%06d', (int) $e['id']) . strtoupper(substr(md5(site()->key), 0, 4));
}

/** <immobilie> eines Eintrags. $files: [zip-pfad => lokale datei] wird ergänzt (Bilder fürs ZIP) */
function immobilien_openimmo_object(DOMDocument $d, array $t, ?array $team, array $e, array $opt, array &$files, array &$warn): DOMElement
{
    $n = fn(string $name, ?string $text = null, array $a = []) => immobilien_openimmo_node($d, $name, $text, $a);
    $has = fn(string $f) => Tables::field($t, $f) !== null;
    $v = fn(string $f) => $e[$f] ?? null;
    $s = fn(string $f) => trim((string) ($e[$f] ?? ''));
    $b = fn(bool $x) => $x ? 'true' : 'false';
    $aus = array_map('strval', (array) ($e['ausstattung'] ?? []));
    $user = [];   // klxm_*: Angaben, die OpenImmo nicht genau abbildet
    $obj = '#' . $e['id'] . ' „' . mb_strimwidth($s('titel'), 0, 50, '…') . '“';

    // Objektkategorie
    $nutz = $s('nutzungsart') ?: 'wohnen';
    $nAttr = [];
    foreach (IMMOBILIEN_OI_MAP['nutzungsart'] as $k => $attr) $nAttr[$attr] = $b($nutz === $k);
    $verm = $s('vermarktung');
    $art = isset(IMMOBILIEN_OI_MAP['objektart'][$s('objektart')]) ? $s('objektart') : 'sonstige';
    $typ = $s('objekttyp');
    $typOut = $typ !== '' ? immobilien_openimmo_typ_out($art, $typ) : null;
    if ($typ !== '' && (!$typOut || immobilien_openimmo_typ_in($art, $typOut[1]) !== $typ)) $user['klxm_objekttyp'] = $typ;
    $kids = ['objektkategorie' => immobilien_openimmo_section($d, 'objektkategorie', [
        'nutzungsart' => $n('nutzungsart', null, $nAttr),
        'vermarktungsart' => $n('vermarktungsart', null, ['KAUF' => $b($verm === 'kauf'), 'MIETE_PACHT' => $b($verm === 'miete')]),
        'objektart' => immobilien_openimmo_section($d, 'objektart', [$art => $n($art, null, $typOut ? [$typOut[0] => $typOut[1]] : [])]),
    ])];

    // Lage – ohne Freigabe auf Wunsch ohne Straße, Hausnummer und Koordinaten
    $frei = (bool) $v('adresse_zeigen');
    $hide = !$frei && !empty($opt['hide_address']);
    $geo = [];
    foreach (['plz' => 'plz', 'ort' => 'ort', 'strasse' => 'strasse', 'hausnummer' => 'hausnummer', 'stadtteil' => 'regionaler_zusatz', 'etage' => 'etage', 'etagen' => 'anzahl_etagen'] as $f => $node) {
        if (!$has($f) || ($hide && in_array($f, ['strasse', 'hausnummer'], true))) continue;
        $val = IMMOBILIEN_OI_FIELDS[$f][1] === 'int' ? immobilien_openimmo_fmt($v($f), true) : $s($f);
        if ($val !== null && $val !== '') $geo[$node] = $n($node, $val);
    }
    $geo['plz'] ??= $n('plz');
    $geo['ort'] ??= $n('ort');
    if (!$hide && ($p = \Core\Maps::parse($s('geo')))) $geo['geokoordinaten'] = $n('geokoordinaten', null, ['breitengrad' => immobilien_openimmo_fmt6($p[0]), 'laengengrad' => immobilien_openimmo_fmt6($p[1])]);
    $geo['land'] = $n('land', null, ['iso_land' => 'DEU']);
    $kids['geo'] = immobilien_openimmo_section($d, 'geo', $geo, true);

    // Kontaktperson (Pflicht): Ansprechpartner aus „team“, sonst die Firma
    $p = $team && $v('ansprechpartner') ? Entries::find($team, (int) $v('ansprechpartner')) : null;
    $org = immobilien_openimmo_org();
    $full = trim((string) ($p[$team['settings']['title_field'] ?? 'name'] ?? $p['name'] ?? ''));
    $parts = $full !== '' ? preg_split('~\s+~u', $full) : [];
    $last = $parts ? array_pop($parts) : $org['firma'];
    $kp = [
        'email_zentrale' => $n('email_zentrale', $org['email']),
        'email_direkt' => ($m = trim((string) ($p['email'] ?? ''))) !== '' ? $n('email_direkt', $m) : null,
        'tel_zentrale' => $org['phone'] !== '' ? $n('tel_zentrale', $org['phone']) : null,
        'tel_durchw' => ($tel = trim((string) ($p['telefon'] ?? ''))) !== '' ? $n('tel_durchw', $tel) : null,
        'name' => $n('name', $last),
        'vorname' => $parts ? $n('vorname', implode(' ', $parts)) : null,
        'position' => ($pos = trim((string) ($p['position'] ?? ''))) !== '' ? $n('position', $pos) : null,
        'firma' => $org['firma'] !== '' ? $n('firma', $org['firma']) : null,
    ];
    if ($org['email'] === '' && $kp['email_direkt'] !== null) $kp['email_zentrale'] = null;   // Auswahl: eine Kontaktangabe genügt
    $kids['kontaktperson'] = immobilien_openimmo_section($d, 'kontaktperson', $kp, true);

    // Preise
    $pr = [];
    $anfrage = (bool) $v('preis_auf_anfrage');
    $kpv = immobilien_openimmo_fmt($v('kaufpreis'));
    if ($verm === 'kauf' && ($kpv !== null || $anfrage)) {
        // „Auf Anfrage“: laut XSD Wert 0 mit auf_anfrage="true"
        $pr['kaufpreis'] = $n('kaufpreis', $anfrage && $kpv === null ? '0' : $kpv, $anfrage ? ['auf_anfrage' => 'true'] : []);
    } elseif ($kpv !== null) {
        $pr['kaufpreis'] = $n('kaufpreis', $kpv);
    }
    if ($anfrage && $verm !== 'kauf') $user['klxm_preis_auf_anfrage'] = 'true';
    // Kaltmiete = Nettokaltmiete; bei Miete zusätzlich als <kaltmiete> (viele Portale lesen nur dieses Element)
    if (($x = immobilien_openimmo_fmt($v('kaltmiete'))) !== null) {
        $pr['nettokaltmiete'] = $n('nettokaltmiete', $x);
        if ($verm === 'miete') $pr['kaltmiete'] = $n('kaltmiete', $x);
    }
    foreach (['warmmiete', 'nebenkosten', 'hausgeld'] as $f) if (($x = immobilien_openimmo_fmt($v($f))) !== null) $pr[$f] = $n($f, $x);
    if ($verm === 'miete' && $has('heizkosten_enthalten')) $pr['heizkosten_enthalten'] = $n('heizkosten_enthalten', $b((bool) $v('heizkosten_enthalten')));
    if ($has('provisionspflichtig')) $pr['provisionspflichtig'] = $n('provisionspflichtig', $b((bool) $v('provisionspflichtig')));
    if ($s('provision') !== '') $pr['aussen_courtage'] = $n('aussen_courtage', $s('provision'));
    $pr['waehrung'] = $n('waehrung', null, ['iso_waehrung' => 'EUR']);
    $kaution = $s('kaution');
    if ($kaution !== '') {
        // Nur eindeutige Beträge als Zahl („1500“, „1500,50“) – „1.500“ oder „3 Kaltmieten“ bleiben Text
        if (preg_match('~^\d+([.,]\d{1,2})?$~', $kaution)) $pr['kaution'] = $n('kaution', (string) immobilien_openimmo_fmt(str_replace(',', '.', $kaution)));
        else $pr['kaution_text'] = $n('kaution_text', $kaution);
    }
    $kids['preise'] = immobilien_openimmo_section($d, 'preise', $pr);

    // Flächen
    $fl = [];
    foreach (['wohnflaeche', 'nutzflaeche', 'grundstuecksflaeche', 'zimmer', 'schlafzimmer', 'badezimmer', 'stellplaetze'] as $f) {
        [$path, $kind] = IMMOBILIEN_OI_FIELDS[$f];
        $x = immobilien_openimmo_fmt($v($f), $kind === 'int');
        if ($x !== null && !($f === 'stellplaetze' && (int) $x < 1)) $fl[basename($path)] = $n(basename($path), $x);
    }

    // Ausstattung (Mehrfachauswahl → Elemente/Attribute) und Verwaltung
    $au = [];
    $vo = [];
    $stp = [];
    foreach (IMMOBILIEN_OI_AUSSTATTUNG as $key => $spec) {
        if (!in_array($key, $aus, true)) continue;
        [$sec, $name, $kind] = $spec;
        if ($kind === 'attr' && $name === 'stellplatzart') { $stp[$spec[3]] = 'true'; continue; }
        $node = match ($kind) {
            'bool' => $n($name, 'true'),
            'count' => $n($name, '1'),
            'attr' => $n($name, null, [$spec[3] => 'true']),
            'enum' => $n($name, null, [$spec[3] => $spec[4]]),
        };
        if ($sec === 'flaechen') $fl[$name] = $node; elseif ($sec === 'verwaltung_objekt') $vo[$name] = $node; else $au[$name] = $node;
    }
    if ($stp) $au['stellplatzart'] = $n('stellplatzart', null, $stp);
    $kids['flaechen'] = immobilien_openimmo_section($d, 'flaechen', $fl);
    if (($x = immobilien_openimmo_out('ausstatt_kategorie', $s('ausstatt_kategorie'))) !== null) $au['ausstatt_kategorie'] = $n('ausstatt_kategorie', $x);
    foreach (['heizungsart', 'befeuerung'] as $multi) {
        $attrs = [];
        foreach ((array) ($e[$multi] ?? []) as $k) if (($x = immobilien_openimmo_out($multi, (string) $k)) !== null) $attrs[$x] = 'true';
        if ($attrs) $au[$multi] = $n($multi, null, $attrs);
    }
    $kids['ausstattung'] = immobilien_openimmo_section($d, 'ausstattung', $au);

    // Zustand und Energieausweis
    $za = [];
    if ($s('baujahr') !== '') $za['baujahr'] = $n('baujahr', $s('baujahr'));
    if ($s('modernisierung') !== '') $za['letztemodernisierung'] = $n('letztemodernisierung', $s('modernisierung'));
    if (($x = immobilien_openimmo_out('zustand', $s('zustand'))) !== null) {
        $za['zustand'] = $n('zustand', null, ['zustand_art' => $x]);
        if (immobilien_openimmo_in('zustand', $x) !== $s('zustand')) $user['klxm_zustand'] = $s('zustand');
    }
    $ea = $s('energieausweis');
    if ($ea !== '') {
        $ep = [];
        if (in_array($ea, ['bedarf', 'verbrauch'], true)) {
            $ep['epart'] = $n('epart', IMMOBILIEN_OI_MAP['energieausweis'][$ea]);
            if (($g = $s('energie_gueltig_bis')) !== '') $ep['gueltig_bis'] = $n('gueltig_bis', $g);
            if (($x = immobilien_openimmo_fmt($v('energie_kennwert'))) !== null) $ep[$ea === 'verbrauch' ? 'energieverbrauchkennwert' : 'endenergiebedarf'] = $n($ea === 'verbrauch' ? 'energieverbrauchkennwert' : 'endenergiebedarf', $x);
            if ($s('energietraeger') !== '') $ep['primaerenergietraeger'] = $n('primaerenergietraeger', $s('energietraeger'));
            if (($x = immobilien_openimmo_out('energie_klasse', $s('energie_klasse'))) !== null) $ep['wertklasse'] = $n('wertklasse', $x);
            if ($s('energie_baujahr') !== '') $ep['baujahr'] = $n('baujahr', $s('energie_baujahr'));
            $ep['gebaeudeart'] = $n('gebaeudeart', in_array($nutz, ['wohnen', 'waz'], true) ? 'wohn' : 'nichtwohn');
        } else {
            $ep['jahrgang'] = $n('jahrgang', IMMOBILIEN_OI_MAP['energieausweis'][$ea]);
        }
        $za['energiepass'] = immobilien_openimmo_section($d, 'energiepass', $ep);
    }
    $za['verkaufstatus'] = $n('verkaufstatus', null, ['stand' => immobilien_openimmo_out('stand', $s('stand')) ?? 'OFFEN']);
    $kids['zustand_angaben'] = immobilien_openimmo_section($d, 'zustand_angaben', $za);

    // Freitexte (reiner Text)
    $ft = [];
    foreach (IMMOBILIEN_OI_TEXTS as $f => $node) {
        $text = in_array($f, ['titel', 'kurztext'], true) ? $s($f) : immobilien_openimmo_html_text($s($f));
        if ($text !== '') $ft[$node] = $n($node, $text);
    }
    $kids['freitexte'] = immobilien_openimmo_section($d, 'freitexte', $ft);

    // Anhänge: Titelbild, Bilder, Grundriss (+ Exposé nur auf Wunsch), Video- und Rundgang-Link
    $an = [];
    if (!empty($opt['images'])) {
        $media = [['bild', 'TITELBILD']];
        for ($i = 1; $i <= IMMOBILIEN_SLOTS; $i++) $media[] = ['bild_' . $i, 'BILD'];
        $media[] = ['grundriss', 'GRUNDRISS'];
        if (!empty($opt['include_expose'])) $media[] = ['expose', 'DOKUMENTE'];
        foreach ($media as [$f, $gruppe]) {
            if (!$has($f) || !($mid = (int) ($e[$f] ?? 0)) || !($m = Media::find($mid))) continue;
            $file = Media::localFile($m);
            if (!is_file($file)) { $warn[] = "$obj: Datei zu „{$f}“ fehlt im Medienordner – übersprungen."; continue; }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $mime = (string) ($m['mime'] ?? '') ?: 'application/octet-stream';
            if ($opt['xml_only']) {
                $url = Media::url($m);
                if (!preg_match('~^https?://~i', $url)) {
                    $base = rtrim((string) ($opt['base_url'] ?? ''), '/') ?: site_url();
                    if ($base === '') { $warn[] = "$obj: Bilder ohne bekannte Website-Adresse (Einstellung „Adresse der Website“) – nicht verlinkt."; break; }
                    $url = $base . $url;
                }
                $an[] = immobilien_openimmo_section($d, 'anhang', ['anhangtitel' => ($alt = Media::alt($m)) !== '' ? $n('anhangtitel', $alt) : null,
                    'format' => $n('format', $mime), 'daten' => immobilien_openimmo_section($d, 'daten', ['pfad' => $n('pfad', $url)])],
                    false, ['location' => 'REMOTE', 'gruppe' => $gruppe]);
                continue;
            }
            $zipPath = ($gruppe === 'DOKUMENTE' ? 'dokumente/' : 'bilder/') . $mid . '-' . (Core\Pages::slugify((string) preg_replace('~^\d+-~', '', pathinfo((string) ($m['original_name'] ?? ''), PATHINFO_FILENAME))) ?: 'datei') . '.' . $ext;
            $files[$zipPath] = $file;
            $an[] = immobilien_openimmo_section($d, 'anhang', [
                'anhangtitel' => ($alt = Media::alt($m) ?: Media::title($m)) !== '' ? $n('anhangtitel', $alt) : null,
                'format' => $n('format', $mime),
                'daten' => immobilien_openimmo_section($d, 'daten', ['pfad' => $n('pfad', $zipPath)]),
            ], false, ['location' => 'EXTERN', 'gruppe' => $gruppe]);
        }
    }
    foreach ([['video_url', 'FILMLINK', 'Video'], ['rundgang_url', 'LINKS', '360°-Rundgang']] as [$f, $gruppe, $titel]) {
        if ($s($f) === '') continue;
        $an[] = immobilien_openimmo_section($d, 'anhang', ['anhangtitel' => $n('anhangtitel', $titel), 'format' => $n('format', 'text/html'),
            'daten' => immobilien_openimmo_section($d, 'daten', ['pfad' => $n('pfad', $s($f))])], false, ['location' => 'REMOTE', 'gruppe' => $gruppe]);
    }
    $kids['anhaenge'] = $an ? immobilien_openimmo_section($d, 'anhaenge', ['anhang' => $an]) : null;

    // Verwaltung
    $vo['objektadresse_freigeben'] = $n('objektadresse_freigeben', $b($frei));
    if ($s('verfuegbar_ab') !== '') $vo['verfuegbar_ab'] = $n('verfuegbar_ab', $s('verfuegbar_ab'));
    $kids['verwaltung_objekt'] = immobilien_openimmo_section($d, 'verwaltung_objekt', $vo);
    $kids['verwaltung_techn'] = immobilien_openimmo_section($d, 'verwaltung_techn', [
        'objektnr_extern' => $n('objektnr_extern', $s('objektnr') !== '' ? $s('objektnr') : 'KLXM-' . $e['id']),
        'aktion' => $n('aktion'),
        'openimmo_obid' => $n('openimmo_obid', $s('openimmo_id') !== '' ? $s('openimmo_id') : immobilien_openimmo_obid($e)),
        'kennung_ursprung' => $n('kennung_ursprung', 'KLXM Studio'),
        'stand_vom' => $n('stand_vom', date('Y-m-d', strtotime((string) ($e['updated_at'] ?? '')) ?: time())),
    ], true);
    if (!empty($e['featured'])) $user['klxm_featured'] = 'true';
    $kids['user_defined_simplefield'] = array_map(fn($k, $val) => $n('user_defined_simplefield', $val, ['feldname' => $k]), array_keys($user), $user);
    return immobilien_openimmo_section($d, 'immobilie', $kids, true);
}

/** Koordinate mit 6 Nachkommastellen ohne Nullen am Ende */
function immobilien_openimmo_fmt6(float $x): string
{
    return rtrim(rtrim(number_format($x, 6, '.', ''), '0'), '.');
}

/** Anbieter-Angaben aus den Einstellungen der Website (mit Rückfall auf übliche Namen) */
function immobilien_openimmo_org(): array
{
    $first = function (array $keys): string {
        foreach ($keys as $k) if (($v = trim((string) setting($k, ''))) !== '') return $v;
        return '';
    };
    return [
        'firma' => $first(['org_name', 'name', 'site_name', 'short_name']) ?: site()->label(),
        'email' => $first(['email', 'contact_email', 'mail']),
        'phone' => $first(['phone', 'telefon', 'tel']),
    ];
}

/**
 * Veröffentlichte Objekte als OpenImmo 1.2.7 exportieren (ZIP mit Bildern oder nur XML). Siehe Kopfkommentar.
 * @throws RuntimeException bei fehlender Tabelle oder nicht beschreibbarer Zieldatei
 */
function immobilien_openimmo_export(array $opt = []): array
{
    $opt += ['out' => null, 'ids' => null, 'images' => true, 'xml_only' => false, 'include_expose' => false, 'hide_address' => false,
        'anbieternr' => null, 'anid' => null, 'base_url' => null, 'log' => null];
    $log = is_callable($opt['log']) ? $opt['log'] : static fn(string $m) => null;
    [$t, $team] = immobilien_openimmo_tables();
    if (!$t) throw new RuntimeException('Die Datentabelle „immobilien“ fehlt.');
    $out = (string) ($opt['out'] ?: site()->storage('openimmo') . '/openimmo-' . date('Ymd-His') . ($opt['xml_only'] ? '.xml' : '.zip'));
    if (str_ends_with(strtolower($out), '.xml')) $opt['xml_only'] = true;
    if (!is_dir(dirname($out)) && !@mkdir(dirname($out), 0770, true)) throw new RuntimeException('Zielordner lässt sich nicht anlegen: ' . dirname($out));

    $q = ['status' => 'published', 'lang' => \Core\Lang::default()];
    if (is_array($opt['ids'])) $q['ids'] = array_map('intval', $opt['ids']) ?: [0];
    $entries = Entries::query($t, $q);

    $d = new DOMDocument('1.0', 'UTF-8');
    $d->formatOutput = true;
    $org = immobilien_openimmo_org();
    $root = $d->appendChild($d->createElement('openimmo'));
    $root->appendChild(immobilien_openimmo_node($d, 'uebertragung', null, array_filter([
        'art' => 'OFFLINE', 'umfang' => is_array($opt['ids']) ? 'TEIL' : 'VOLL', 'modus' => 'NEW', 'version' => '1.2.7',
        'sendersoftware' => 'KLXM Studio', 'senderversion' => defined('CMS_VERSION') ? (string) CMS_VERSION : '1.0',
        'techn_email' => $org['email'], 'timestamp' => date('Y-m-d\TH:i:s'),
    ], fn($x) => $x !== '')));
    $anbieter = $root->appendChild($d->createElement('anbieter'));
    $nr = trim((string) ($opt['anbieternr'] ?? '')) ?: trim((string) setting('openimmo_anbieternr', ''));
    if ($nr !== '') $anbieter->appendChild(immobilien_openimmo_node($d, 'anbieternr', $nr));
    $anbieter->appendChild(immobilien_openimmo_node($d, 'firma', $org['firma']));
    $anid = trim((string) ($opt['anid'] ?? '')) ?: trim((string) setting('openimmo_anid', '')) ?: 'AKLX' . strtoupper(substr(md5(site()->key . '|' . $org['firma']), 0, 12));
    $anbieter->appendChild(immobilien_openimmo_node($d, 'openimmo_anid', $anid));

    $files = [];
    $warn = [];
    foreach ($entries as $e) {
        $anbieter->appendChild(immobilien_openimmo_object($d, $t, $team, $e, $opt, $files, $warn));
        $log('+ #' . $e['id'] . ' ' . ($e['objektnr'] ?? '') . ' „' . mb_strimwidth((string) ($e['titel'] ?? ''), 0, 60, '…') . '“');
    }
    $xml = (string) $d->saveXML();

    if ($opt['xml_only']) {
        if (file_put_contents($out, $xml) === false) throw new RuntimeException("Datei lässt sich nicht schreiben: $out");
        return ['file' => $out, 'objects' => count($entries), 'images' => 0, 'warnings' => $warn];
    }
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('Auf dem Server fehlt die PHP-Erweiterung zip – mit --xml-only nur das XML schreiben.');
    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException("ZIP-Datei lässt sich nicht schreiben: $out");
    $zip->addFromString('openimmo.xml', $xml);
    foreach ($files as $rel => $file) $zip->addFile($file, $rel);
    $zip->close();
    return ['file' => $out, 'objects' => count($entries), 'images' => count($files), 'warnings' => $warn];
}
