<?php
/**
 * Jeu de couleurs de l'administration
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Admin;

use Theme\Contracts\FeatureInterface;
use WP_User;

/**
 * Ajoute un jeu de couleurs du back-office aux couleurs du client.
 *
 * Le scheme s'ajoute à ceux de WordPress : il apparaît dans le sélecteur
 * « Jeu de couleurs de l'administration » de la page Profil, et chaque
 * utilisateur reste libre d'en choisir un autre. Il est simplement appliqué
 * d'office aux comptes créés ensuite, qui n'ont jamais fait ce choix.
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

    /** Répertoire de sortie de Vite, relatif à la racine du thème. */
    private const DIST_DIR = '/dist/assets';

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
     * Schéma de couleur à supprimer
     */
    private const SCHEMES_TO_REMOVE = ['blue', 'sunrise', 'ectoplasm', 'ocean', 'light', 'coffee'];

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
        // Le core déclare ses propres schemes sur `admin_init` en priorité 1 ;
        // celui du thème s'ajoute donc à la suite, sans en retirer aucun.
        add_action( 'admin_init', [ $this, 'register_scheme' ] );

        // Supprime des jeux de couleurs inutiles
        add_action( 'admin_head', [$this,'admin_color_scheme_options'] );

        if ( self::DEFAULT_FOR_NEW_USERS ) {
            add_filter( 'insert_user_meta', [ $this, 'set_default_for_new_user' ], 10, 3 );
        }
    }

    /**  Supprime des jeux de couleurs inutiles  */
    public function admin_color_scheme_options() {
        global $_wp_admin_css_colors;
        if ( ! empty( $_wp_admin_css_colors ) ) {
            foreach (self::SCHEMES_TO_REMOVE as $scheme_name) {
                unset($_wp_admin_css_colors[$scheme_name]);
            }
        }
    }

    /**
     * Déclare le scheme au core.
     *
     * Sans build disponible, on ne déclare rien : mieux vaut une entrée absente
     * du sélecteur qu'une entrée pointant sur une feuille inexistante.
     */
    public function register_scheme(): void {
        $url = $this->stylesheet_url();

        if ( '' === $url ) {
            return;
        }

        wp_admin_css_color(
            self::SCHEME,
            _x( 'Mon thème', 'jeu de couleurs de l’administration', 'mon-theme' ),
            $url,
            self::PALETTE,
            self::ICON_COLORS
        );
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

    /** URL publique de la feuille buildée, ou chaîne vide si le build manque. */
    private function stylesheet_url(): string {
        $manifest = $this->read_manifest();
        $file     = $manifest[ self::ENTRY ]['file'] ?? '';

        if ( '' === $file ) {
            return '';
        }

        return get_template_directory_uri() . self::DIST_DIR . '/' . $file;
    }

    /** Lit et décode le manifest JSON généré par Vite. */
    private function read_manifest(): array {
        $path = get_template_directory() . self::DIST_DIR . '/.vite/manifest.json';

        if ( ! file_exists( $path ) ) {
            return [];
        }

        return json_decode( (string) file_get_contents( $path ), true ) ?? [];
    }
}
