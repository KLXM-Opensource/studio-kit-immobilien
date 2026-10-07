<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

/**
 * Datenmodell des Kits „immobilien“ – eine Quelle für Startinhalte (tools/demo-content.php), OpenImmo-Import/-Export
 * (tools/openimmo.php) und die Blöcke. Feldnamen und Auswahlwerte folgen OpenImmo 1.2.7 (Elementnamen in Kleinschreibung,
 * Kommentar „OI:“ = Pfad im XML unter <immobilie>), damit manuell erfasste Objekte ohne Umweg exportiert werden können.
 *
 * immobilien_schema()          → [handle => Tabellendefinition] für Core\Data\Tables::save()
 * immobilien_options($feld)    → [wert => Bezeichnung] einer Auswahl (für Filter, Anzeige, OpenImmo)
 */

/** Auswahllisten (Schlüssel = OpenImmo-Wert in Kleinschreibung) */
function immobilien_option_defs(): array
{
    return [
        // OI: objektkategorie/vermarktungsart @KAUF / @MIETE_PACHT
        'vermarktung' => ['kauf' => 'Kaufen', 'miete' => 'Mieten'],
        // OI: objektkategorie/nutzungsart @WOHNEN @GEWERBE @ANLAGE @WAZ
        'nutzungsart' => ['wohnen' => 'Wohnen', 'gewerbe' => 'Gewerbe', 'anlage' => 'Anlage', 'waz' => 'Wohnen auf Zeit'],
        // OI: objektkategorie/objektart/<element>
        'objektart' => ['wohnung' => 'Wohnung', 'haus' => 'Haus', 'grundstueck' => 'Grundstück', 'buero_praxen' => 'Büro / Praxis',
            'einzelhandel' => 'Einzelhandel', 'gastgewerbe' => 'Gastgewerbe', 'hallen_lager_prod' => 'Halle / Lager / Produktion',
            'zinshaus_renditeobjekt' => 'Zinshaus / Renditeobjekt', 'parken' => 'Stellplatz / Garage', 'zimmer' => 'Zimmer', 'sonstige' => 'Sonstiges'],
        // OI: objektkategorie/objektart/wohnung@wohnungtyp bzw. haus@haustyp (häufige Werte)
        'objekttyp' => ['' => '–', 'etage' => 'Etagenwohnung', 'erdgeschoss' => 'Erdgeschosswohnung', 'dachgeschoss' => 'Dachgeschosswohnung',
            'maisonette' => 'Maisonette', 'penthouse' => 'Penthouse', 'loft' => 'Loft', 'terrassen' => 'Terrassenwohnung', 'apartment' => 'Apartment',
            'einfamilienhaus' => 'Einfamilienhaus', 'zweifamilienhaus' => 'Zweifamilienhaus', 'mehrfamilienhaus' => 'Mehrfamilienhaus',
            'doppelhaushaelfte' => 'Doppelhaushälfte', 'reihenhaus' => 'Reihenhaus', 'reiheneck' => 'Reiheneckhaus', 'reihenmittel' => 'Reihenmittelhaus',
            'stadthaus' => 'Stadthaus', 'villa' => 'Villa', 'bungalow' => 'Bungalow', 'bauernhaus' => 'Bauernhaus'],
        // OI: zustand_angaben/verkaufstatus@stand (OFFEN, RESERVIERT, VERKAUFT)
        'stand' => ['offen' => 'Verfügbar', 'reserviert' => 'Reserviert', 'verkauft' => 'Verkauft / vermietet'],
        // OI: zustand_angaben/zustand@zustand_art
        'zustand' => ['' => '–', 'erstbezug' => 'Erstbezug', 'erstbezug_nach_sanierung' => 'Erstbezug nach Sanierung', 'neuwertig' => 'Neuwertig',
            'gepflegt' => 'Gepflegt', 'modernisiert' => 'Modernisiert', 'teil_vollsaniert' => 'Saniert', 'vollstaendig_renoviert' => 'Renoviert',
            'renovierungsbeduerftig' => 'Renovierungsbedürftig', 'nach_vereinbarung' => 'Nach Vereinbarung', 'projektiert' => 'Projektiert', 'rohbau' => 'Rohbau'],
        // OI: ausstattung/ausstatt_kategorie
        'ausstatt_kategorie' => ['' => '–', 'standard' => 'Standard', 'gehoben' => 'Gehoben', 'luxus' => 'Luxus'],
        // OI: ausstattung/* (Ja/Nein-Elemente bzw. Flächen > 0)
        'ausstattung' => ['balkon' => 'Balkon', 'terrasse' => 'Terrasse', 'garten' => 'Garten', 'aufzug' => 'Aufzug', 'keller' => 'Keller',
            'einbaukueche' => 'Einbauküche', 'gaeste_wc' => 'Gäste-WC', 'barrierefrei' => 'Barrierefrei', 'kamin' => 'Kamin', 'klimatisiert' => 'Klimaanlage',
            'garage' => 'Garage', 'tiefgarage' => 'Tiefgarage', 'stellplatz' => 'Stellplatz', 'haustiere' => 'Haustiere erlaubt', 'denkmalschutz' => 'Denkmalschutz',
            'moebliert' => 'Möbliert', 'wg_geeignet' => 'WG-geeignet', 'glasfaser' => 'Glasfaser'],
        // OI: ausstattung/heizungsart
        'heizungsart' => ['zentral' => 'Zentralheizung', 'etage' => 'Etagenheizung', 'fern' => 'Fernwärme', 'fussboden' => 'Fußbodenheizung', 'ofen' => 'Ofenheizung'],
        // OI: ausstattung/befeuerung
        'befeuerung' => ['gas' => 'Gas', 'oel' => 'Öl', 'fern' => 'Fernwärme', 'waermepumpe' => 'Wärmepumpe', 'luftwp' => 'Luft-Wärmepumpe',
            'erdwaerme' => 'Erdwärme', 'solar' => 'Solar', 'pellet' => 'Pellets', 'holz' => 'Holz', 'elektro' => 'Strom', 'block' => 'Blockheizkraftwerk'],
        // OI: zustand_angaben/energiepass/epart (BEDARF / VERBRAUCH), ohne Ausweis: ohnenachweis bzw. Freitext
        'energieausweis' => ['bedarf' => 'Bedarfsausweis', 'verbrauch' => 'Verbrauchsausweis', 'ohne' => 'Nicht erforderlich (z. B. Denkmal)', 'folgt' => 'Liegt zur Besichtigung vor'],
        // OI: zustand_angaben/energiepass/wertklasse
        'energie_klasse' => ['' => '–', 'a+' => 'A+', 'a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E', 'f' => 'F', 'g' => 'G', 'h' => 'H'],
    ];
}

