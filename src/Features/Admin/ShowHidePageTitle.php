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
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Ajoute au panneau de document de Gutenberg un interrupteur « Afficher le titre »,
 * qui masque le champ titre du canvas quand il est désactivé.
 *
 * **Le réglage est global, pas par contenu.** Il vit dans une option, et vaut donc
 * pour tous les types déclarés : basculer l'interrupteur depuis une page le bascule
 * partout. L'interrupteur reste dans la barre latérale, là où le besoin se présente,
 * mais ne décrit pas la page courante — le libellé d'aide le dit explicitement.
 *
 * **Périmètre strictement éditorial.** Le socle ne lit l'option nulle part ailleurs :
 * le rendu public reste l'affaire des templates du thème, libres de la consulter ou
 * de l'ignorer. Rien ici ne change ce que voit un visiteur.
 *
 * **Une route REST plutôt que `register_setting()`.** L'endpoint `/wp/v2/settings`
 * exige `manage_options`, écrit en dur dans `WP_REST_Settings_Controller` et
 * qu'aucun filtre ne permet d'abaisser : y adosser l'option interdirait le réglage
 * à toute personne n'administrant pas le site. Le socle expose donc sa propre route,
 * dont la permission est portée par `$capability`.
 *
 * L'écriture est **immédiate**, et non différée jusqu'au « Mettre à jour » du contenu :
 * le bouton de l'éditeur enregistre le post, pas les réglages du site, et un réglage
 * global n'a de toute façon pas à dépendre de l'enregistrement d'une page particulière.
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
 * silence au premier renommage. Elle est confiée au script, qui la pose lui-même dans
 * le document de l'éditeur et dans celui du canvas iframé. `add_editor_style()` y ferait
 * réécrire les sélecteurs par le core, préfixés de `.editor-styles-wrapper`, ce qui
 * déplacerait la classe conditionnelle et casserait la règle.
 *
 * L'option stocke « masquer », l'interrupteur affiche « afficher » : une option absente
 * vaut donc titre visible, et rien ne change à l'activation de la feature.
 */
final class ShowHidePageTitle implements FeatureInterface {

    /** Types de contenu recevant l'interrupteur, par défaut. */
    private const DEFAULT_POST_TYPES = [ 'page' ];

    /** Option stockant l'état, par défaut. */
    private const DEFAULT_OPTION = 'deefuse_hide_post_title';

    /** Capability requise pour basculer le réglage, par défaut. */
    private const DEFAULT_CAPABILITY = 'edit_posts';

    /** Espace de noms de la route REST. */
    private const REST_NAMESPACE = 'deefuse/v1';

    /** Route REST d'écriture du réglage. */
    private const REST_ROUTE = '/hide-post-title';

    /** Classe portée par les `<body>` quand le titre est masqué. */
    private const BODY_CLASS = 'deefuse-hide-post-title';

    /** Identifiant de la balise `<style>` posée dans chaque document. */
    private const STYLE_ID = 'deefuse-hide-post-title-css';

    /** Identifiant du plugin Gutenberg, tel que passé à `registerPlugin()`. */
    private const PLUGIN_NAME = 'deefuse-show-hide-page-title';

    /**
     * @param ThemeContext $context    Identité du thème, pour le handle du script.
     * @param string[]     $post_types Types de contenu où l'interrupteur est proposé.
     * @param string       $option     Option stockant l'état, commune à tous ces types.
     * @param string       $capability Capability requise pour basculer le réglage.
     */
    public function __construct(
        private readonly ThemeContext $context,
        private readonly array $post_types = self::DEFAULT_POST_TYPES,
        private readonly string $option = self::DEFAULT_OPTION,
        private readonly string $capability = self::DEFAULT_CAPABILITY,
    ) {}

    /** Enregistre la route REST et le script du panneau. */
    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_route' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Ajoute à l\'éditeur de blocs un interrupteur « Afficher le titre », commun à toutes les pages.', 'wp-theme-core' );
    }

    /** Déclare la route d'écriture du réglage. */
    public function register_route(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_ROUTE,
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update' ],
                'permission_callback' => fn(): bool => current_user_can( $this->capability ),
                'args'                => [
                    'hidden' => [
                        'type'     => 'boolean',
                        'required' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Enregistre l'état et le renvoie tel que relu.
     *
     * `update_option()` retourne `false` aussi bien sur échec que lorsque la valeur
     * est déjà celle demandée : on relit plutôt que d'interpréter ce retour, et le
     * client se cale sur ce que la base contient réellement.
     */
    public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        update_option( $this->option, (bool) $request->get_param( 'hidden' ) );

        return new WP_REST_Response( [ 'hidden' => $this->hidden() ] );
    }

    /**
     * Injecte le panneau dans l'éditeur.
     *
     * `enqueue_block_editor_assets` se déclenche aussi sur l'éditeur de site et
     * l'écran des widgets, où ce panneau n'a pas de sens : on s'en tient aux écrans
     * d'édition des types déclarés. Un écran indéterminé fait renoncer plutôt que
     * charger le script partout — et sans la capability, il n'y a rien à proposer.
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

        $handle = $this->context->handle( 'show-hide-page-title' );

        // Handle sans source : le script n'existe que par ses inlines.
        wp_register_script(
            $handle,
            false,
            [ 'wp-plugins', 'wp-editor', 'wp-components', 'wp-element', 'wp-data', 'wp-api-fetch' ],
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

    /** État courant du réglage. */
    private function hidden(): bool {
        return (bool) get_option( $this->option, false );
    }

    /**
     * Configuration transmise au script.
     *
     * L'état initial est passé ici plutôt que lu par une requête au démarrage : il est
     * déjà connu au moment où la page de l'éditeur se construit, et une lecture REST
     * n'ajouterait qu'un aller-retour et un instant d'affichage incohérent.
     *
     * Les libellés sont traduits ici plutôt que dans le JS : le socle n'a pas de build,
     * donc pas de catalogue JSON à charger par `wp_set_script_translations()`. Les
     * résoudre en PHP les fait entrer dans le catalogue `wp-theme-core`, déjà livré
     * avec le package.
     *
     * @return array<string, mixed>
     */
    private function config(): array {
        return [
            'pluginName'  => self::PLUGIN_NAME,
            'restPath'    => '/' . self::REST_NAMESPACE . self::REST_ROUTE,
            'postTypes'   => array_values( $this->post_types ),
            'hidden'      => $this->hidden(),
            'bodyClass'   => self::BODY_CLASS,
            'styleId'     => self::STYLE_ID,
            'css'         => $this->css(),
            'panelTitle'  => __( 'Titre', 'wp-theme-core' ),
            'toggleLabel' => __( 'Afficher le titre', 'wp-theme-core' ),
            'helpShown'   => __( 'Réglage global : le titre est affiché dans l’éditeur, sur tous les contenus concernés.', 'wp-theme-core' ),
            'helpHidden'  => __( 'Réglage global : le titre est masqué dans l’éditeur, sur tous les contenus concernés ; il reste modifiable en haut de cette barre latérale.', 'wp-theme-core' ),
            'error'       => __( 'Le réglage n’a pas pu être enregistré.', 'wp-theme-core' ),
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
