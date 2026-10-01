<?php
/**
 * Balises Open Graph et Twitter Card
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Seo;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use Deefuse\ThemeCore\Support\ViteManifest;

/**
 * Sort les balises `og:*` et `twitter:*` sur les contenus singuliers.
 *
 * **WordPress ne génère aucune balise Open Graph par défaut** (confirmé par
 * recherche exhaustive dans `wp-includes/`) : sans cette feature ou un plugin
 * SEO tiers, un lien partagé sur les réseaux n'a ni image ni description.
 *
 * **Un seul filtre pour l'ensemble des balises**, `self::FILTER`, plutôt qu'un
 * filtre par balise — même logique que `document_title_parts` côté core et
 * que `Seo\DocumentTitle::filter_parts()` côté socle : composer un tableau
 * associatif complet est plus simple à surcharger qu'un filtre par balise.
 *
 * **L'image de repli est un fichier généré par le build, pas une valeur du
 * constructeur.** Contrairement à une couleur de marque
 * (`Front\Favicons::$theme_color_light`), une image n'est pas une valeur
 * qu'un provider fournirait en argument nommé : `bin/generate-og-image.mjs`
 * (thème) écrit `dist/assets/image-og-default.png`, résolu ici exactement
 * comme `Front\Favicons` résout les siens — `ViteManifest` en dépendance
 * optionnelle, vérification d'existence avant de construire l'URL, balises
 * omises plutôt que cassées si le fichier n'a jamais été généré.
 *
 * **Dépend de `add_theme_support( 'post-thumbnails' )`** (posé par
 * `Setup\ThemeSupport`) pour résoudre l'image à la une ; sans lui,
 * `get_post_thumbnail_id()` renvoie toujours `0` et l'image de repli est
 * systématiquement utilisée.
 *
 * **Ne rend rien éditable** : la description réutilise
 * `MetaDescription::resolve_description()`, sans dupliquer sa logique de repli.
 *
 * Attention : un plugin SEO (Yoast, Rank Math) génère ses propres balises
 * Open Graph et rendrait cette feature redondante — pas de détection
 * automatique, à retirer manuellement du provider en pareil cas.
 */
final class OpenGraph implements FeatureInterface {

    /** Filtre unique portant l'ensemble des balises, avant rendu. */
    public const FILTER = 'deefuse/seo/open_graph_tags';

    /** Fichier généré par bin/generate-og-image.mjs, relatif à dist/assets/. */
    private const DEFAULT_IMAGE = 'image-og-default.png';

    /**
     * @param ViteManifest $manifest Résolution des URLs sous dist/assets/.
     */
    public function __construct(
        private readonly ViteManifest $manifest = new ViteManifest(),
    ) {}

    /** Enregistre l'affichage des balises dans le `<head>`. */
    public function register(): void {
        add_action( 'wp_head', [ $this, 'render' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Ajoute les balises Open Graph : titre, description et image affichés quand un lien vers le site est partagé.', 'wp-theme-core' );
    }

    /** Affiche les balises, uniquement sur un contenu singulier. */
    public function render(): void {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_queried_object_id();

        /**
         * Filtre l'ensemble des balises Open Graph et Twitter Card avant rendu.
         *
         * Tableau associatif `og:xxx`/`twitter:xxx` => valeur. Une valeur vide
         * fait disparaître la balise correspondante — retirer une clé produit
         * le même effet que la vider.
         *
         * @param array<string, string> $tags    Balises résolues par le socle.
         * @param int                   $post_id Identifiant du post affiché.
         */
        $tags = apply_filters( self::FILTER, $this->resolve_tags( $post_id ), $post_id );

        foreach ( $tags as $name => $content ) {
            $content = trim( (string) $content );

            if ( '' === $content ) {
                continue;
            }

            $attribute = str_starts_with( $name, 'twitter:' ) ? 'name' : 'property';
            $escaped   = str_ends_with( $name, ':url' ) || str_ends_with( $name, ':image' )
                ? esc_url( $content )
                : esc_attr( $content );

            printf( '<meta %s="%s" content="%s">' . "\n", esc_attr( $attribute ), esc_attr( $name ), $escaped );
        }
    }

    /**
     * Construit les balises par défaut pour un post.
     *
     * @param  int $post_id Identifiant du post.
     * @return array<string, string>
     */
    private function resolve_tags( int $post_id ): array {
        $description = MetaDescription::resolve_description( $post_id );
        $image       = $this->resolve_image( $post_id );

        $tags = [
            'og:type'             => 'page' === get_post_type( $post_id ) ? 'website' : 'article',
            'og:title'            => get_the_title( $post_id ),
            'og:description'      => $description,
            'og:url'              => (string) get_permalink( $post_id ),
            'og:site_name'        => get_bloginfo( 'name' ),
            'og:locale'           => get_locale(),
            'twitter:title'       => get_the_title( $post_id ),
            'twitter:description' => $description,
        ];

        if ( '' !== $image ) {
            $tags['og:image']      = $image;
            $tags['twitter:card']  = 'summary_large_image';
            $tags['twitter:image'] = $image;
        }

        return $tags;
    }

    /** Image à la une, ou l'image de repli générée par le build si absente. */
    private function resolve_image( int $post_id ): string {
        $thumbnail_id = get_post_thumbnail_id( $post_id );

        if ( $thumbnail_id ) {
            $src = wp_get_attachment_image_url( $thumbnail_id, 'large' );

            if ( is_string( $src ) && '' !== $src ) {
                return $src;
            }
        }

        return $this->file_url( self::DEFAULT_IMAGE );
    }

    /** URL publique d'un fichier de `dist/assets/`, ou chaîne vide s'il est absent. */
    private function file_url( string $file ): string {
        return '' === $this->file_path( $file )
            ? ''
            : $this->manifest->asset_url( $file );
    }

    /** Chemin disque d'un fichier de `dist/assets/`, ou chaîne vide s'il est absent. */
    private function file_path( string $file ): string {
        $path = get_theme_file_path( 'dist/assets/' . $file );

        return file_exists( $path ) ? $path : '';
    }
}
