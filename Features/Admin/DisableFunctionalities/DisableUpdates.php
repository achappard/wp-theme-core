<?php
/**
 * Désactivation des mises à jour automatiques
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Admin\DisableFunctionalities;

use Theme\Contracts\FeatureInterface;

/** Désactive toutes les mises à jour automatiques WordPress (core, extensions, thèmes, traductions). */
final class DisableUpdates implements FeatureInterface {

    /** Enregistre les filtres nécessaires à la désactivation des mises à jour automatiques. */
    public function register(): void {
        // Mises à jour automatiques du core, mineures comme majeures.
        add_filter( 'allow_minor_auto_core_updates', '__return_false' );
        add_filter( 'allow_major_auto_core_updates', '__return_false' );
        add_filter( 'auto_update_core', '__return_false' );

        // Mises à jour automatiques des extensions et des thèmes.
        add_filter( 'auto_update_plugin', '__return_false' );
        add_filter( 'auto_update_theme', '__return_false' );

        // Mises à jour automatiques des traductions.
        add_filter( 'auto_update_translation', '__return_false' );

        // Tests Site Health correspondants : le comportement est intentionnel.
        add_filter( 'site_status_tests', [ $this, 'remove_update_health_tests' ] );
    }

    /**
     * Supprime les tests Site Health relatifs aux mises à jour pour éviter les faux avertissements.
     *
     * @param array $tests Tests déclarés par le core et les extensions.
     * @return array
     */
    public function remove_update_health_tests( array $tests ): array {
        unset( $tests['async']['background_updates'] );
        unset( $tests['direct']['plugin_theme_auto_updates'] );

        return $tests;
    }
}
