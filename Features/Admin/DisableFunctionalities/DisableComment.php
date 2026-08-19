<?php
/**
 * Désactivation du système de commentaires
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Admin\DisableFunctionalities;

use Theme\Contracts\FeatureInterface;
use WP_Admin_Bar;

/** Désactive intégralement le système de commentaires (front, admin, widgets, tableau de bord). */
final class DisableComment implements FeatureInterface {

    /** Enregistre les hooks nécessaires à la désactivation des commentaires. */
    public function register(): void {
        // Support des commentaires côté administration.
        add_action( 'admin_init', [ $this, 'disable_comments_admin' ] );
        add_action( 'init', [ $this, 'disable_comments_post_types' ] );

        // Fermeture des commentaires côté front.
        add_filter( 'comments_open', '__return_false', 20, 2 );
        add_filter( 'pings_open', '__return_false', 20, 2 );

        // Masquage des commentaires déjà enregistrés.
        add_filter( 'comments_array', '__return_empty_array', 10, 2 );

        // Entrées d'interface : menu, barre d'administration, widget, tableau de bord.
        add_action( 'admin_menu', [ $this, 'remove_comments_menu' ] );
        add_action( 'admin_bar_menu', [ $this, 'remove_comments_admin_bar' ], 999 );
        add_action( 'widgets_init', [ $this, 'disable_comments_widget' ] );
        add_action( 'wp_dashboard_setup', [ $this, 'remove_dashboard_comments' ] );
    }

    /** Redirige les pages de commentaires et retire les métaboxes associées. */
    public function disable_comments_admin(): void {
        global $pagenow;

        if ( in_array( $pagenow, [ 'edit-comments.php', 'options-discussion.php' ], true ) ) {
            wp_redirect( admin_url() );
            exit;
        }

        remove_meta_box( 'commentstatusdiv', 'post', 'normal' );
        remove_meta_box( 'commentsdiv', 'post', 'normal' );
        remove_meta_box( 'trackbacksdiv', 'post', 'normal' );
    }

    /** Retire le support des commentaires et des rétroliens de tous les types de contenu. */
    public function disable_comments_post_types(): void {
        $post_types = get_post_types();

        foreach ( $post_types as $post_type ) {
            if ( post_type_supports( $post_type, 'comments' ) ) {
                remove_post_type_support( $post_type, 'comments' );
                remove_post_type_support( $post_type, 'trackbacks' );
            }
        }
    }

    /** Retire le menu Commentaires et la page Réglages > Discussion. */
    public function remove_comments_menu(): void {
        remove_menu_page( 'edit-comments.php' );
        remove_submenu_page( 'options-general.php', 'options-discussion.php' );
    }

    /**
     * Retire les commentaires de la barre d'administration.
     *
     * @param WP_Admin_Bar $wp_admin_bar Barre d'administration en cours de construction.
     */
    public function remove_comments_admin_bar( WP_Admin_Bar $wp_admin_bar ): void {
        $wp_admin_bar->remove_node( 'comments' );
    }

    /** Désenregistre le widget des commentaires récents. */
    public function disable_comments_widget(): void {
        unregister_widget( 'WP_Widget_Recent_Comments' );
    }

    /** Retire le module Commentaires récents du tableau de bord. */
    public function remove_dashboard_comments(): void {
        remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
    }
}
