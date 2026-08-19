<?php

declare(strict_types=1);

namespace Theme\Features\Admin\DisableFunctionalities;

use Theme\Contracts\FeatureInterface;
use WP_Admin_Bar;

/** Désactive intégralement le système de commentaires WordPress (front, admin, widgets, dashboard). */
class DisableComment implements FeatureInterface
{

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        // Désactive le support des commentaires
        add_action( 'admin_init', [ $this, 'disable_comments_admin' ] );
        add_action( 'init', [ $this, 'disable_comments_post_types' ] );

        // Ferme les commentaires sur le front-end
        add_filter( 'comments_open', '__return_false', 20, 2 );
        add_filter( 'pings_open', '__return_false', 20, 2 );

        // Cache les commentaires existants
        add_filter( 'comments_array', '__return_empty_array', 10, 2 );

        // Retire le menu des commentaires de l'admin
        add_action( 'admin_menu', [ $this, 'remove_comments_menu' ] );

        // Retire les commentaires de la barre d'administration
        add_action( 'admin_bar_menu', [ $this, 'remove_comments_admin_bar' ], 999 );

        // Retire le widget des commentaires
        add_action( 'widgets_init', [ $this, 'disable_comments_widget' ] );

        // Retire le support des commentaires du tableau de bord
        add_action( 'wp_dashboard_setup', [ $this, 'remove_dashboard_comments' ] );
    }

    /**
     * Désactive les commentaires dans l'administration
     */
    public function disable_comments_admin(): void {
        // Redirige toutes les tentatives d'accès à la page des commentaires
        global $pagenow;

        if ( in_array( $pagenow, [ 'edit-comments.php', 'options-discussion.php' ], true ) ) {
            wp_redirect( admin_url() );
            exit;
        }

        // Retire les métaboxes des commentaires
        remove_meta_box( 'commentstatusdiv', 'post', 'normal' );
        remove_meta_box( 'commentsdiv', 'post', 'normal' );
        remove_meta_box( 'trackbacksdiv', 'post', 'normal' );
    }

    /**
     * Désactive les commentaires pour tous les types de publication
     */
    public function disable_comments_post_types(): void {
        $post_types = get_post_types();

        foreach ( $post_types as $post_type ) {
            if ( post_type_supports( $post_type, 'comments' ) ) {
                remove_post_type_support( $post_type, 'comments' );
                remove_post_type_support( $post_type, 'trackbacks' );
            }
        }
    }

    /**
     * Retire le menu des commentaires et la page Réglages > Discussion de l'admin
     */
    public function remove_comments_menu(): void {
        remove_menu_page( 'edit-comments.php' );
        remove_submenu_page( 'options-general.php', 'options-discussion.php' );
    }

    /**
     * Retire les commentaires de la barre d'administration
     */
    public function remove_comments_admin_bar( WP_Admin_Bar $wp_admin_bar ): void {
        $wp_admin_bar->remove_node( 'comments' );
    }

    /**
     * Désactive le widget des commentaires
     */
    public function disable_comments_widget(): void {
        unregister_widget( 'WP_Widget_Recent_Comments' );
    }

    /**
     * Retire le widget des commentaires du tableau de bord
     */
    public function remove_dashboard_comments(): void {
        remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
    }
}