<?php
/**
 * Suppression du sélecteur de langue au login
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/** Supprime le sélecteur de langue sur la page de connexion WordPress. */
final class DisableLoginLanguageDropdown implements FeatureInterface {

    /** Enregistre le filtre masquant le sélecteur de langue. */
    public function register(): void {
        add_filter( 'login_display_language_dropdown', '__return_false' );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Retire le sélecteur de langue de la page de connexion.', 'wp-theme-core' );
    }
}
