<?php
/**
 * Désactivation de la barre d'administration
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use WP_Admin_Bar;

/** Masque la barre d'administration côté front et supprime le logo WP dans l'admin. */
final class DisableAdminBar implements FeatureInterface {

    /** Enregistre les hooks nécessaires à la désactivation de la barre d'administration. */
    public function register(): void {
        add_action( 'after_setup_theme', [ $this, 'disable_admin_bar' ] );
        add_action( 'admin_bar_menu', [ $this, 'remove_wp_logo' ], 999 );
        add_filter( 'show_admin_bar', '__return_false' );
        add_action( 'admin_print_styles-profile.php', [ $this, 'hide_admin_bar_profile_option' ] );
    }

    /** Désactive la barre d'administration pour tous les visiteurs côté front. */
    public function disable_admin_bar(): void {
        show_admin_bar( false );
    }

    /**
     * Supprime le logo WordPress et son menu déroulant de la barre d'administration.
     *
     * @param WP_Admin_Bar $wp_admin_bar Barre d'administration en cours de construction.
     */
    public function remove_wp_logo( WP_Admin_Bar $wp_admin_bar ): void {
        $wp_admin_bar->remove_node( 'wp-logo' );
    }

    /** Masque l'option « Barre d'outils » du profil utilisateur, devenue sans effet. */
    public function hide_admin_bar_profile_option(): void {
        echo '<style>.show-admin-bar { display: none !important; }</style>';
    }
}
