<?php
/**
 * Dialogue avec le serveur de développement Vite
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Support;

/**
 * Arbitre entre développement et production, et sert les assets en ES modules.
 *
 * Le plugin `wordpressHot` de `vite.config.js` écrit `dist/hot` au démarrage du dev
 * server et le supprime à l'arrêt : sa présence signale que `npm run dev` tourne, son
 * contenu donne l'URL du serveur. Toute feature qui enfile un asset Vite passe par ici
 * plutôt que de refaire ce test elle-même — le nom du fichier témoin et la façon de le
 * lire ne sont connus que d'un seul endroit (`Front\Assets`, `Admin\LoginBranding`).
 *
 * La classe ne pose aucun hook à l'enregistrement du thème : `serve_as_modules()` est
 * appelée depuis un callback d'enfilement, au moment où les handles concernés sont connus.
 */
final class ViteDevServer {

    /** Fichier témoin du dev server, relatif à la racine du thème. */
    private const HOT_FILE = '/dist/hot';

    /** Indique si le dev server Vite tourne. */
    public function is_active(): bool {
        return file_exists( $this->hot_file_path() );
    }

    /** URL du dev server, sans slash final. Chaîne vide s'il ne tourne pas. */
    public function url(): string {
        return rtrim( (string) file_get_contents( $this->hot_file_path() ), '/' );
    }

    /**
     * Ajoute `type="module"` aux balises <script> des handles donnés.
     *
     * Requis car les ES modules (import/export) ne fonctionnent qu'avec ce type.
     * Les handles sont passés en paramètre plutôt que fixés ici : chaque feature
     * nomme les siens, seule la réécriture de la balise est commune.
     *
     * @param string[] $handles Handles WordPress à servir en module.
     */
    public function serve_as_modules( array $handles ): void {
        add_filter(
            'script_loader_tag',
            static function ( string $tag, string $handle ) use ( $handles ): string {
                if ( ! in_array( $handle, $handles, true ) ) {
                    return $tag;
                }

                if ( str_contains( $tag, 'type=' ) ) {
                    return (string) preg_replace( '/type=["\']text\/javascript["\']/', 'type="module"', $tag );
                }

                return str_replace( '<script ', '<script type="module" ', $tag );
            },
            10,
            2
        );
    }

    /** Chemin absolu du fichier témoin `dist/hot`. */
    private function hot_file_path(): string {
        return get_template_directory() . self::HOT_FILE;
    }
}