/** [wert => Bezeichnung] einer Auswahl */
function immobilien_options(string $field): array
{
    return immobilien_option_defs()[$field] ?? [];
}

/** Auswahl als Optionstext der Datentabellen („wert=Bezeichnung“ je Zeile) */
function immobilien_options_text(string $field): string
{
    $out = [];
    foreach (immobilien_options($field) as $k => $v) if ($k !== '') $out[] = $k . '=' . $v;
    return implode("\n", $out);
}

/** Anzahl der Bild-Plätze (bild_1 … bild_N) neben Titelbild und Grundriss */
const IMMOBILIEN_SLOTS = 12;

/** Tabellendefinitionen: immobilien, team (Ansprechpartner) und die verschlüsselten Eingänge */
function immobilien_schema(): array
{
    $o = 'immobilien_options_text';
    $h = fn(string $name, string $label, string $type = 'text', array $x = []) => ['name' => $name, 'label' => $label, 'type' => $type] + $x;
    $slots = [];
    for ($i = 1; $i <= IMMOBILIEN_SLOTS; $i++) $slots[] = $h('bild_' . $i, 'Bild ' . $i, 'media', ['width' => 'half'] + ($i === 1 ? ['help' => 'Weitere Fotos in der gewünschten Reihenfolge. Alt-Text in der Mediathek pflegen (z. B. „Wohnzimmer mit Blick auf den Garten“).'] : []));

    return [
        'immobilien' => [
            'handle' => 'immobilien', 'name' => 'Immobilien', 'singular' => 'Immobilie', 'icon' => 'house-line',
            'description' => 'Angebote zum Kaufen und Mieten – Felder nach OpenImmo. Detailseite unter /immobilien/…; Exposé auf Anfrage.',
            'fields' => [
                // Kopf (OI: freitexte/objekttitel, verwaltung_techn/objektnr_extern, objektkategorie)
                $h('titel', 'Titel', 'text', ['required' => true, 'in_list' => true, 'searchable' => true, 'help' => 'Wie im Exposé, z. B. „Helle 3-Zimmer-Wohnung mit Südbalkon“.']),
                $h('kurztext', 'Kurztext', 'textarea', ['help' => 'Ein, zwei Sätze für Listen, Vorschau und Suchmaschinen.']),
                $h('objektnr', 'Objektnummer', 'text', ['width' => 'half', 'in_list' => true, 'searchable' => true, 'help' => 'Ihre interne Nummer (OpenImmo: objektnr_extern).']),
                $h('stand', 'Status', 'select', ['width' => 'half', 'in_list' => true, 'options' => $o('stand'), 'help' => 'Reserviert/verkauft bleiben sichtbar (Referenz) – oder Eintrag auf „Entwurf“ setzen.']),
                $h('vermarktung', 'Vermarktung', 'select', ['width' => 'half', 'required' => true, 'in_list' => true, 'options' => $o('vermarktung')]),
                $h('nutzungsart', 'Nutzungsart', 'select', ['width' => 'half', 'options' => $o('nutzungsart')]),
                $h('objektart', 'Objektart', 'select', ['width' => 'half', 'required' => true, 'in_list' => true, 'options' => $o('objektart')]),
                $h('objekttyp', 'Objekttyp (optional)', 'select', ['width' => 'half', 'options' => $o('objekttyp')]),
                $h('featured', 'Als Top-Immobilie hervorheben', 'bool', ['width' => 'half', 'help' => 'Für die Startseite („Nur Top-Immobilien“).']),
                $h('ansprechpartner', 'Ansprechpartner', 'relation', ['target' => 'team', 'width' => 'half']),
                // Lage (OI: geo, verwaltung_objekt/objektadresse_freigeben)
                $h('strasse', 'Straße', 'text', ['width' => 'half']),
                $h('hausnummer', 'Hausnummer', 'text', ['width' => 'half']),
                $h('plz', 'PLZ', 'text', ['width' => 'half']),
                $h('ort', 'Ort', 'text', ['width' => 'half', 'in_list' => true, 'searchable' => true]),
                $h('stadtteil', 'Stadtteil / Lage (optional)', 'text', ['width' => 'half', 'help' => 'OpenImmo: regionaler_zusatz – erscheint statt der Straße, solange die Adresse nicht freigegeben ist.']),
                $h('adresse_zeigen', 'Genaue Adresse öffentlich zeigen', 'bool', ['width' => 'half', 'help' => 'Aus: nur PLZ, Ort und Stadtteil; die Karte zeigt einen ungefähren Umkreis.']),
                $h('geo', 'Lage auf der Karte', 'geo'),
                // Preise (OI: preise)
                $h('kaufpreis', 'Kaufpreis (€)', 'number', ['width' => 'half']),
                $h('preis_auf_anfrage', 'Preis auf Anfrage', 'bool', ['width' => 'half']),
                $h('kaltmiete', 'Kaltmiete (€)', 'number', ['width' => 'half']),
                $h('warmmiete', 'Warmmiete (€)', 'number', ['width' => 'half']),
                $h('nebenkosten', 'Nebenkosten (€)', 'number', ['width' => 'half']),
                $h('heizkosten_enthalten', 'Heizkosten in den Nebenkosten enthalten', 'bool', ['width' => 'half']),
                $h('hausgeld', 'Hausgeld (€, Eigentumswohnung)', 'number', ['width' => 'half']),
                $h('kaution', 'Kaution', 'text', ['width' => 'half', 'help' => 'Freitext, z. B. „3 Nettokaltmieten“.']),
                $h('provisionspflichtig', 'Provisionspflichtig für Käufer/Mieter', 'bool', ['width' => 'half']),
                $h('provision', 'Provision (Text)', 'text', ['width' => 'half', 'help' => 'z. B. „3,57 % inkl. MwSt. vom Kaufpreis“ (OpenImmo: aussen_courtage).']),
                // Flächen (OI: flaechen, geo/etage)
                $h('wohnflaeche', 'Wohnfläche (m²)', 'number', ['width' => 'half', 'in_list' => true]),
                $h('nutzflaeche', 'Nutzfläche (m²)', 'number', ['width' => 'half']),
                $h('grundstuecksflaeche', 'Grundstücksfläche (m²)', 'number', ['width' => 'half']),
                $h('zimmer', 'Zimmer', 'number', ['width' => 'half', 'in_list' => true, 'help' => 'Halbe Zimmer möglich, z. B. 2,5.']),
                $h('schlafzimmer', 'Schlafzimmer', 'number', ['width' => 'half']),
                $h('badezimmer', 'Badezimmer', 'number', ['width' => 'half']),
                $h('etage', 'Etage', 'number', ['width' => 'half', 'help' => '0 = Erdgeschoss.']),
                $h('etagen', 'Etagen im Haus', 'number', ['width' => 'half']),
                $h('stellplaetze', 'Stellplätze', 'number', ['width' => 'half']),
                // Ausstattung und Zustand (OI: ausstattung, zustand_angaben)
                $h('ausstattung', 'Ausstattung', 'multiselect', ['options' => $o('ausstattung')]),
                $h('ausstatt_kategorie', 'Ausstattungsqualität', 'select', ['width' => 'half', 'options' => $o('ausstatt_kategorie')]),
                $h('zustand', 'Zustand', 'select', ['width' => 'half', 'options' => $o('zustand')]),
                $h('baujahr', 'Baujahr', 'text', ['width' => 'half']),
                $h('modernisierung', 'Letzte Modernisierung', 'text', ['width' => 'half', 'help' => 'Freitext, z. B. „2021 (Bäder, Fenster)“.']),
                $h('verfuegbar_ab', 'Verfügbar ab', 'text', ['width' => 'half', 'help' => 'Freitext, z. B. „sofort“ oder „nach Vereinbarung“.']),
                $h('heizungsart', 'Heizungsart', 'multiselect', ['options' => $o('heizungsart')]),
                $h('befeuerung', 'Energieträger der Heizung', 'multiselect', ['options' => $o('befeuerung')]),
                // Energieausweis – Pflichtangaben in Anzeigen nach § 87 GEG (OI: zustand_angaben/energiepass)
                $h('energieausweis', 'Energieausweis', 'select', ['width' => 'half', 'options' => $o('energieausweis'),
                    'help' => 'Pflichtangaben in Immobilienanzeigen (§ 87 GEG): Art, Kennwert, wesentlicher Energieträger, Baujahr, Effizienzklasse.']),
                $h('energie_kennwert', 'Endenergie (kWh/(m²·a))', 'number', ['width' => 'half']),
                $h('energie_klasse', 'Energieeffizienzklasse', 'select', ['width' => 'half', 'options' => $o('energie_klasse')]),
                $h('energietraeger', 'Wesentlicher Energieträger', 'text', ['width' => 'half', 'help' => 'z. B. „Gas“, „Fernwärme“, „Strom-Mix (Wärmepumpe)“.']),
                $h('energie_baujahr', 'Baujahr laut Ausweis', 'text', ['width' => 'half']),
                $h('energie_gueltig_bis', 'Ausweis gültig bis', 'date', ['width' => 'half']),
                // Texte (OI: freitexte)
                $h('beschreibung', 'Objektbeschreibung', 'richtext'),
                $h('ausstattung_text', 'Ausstattung (Text)', 'richtext'),
                $h('lage_text', 'Lage', 'richtext'),
                $h('sonstiges', 'Sonstiges', 'richtext'),
                // Medien (OI: anhaenge – gruppe TITELBILD, BILD, GRUNDRISS, DOKUMENTE; links)
                $h('bild', 'Titelbild', 'media', ['help' => 'Querformat, lange Seite mind. 2000 px. Erscheint in Listen, als Vorschau und oben auf der Detailseite.']),
                ...$slots,
                $h('grundriss', 'Grundriss', 'media', ['width' => 'half']),
                $h('expose', 'Exposé (PDF, nicht öffentlich)', 'file', ['width' => 'half',
                    'help' => 'Wird nicht auf der Website verlinkt. Interessenten fordern es über „Exposé anfordern“ an – Sie senden es nach Prüfung der Anfrage.']),
                $h('video_url', 'Video (YouTube- oder Vimeo-Link)', 'url', ['width' => 'half', 'help' => 'Lädt erst nach einem Klick (Zwei-Klick-Lösung).']),
                $h('rundgang_url', '360°-Rundgang (Link)', 'url', ['width' => 'half', 'help' => 'Externer Rundgang (z. B. Matterport) – öffnet in neuem Fenster.']),
                // Technisch (OI: verwaltung_techn/openimmo_obid) – nur für Import/Export
                $h('openimmo_id', 'OpenImmo-ID (automatisch)', 'text', ['help' => 'Wird beim Import gesetzt und beim Export verwendet. Nicht ändern.']),
            ],
            'settings' => ['route' => 'immobilien', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'kurztext', 'sort_field' => 'sort', 'sort_dir' => 'asc',
                'workflow' => 1, 'schema_type' => 'none', 'search' => ['enabled' => '1']],
        ],
        'team' => [
            'handle' => 'team', 'name' => 'Team', 'singular' => 'Ansprechpartner', 'icon' => 'users-three',
            'description' => 'Ansprechpartnerinnen und -partner – erscheinen auf Objekt-Detailseiten und auf der Seite „Über uns“.',
            'fields' => [
                $h('name', 'Name', 'text', ['required' => true, 'in_list' => true, 'searchable' => true]),
                $h('position', 'Position', 'text', ['in_list' => true, 'help' => 'z. B. „Immobilienberaterin“ oder „Hausverwaltung, WEG“.']),
                $h('foto', 'Foto', 'media', ['help' => 'Hochformat 4:5.']),
                $h('telefon', 'Telefon', 'tel', ['width' => 'half']),
                $h('email', 'E-Mail', 'email', ['width' => 'half']),
                $h('bereich', 'Bereich', 'select', ['width' => 'half', 'options' => "makler=Verkauf & Vermietung\nverwaltung=Hausverwaltung\nleitung=Geschäftsführung"]),
                $h('kurztext', 'Kurztext', 'textarea'),
            ],
            'settings' => ['title_field' => 'name', 'image_field' => 'foto', 'description_field' => 'position', 'sort_field' => 'sort', 'sort_dir' => 'asc', 'workflow' => 1, 'schema_type' => 'none'],
        ],
    ];
}

