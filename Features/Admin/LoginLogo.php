<?php
/**
 * Logo de la page de connexion
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Admin;

use Theme\Contracts\FeatureInterface;
use Theme\Support\ViteManifest;

/**
 * Remplace le logo WordPress de la page de connexion par celui du thème.
 *
 * Le logo n'est pas référencé ici mais dans `resources/scss/login.scss`, ce qui
 * le fait entrer dans le graphe Vite : servi par le dev server en développement,
 * émis dans `dist/assets/` avec un nom haché au build. Cette classe se contente
 * donc de charger la feuille de la page de connexion, selon le même mécanisme que
 * `Front\Assets` — présence de `dist/hot` pour arbitrer entre dev et production.
 */
final class LoginLogo implements FeatureInterface {

    /** Entrée de la page de connexion, telle que déclarée dans vite.config.js. */
    private const ENTRY = 'resources/js/login.js';

    /** Handles WordPress des scripts Vite (pour le filtre type="module"). */
    private const SCRIPT_HANDLES = [ 'vite-client-login', 'mon-theme-login' ];

    /** Enregistre les hooks de personnalisation de la page de connexion. */
    public function register(): void {
        add_action( 'login_enqueue_scripts', [ $this, 'enqueue' ] );
        add_filter( 'login_headerurl', [ $this, 'set_logo_url' ] );
        add_filter( 'login_headertext', [ $this, 'set_logo_text' ] );
    }

    /** Point d'entrée : sélectionne le mode dev ou production. */
    public function enqueue(): void {
        if ( $this->is_dev_mode() ) {
            $this->enqueue_dev_assets();
        } else {
            $this->enqueue_production_assets();
        }
    }

    /** Détecte le mode dev via la présence du fichier `dist/hot`. */
    private function is_dev_mode(): bool {
        return file_exists( $this->hot_file_path() );
    }

    /** Chemin absolu du fichier `dist/hot`. */
    private function hot_file_path(): string {
        return get_template_directory() . '/dist/hot';
    }

    /** Lit l'URL du serveur Vite depuis `dist/hot`. */
    private function get_dev_server_url(): string {
        return rtrim( (string) file_get_contents( $this->hot_file_path() ), '/' );
    }

    /**
     * Charge l'entrée depuis le serveur Vite local.
     *
     * Le SCSS est injecté par le module JS : aucune balise <link> n'est attendue
     * en développement, et le logo est servi directement par Vite.
     */
    private function enqueue_dev_assets(): void {
        $base = $this->get_dev_server_url();

        wp_enqueue_script( 'vite-client-login', $base . '/@vite/client', [], null, false );
        wp_enqueue_script( 'mon-theme-login', $base . '/' . self::ENTRY, [ 'vite-client-login' ], null, true );

        add_filter( 'script_loader_tag', [ $this, 'add_module_type' ], 10, 2 );
    }

    /** Charge la feuille buildée, dont l'URL du logo a été réécrite par Vite. */
    private function enqueue_production_assets(): void {
        $entry = ViteManifest::entry( self::ENTRY );

        if ( empty( $entry ) ) {
            return;
        }

        foreach ( $entry['css'] ?? [] as $css_file ) {
            wp_enqueue_style( 'mon-theme-login', ViteManifest::asset_url( (string) $css_file ), [], null );
        }
    }

    /** Ajoute `type="module"` aux balises <script> des handles Vite. */
    public function add_module_type( string $tag, string $handle ): string {
        if ( ! in_array( $handle, self::SCRIPT_HANDLES, true ) ) {
            return $tag;
        }

        if ( str_contains( $tag, 'type=' ) ) {
            return (string) preg_replace( '/type=["\']text\/javascript["\']/', 'type="module"', $tag );
        }

        return str_replace( '<script ', '<script type="module" ', $tag );
    }

    /** Pointe le lien du logo vers l'accueil du site plutôt que vers wordpress.org. */
    public function set_logo_url(): string {
        return home_url( '/' );
    }

    /**
     * Remplace le texte du lien du logo par le nom du site.
     *
     * L'échappement est à la charge du thème : le core sort cette valeur telle
     * quelle (cf. wp-login.php, `echo $login_header_text`).
     */
    public function set_logo_text(): string {
        return esc_html( get_bloginfo( 'name' ) );
    }
}
