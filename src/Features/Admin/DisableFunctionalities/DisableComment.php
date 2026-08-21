<?php
/**
 * Désactivation du système de commentaires
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use WP_Admin_Bar;
use WP_REST_Response;

/** Désactive intégralement le système de commentaires (front, admin, widgets, tableau de bord, API REST). */
final class DisableComment implements FeatureInterface {

    /** Préfixe des routes REST servies par `WP_REST_Comments_Controller`. */
    private const REST_ROUTE_PREFIX = '/wp/v2/comments';

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

        // Fermeture des routes de l'API REST.
        add_filter( 'rest_endpoints', [ $this, 'remove_comment_rest_routes' ] );
        add_action( 'rest_api_init', [ $this, 'filter_content_rest_links' ] );

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

    /**
     * Retire de l'API REST les routes dédiées aux commentaires.
     *
     * Deux routes sont concernées, toutes deux servies par `WP_REST_Comments_Controller` :
     * `/wp/v2/comments` (liste des commentaires et dépôt d'un nouveau) et
     * `/wp/v2/comments/(?P<id>[\d]+)` (lecture, modification, suppression). Le test porte sur
     * leur préfixe commun plutôt que sur ces deux chaînes exactes : la seconde contient une
     * expression régulière du core, qu'une version future peut réécrire — la route
     * réapparaîtrait alors sans que rien ne le signale.
     *
     * Retirées ici, elles disparaissent également de l'index de l'API (`/wp-json/wp/v2`),
     * construit à partir des mêmes données, et répondent `rest_no_route` (404).
     *
     * @param array $endpoints Routes enregistrées, indexées par motif.
     * @return array
     */
    public function remove_comment_rest_routes( array $endpoints ): array {
        foreach ( array_keys( $endpoints ) as $route ) {
            if ( str_starts_with( (string) $route, self::REST_ROUTE_PREFIX ) ) {
                unset( $endpoints[ $route ] );
            }
        }

        return $endpoints;
    }

    /**
     * Branche le nettoyage des liens sur les réponses REST de tous les contenus.
     *
     * Le lien `replies` est posé par `WP_REST_Posts_Controller`, et le filtre qui permet
     * de l'intercepter est propre à chaque type de contenu (`rest_prepare_post`,
     * `rest_prepare_page`, `rest_prepare_{cpt}`…) : il faut donc les brancher un à un.
     */
    public function filter_content_rest_links(): void {
        foreach ( get_post_types( [ 'show_in_rest' => true ], 'names' ) as $post_type ) {
            add_filter( "rest_prepare_{$post_type}", [ $this, 'remove_replies_link' ] );
        }
    }

    /**
     * Retire des réponses REST le lien `replies`, qui pointe vers les commentaires.
     *
     * Le core ajoute ce lien à tout article et toute page **sans condition** — le nom du
     * type est écrit en dur dans `WP_REST_Posts_Controller::prepare_links()`, en marge du
     * test sur le support `comments`. Retirer ce support ne suffit donc pas : sans ce
     * filtre, l'API continuerait d'annoncer `/wp/v2/comments?post=X`, désormais en 404.
     *
     * Le paramètre n'est pas typé `WP_REST_Response` : les callbacks branchés avant le
     * nôtre peuvent légitimement rendre un tableau, que `rest_ensure_response()` convertira
     * plus tard. On laisse alors la réponse intacte plutôt que de lever une TypeError.
     *
     * @param mixed $response Réponse préparée pour un contenu.
     * @return mixed
     */
    public function remove_replies_link( mixed $response ): mixed {
        if ( $response instanceof WP_REST_Response ) {
            $response->remove_link( 'replies' );
        }

        return $response;
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
