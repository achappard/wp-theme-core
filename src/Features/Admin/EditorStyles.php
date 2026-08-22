<?php
/**
 * Styles du canvas de l'éditeur de blocs
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use Deefuse\ThemeCore\Support\ViteManifest;

/**
 * Applique au canvas de l'éditeur de blocs la feuille `resources/scss/editor-gutenberg.scss`,
 * pour que le contenu s'y affiche avec les styles du thème plutôt qu'avec ceux du core.
 *
 * Deux gestes indissociables, sur `after_setup_theme` :
 * `add_theme_support( 'editor-styles' )` puis `add_editor_style()`. Le second ne
 * déclare à lui seul que `editor-style` — le support de l'éditeur classique, hérité
 * de TinyMCE. Or `get_block_editor_theme_styles()` teste `editor-styles`, au pluriel,
 * et ignore purement et simplement la feuille tant qu'il n'est pas posé.
 *
 * **Un chemin, jamais une URL.** La même fonction du core traite toute valeur
 * commençant par `//` ou `http` en la téléchargeant par `wp_remote_get()` : le site
 * s'appellerait lui-même en HTTP à chaque ouverture de l'éditeur. Un chemin relatif
 * au thème est au contraire lu sur disque et injecté dans le canvas avec un `baseURL`
 * correct, dont dépend la résolution des `url()` de la feuille (polices, images).
 * D'où le recours à `ViteManifest::entry_relative_path()` et non à `entry_url()`.
 *
 * **Toujours servie depuis `dist/assets/`**, comme le jeu de couleurs de l'admin, et
 * pour la même raison : c'est WordPress qui lit le fichier, pas le navigateur. Une URL
 * du serveur Vite renverrait un module JavaScript, et le dev server ne produit aucun
 * `.css` sur disque. Modifier les styles de l'éditeur impose donc un `npm run build`,
 * même quand `npm run dev` tourne — il n'y a pas de HMR dans le canvas.
 *
 * Sans build disponible, rien n'est déclaré, pas même le support : outre l'absence de
 * feuille, `editor-styles` fait passer le sous-menu Apparence de « Compositions » à
 * « Design » sur un thème classique (cf. wp-admin/menu.php), effet de bord qu'il serait
 * absurde d'assumer pour une feuille manquante. Sur les projets qui enregistrent
 * `DisableLockAppearanceTools`, cette entrée est de toute façon retirée et l'accès
 * direct à `site-editor.php` redirigé.
 *
 * Deux comportements du core à connaître avant d'écrire la feuille : les sélecteurs
 * sont préfixés par `.editor-styles-wrapper` — un `body { … }` s'applique donc à la
 * zone d'édition — et le CSS est **inliné** dans la page de l'éditeur. Y importer
 * Bootstrap en entier alourdirait chaque chargement de l'éditeur d'autant.
 */
final class EditorStyles implements FeatureInterface {

    /** Entrée CSS de l'éditeur, telle que déclarée dans vite.config.js. */
    private const DEFAULT_ENTRY = 'resources/scss/editor-gutenberg.scss';

    /**
     * @param string $entry Entrée CSS, telle que déclarée dans vite.config.js.
     */
    public function __construct(
        private readonly string $entry = self::DEFAULT_ENTRY,
        private readonly ViteManifest $manifest = new ViteManifest(),
    ) {}

    /** Enregistre la déclaration des styles de l'éditeur. */
    public function register(): void {
        add_action( 'after_setup_theme', [ $this, 'add_editor_styles' ] );
    }

    /**
     * Déclare la feuille au core.
     *
     * Le test sur l'extension n'est pas une précaution de principe : une entrée
     * SCSS dont la compilation ne produit **aucune règle** — feuille encore vide
     * au démarrage d'un projet, ou vidée depuis — ne fait pas émettre de `.css`
     * par Vite. Le manifest pointe alors vers le module JavaScript fantôme émis à
     * la place, que le core lirait et inlinerait tel quel dans le canvas. Mieux
     * vaut un éditeur sans styles qu'un éditeur nourri de JavaScript.
     *
     * En RTL, `add_editor_style()` ajoute d'office un second fichier en `-rtl.css`.
     * Vite n'en produit pas : le core ne le trouve pas sur disque et l'ignore, sans
     * incidence sur la feuille principale.
     */
    public function add_editor_styles(): void {
        $path = $this->manifest->entry_relative_path( $this->entry );

        if ( ! str_ends_with( $path, '.css' ) ) {
            return;
        }

        add_theme_support( 'editor-styles' );
        add_editor_style( $path );
    }
}
