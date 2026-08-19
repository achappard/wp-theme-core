<?php
/**
 * Lecture du manifest Vite
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Support;

/**
 * Résout les noms de fichiers hachés produits par `npm run build`.
 *
 * Vite émet ses sorties dans `dist/assets/` avec un hash de contenu dans le nom,
 * et récapitule la correspondance source → fichier émis dans
 * `dist/assets/.vite/manifest.json`. Toute feature qui enfile un asset buildé
 * passe par ici plutôt que d'ouvrir ce fichier elle-même : le chemin du manifest
 * et la forme de ses entrées ne sont connus que d'un seul endroit.
 *
 * Le manifest est lu une fois par requête — il ne peut pas changer en cours de
 * route, et plusieurs features le consultent (`Front\Assets`, `Admin\LoginLogo`,
 * `Admin\AdminColorScheme`).
 */
final class ViteManifest {

    /** Répertoire de sortie de Vite, relatif à la racine du thème. */
    private const DIST_DIR = '/dist/assets';

    /** Chemin du manifest, relatif au répertoire de sortie. */
    private const MANIFEST = '/.vite/manifest.json';

    /** Manifest décodé, mémorisé pour la durée de la requête. */
    private static ?array $manifest = null;

    /**
     * Retourne la description d'une entrée du manifest.
     *
     * @param string $entry Chemin de la source, tel que déclaré dans vite.config.js.
     * @return array Tableau vide si le build manque ou si l'entrée est inconnue.
     */
    public static function entry( string $entry ): array {
        $data = self::load()[ $entry ] ?? [];

        return is_array( $data ) ? $data : [];
    }

    /**
     * URL publique du fichier émis pour une entrée.
     *
     * @param string $entry Chemin de la source, tel que déclaré dans vite.config.js.
     * @return string Chaîne vide si le build manque ou si l'entrée est inconnue.
     */
    public static function entry_url( string $entry ): string {
        $file = (string) ( self::entry( $entry )['file'] ?? '' );

        return '' === $file ? '' : self::asset_url( $file );
    }

    /**
     * URL publique d'un fichier émis, désigné par son chemin dans le manifest.
     *
     * @param string $file Chemin relatif au répertoire de sortie (ex. `app-a1b2c3.css`).
     */
    public static function asset_url( string $file ): string {
        return get_template_directory_uri() . self::DIST_DIR . '/' . $file;
    }

    /**
     * Lit et décode le manifest, une seule fois par requête.
     *
     * Un manifest absent (build jamais lancé) comme un manifest illisible
     * retournent un tableau vide : c'est aux appelants de dégrader proprement.
     * Sans le test sur `is_array()`, un fichier tronqué décodé en scalaire
     * traverserait la méthode et ferait échouer le type de retour — page blanche
     * là où l'on veut simplement un asset manquant.
     */
    private static function load(): array {
        if ( null !== self::$manifest ) {
            return self::$manifest;
        }

        $path = get_template_directory() . self::DIST_DIR . self::MANIFEST;

        $data = file_exists( $path )
            ? json_decode( (string) file_get_contents( $path ), true )
            : null;

        self::$manifest = is_array( $data ) ? $data : [];

        return self::$manifest;
    }
}
