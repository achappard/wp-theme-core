<?php
/**
 * Désactivation des mises à jour automatiques
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Désactive les mises à jour automatiques WordPress (core, extensions, thèmes, traductions).
 *
 * Avec `allow_minor_core: true`, les versions mineures du core (x.y.**z**, les correctifs de sécurité)
 * restent automatiques : seules les versions majeures sont bloquées, et le réglage du serveur
 * (`WP_AUTO_UPDATE_CORE`, `AUTOMATIC_UPDATER_DISABLED`) a le dernier mot.
 */
final class DisableUpdates implements FeatureInterface {

    /**
     * @param bool $allow_minor_core Laisse passer les mises à jour mineures (de sécurité) du core.
     */
    public function __construct(
        private readonly bool $allow_minor_core = false,
    ) {}

    /** Enregistre les filtres nécessaires à la désactivation des mises à jour automatiques. */
    public function register(): void {
        // Mises à jour automatiques du core. `auto_update_core` à false bloquerait aussi les
        // mineures : il n'est posé que si elles sont interdites.
        add_filter( 'allow_major_auto_core_updates', '__return_false' );

        if ( ! $this->allow_minor_core ) {
            add_filter( 'allow_minor_auto_core_updates', '__return_false' );
            add_filter( 'auto_update_core', '__return_false' );
        }

        // Mises à jour automatiques des extensions et des thèmes.
        add_filter( 'auto_update_plugin', '__return_false' );
        add_filter( 'auto_update_theme', '__return_false' );

        // Mises à jour automatiques des traductions.
        add_filter( 'auto_update_translation', '__return_false' );

        // Tests Site Health correspondants : le comportement est intentionnel.
        add_filter( 'site_status_tests', [ $this, 'remove_update_health_tests' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        if ( $this->allow_minor_core ) {
            return __( 'Désactive les mises à jour automatiques des extensions, des thèmes, des traductions et des versions majeures de WordPress. Les mises à jour de sécurité restent automatiques.', 'wp-theme-core' );
        }

        return __( 'Désactive toutes les mises à jour automatiques : WordPress, extensions, thèmes et traductions.', 'wp-theme-core' );
    }

    /**
     * Supprime les tests Site Health relatifs aux mises à jour pour éviter les faux avertissements.
     *
     * Le test `background_updates` est gardé quand les mineures sont autorisées : il signale alors
     * un vrai problème (droits d'écriture, dépôt VCS, constante qui les coupe côté serveur).
     *
     * @param array $tests Tests déclarés par le core et les extensions.
     * @return array
     */
    public function remove_update_health_tests( array $tests ): array {
        if ( ! $this->allow_minor_core ) {
            unset( $tests['async']['background_updates'] );
        }
        unset( $tests['direct']['plugin_theme_auto_updates'] );

        return $tests;
    }
}
