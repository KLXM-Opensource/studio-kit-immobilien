<?php
/**
 * Demo des Kits „immobilien“ für eine bestehende Website einspielen oder entfernen:
 *   CMS_SITE=<key> php kits/immobilien/tools/demo.php            Tabellen, Fotos, Musterobjekte, Seiten
 *   CMS_SITE=<key> php kits/immobilien/tools/demo.php --force    vorhandene Demo vorher entfernen
 *   CMS_SITE=<key> php kits/immobilien/tools/demo.php --remove   Demo-Tabellen, Detailseite, Demo-Fotos und Demo-Seiten entfernen
 */
declare(strict_types=1);

$app = require __DIR__ . '/../../../app/bootstrap.php';
if (($app->theme->name ?? '') !== 'immobilien') { fwrite(STDERR, "Die Website nutzt nicht das Kit „immobilien“ (CMS_SITE=…).\n"); exit(1); }
require_once __DIR__ . '/immo-demo.php';
$args = array_slice($argv, 1);
$log = static function (string $m): void { echo $m, "\n"; };
if (in_array('--remove', $args, true)) { immo_demo_remove($log, true); \Core\PageCache::clear(); echo "Demo entfernt.\n"; exit(0); }
exit(immo_demo_install(in_array('--force', $args, true), $log) ? 0 : 1);
