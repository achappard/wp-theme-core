<?php
/**
 * Habillage de marque de la page de connexion
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use Deefuse\ThemeCore\Support\ViteDevServer;
use Deefuse\ThemeCore\Support\ViteManifest;
use Deefuse\ThemeCore\ThemeContext;

/**
 * Applique l'identité visuelle du thème à la page de connexion (wp-login.php).
 *
 * Deux volets. Côté PHP : le lien du logo pointe vers l'accueil et son texte reprend
 * le nom du site. Côté CSS : `resources/scss/login.scss` remplace le logo WordPress et
 * teinte les contrôles du formulaire (bouton, icône d'affichage du mot de passe, case
 * « Se souvenir de moi ») à la couleur de marque `$base-color`, partagée avec le jeu de
 * couleurs de l’admin, définie dans `_variables.scss` (`$brand_base-color`).
 *
 * Le logo et les couleurs ne sont pas référencés ici mais dans la feuille, ce qui les
 * fait entrer dans le graphe Vite : servis par le dev server en développement, émis dans
 * `dist/assets/` avec un nom haché au build. Cette classe se contente donc de charger la
 * feuille, selon le même mécanisme que `Front\Assets` — l'arbitrage entre dev et
 * production est délégué à `Support\ViteDevServer`. Enfilée sur `login_enqueue_scripts`,
 * elle couvre toutes les actions de wp-login.php, dont le formulaire « mot de passe oublié ».
 */
final class LoginBranding implements FeatureInterface {

    /** Entrée de la page de connexion, telle que déclarée dans vite.config.js. */
    private const ENTRY = 'resources/js/login.js';

    public function __construct(
        private readonly ThemeContext $context,
        private readonly ViteDevServer $dev_server = new ViteDevServer(),
        private readonly ViteManifest $manifest = new ViteManifest(),
    ) {}

    /** Handles WordPress des scripts Vite (pour le filtre type="module"). */
    private function script_handles(): array {
        return [ $this->context->handle( 'vite-client-login' ), $this->context->handle( 'login' ) ];
    }

    /** Enregistre les hooks de personnalisation de la page de connexion. */
    public function register(): void {
        add_action( 'login_enqueue_scripts', [ $this, 'enqueue' ] );
        add_filter( 'login_headerurl', [ $this, 'set_logo_url' ] );
        add_filter( 'login_headertext', [ $this, 'set_logo_text' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Applique le logo et les couleurs du thème à la page de connexion.', 'wp-theme-core' );
    }

    /** Point d'entrée : sélectionne le mode dev ou production. */
    public function enqueue(): void {
        if ( $this->dev_server->is_active() ) {
            $this->enqueue_dev_assets();
        } else {
            $this->enqueue_production_assets();
        }
    }

    /**
     * Charge l'entrée depuis le serveur Vite local.
     *
     * Le SCSS est injecté par le module JS : aucune balise <link> n'est attendue
     * en développement, et le logo est servi directement par Vite.
     */
    private function enqueue_dev_assets(): void {
        $base   = $this->dev_server->url();
        $client = $this->context->handle( 'vite-client-login' );

        wp_enqueue_script( $client, $base . '/@vite/client', [], null, false );
        wp_enqueue_script( $this->context->handle( 'login' ), $base . '/' . self::ENTRY, [ $client ], null, true );

        $this->dev_server->serve_as_modules( $this->script_handles() );
    }

    /** Charge la feuille buildée, dont l'URL du logo a été réécrite par Vite. */
    private function enqueue_production_assets(): void {
        $entry = $this->manifest->entry( self::ENTRY );

        if ( empty( $entry ) ) {
            return;
        }

        foreach ( $entry['css'] ?? [] as $css_file ) {
            wp_enqueue_style( $this->context->handle( 'login' ), $this->manifest->asset_url( (string) $css_file ), [], null );
        }
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
