<?php
/**
 * Panneau SEO du document, dans l'éditeur de blocs
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Seo;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use Deefuse\ThemeCore\ThemeContext;

/**
 * Ajoute au panneau de document de Gutenberg un panneau « SEO », avec un champ
 * de meta description (compteur de caractères indicatif) et un interrupteur
 * d'inclusion dans le sitemap.
 *
 * **Seule classe qui appelle `register_post_meta()`** pour
 * `MetaDescription::META_KEY` et `SitemapExclusion::META_KEY` — ces deux
 * classes se contentent de lire `get_post_meta()`. La dépendance va donc dans
 * un seul sens : cette classe connaît leurs constantes, elles ignorent tout
 * de celle-ci. Elles fonctionnent sans elle (repli sur l'extrait, sitemap
 * complet) ; c'est elle qui rend leurs metas éditables depuis l'éditeur.
 *
 * **`register_post_meta` + `show_in_rest` suffit**, sans route REST custom :
 * contrairement à `Admin\ShowHidePageTitle` (réglage global, une option),
 * chaque post porte sa propre valeur — le magasin `core/editor` de l'éditeur
 * de blocs la lit et l'écrit nativement via l'attribut `meta`. L'enregistrement
 * suit donc le bouton « Mettre à jour » du post, pas une écriture immédiate.
 *
 * **Le JS est lu sur disque**, comme `Admin\ShowHidePageTitle` : pas de build
 * dans le socle, le script est injecté en inline sur un handle sans source.
 */
final class SeoPanel implements FeatureInterface {

    /** Types de contenu recevant le panneau, par défaut. */
    private const DEFAULT_POST_TYPES = [ 'post', 'page' ];

    /** Capability requise pour voir et modifier le panneau, par défaut. */
    private const DEFAULT_CAPABILITY = 'edit_posts';

    /** Limite indicative de la description, en caractères — convention SEO, pas une limite technique WordPress ni Google. */
    private const DESCRIPTION_LIMIT = 160;

    /** Nom au sens de `wp.plugins.registerPlugin()` — un plugin d'éditeur Gutenberg, pas un plugin WordPress. */
    private const EDITOR_PLUGIN_NAME = 'deefuse-seo-panel';

    /**
     * @param ThemeContext $context    Identité du thème, pour le handle du script.
     * @param string[]     $post_types Types de contenu où le panneau est proposé.
     * @param string       $capability Capability requise pour voir et modifier le panneau.
     */
    public function __construct(
        private readonly ThemeContext $context,
        private readonly array $post_types = self::DEFAULT_POST_TYPES,
        private readonly string $capability = self::DEFAULT_CAPABILITY,
    ) {}

    /** Enregistre les metas et le script du panneau. */
    public function register(): void {
        add_action( 'init', [ $this, 'register_meta' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Ajoute à l\'éditeur de blocs un panneau SEO : meta description et exclusion du sitemap.', 'wp-theme-core' );
    }

    /** Déclare les deux metas auprès des types de contenu concernés. */
    public function register_meta(): void {
        foreach ( $this->post_types as $post_type ) {
            register_post_meta( $post_type, MetaDescription::META_KEY, [
                'type'              => 'string',
                'single'            => true,
                'default'           => '',
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => fn(): bool => current_user_can( $this->capability ),
            ] );

            register_post_meta( $post_type, SitemapExclusion::META_KEY, [
                'type'          => 'boolean',
                'single'        => true,
                'default'       => false,
                'show_in_rest'  => true,
                'auth_callback' => fn(): bool => current_user_can( $this->capability ),
            ] );
        }
    }

    /**
     * Injecte le panneau dans l'éditeur, sur les écrans concernés.
     *
     * Même garde que `Admin\ShowHidePageTitle::enqueue()` : un écran
     * indéterminé, hors périmètre, ou sans la capability requise fait
     * renoncer plutôt que charger le script partout.
     */
    public function enqueue(): void {
        $screen = get_current_screen();

        if ( null === $screen || ! in_array( $screen->post_type, $this->post_types, true ) ) {
            return;
        }

        if ( ! current_user_can( $this->capability ) ) {
            return;
        }

        $script = $this->script();

        if ( '' === $script ) {
            return;
        }

        $handle = $this->context->handle( 'seo-panel' );

        // Handle sans source : le script n'existe que par ses inlines.
        wp_register_script(
            $handle,
            false,
            [ 'wp-plugins', 'wp-editor', 'wp-components', 'wp-element', 'wp-data' ],
            null,
            true
        );
        wp_enqueue_script( $handle );

        wp_add_inline_script(
            $handle,
            'window.DeefuseSeoPanel = ' . wp_json_encode( $this->config() ) . ';',
            'before'
        );
        wp_add_inline_script( $handle, $script );
    }

    /**
     * Configuration transmise au script — libellés traduits ici, comme
     * `ShowHidePageTitle::config()` : le socle n'a pas de catalogue JS.
     *
     * @return array<string, mixed>
     */
    private function config(): array {
        return [
            'editorPluginName' => self::EDITOR_PLUGIN_NAME,
            'panelTitle'       => __( 'SEO', 'wp-theme-core' ),
            'metaKeys'         => [
                'description'    => MetaDescription::META_KEY,
                'excludeSitemap' => SitemapExclusion::META_KEY,
            ],
            'descriptionLimit' => self::DESCRIPTION_LIMIT,
            'descriptionLabel' => __( 'Meta description', 'wp-theme-core' ),
            /* translators: 1: nombre de caractères saisis, 2: limite recommandée */
            'descriptionHelp'  => __( '%1$d/%2$d caractères', 'wp-theme-core' ),
            /* translators: 1: nombre de caractères saisis, 2: limite recommandée */
            'descriptionOver'  => __( '%1$d caractères — au-delà des %2$d recommandés par Google', 'wp-theme-core' ),
            'sitemapTitle'     => __( 'Sitemap', 'wp-theme-core' ),
            'sitemapLabel'     => __( 'Inclure cette page dans le sitemap', 'wp-theme-core' ),
            'sitemapHelp'      => __( 'Désactiver retire ce contenu des sitemaps XML générés par WordPress.', 'wp-theme-core' ),
        ];
    }

    /** Lit le script du panneau dans le répertoire `assets/` du package. */
    private function script(): string {
        $path = dirname( __DIR__, 3 ) . '/assets/js/seo-panel.js';

        return is_file( $path ) ? (string) file_get_contents( $path ) : '';
    }
}
