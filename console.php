<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

/**
 * Befehle des Kits „immobilien“ für bin/console (Core\Kit::commands). OpenImmo ist optional – Standard ist die Erfassung in der Verwaltung.
 *
 *   php bin/console openimmo:import <datei.xml|datei.zip> [--dry-run] [--no-images] [--no-update] [--remote] [--draft]
 *                                   [--delete=draft|delete|ignore] [--prune] [--site=key]
 *     Objekte aus OpenImmo 1.2.x übernehmen (Export aus onOffice, FlowFact, Propstack, ImmoScout …). Abgleich über die
 *     OpenImmo-ID, sonst die Objektnummer. --dry-run schreibt nichts; --remote lädt REMOTE-Anhänge (Zeit- und Größenlimit);
 *     --draft legt neue Objekte als Entwurf an; --delete bestimmt, was bei aktionart="DELETE" geschieht (Standard: Entwurf);
 *     --prune setzt bei einer Vollübertragung (umfang="VOLL") früher importierte, nun fehlende Objekte auf Entwurf.
 *   php bin/console openimmo:export [--out=datei.zip|datei.xml] [--xml-only] [--ids=1,2] [--no-images] [--with-expose]
 *                                   [--hide-address] [--anbieternr=…] [--base-url=https://…] [--site=key]
 *     Veröffentlichte Objekte als OpenImmo 1.2.7 (ZIP mit Bildern; --xml-only: nur XML, Bilder als Adressen der Website –
 *     ohne eingestellte Website-Adresse mit --base-url).
 *     Exposé-PDFs nur mit --with-expose. Ohne --out: storage/…/openimmo/openimmo-JJJJMMTT-HHMMSS.zip
 */

require_once __DIR__ . '/tools/openimmo.php';

/** Wert einer Option „--name=wert“ (null, wenn nicht angegeben) */
$immoOpt = static function (array $args, string $name): ?string {
    foreach ($args as $a) if (str_starts_with($a, "--$name=")) return substr($a, strlen($name) + 3);
    return null;
};

/** Tabellen vorhanden? Sonst Hinweis, wie sie entstehen */
$immoTables = static function (): bool {
    [$t, $team] = immobilien_openimmo_tables();
    if ($t && $team) return true;
    $missing = implode(' und ', array_filter([$t ? null : '„immobilien“', $team ? null : '„team“']));
    fwrite(STDERR, (!$t && !$team ? "Datentabellen $missing fehlen" : "Datentabelle $missing fehlt") . " auf Website „" . site()->key . "“.\n"
        . "Die Tabellen entstehen mit den Startinhalten des Kits „immobilien“:\n"
        . "  – neue Website:      php bin/console site:create <key> <domain> immobilien --content=full\n"
        . "  – bestehende Website: Kit „immobilien“ wählen und Startinhalte einspielen (php bin/console reseed --force --site=" . site()->key . " – löscht ALLE Inhalte!)\n"
        . "  – oder in der Verwaltung unter Daten anlegen (Felder siehe kits/immobilien/tools/schema.php).\n");
    return false;
};

