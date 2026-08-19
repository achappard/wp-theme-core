<?php
/**
 * Jeu de couleurs de l'administration
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Admin;

use Theme\Contracts\FeatureInterface;
use Theme\Support\ViteManifest;
use WP_User;

/**
 * Impose au back-office la palette du client, et restreint le choix laissé au core.
 *
 * Deux gestes complémentaires, tous deux sur `admin_init` :
 * le scheme du thème est ajouté à ceux de WordPress — il apparaît dans le
 * sélecteur « Jeu de couleurs de l'administration » de la page Profil et devient
 * le défaut des comptes créés ensuite — puis les schemes du core listés dans
 * `SCHEMES_TO_REMOVE` en sont retirés. Chaque utilisateur reste libre de choisir
 * parmi ceux qui restent.
 *
 * La feuille est compilée par Vite depuis `resources/scss/admin-colors.scss`
 * (qui dérive la palette des sources SCSS du core), puis déclarée au core via
 * `wp_admin_css_color()`. C'est WordPress qui la charge, à la place de son
 * propre `colors.css` — le thème ne l'enfile jamais lui-même.
 *
 * Elle est donc toujours servie depuis `dist/assets/` : une URL du serveur Vite
 * renverrait un module JavaScript, que le core insérerait dans un `<link>`.
 * Modifier la palette impose de relancer `npm run build`, même en développement.
 */
final class AdminColorScheme implements FeatureInterface {

    /** Identifiant du scheme, stocké dans la meta utilisateur `admin_color`. */
    private const SCHEME = 'mon-theme';

    /** Entrée CSS du scheme, telle que déclarée dans vite.config.js. */
    private const ENTRY = 'resources/scss/admin-colors.scss';

    /** Scheme retenu par WordPress pour les comptes qui n'ont rien choisi. */
    private const CORE_DEFAULT = 'modern';

    /**
     * Applique le scheme aux nouveaux comptes, à la place de celui du core.
     * Passer à `false` pour le proposer sans l'imposer à personne.
     */
    private const DEFAULT_FOR_NEW_USERS = true;

    /**
     * Aperçu affiché dans le sélecteur du profil (quatre pastilles).
     * Reprend les variables de `resources/scss/admin-colors.scss`.
     */
    private const PALETTE = [ '#072e3e', '#0d4457', '#12718f', '#d1621f' ];

    /**
     * Jeux de couleurs du core retirés du sélecteur.
     *
     * Ne jamais y ajouter `modern` : le core en fait son filet de sécurité partout
     * où le scheme enregistré d'un utilisateur a disparu — feuille de styles
     * (`wp_style_loader_src()`), couleurs d'icônes (`wp_color_scheme_settings()`)
     * et sélecteur du profil (`admin_color_scheme_picker()`) — et `CORE_DEFAULT`
     * ci-dessus s'appuie sur la même valeur.
     */
    private const SCHEMES_TO_REMOVE = [ 'blue', 'sunrise', 'ectoplasm', 'ocean', 'light', 'coffee' ];

    /**
     * Couleurs des icônes du menu, transmises en JS par le core (`_wpColorScheme`)
     * pour les icônes SVG des extensions. À garder alignées sur `$icon-color`.
     */
    private const ICON_COLORS = [
        'base'    => '#a7bfc8',
        'focus'   => '#fff',
        'current' => '#fff',
    ];

    /** Enregistre les hooks du jeu de couleurs. */
    public function register(): void {
        // Le core déclare ses propres schemes sur `admin_init` en priorité 1 :
        // on s'ajoute après lui, puis on retire les siens dans la foulée.
        add_action( 'admin_init', [ $this, 'register_scheme' ] );
        add_action( 'admin_init', [ $this, 'remove_core_schemes' ], 20 );

        if ( self::DEFAULT_FOR_NEW_USERS ) {
            add_filter( 'insert_user_meta', [ $this, 'set_default_for_new_user' ], 10, 3 );
        }
    }

    /**
     * Déclare le scheme au core.
     *
     * Sans build disponible, on ne déclare rien : mieux vaut une entrée absente
     * du sélecteur qu'une entrée pointant sur une feuille inexistante.
     */
    public function register_scheme(): void {
        $url = ViteManifest::entry_url( self::ENTRY );

        if ( '' === $url ) {
            return;
        }

        wp_admin_css_color(
            self::SCHEME,
            // Nom de marque : rien à traduire, contrairement aux libellés du core.
            'Mon thème',
            $url,
            self::PALETTE,
            self::ICON_COLORS
        );
    }

    /**
     * Retire du sélecteur les jeux de couleurs du core jugés inutiles.
     *
     * Le retrait a lieu sur `admin_init`, et non sur `admin_head` : à ce
     * moment-là la feuille de couleurs est déjà imprimée (`admin_print_styles`
     * précède `admin_head` dans `admin-header.php`), si bien qu'un utilisateur
     * ayant retenu un scheme supprimé continuerait d'en voir la CSS, avec les
     * icônes et le sélecteur du profil retombés, eux, sur `modern`. En retirant
     * plus tôt, le repli du core s'applique partout de la même façon. Cela couvre
     * en outre `admin-ajax.php`, qui déclenche `admin_init` mais pas `admin_head`,
     * et par où transite l'aperçu instantané du profil.
     *
     * @global array $_wp_admin_css_colors
     */
    public function remove_core_schemes(): void {
        global $_wp_admin_css_colors;

        if ( ! is_array( $_wp_admin_css_colors ) ) {
            return;
        }

        foreach ( self::SCHEMES_TO_REMOVE as $scheme ) {
            unset( $_wp_admin_css_colors[ $scheme ] );
        }
    }

    /**
     * Applique le scheme du thème aux comptes nouvellement créés.
     *
     * `wp_insert_user()` écrit `admin_color` à la création de chaque compte, et
     * le formulaire d'ajout d'utilisateur n'expose pas le sélecteur : une valeur
     * encore égale au défaut du core signale donc bien un choix jamais exprimé.
     * Le test sur `$update` laisse par ailleurs intactes les mises à jour de
     * profil — un utilisateur qui préfère « Défaut » le reste.
     *
     * @param array   $meta   Metas sur le point d'être enregistrées.
     * @param WP_User $user   Utilisateur concerné.
     * @param bool    $update Mise à jour d'un compte existant, ou création.
     * @return array
     */
    public function set_default_for_new_user( array $meta, WP_User $user, bool $update ): array {
        if ( $update || self::CORE_DEFAULT !== ( $meta['admin_color'] ?? '' ) ) {
            return $meta;
        }

        $meta['admin_color'] = self::SCHEME;

        return $meta;
    }
}
