<?php
/**
 * Interrupteur d'affichage du titre dans l'éditeur de blocs
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use Deefuse\ThemeCore\ThemeContext;

/**
 * Ajoute au panneau de document de Gutenberg un interrupteur « Afficher le titre »,
 * qui masque le champ titre du canvas quand il est désactivé.
 *
 * **Périmètre strictement éditorial.** L'état est conservé dans une meta de contenu,
 * mais le socle ne le lit nulle part ailleurs : le rendu public reste l'affaire des
 * templates du thème, libres de consulter cette meta ou de l'ignorer. Rien ici ne
 * change ce que voit un visiteur.
 *
 * **Le JS est lu sur disque, jamais servi par une URL.** Il vit dans `assets/` du
 * package, donc sous `vendor/` une fois installé — un répertoire que beaucoup
 * d'hébergements refusent de servir. Une balise pointant vers `vendor/` fonctionnerait
 * en local et échouerait en production sans le moindre message. Son contenu est donc
 * injecté en inline, sur un handle sans source.
 *
 * **La feuille, elle, n'est pas un fichier** : quatre lignes construites par `css()`
 * à partir de `BODY_CLASS`. Un fichier CSS aurait redit cette classe en dur, sans que
 * rien ne vérifie qu'elle suive la constante — la règle aurait cessé de s'appliquer en
 * silence au premier renommage. Elle est confiée au script, qui la pose lui-même.
 *
 * **Pourquoi le JS pose la feuille plutôt que `add_editor_style()`.** Le champ titre
 * vit dans le canvas, iframé depuis WP 6.5, et `add_editor_style()` y fait réécrire
 * les sélecteurs par le core, préfixés de `.editor-styles-wrapper` — ce qui déplacerait
 * la classe conditionnelle et casserait la règle. En posant la feuille dans les deux
 * documents, on couvre le canvas iframé comme l'ancien rendu en place, sans dépendre
 * d'une transformation dont la forme n'est pas contractuelle.
 *
 * La meta stocke « masquer », l'interrupteur affiche « afficher » : une meta absente
 * vaut donc titre visible, et les contenus existants restent inchangés à l'activation.
 */
final class ShowHidePageTitle implements FeatureInterface {

    /** Types de contenu concernés, par défaut. */
    private const DEFAULT_POST_TYPES = [ 'page' ];

    /** Clé de la meta, par défaut. Le préfixe `_` la rend protégée côté core. */
    private const DEFAULT_META_KEY = '_hide_post_title';

    /** Classe portée par les `<body>` quand le titre est masqué. */
    private const BODY_CLASS = 'deefuse-hide-post-title';

    /** Identifiant de la balise `<style>` posée dans chaque document. */
    private const STYLE_ID = 'deefuse-hide-post-title-css';

    /** Identifiant du plugin Gutenberg, tel que passé à `registerPlugin()`. */
    private const PLUGIN_NAME = 'deefuse-show-hide-page-title';

    /**
     * @param ThemeContext $context    Identité du thème, pour le handle du script.
     * @param string[]     $post_types Types de contenu recevant l'interrupteur.
     * @param string       $meta_key   Clé de la meta stockant l'état.
     */
    public function __construct(
        private readonly ThemeContext $context,
        private readonly array $post_types = self::DEFAULT_POST_TYPES,
        private readonly string $meta_key = self::DEFAULT_META_KEY,
    ) {}

