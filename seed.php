<?php
/*
 * Startinhalte des Kits „immobilien“ – werden beim ersten Aufruf einer neuen Website eingespielt.
 * Alles frei erfunden und als Demo gekennzeichnet („Musterhaus Immobilien (Demo)“, Musterstadt, Beispielobjekte).
 * Texte in [eckigen Klammern] sind Platzhalter (erscheinen in der Übersicht der Verwaltung als offen).
 *
 * Mit Startinhalten (after → tools/immo-demo.php): Datentabellen „Immobilien“ und „Team“ (OpenImmo-konform, tools/schema.php),
 * verschlüsselte Eingänge (Exposé-Anfragen, Suchaufträge, Bewertungen, Schadensmeldungen), Fotos von Pexels (demo/credits.json),
 * Musterobjekte, Seiten (Start, Immobilien, Verkaufen, Hausverwaltung, Suchauftrag, Über uns, Kontakt) und die Detailseite.
 * Ohne Startinhalte: nur diese Startseite und die Rechtstexte als Vorlagen.
 * Farben, Schriften, Kopf und Fuß: Verwaltung → Design (Standard = Vorlage „Immobilien · Anthrazit & Messing“).
 */

$workday = fn(string $tag, string $bis = '18:00') => ['tag' => $tag, 'von' => '09:00', 'bis' => $bis, 'pause_von' => '', 'pause_bis' => '', 'notiz' => ''];
$legal = fn(string $slug, string $title, string $text) => [
    'slug' => $slug, 'title' => $title, 'menu' => false,
    'blocks' => [
        ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => '', 'title' => $title, 'text' => '']],
        ['type' => 'richtext', 'data' => ['variant' => 'standard', 'title' => '', 'text' => $text]],
    ],
];

