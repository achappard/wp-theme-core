<?php

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Front\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Supprime Dashicons pour les visiteurs non connectés.
 *
 * Dashicons est chargé par défaut même en front-end ; inutile pour les visiteurs
 * puisque la barre d'administration est désactivée (DisableAdminBar).
 */
class DisableDashicons implements FeatureInterface
{

    /** Enregistre le hook de défilement en fin de queue (priorité 100). */
    public function register(): void
    {
        add_action( 'wp_enqueue_scripts', [ $this, 'dequeue' ], 100 );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string
    {
        return __( 'Ne charge plus la police d\'icônes Dashicons pour les visiteurs non connectés.', 'wp-theme-core' );
    }

    /** Retire Dashicons de la queue uniquement pour les visiteurs non connectés. */
    public function dequeue(): void {
        if ( ! is_user_logged_in() ) {
            wp_dequeue_style( 'dashicons' );
            wp_deregister_style( 'dashicons' );
        }
    }
}