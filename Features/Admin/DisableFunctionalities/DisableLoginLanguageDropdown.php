<?php
/**
 * Suppression du sélecteur de langue au login
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Admin\DisableFunctionalities;

use Theme\Contracts\FeatureInterface;

/** Supprime le sélecteur de langue sur la page de connexion WordPress. */
final class DisableLoginLanguageDropdown implements FeatureInterface {

    /** Enregistre le filtre masquant le sélecteur de langue. */
    public function register(): void {
        add_filter( 'login_display_language_dropdown', '__return_false' );
    }
}
