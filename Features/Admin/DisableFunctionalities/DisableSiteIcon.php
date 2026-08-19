<?php

declare(strict_types=1);

namespace Theme\Features\Admin\DisableFunctionalities;

use Theme\Contracts\FeatureInterface;

/** Masque la section "Icône du site" dans la page Réglages > Général, inutile pour un site vitrine. */
class DisableSiteIcon implements FeatureInterface
{

    /** Enregistre le hook nécessaire au masquage de la section. */
    public function register(): void {
        add_action( 'admin_head', [ $this, 'hide_via_css' ] );
    }

    /** Injecte un style inline pour masquer la ligne icône du site, uniquement sur la page Réglages > Général. */
    public function hide_via_css(): void {
        $screen = get_current_screen();
        if ( $screen && 'options-general' === $screen->id ) {
            echo '<style>tr.site-icon-section { display: none !important; }</style>';
        }
    }
}