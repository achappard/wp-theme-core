<?php
/**
 * Exclusion individuelle des sitemaps XML
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Seo;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Retire du sitemap XML les contenus marqués comme exclus.
 *
 * **WordPress ne propose aucun mécanisme natif d'exclusion individuelle.** Le
 * filtre `wp_sitemaps_posts_entry` reçoit bien le `WP_Post`, mais seulement
 * pour modifier son rendu — retirer une entrée de la liste n'est pas possible
 * à ce stade. Le bon point d'extension est `wp_sitemaps_posts_query_args`
 * (une fois par type de contenu, avant construction de la `WP_Query` qui
 * liste les posts du sitemap — cf. `WP_Sitemaps_Posts::get_posts_query_args()`),
 * où l'on injecte un `meta_query` d'exclusion.
 *
 * **Ne rend rien éditable nulle part** — même remarque que `MetaDescription` :
 * cette classe lit `get_post_meta()`, jamais `register_post_meta()`.
 * `Seo\SeoPanel` rend le réglage éditable, via `self::META_KEY`.
 *
 * L'absence de meta (contenu jamais ouvert dans l'éditeur, ou `SeoPanel`
 * jamais enregistrée) vaut inclusion : le sitemap reste complet par défaut.
 */
final class SitemapExclusion implements FeatureInterface {

    /** Clé de la meta d'exclusion. Valeur `'1'` (stockage WordPress d'un booléen vrai) = exclu. */
    public const META_KEY = '_deefuse_seo_exclude_from_sitemap';

    /** Enregistre le filtre des arguments de requête du sitemap. */
    public function register(): void {
        add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'filter_query_args' ], 10, 2 );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Retire du sitemap XML les contenus marqués comme exclus.', 'wp-theme-core' );
    }

    /**
     * Ajoute la condition d'exclusion à la requête du sitemap.
     *
     * Se compose avec un `meta_query` déjà présent (posé par un autre filtre)
     * plutôt que de l'écraser : la condition d'exclusion est encapsulée dans
     * son propre groupe, combiné en `AND` avec le reste.
     *
     * @param  array<string, mixed> $args      Arguments de la `WP_Query` du sitemap.
     * @param  string               $post_type Type de contenu concerné.
     * @return array<string, mixed>
     */
    public function filter_query_args( array $args, string $post_type ): array {
        $not_excluded = [
            'relation' => 'OR',
            [
                'key'     => self::META_KEY,
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => self::META_KEY,
                'value'   => '1',
                'compare' => '!=',
            ],
        ];

        $args['meta_query'] = isset( $args['meta_query'] ) && is_array( $args['meta_query'] )
            ? [ 'relation' => 'AND', $not_excluded, $args['meta_query'] ]
            : $not_excluded;

        return $args;
    }
}
