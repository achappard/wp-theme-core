<?php
/**
 * Blocage de l'énumération des comptes utilisateurs
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Security;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use WP_REST_Response;

/**
 * Ferme au public tous les canaux par lesquels WordPress publie l'identité de ses comptes.
 *
 * Le core ne livre à un visiteur anonyme ni mot de passe ni adresse e-mail, mais il publie le
 * `user_nicename` de chaque auteur — dérivé du login à la création du compte. Associé à
 * l'`ID 1`, presque toujours celui de l'administrateur, cela fournit la moitié du couple
 * attendu par le formulaire de connexion. Le core assume ce comportement ; ce n'est donc pas
 * une faille à corriger, mais une surface d'attaque dont ce projet n'a aucun usage.
 *
 * Six canaux la publient, et les fermer à moitié ne sert à rien — un scanner les essaie
 * tous :
 *
 * 1. les routes REST `/wp/v2/users` (liste, détail, `/me`, mots de passe d'application) ;
 * 2. le lien `_links.author` posé sur chaque contenu par l'API REST ;
 * 3. les archives d'auteur (`/?author=1`), qui révèlent le slug par simple redirection ;
 * 4. la réponse oEmbed d'un contenu, qui nomme son auteur et lie vers son archive ;
 * 5. le sitemap des utilisateurs (`wp-sitemap-users-1.xml`), qui liste ces archives ;
 * 6. les flux RSS et Atom, qui nomment l'auteur de chaque contenu.
 *
 * **Tout est conditionné à l'absence de session**, sauf les archives d'auteur : le projet n'en
 * expose aucune, elles n'ont donc pas lieu d'exister pour qui que ce soit. Un utilisateur
 * connecté retrouve le reste intact, ce dont l'éditeur a besoin pour fonctionner.
 *
 * Ce durcissement ne remplace ni un mot de passe fort ni la limitation des tentatives de
 * connexion : il retire l'amorce, pas la cible.
 */
final class DisableUserEnumeration implements FeatureInterface {

    /** Préfixe des routes REST servies par `WP_REST_Users_Controller`. */
    private const REST_ROUTE_PREFIX = '/wp/v2/users';

