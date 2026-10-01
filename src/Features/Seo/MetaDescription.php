<?php
/**
 * Balise meta description
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Seo;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Sort `<meta name="description">` sur les contenus singuliers, à partir d'une
 * meta de post, avec repli sur l'extrait de l'article.
 *
 * **Ne rend la meta éditable nulle part.** Cette classe se contente de lire
 * `get_post_meta()` — jamais `register_post_meta()`. C'est `Seo\SeoPanel` qui
 * rend la saisie possible depuis l'éditeur de blocs, en s'appuyant sur
 * `self::META_KEY`. La dépendance va donc dans un seul sens : `SeoPanel`
 * connaît cette classe (sa constante), cette classe ignore tout de `SeoPanel`
 * — la balise se génère normalement même si `SeoPanel` n'est jamais
 * enregistrée (à ceci près qu'aucune interface n'existe alors pour renseigner
 * la meta, qui resterait vide, faisant retomber la classe sur son repli).
 *
 * `resolve_description()` est exposée en `public static` : `Seo\OpenGraph`
 * s'appuie dessus pour sa propre balise `og:description`, plutôt que de
 * dupliquer la logique de repli.
 *
 * Attention : un plugin SEO (Yoast, Rank Math) génère sa propre balise
 * description et rendrait cette feature redondante — pas de détection
 * automatique, à retirer manuellement du provider en pareil cas.
 */
final class MetaDescription implements FeatureInterface {

    /** Clé de la meta stockant la description saisie. */
    public const META_KEY = '_deefuse_seo_description';

    /** Enregistre l'affichage de la balise dans le `<head>`. */
    public function register(): void {
        add_action( 'wp_head', [ $this, 'render' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Ajoute aux pages la meta description lue par les moteurs de recherche, ou à défaut l\'extrait du contenu.', 'wp-theme-core' );
    }

    /** Affiche la balise, uniquement sur un contenu singulier. */
    public function render(): void {
        if ( ! is_singular() ) {
            return;
        }

        $description = self::resolve_description( get_queried_object_id() );

        if ( '' === $description ) {
            return;
        }

        printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
    }

    /**
     * Résout la description d'un post : la meta si elle est renseignée, sinon
     * l'extrait de l'article (manuel ou généré par le core).
     *
     * Point d'entrée public partagé avec `Seo\OpenGraph`, pour ne pas dupliquer
     * ce repli entre les deux classes.
     *
     * @param int $post_id Identifiant du post.
     */
    public static function resolve_description( int $post_id ): string {
        $meta = trim( (string) get_post_meta( $post_id, self::META_KEY, true ) );

        if ( '' !== $meta ) {
            return $meta;
        }

        return trim( wp_strip_all_tags( get_the_excerpt( $post_id ) ) );
    }
}
