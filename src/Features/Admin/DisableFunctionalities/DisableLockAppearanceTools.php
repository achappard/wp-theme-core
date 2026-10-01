<?php
/**
 * Verrouillage des outils de personnalisation visuelle
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Bloque l'accès aux outils de personnalisation visuelle — Personnaliser, Éditeur de site et
 * Polices — pour le thème actif, tout en laissant l'« Aperçu en direct » des autres thèmes.
 *
 * L'aperçu d'un thème passe par ces mêmes écrans, avec le thème prévisualisé en paramètre :
 * `customize.php?theme={slug}` pour un thème classique, `site-editor.php?wp_theme_preview={slug}`
 * pour un thème à blocs. Retirer la capability `customize` couperait donc l'aperçu avec le
 * Personnaliseur : on filtre plutôt l'accès sur ce paramètre, en ne laissant passer que les
 * aperçus d'un **autre** thème que le thème actif.
 *
 * Sur Apparence > Thèmes, le bouton « Personnaliser » du thème actif (carte et fiche détaillée)
 * et les liens d'en-tête / d'arrière-plan qui l'accompagnent sont masqués en CSS : ils mèneraient
 * sinon à la redirection, sans explication.
 */
final class DisableLockAppearanceTools implements FeatureInterface {

    /** Écrans de personnalisation visuelle, et paramètre désignant le thème prévisualisé sur chacun. */
    private const PREVIEW_PARAMS = [
        'customize.php'    => 'theme',
        'site-editor.php'  => 'wp_theme_preview',
        'font-library.php' => null,
    ];

    /** Enregistre les hooks de suppression des menus, de redirection et de masquage. */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'remove_menus' ], 999 );
        add_action( 'admin_init', [ $this, 'redirect' ] );
        add_action( 'admin_head-themes.php', [ $this, 'hide_active_theme_customize' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Bloque l\'accès au Personnaliseur, à l\'Éditeur de site et aux Polices pour le thème actif, sans empêcher l\'aperçu des autres thèmes.', 'wp-theme-core' );
    }

    /** Supprime les entrées Personnaliser, Éditeur et Polices du sous-menu Apparence. */
    public function remove_menus(): void {
        global $submenu;

        if ( ! isset( $submenu['themes.php'] ) ) {
            return;
        }

        foreach ( $submenu['themes.php'] as $key => $item ) {
            if ( ! isset( $item[2] ) ) {
                continue;
            }

            if (
                str_starts_with( $item[2], 'customize.php' ) ||
                str_starts_with( $item[2], 'site-editor.php' ) ||
                str_starts_with( $item[2], 'font-library.php' )
            ) {
                unset( $submenu['themes.php'][ $key ] );
            }
        }
    }

    /**
     * Redirige vers Apparence > Thèmes en cas d'accès au Personnaliseur, à l'éditeur de site
     * ou aux polices — sauf pour l'aperçu d'un autre thème que le thème actif.
     */
    public function redirect(): void {
        global $pagenow;

        if ( ! array_key_exists( $pagenow, self::PREVIEW_PARAMS ) || $this->is_other_theme_preview( $pagenow ) ) {
            return;
        }

        wp_safe_redirect( admin_url( 'themes.php' ) );
        exit;
    }

    /** Masque le bouton « Personnaliser » du thème actif et ses liens associés, sur la carte comme dans la fiche détaillée. */
    public function hide_active_theme_customize(): void {
        echo '<style>.theme-browser .theme.active .load-customize, .theme-overlay .active-theme > .button { display: none !important; }</style>';
    }

    /** Indique si la requête prévisualise un thème installé autre que le thème actif. */
    private function is_other_theme_preview( string $page ): bool {
        $param = self::PREVIEW_PARAMS[ $page ];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule, décide d'une redirection.
        $slug = null !== $param && isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';

        return '' !== $slug
            && get_stylesheet() !== $slug
            && wp_get_theme( $slug )->exists();
    }
}
