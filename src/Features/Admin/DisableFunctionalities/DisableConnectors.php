<?php
/**
 * Désactivation de la page Connectors
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Retire la page « Connectors » (Réglages → Connectors), apparue en WordPress 7.0.
 *
 * Cette page configure les connexions aux fournisseurs d'IA via le client natif
 * (`\WordPress\AiClient\AiClient`). Le projet n'en fait aucun usage — sur cette installation
 * elle affiche d'ailleurs une erreur, ses fichiers de build n'étant pas présents. On la retire
 * pour ne pas exposer un réglage inutile.
 *
 * Deux volets, sur le modèle de `DisableComment` :
 * - `admin_menu` retire l'entrée du sous-menu Réglages ;
 * - `admin_init` bloque l'accès direct par l'URL `options-connectors.php`, qu'un simple
 *   masquage de menu laisserait atteignable.
 */
final class DisableConnectors implements FeatureInterface {

    /** Enregistre les hooks de désactivation de la page Connectors. */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'remove_menu' ], 999 );
        add_action( 'admin_init', [ $this, 'block_direct_access' ] );
    }

    /** Retire l'entrée « Connectors » du sous-menu Réglages. */
    public function remove_menu(): void {
        remove_submenu_page( 'options-general.php', 'options-connectors.php' );
    }

    /** Redirige toute tentative d'accès direct à la page vers le tableau de bord. */
    public function block_direct_access(): void {
        global $pagenow;

        if ( 'options-connectors.php' === $pagenow ) {
            wp_safe_redirect( admin_url() );
            exit;
        }
    }
}