/**
 * Verschlüsselte Eingänge (Core\Data\Inbox – nur in der Verwaltung mit dem geheimen Schlüssel lesbar):
 * Exposé-Anfragen, Suchaufträge, Bewertungsanfragen (Eigentümer), Schadensmeldungen (Hausverwaltung).
 */
function immobilien_inboxes(): array
{
    $h = fn(string $name, string $label, string $type = 'text', array $x = []) => ['name' => $name, 'label' => $label, 'type' => $type] + $x;
    $person = [
        $h('vorname', 'Vorname', 'text', ['required' => true, 'width' => 'half']),
        $h('nachname', 'Nachname', 'text', ['required' => true, 'width' => 'half']),
        $h('email', 'E-Mail', 'email', ['required' => true, 'width' => 'half']),
        $h('telefon', 'Telefon', 'tel', ['width' => 'half']),
    ];
    $inbox = fn(string $handle, string $name, string $singular, string $icon, string $desc, array $fields, string $submit, string $success, array $form = []) => [
        'handle' => $handle, 'name' => $name, 'singular' => $singular, 'icon' => $icon, 'description' => $desc, 'fields' => $fields,
        'settings' => ['kind' => 'inbox', 'title_field' => 'nachname', 'form' => ['enabled' => 1, 'submit' => $submit, 'success' => $success] + $form],
    ];
    return [
        // Widerrufsfunktion (§ 356a BGB, seit 19.06.2026): Schaltfläche „Vertrag widerrufen“ im Fußbereich → dieses Formular →
        // „Widerruf bestätigen“; die Eingangsbestätigung mit Inhalt und Zeitpunkt geht automatisch per E-Mail hinaus.
        'widerrufe' => $inbox('widerrufe', 'Widerrufe', 'Widerruf', 'arrow-counter-clockwise',
            'Widerrufe von Maklerverträgen (z. B. nach einer Exposé-Anfrage) – Eingangsbestätigung per E-Mail ist eingeschaltet.', [
                ...$person,
                $h('vertrag', 'Welchen Vertrag widerrufen Sie?', 'text', ['required' => true, 'help' => 'z. B. Objektnummer oder Titel des Objekts, zu dem Sie ein Exposé angefordert haben.']),
                $h('datum', 'Datum der Anfrage bzw. des Vertrags (optional)', 'date', ['width' => 'half']),
                $h('nachricht', 'Anmerkung (optional)', 'textarea'),
                $h('erklaerung', 'Hiermit widerrufe ich den von mir abgeschlossenen Vertrag über die Erbringung der oben genannten Dienstleistung.', 'bool', ['required' => true]),
            ], 'Widerruf bestätigen', 'Ihr Widerruf ist eingegangen. Eine Bestätigung mit Datum und Uhrzeit haben wir an Ihre E-Mail-Adresse gesendet.',
            ['receipt' => ['enabled' => 1, 'field' => 'email', 'subject' => 'Eingangsbestätigung Ihres Widerrufs', 'include' => 1,
                'text' => 'hiermit bestätigen wir den Eingang Ihres Widerrufs. Die Angaben, wie Sie sie übermittelt haben, finden Sie unten.']]),
        'expose_anfragen' => $inbox('expose_anfragen', 'Exposé-Anfragen', 'Exposé-Anfrage', 'file-text',
            'Anfragen nach dem Exposé eines Objekts – das Objekt wird aus der Detailseite übernommen.', [
                $h('objekt', 'Objekt', 'text', ['required' => true, 'help' => 'Objektnummer und Titel – wird auf der Detailseite vorausgefüllt.']),
                ...$person,
                $h('strasse', 'Straße und Hausnummer', 'text', ['width' => 'half']),
                $h('plz_ort', 'PLZ und Ort', 'text', ['width' => 'half']),
                $h('finanzierung', 'Finanzierung', 'select', ['width' => 'half', 'options' => "geklaert=Finanzierung ist geklärt\npruefung=In Prüfung\noffen=Noch offen\nmiete=Nicht nötig (Miete)"]),
                $h('besichtigung', 'Besichtigung gewünscht', 'bool', ['width' => 'half']),
                $h('nachricht', 'Nachricht (optional)', 'textarea'),
                $h('widerruf', 'Ich habe die Widerrufsbelehrung gelesen und wünsche ausdrücklich, dass Sie vor Ablauf der Widerrufsfrist mit Ihrer Tätigkeit beginnen.', 'bool', ['required' => true]),
            ], 'Exposé anfordern', 'Vielen Dank – wir senden Ihnen das Exposé in Kürze per E-Mail.'),
        'suchauftraege' => $inbox('suchauftraege', 'Suchaufträge', 'Suchauftrag', 'magnifying-glass',
            'Suchprofile von Interessenten – wir melden uns, sobald ein passendes Objekt hereinkommt.', [
                $h('vermarktung', 'Ich möchte', 'select', ['required' => true, 'width' => 'half', 'options' => "kauf=Kaufen\nmiete=Mieten"]),
                $h('objektart', 'Objektart', 'select', ['width' => 'half', 'options' => "wohnung=Wohnung\nhaus=Haus\ngrundstueck=Grundstück\ngewerbe=Gewerbe"]),
                $h('region', 'Ort / Region', 'text', ['width' => 'half']),
                $h('zimmer', 'Zimmer (mindestens)', 'number', ['width' => 'half']),
                $h('flaeche', 'Wohnfläche (mindestens, m²)', 'number', ['width' => 'half']),
                $h('budget', 'Budget bzw. Miete (höchstens, €)', 'number', ['width' => 'half']),
                ...$person,
                $h('nachricht', 'Was ist Ihnen sonst wichtig? (optional)', 'textarea'),
            ], 'Suchauftrag senden', 'Danke – wir melden uns, sobald ein passendes Objekt verfügbar ist.'),
        'bewertungen' => $inbox('bewertungen', 'Bewertungsanfragen', 'Bewertungsanfrage', 'chart-line-up',
            'Eigentümer, die verkaufen oder vermieten möchten – kostenlose Wertermittlung.', [
                $h('anliegen', 'Ich möchte', 'select', ['required' => true, 'width' => 'half', 'options' => "verkaufen=Verkaufen\nvermieten=Vermieten\nbewerten=Nur den Wert wissen\nverwaltung=Verwaltung übergeben"]),
                $h('objektart', 'Objektart', 'select', ['width' => 'half', 'options' => "wohnung=Wohnung\nhaus=Haus\nmehrfamilienhaus=Mehrfamilienhaus\ngrundstueck=Grundstück\ngewerbe=Gewerbe"]),
                $h('plz_ort', 'PLZ und Ort des Objekts', 'text', ['required' => true, 'width' => 'half']),
                $h('wohnflaeche', 'Wohnfläche (m², ca.)', 'number', ['width' => 'half']),
                $h('baujahr', 'Baujahr (ca.)', 'text', ['width' => 'half']),
                $h('zeitpunkt', 'Zeitpunkt', 'select', ['width' => 'half', 'options' => "sofort=So bald wie möglich\nhalbjahr=In den nächsten 6 Monaten\nspaeter=Später / unklar"]),
                ...$person,
                $h('nachricht', 'Nachricht (optional)', 'textarea'),
            ], 'Bewertung anfragen', 'Vielen Dank – wir melden uns innerhalb von zwei Werktagen für einen Termin.'),
        'schadensmeldungen' => $inbox('schadensmeldungen', 'Schadensmeldungen', 'Schadensmeldung', 'wrench',
            'Meldungen von Mietern und Eigentümern an die Hausverwaltung. Fotos: Dateifeld ergänzen, sobald die Zustellung per E-Mail eingerichtet ist (Dateien gehen nur als Anhang hinaus).', [
                $h('objekt', 'Objekt (Straße, Hausnummer, Ort)', 'text', ['required' => true]),
                $h('lage', 'Wohnung / Lage im Haus', 'text', ['width' => 'half', 'help' => 'z. B. „2. OG links“ oder „Treppenhaus“.']),
                $h('art', 'Art des Schadens', 'select', ['required' => true, 'width' => 'half', 'options' => "wasser=Wasser / Sanitär\nheizung=Heizung\nelektrik=Elektrik\nfenster_tueren=Fenster / Türen / Schlösser\naufzug=Aufzug\ndach_fassade=Dach / Fassade\nsonstiges=Sonstiges"]),
                $h('dringend', 'Dringend (z. B. Wasser tritt aus, Heizungsausfall im Winter)', 'bool'),
                $h('beschreibung', 'Was ist passiert?', 'textarea', ['required' => true]),
                ...$person,
                $h('zugang', 'Zugang zur Wohnung', 'select', ['width' => 'half', 'options' => "termin=Bitte Termin vereinbaren\nschluessel=Schlüssel bei Nachbarn/Hausmeister\nanwesend=Meist anwesend"]),
            ], 'Schaden melden', 'Danke – Ihre Meldung ist eingegangen. Fotos schicken Sie gern per Antwort auf unsere Rückmeldung. Bei Gefahr (Gas, Wasser, Strom) rufen Sie bitte zusätzlich den Notdienst an.'),
    ];
}
