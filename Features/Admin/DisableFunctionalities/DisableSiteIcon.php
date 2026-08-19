<?php
/**
 * Masquage de l'icône du site dans les réglages
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Admin\DisableFunctionalities;

use Theme\Contracts\FeatureInterface;

/** Masque la section « Icône du site » de la page Réglages > Général, inutile pour un site vitrine. */
final class DisableSiteIcon implements FeatureInterface {

    /** Enregistre le hook nécessaire au masquage de la section. */
    public function register(): void {
        add_action( 'admin_head', [ $this, 'hide_via_css' ] );
    }

    /** Masque la ligne « Icône du site », uniquement sur la page Réglages > Général. */
    public function hide_via_css(): void {
        $screen = get_current_screen();

        if ( $screen && 'options-general' === $screen->id ) {
            echo '<style>tr.site-icon-section { display: none !important; }</style>';
        }
    }
}
