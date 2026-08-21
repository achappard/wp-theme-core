<?php
/**
 * Nettoyage des modules par défaut du tableau de bord
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/** Supprime les modules inutiles du tableau de bord pour simplifier l'interface d'administration. */
final class DisableDefaultDashboardWidgets implements FeatureInterface {

    /** Enregistre le hook de nettoyage du tableau de bord. */
    public function register(): void {
        add_action( 'wp_dashboard_setup', [ $this, 'remove_dashboard_widgets' ] );
    }

    /**
     * Retire le panneau de bienvenue et les modules par défaut du tableau de bord.
     *
     * `wp_dashboard_setup` se déclenche avant le rendu de la page : le
     * `remove_action()` sur le panneau de bienvenue y est donc encore effectif.
     */
    public function remove_dashboard_widgets(): void {
        // Panneau « Bienvenue sur WordPress ».
        remove_action( 'welcome_panel', 'wp_welcome_panel' );

        remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );   // Brouillon rapide
        remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );    // Activité
        remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );       // Événements et actualités WordPress
        remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );     // Autres actualités WordPress
    }
}