return [
    'openimmo:import' => [
        'OpenImmo-Datei (.xml/.zip) importieren [--dry-run] [--no-images] [--no-update] [--remote] [--draft] [--delete=draft|delete|ignore] [--prune]',
        function (array $args) use ($immoOpt, $immoTables): int {
            $file = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')))[0] ?? '';
            if ($file === '') {
                fwrite(STDERR, "Aufruf: openimmo:import <datei.xml|datei.zip> [--dry-run] [--no-images] [--no-update] [--remote] [--draft] [--delete=draft|delete|ignore] [--prune]\n");
                return 1;
            }
            if (!$immoTables()) return 1;
            $delete = $immoOpt($args, 'delete') ?? 'draft';
            if (!in_array($delete, ['draft', 'delete', 'ignore'], true)) { fwrite(STDERR, "--delete=draft|delete|ignore\n"); return 1; }
            $dry = in_array('--dry-run', $args, true);
            echo ($dry ? 'Probelauf (es wird nichts gespeichert): ' : 'Import: ') . $file . ' → Website „' . site()->key . "“\n";
            try {
                $r = immobilien_openimmo_import($file, [
                    'dry' => $dry, 'update' => !in_array('--no-update', $args, true), 'images' => !in_array('--no-images', $args, true),
                    'allow_remote' => in_array('--remote', $args, true), 'delete' => $delete, 'prune' => in_array('--prune', $args, true),
                    'status' => in_array('--draft', $args, true) ? 'draft' : 'published', 'log' => fn(string $m) => print("  $m\n"),
                ]);
            } catch (Throwable $e) {
                fwrite(STDERR, 'Fehler: ' . $e->getMessage() . "\n");
                return 1;
            }
            $w = $dry ? 'würden ' : '';
            echo "\n" . ($dry ? 'Ergebnis des Probelaufs' : 'Ergebnis') . ":\n"
                . "  {$r['created']} " . ($dry ? 'würden angelegt' : 'angelegt') . ", {$r['updated']} " . ($dry ? 'würden aktualisiert' : 'aktualisiert')
                . ", {$r['deleted']} {$w}" . ($delete === 'delete' ? 'gelöscht' : 'auf Entwurf gesetzt') . " (Löschmeldungen), {$r['skipped']} übersprungen"
                . ($r['pruned'] ? ", {$r['pruned']} nicht mehr im Bestand → Entwurf" : '') . "\n"
                . ($dry ? '' : "  {$r['media']} Medien übernommen, {$r['team_created']} Ansprechpartner neu im Team\n");
            if ($r['warnings']) {
                echo "\nHinweise (" . count($r['warnings']) . "):\n";
                foreach ($r['warnings'] as $m) echo "  ! $m\n";
            }
            if ($r['errors']) {
                echo "\nFehler (" . count($r['errors']) . "):\n";
                foreach ($r['errors'] as $m) echo "  ✗ $m\n";
            }
            if (!$r['created'] && !$r['updated'] && !$r['deleted'] && !$r['errors']) echo "\nNichts zu tun – keine passenden Objekte in der Datei.\n";
            return $r['errors'] ? 2 : 0;
        },
    ],
    'openimmo:export' => [
        'Veröffentlichte Objekte als OpenImmo 1.2.7 exportieren [--out=datei.zip|.xml] [--xml-only] [--ids=1,2] [--no-images] [--with-expose] [--hide-address]',
        function (array $args) use ($immoOpt, $immoTables): int {
            if (!$immoTables()) return 1;
            $ids = $immoOpt($args, 'ids');
            $out = $immoOpt($args, 'out');
            if ($out !== null && $out !== '' && !str_starts_with($out, '/')) $out = getcwd() . '/' . $out;
            try {
                $r = immobilien_openimmo_export([
                    'out' => $out ?: null, 'xml_only' => in_array('--xml-only', $args, true), 'images' => !in_array('--no-images', $args, true),
                    'ids' => $ids !== null ? array_values(array_filter(array_map('intval', explode(',', $ids)))) : null,
                    'include_expose' => in_array('--with-expose', $args, true), 'hide_address' => in_array('--hide-address', $args, true),
                    'anbieternr' => $immoOpt($args, 'anbieternr'), 'base_url' => $immoOpt($args, 'base-url'), 'log' => fn(string $m) => print("  $m\n"),
                ]);
            } catch (Throwable $e) {
                fwrite(STDERR, 'Fehler: ' . $e->getMessage() . "\n");
                return 1;
            }
            echo "\n✓ {$r['objects']} Objekt(e) exportiert" . ($r['images'] ? ", {$r['images']} Datei(en) im ZIP" : '') . "\n  Datei: {$r['file']}\n";
            if ($r['warnings']) {
                echo "\nHinweise (" . count($r['warnings']) . "):\n";
                foreach ($r['warnings'] as $m) echo "  ! $m\n";
            }
            if (!$r['objects']) echo "\nHinweis: Keine veröffentlichten Objekte" . ($ids !== null ? ' mit diesen IDs' : '') . " – die Datei enthält nur die Anbieterangaben.\n";
            return 0;
        },
    ],
];
