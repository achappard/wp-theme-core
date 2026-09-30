<?php
/**
 * Désactivation des services de mise à jour
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Désactive les « Services de mise à jour » (pings de publication).
 *
 * À chaque article publié, WordPress programme `do_all_pings`, dont `generic_ping()` envoie un
 * ping XML-RPC `weblogUpdates.ping` à chaque URL de l'option `ping_sites` (par défaut
 * Ping-O-Matic). Ces annuaires de blogs n'ont plus d'intérêt : les moteurs découvrent les
 * contenus par le sitemap. Reste une requête sortante inutile à chaque publication.
 *
 * Depuis WordPress 7.1, le core retire déjà ces pings hors production
 * (`wp_should_disable_pings_for_environment()`) ; cette feature les coupe aussi en production.
 *
 * Deux leviers :
 * - le filtre `enable_update_services_configuration` masque la section de
 *   *Réglages › Écriture* et son onglet d'aide — c'est le réglage que le core applique
 *   lui-même en multisite (`ms-default-filters.php`) ;
 * - `generic_ping` est retiré de `do_all_pings`. Le core l'y accroche dans
 *   `default-filters.php`, chargé avant le thème : le retrait peut se faire dès `register()`.
 *   Pingbacks et rétroliens, aussi déclenchés par `do_all_pings`, ne sont pas concernés.
 *
 * L'option `ping_sites` reste en base, simplement inutilisée.
 */
final class DisableUpdateServices implements FeatureInterface {

    /** Masque la section des réglages et retire l'envoi des pings. */
    public function register(): void {
        add_filter( 'enable_update_services_configuration', '__return_false' );
        remove_action( 'do_all_pings', 'generic_ping' );
    }
}