    /** Enregistre la meta et le script du panneau. */
    public function register(): void {
        add_action( 'init', [ $this, 'register_meta' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue' ] );
    }

    /**
     * Déclare la meta sur chaque type de contenu concerné.
     *
     * `show_in_rest` est indispensable : c'est par l'API REST que l'éditeur lit et
     * écrit les metas. Sans `auth_callback`, une clé préfixée `_` serait refusée en
     * écriture, le core considérant les metas protégées comme hors de portée.
     */
    public function register_meta(): void {
        foreach ( $this->post_types as $post_type ) {
            register_post_meta(
                $post_type,
                $this->meta_key,
                [
                    'type'          => 'boolean',
                    'single'        => true,
                    'default'       => false,
                    'show_in_rest'  => true,
                    'auth_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
                ]
            );
        }
    }

    /**
     * Injecte le panneau dans l'éditeur.
     *
     * `enqueue_block_editor_assets` se déclenche aussi sur l'éditeur de site et
     * l'écran des widgets, où ce panneau n'a pas de sens : on s'en tient aux écrans
     * d'édition des types déclarés. Un écran indéterminé fait renoncer plutôt que
     * charger le script partout.
     */
    public function enqueue(): void {
        $screen = get_current_screen();

        if ( null === $screen || ! in_array( $screen->post_type, $this->post_types, true ) ) {
            return;
        }

        $script = $this->script();

        if ( '' === $script ) {
            return;
        }

        $handle = $this->context->handle( 'show-hide-page-title' );

        // Handle sans source : le script n'existe que par ses inlines.
        wp_register_script(
            $handle,
            false,
            [ 'wp-plugins', 'wp-editor', 'wp-components', 'wp-element', 'wp-data', 'wp-core-data' ],
            null,
            true
        );
        wp_enqueue_script( $handle );

        wp_add_inline_script(
            $handle,
            'window.DeefuseShowHidePageTitle = ' . wp_json_encode( $this->config() ) . ';',
            'before'
        );
        wp_add_inline_script( $handle, $script );
    }

    /**
     * Configuration transmise au script.
     *
     * Les libellés sont traduits ici plutôt que dans le JS : le socle n'a pas de
     * build, donc pas de catalogue JSON à charger par `wp_set_script_translations()`.
     * Les résoudre en PHP les fait entrer dans le catalogue `wp-theme-core`, déjà
     * livré avec le package.
     *
     * @return array<string, mixed>
     */
    private function config(): array {
        return [
            'pluginName'  => self::PLUGIN_NAME,
            'postTypes'   => array_values( $this->post_types ),
            'metaKey'     => $this->meta_key,
            'bodyClass'   => self::BODY_CLASS,
            'styleId'     => self::STYLE_ID,
            'css'         => $this->css(),
            'panelTitle'  => __( 'Titre', 'wp-theme-core' ),
            'toggleLabel' => __( 'Afficher le titre', 'wp-theme-core' ),
            'helpShown'   => __( 'Le titre est affiché dans l’éditeur.', 'wp-theme-core' ),
            'helpHidden'  => __( 'Le titre est masqué dans l’éditeur ; il reste modifiable en haut de cette barre latérale.', 'wp-theme-core' ),
        ];
    }

    /**
     * Feuille masquant le champ titre du canvas.
     *
     * Construite ici, et non lue dans un fichier : la classe conditionnelle est une
     * constante de cette classe, et un fichier l'aurait redite en dur.
     *
     * Deux sélecteurs pour deux générations de Gutenberg — `edit-post-` a précédé
     * `editor-` (WP 6.6), et les deux coexistent selon la version du core.
     */
    private function css(): string {
        $class = self::BODY_CLASS;

        return <<<CSS
        /* Masque le champ titre natif — il reste modifiable en haut de la barre latérale. */
        body.{$class} .editor-visual-editor__post-title-wrapper,
        body.{$class} .edit-post-visual-editor__post-title-wrapper {
            display: none !important;
        }
        CSS;
    }

    /**
     * Lit le script du panneau dans le répertoire `assets/` du package.
     *
     * Un fichier absent retourne une chaîne vide, à charge de l'appelant de ne rien
     * enfiler : mieux vaut un panneau manquant qu'un handle sans son script.
     */
    private function script(): string {
        $path = dirname( __DIR__, 3 ) . '/assets/js/show-hide-page-title.js';

        return is_file( $path ) ? (string) file_get_contents( $path ) : '';
    }
}
