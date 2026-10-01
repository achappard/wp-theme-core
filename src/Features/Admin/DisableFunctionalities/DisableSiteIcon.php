<?php
/**
 * Masquage de l'icône du site dans les réglages
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/** Masque la section « Icône du site » de la page Réglages > Général, inutile pour un site vitrine. */
final class DisableSiteIcon implements FeatureInterface {

    /** Enregistre le hook nécessaire au masquage de la section. */
    public function register(): void {
        add_action( 'admin_head', [ $this, 'hide_via_css' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Masque le réglage « Icône du site » de Réglages › Général.', 'wp-theme-core' );
    }

    /** Masque la ligne « Icône du site », uniquement sur la page Réglages > Général. */
    public function hide_via_css(): void {
        $screen = get_current_screen();

        if ( $screen && 'options-general' === $screen->id ) {
            echo '<style>tr.site-icon-section { display: none !important; }</style>';
        }
    }
}
