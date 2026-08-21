<?php
/**
 * Chargement des assets front-end
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Front;

use Theme\Contracts\FeatureInterface;
use Theme\Support\ViteDevServer;
use Theme\Support\ViteManifest;
use Theme\ThemeContext;

/**
 * Charge les assets CSS et JS du thème via Vite.
 *
 * En développement : se connecte au serveur Vite (HMR activé).
 * En production    : résout les noms hachés via le manifest (`Support\ViteManifest`).
 *
 * L'arbitrage entre les deux modes est délégué à `Support\ViteDevServer`, qui teste
 * la présence du fichier témoin `dist/hot`.
 */
final class Assets implements FeatureInterface {

    /** Entrée JS du thème, telle que déclarée dans vite.config.js. */
    private const ENTRY = 'resources/js/app.js';

    public function __construct(
        private readonly ThemeContext $context,
        private readonly ViteDevServer $dev_server = new ViteDevServer(),
        private readonly ViteManifest $manifest = new ViteManifest(),
    ) {}

    /** Handles WordPress des scripts Vite (pour le filtre type="module"). */
    private function script_handles(): array {
        return [ $this->context->handle( 'vite-client' ), $this->context->handle( 'app' ) ];
    }

    /** Enregistre le hook d'enfilement des assets. */
    public function register(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
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
     * Charge les assets depuis le serveur Vite local.
     * Le client Vite gère le HMR (Hot Module Replacement) automatiquement.
     */
    private function enqueue_dev_assets(): void {
        $base   = $this->dev_server->url();
        $client = $this->context->handle( 'vite-client' );

        wp_enqueue_script(
            $client,
            $base . '/@vite/client',
            [],
            null,
            false
        );

        // Le fichier JS importe le SCSS — Vite l'injecte dynamiquement en dev.
        wp_enqueue_script(
            $this->context->handle( 'app' ),
            $base . '/' . self::ENTRY,
            [ $client ],
            null,
            true
        );

        $this->dev_server->serve_as_modules( $this->script_handles() );
    }

    /**
     * Charge les assets buildés depuis le manifest Vite.
     * Vite extrait le CSS dans un fichier séparé avec un nom haché.
     */
    private function enqueue_production_assets(): void {
        $entry = $this->manifest->entry( self::ENTRY );

        if ( empty( $entry ) ) {
            return;
        }

        foreach ( $entry['css'] ?? [] as $css_file ) {
            wp_enqueue_style( $this->context->handle( 'app' ), $this->manifest->asset_url( (string) $css_file ), [], null );
        }

        wp_enqueue_script(
            $this->context->handle( 'app' ),
            $this->manifest->asset_url( (string) $entry['file'] ),
            [],
            null,
            true
        );

        $this->dev_server->serve_as_modules( $this->script_handles() );
    }
}