    /** Enregistre les hooks de fermeture des six canaux. */
    public function register(): void {
        // API REST : routes des utilisateurs, puis liens qui y renvoient.
        add_filter( 'rest_endpoints', [ $this, 'remove_user_rest_routes' ] );
        add_action( 'rest_api_init', [ $this, 'filter_content_rest_links' ] );

        // Archives d'auteur et sitemap. Priorité 1 : impérativement avant
        // `redirect_canonical()` et `WP_Sitemaps::render_sitemaps()`, tous deux à 10.
        add_action( 'template_redirect', [ $this, 'force_404_on_user_routes' ], 1 );

        // Représentations publiques d'un contenu : oEmbed, sitemap, flux.
        add_filter( 'oembed_response_data', [ $this, 'remove_author_from_oembed' ] );
        add_filter( 'wp_sitemaps_add_provider', [ $this, 'remove_users_sitemap' ], 10, 2 );
        add_filter( 'the_author', [ $this, 'replace_feed_author_name' ] );
        add_filter( 'get_the_author_user_url', [ $this, 'remove_feed_author_url' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Empêche les visiteurs de découvrir les identifiants des comptes (API, archives d\'auteur, flux RSS, sitemap).', 'wp-theme-core' );
    }

    /**
     * Retire les routes REST des utilisateurs pour les visiteurs non authentifiés.
     *
     * Six routes portent ce préfixe (liste, détail, `/me` et les mots de passe
     * d'application) : on les filtre toutes, plutôt que de les énumérer.
     *
     * Contrairement aux commentaires, ces routes ne peuvent pas être supprimées purement et
     * simplement : l'éditeur Gutenberg interroge `/wp/v2/users` pour son sélecteur d'auteur.
     * D'où le test sur l'authentification, et non sur la capability `list_users` — un
     * contributeur, qui ne l'a pas, doit garder un éditeur fonctionnel. L'utilisateur courant
     * est déjà résolu à ce stade : `WP_REST_Server::serve_request()` authentifie avant de
     * router.
     *
     * Retirer la route plutôt que renvoyer une erreur d'autorisation a un mérite : la réponse
     * est un 404 `rest_no_route`, qui ne confirme même pas l'existence d'un compte.
     *
     * @param array $endpoints Routes enregistrées, indexées par motif.
     * @return array
     */
    public function remove_user_rest_routes( array $endpoints ): array {
        if ( is_user_logged_in() ) {
            return $endpoints;
        }

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
     * Le filtre permettant d'intercepter ces liens est propre à chaque type de contenu
     * (`rest_prepare_post`, `rest_prepare_page`, `rest_prepare_{cpt}`…) : il faut donc les
     * brancher un à un.
     */
    public function filter_content_rest_links(): void {
        if ( is_user_logged_in() ) {
            return;
        }

        foreach ( get_post_types( [ 'show_in_rest' => true ], 'names' ) as $post_type ) {
            add_filter( "rest_prepare_{$post_type}", [ $this, 'remove_author_link' ] );
        }
    }

    /**
     * Retire des réponses REST le lien `author`, qui désigne un compte.
     *
     * Ce lien porte l'`ID` de l'auteur et pointe vers `/wp/v2/users/{id}`, route désormais
     * absente pour un visiteur anonyme : le laisser reviendrait à annoncer un compte par une
     * URL morte. Il est également marqué `embeddable`, donc suivi par tout appel `?_embed`.
     *
     * Le paramètre n'est pas typé `WP_REST_Response` : les callbacks branchés avant le nôtre
     * peuvent légitimement rendre un tableau, que `rest_ensure_response()` convertira plus
     * tard. On laisse alors la réponse intacte plutôt que de lever une TypeError.
     *
     * @param mixed $response Réponse préparée pour un contenu.
     * @return mixed
     */
    public function remove_author_link( mixed $response ): mixed {
        if ( $response instanceof WP_REST_Response ) {
            $response->remove_link( 'author' );
        }

        return $response;
    }

    /**
     * Transforme en page 404 les URL qui parlent des utilisateurs.
     *
     * Deux cas. Les **archives d'auteur** d'abord : le projet n'en expose aucune, la page
     * n'existe donc pas et un 404 est plus juste qu'une redirection vers l'accueil.
     * L'URL du **sitemap des utilisateurs** ensuite : son fournisseur est retiré plus bas,
     * mais `WP_Sitemaps::render_sitemaps()` se contente alors de rendre la main
     * (`if ( ! $provider ) { return; }`), ce qui fait répondre l'accueil en 200 à
     * `wp-sitemap-users-1.xml`. Aucune donnée ne fuit, mais l'URL doit se comporter comme ce
     * qu'elle est devenue : une page absente.
     *
     * **La priorité 1 n'est pas décorative.** Sur `/?author=1`, `redirect_canonical()`
     * (`template_redirect`, priorité 10) répond un 301 dont l'en-tête `Location` contient
     * `/author/{slug}/` : l'énumération réussit alors sans même que la page soit chargée. Il
     * faut donc court-circuiter avant lui, sans quoi ce blocage ne bloque rien.
     */
    public function force_404_on_user_routes(): void {
        if ( ! is_author() && 'users' !== get_query_var( 'sitemap' ) ) {
            return;
        }

        global $wp_query;

        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
    }

    /**
     * Retire l'auteur des réponses oEmbed.
     *
     * Interrogée sans authentification, la route `/wp-json/oembed/1.0/embed` renvoie le
     * `display_name` de l'auteur et l'URL de son archive — donc son slug. La spécification
     * oEmbed donne `author_name` et `author_url` pour facultatifs : les supprimer laisse une
     * réponse valide, que les consommateurs affichent sans mention d'auteur.
     *
     * @param mixed $data Données de la réponse oEmbed.
     * @return mixed
     */
    public function remove_author_from_oembed( mixed $data ): mixed {
        if ( ! is_array( $data ) ) {
            return $data;
        }

        unset( $data['author_name'], $data['author_url'] );

        return $data;
    }

    /**
     * Empêche l'enregistrement du fournisseur de sitemap des utilisateurs.
     *
     * `wp-sitemap-users-1.xml` liste les archives d'auteur, donc les slugs. Le registre du
     * core écarte tout fournisseur qui n'est pas une instance de `WP_Sitemaps_Provider` :
     * rendre `null` suffit à retirer la section, et l'index n'y renvoie plus.
     *
     * @param mixed  $provider Fournisseur sur le point d'être enregistré.
     * @param string $name     Nom du fournisseur.
     * @return mixed
     */
    public function remove_users_sitemap( mixed $provider, string $name ): mixed {
        return 'users' === $name ? null : $provider;
    }

    /**
     * Remplace, dans les flux, le nom de l'auteur par celui du site.
     *
     * RSS 2.0 écrit `<dc:creator>` et Atom `<author><name>` à chaque élément, toujours, sans
     * qu'aucun réglage ne permette de s'en passer : rendre une chaîne vide produirait un flux
     * mal formé. Le nom du site est la seule valeur qui garde le format correct sans désigner
     * personne. Hors flux, le filtre est transparent — un template qui affiche l'auteur d'un
     * article continue de fonctionner.
     *
     * @param mixed $display_name Nom affiché de l'auteur.
     * @return mixed
     */
    public function replace_feed_author_name( mixed $display_name ): mixed {
        return is_feed() ? get_bloginfo( 'name' ) : $display_name;
    }

    /**
     * Retire, dans les flux Atom, l'URL personnelle déclarée au profil de l'auteur.
     *
     * Le hook est bien `get_the_author_user_url`, et non `get_the_author_url` :
     * `get_the_author_meta()` préfixe `user_` aux champs de la table des utilisateurs avant
     * de composer le nom du filtre. Branché sur la forme sans préfixe, le filtre ne serait
     * jamais appelé — et rien ne le signalerait.
     *
     * @param mixed $url URL du profil de l'auteur.
     * @return mixed
     */
    public function remove_feed_author_url( mixed $url ): mixed {
        return is_feed() ? '' : $url;
    }
}