return [
    'settings' => [
        'org_name' => 'Musterhaus Immobilien (Demo)',
        'short_name' => 'Musterhaus Immobilien',
        'tagline' => 'Verkauf, Vermietung und Hausverwaltung in Musterstadt.',
        'street' => 'Marktplatz 1',
        'zip' => '12345',
        'city' => 'Musterstadt',
        'country' => '',
        'phone' => '0123 456789-0',
        'email' => 'kontakt@example.com',
        'hours' => [$workday('1'), $workday('2'), $workday('3'), $workday('4'), $workday('5', '16:00')],
        'hours_note' => 'Besichtigungen auch abends und samstags nach Vereinbarung.',
        'geo' => '',
        'header_cta_label' => 'Immobilie bewerten',
        'header_cta_link' => '/verkaufen#bewertung',
        'topbar_text' => 'Notdienst Hausverwaltung: [Telefonnummer]',
        'footer_statement' => 'Gut zuhause. Gut verwaltet.',
        'footer_text' => 'Demo-Inhalte des Kits „Immobilien“: Firma, Objekte, Preise und Personen sind frei erfunden. Fotos: Pexels.',
        'notice_active' => false,
        'notice_text' => '',
        'social' => [],
        'site_title' => 'Musterhaus Immobilien (Demo) – Makler und Hausverwaltung in Musterstadt',
        'site_title_suffix' => '',
        'default_meta_description' => 'Immobilien kaufen, mieten, verkaufen und verwalten lassen in Musterstadt – Demo-Website des Kits „Immobilien“ für Makler und Hausverwaltungen.',
        'schema_type' => 'LocalBusiness',
    ],

    // Tabellen, Fotos, Objekte und Seiten (Core\Seeder ruft 'after' nur „mit Startinhalten“ auf)
    'after' => function () {
        require_once __DIR__ . '/tools/immo-demo.php';
        immo_demo_install();
    },

    'page_refs' => [
        'imprint_page' => 'impressum',
        'privacy_page' => 'datenschutz',
        'immo_withdrawal_page' => 'widerruf',
        'immo_withdraw_form_page' => 'vertrag-widerrufen',
    ],

    'pages' => [
        [
            'slug' => 'start', 'title' => 'Start', 'is_home' => true, 'menu' => false,
            'meta_description' => 'Immobilien kaufen, mieten, verkaufen und verwalten lassen – Demo-Website des Kits „Immobilien“.',
            'blocks' => [
                ['type' => 'hero', 'data' => ['variant' => 'centered', 'eyebrow' => 'Makler und Hausverwaltung', 'title' => 'Zuhause finden. *Werte* erhalten.',
                    'text' => '[Kurzer Satz, wofür Ihr Büro steht – z. B. Region, Erfahrung, Spezialisierung.]',
                    'button_label' => 'Kontakt', 'button_link' => '/kontakt', 'button2_label' => '', 'button2_link' => '', 'points' => '', 'image' => null, 'ratio' => '16:9']],
            ],
        ],
        $legal('impressum', 'Impressum', '<p>[Impressum: Firma, Vertretungsberechtigte, Anschrift, Kontakt, Handelsregister, USt-IdNr., Erlaubnis nach § 34c GewO mit zuständiger Aufsichtsbehörde, Berufshaftpflicht (bei WEG-Verwaltung: Pflichtversicherung nach § 34c Abs. 2 Nr. 3 GewO), Zertifizierung als Verwalter, Kammer.]</p>'),
        $legal('datenschutz', 'Datenschutz', '<p>[Datenschutzerklärung einfügen.]</p><h2>Hinweise zur technischen Umsetzung dieser Website</h2><ul><li>Diese Website setzt für Besucherinnen und Besucher keine Cookies und verwendet keine Tracking- oder Analysedienste.</li><li>Schriften werden lokal ausgeliefert, Karten laden über den eigenen Server.</li><li>Anfragen (Exposé, Suchauftrag, Bewertung, Schadensmeldung) werden verschlüsselt gespeichert und sind nur im Büro lesbar.</li><li>Videos externer Anbieter und 360°-Rundgänge laden erst nach Ihrem Klick.</li></ul>'),
        $legal('widerruf', 'Widerrufsbelehrung', '<p>[Widerrufsbelehrung für den Maklervertrag (Fernabsatz) einfügen und rechtlich prüfen lassen. Mit der Anforderung eines Exposés kann ein Maklervertrag zustande kommen; das Formular „Exposé anfordern“ fragt die Zustimmung zum vorzeitigen Tätigwerden ab.]</p><p>Sie können Ihren Widerruf jederzeit online über die Seite <a href="/vertrag-widerrufen">„Vertrag widerrufen“</a> erklären – oder formlos per E-Mail, Brief oder mit dem Muster-Widerrufsformular unten.</p><h2>Muster-Widerrufsformular</h2><p>(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</p><p>An [Name, Anschrift und E-Mail-Adresse des Maklers]:</p><p>Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über die Erbringung der folgenden Dienstleistung (*):</p><p>Bestellt am (*) / erhalten am (*):</p><p>Name des/der Verbraucher(s):</p><p>Anschrift des/der Verbraucher(s):</p><p>Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier):</p><p>Datum:</p><p>(*) Unzutreffendes streichen.</p>'),
        [
            'slug' => 'vertrag-widerrufen', 'title' => 'Vertrag widerrufen', 'menu' => false,
            'meta_description' => 'Maklervertrag online widerrufen – mit Eingangsbestätigung per E-Mail.',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'Widerruf', 'title' => 'Vertrag widerrufen',
                    'text' => 'Hier können Sie einen mit uns geschlossenen Vertrag – etwa nach einer Exposé-Anfrage – online widerrufen. Sie erhalten sofort eine Eingangsbestätigung per E-Mail.']],
                ['type' => 'contact', 'data' => ['eyebrow' => '', 'title' => '', 'intro' => '', 'show_hours' => false, 'show_map' => false,
                    'form_table' => 'widerrufe', 'form_title' => 'Widerrufserklärung', 'submit_label' => 'Widerruf bestätigen', 'note' => 'Sie können den Widerruf auch formlos per E-Mail oder Brief erklären – siehe Widerrufsbelehrung.']],
            ],
        ],
    ],
];
