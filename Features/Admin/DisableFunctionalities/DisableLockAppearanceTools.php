<?php
/**
 * Verrouillage des outils de personnalisation visuelle
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Admin\DisableFunctionalities;

use Theme\Contracts\FeatureInterface;

/** Bloque l'accès aux outils de personnalisation visuelle : Personnaliser, Éditeur de site et Polices. */
final class DisableLockAppearanceTools implements FeatureInterface {

    /** Enregistre les hooks de suppression des menus et de redirection. */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'remove_menus' ], 999 );
        add_action( 'admin_init', [ $this, 'redirect' ] );
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

    /** Redirige vers Apparence > Thèmes en cas d'accès direct au Personnaliseur ou à l'éditeur de site. */
    public function redirect(): void {
        global $pagenow;

        if ( in_array( $pagenow, [ 'customize.php', 'site-editor.php', 'font-library.php' ], true ) ) {
            wp_redirect( admin_url( 'themes.php' ) );
            exit;
        }
    }
}
