<?php

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Front;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Supprime les balises inutiles ou sensibles injectées par WordPress dans le <head>.
 *
 * Réduit la surface d'information exposée (version WP, endpoints REST)
 * et allège le HTML envoyé au navigateur.
 */
final class CleanHead implements FeatureInterface {

    /** Enregistre les suppressions de hooks sur wp_head. */
    public function register(): void
    {
        // Supprime la version WordPress (sécurité — évite de révéler la version)
        remove_action( 'wp_head', 'wp_generator' );
        add_filter( 'the_generator', '__return_empty_string' );

        // Supprime Windows Live Writer manifest (inutile)
        remove_action( 'wp_head', 'wlwmanifest_link' );

        // Supprime RSD (Really Simple Discovery — inutile)
        remove_action( 'wp_head', 'rsd_link' );

        // Supprime le shortlink (inutile pour le SEO)
        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );

        // Supprime le lien de découverte de l'API REST (expose l'endpoint)
        remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );

        // Supprime les liens de découverte oEmbed
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head', 'wp_oembed_add_host_js'         );

        // Supprime les liens prev/next article (inutiles sauf blog)
        remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );

        // Supprime les liens RSS (flux articles + flux commentaires)
        remove_action( 'wp_head', 'feed_links',       2 );
        remove_action( 'wp_head', 'feed_links_extra', 3 );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string
    {
        return __( 'Retire du code des pages les balises inutiles ou qui révèlent la version de WordPress.', 'wp-theme-core' );
    }
}