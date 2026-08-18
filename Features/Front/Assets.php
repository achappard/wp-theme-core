<?php
/**
 * Chargement des assets front-end
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Front;

use Theme\Contracts\FeatureInterface;

/**
 * Charge les assets CSS et JS du thème via Vite.
 *
 * En développement : se connecte au serveur Vite (HMR activé).
 * En production    : lit dist/assets/.vite/manifest.json pour résoudre les noms hachés.
 *
 * La détection du mode repose sur `dist/hot` :
 * - Présent → `npm run dev` est actif (fichier écrit par le plugin wordpressHot)
 * - Absent  → `npm run build` (le manifest est utilisé)
 */
final class Assets implements FeatureInterface {

    /** Répertoire de sortie de Vite, relatif à la racine du thème. */
    private const DIST_DIR = '/dist/assets';

    /** Entrée JS du thème, telle que déclarée dans vite.config.js. */
    private const ENTRY = 'resources/js/app.js';

    /** Handles WordPress des scripts Vite (pour le filtre type="module"). */
    private const SCRIPT_HANDLES = [ 'vite-client', 'mon-theme-app' ];

    /** Enregistre le hook d'enfilement des assets. */
    public function register(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
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

    /** Lit l'URL du serveur Vite depuis `dist/hot`. */
    private function get_dev_server_url(): string {
        return rtrim( (string) file_get_contents( $this->hot_file_path() ), '/' );
    }

    /** Chemin absolu du fichier `dist/hot`. */
    private function hot_file_path(): string {
        return get_template_directory() . '/dist/hot';
    }

    /**
     * Charge les assets depuis le serveur Vite local.
     * Le client Vite gère le HMR (Hot Module Replacement) automatiquement.
     */
    private function enqueue_dev_assets(): void {
        $base = $this->get_dev_server_url();

        wp_enqueue_script(
            'vite-client',
            $base . '/@vite/client',
            [],
            null,
            false
        );

        // Le fichier JS importe le SCSS — Vite l'injecte dynamiquement en dev.
        wp_enqueue_script(
            'mon-theme-app',
            $base . '/' . self::ENTRY,
            [ 'vite-client' ],
            null,
            true
        );

        add_filter( 'script_loader_tag', [ $this, 'add_module_type' ], 10, 2 );
    }

    /**
     * Charge les assets buildés depuis le manifest Vite.
     * Vite extrait le CSS dans un fichier séparé avec un nom haché.
     */
    private function enqueue_production_assets(): void {
        $manifest = $this->read_manifest();
        $entry    = $manifest[ self::ENTRY ] ?? null;

        if ( empty( $entry ) ) {
            return;
        }

        $dist_url = get_template_directory_uri() . self::DIST_DIR;

        foreach ( $entry['css'] ?? [] as $css_file ) {
            wp_enqueue_style( 'mon-theme-app', $dist_url . '/' . $css_file, [], null );
        }

        wp_enqueue_script(
            'mon-theme-app',
            $dist_url . '/' . $entry['file'],
            [],
            null,
            true
        );

        add_filter( 'script_loader_tag', [ $this, 'add_module_type' ], 10, 2 );
    }

    /** Lit et décode le manifest JSON généré par Vite. */
    private function read_manifest(): array {
        $path = get_template_directory() . self::DIST_DIR . '/.vite/manifest.json';

        if ( ! file_exists( $path ) ) {
            return [];
        }

        return json_decode( (string) file_get_contents( $path ), true ) ?? [];
    }

    /**
     * Ajoute `type="module"` aux balises <script> des handles Vite.
     * Requis car les ES modules (import/export) ne fonctionnent qu'avec ce type.
     */
    public function add_module_type( string $tag, string $handle ): string {
        if ( ! in_array( $handle, self::SCRIPT_HANDLES, true ) ) {
            return $tag;
        }

        if ( str_contains( $tag, 'type=' ) ) {
            return (string) preg_replace( '/type=["\']text\/javascript["\']/', 'type="module"', $tag );
        }

        return str_replace( '<script ', '<script type="module" ', $tag );
    }
}
