<?php

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Front\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Désactive l'API Heartbeat de WordPress côté front-end.
 *
 * Heartbeat envoie des requêtes AJAX périodiques au serveur (toutes les 15-60 s)
 * pour la sauvegarde automatique et le verrouillage de posts — inutile en dehors
 * de l'administration et consommateur de ressources serveur.
 */
class DisableHeartbeat implements FeatureInterface
{

    /** Enregistre le hook de désenregistrement au moment de l'initialisation. */
    public function register(): void
    {
        add_action( 'init', [ $this, 'disableHeartbeat' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string
    {
        return __( 'Désactive les requêtes périodiques Heartbeat sur le site public.', 'wp-theme-core' );
    }

    /** Désenregistre le script heartbeat hors admin (poll AJAX inutile sur le front). */
    public function disableHeartbeat(): void {
        if ( ! is_admin() ) {
            wp_dequeue_script( 'heartbeat' );
            wp_deregister_script( 'heartbeat' );
        }
    }
}