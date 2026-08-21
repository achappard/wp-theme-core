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
 * Le manifest est lu une fois par instance — il ne peut pas changer en cours de
 * requête. Les chemins passent par `get_theme_file_path()` / `get_theme_file_uri()`,
 * qui résolvent le thème actif quel que soit l'emplacement de cette classe et
 * exposent les filtres `theme_file_path` / `theme_file_uri`.
 */
final class ViteManifest {

    /** Répertoire de sortie de Vite, relatif à la racine du thème. */
    private const DIST_DIR = 'dist/assets';

    /** Chemin du manifest, relatif au répertoire de sortie. */
    private const MANIFEST = '/.vite/manifest.json';

    /** Manifest décodé, mémorisé pour la durée de vie de l'instance. */
    private ?array $manifest = null;

    /**
     * Retourne la description d'une entrée du manifest.
     *
     * @param string $entry Chemin de la source, tel que déclaré dans vite.config.js.
     * @return array Tableau vide si le build manque ou si l'entrée est inconnue.
     */
    public function entry( string $entry ): array {
        $data = $this->load()[ $entry ] ?? [];

        return is_array( $data ) ? $data : [];
    }

    /**
     * URL publique du fichier émis pour une entrée.
     *
     * @param string $entry Chemin de la source, tel que déclaré dans vite.config.js.
     * @return string Chaîne vide si le build manque ou si l'entrée est inconnue.
     */
    public function entry_url( string $entry ): string {
        $file = (string) ( $this->entry( $entry )['file'] ?? '' );

        return '' === $file ? '' : $this->asset_url( $file );
    }

    /**
     * URL publique d'un fichier émis, désigné par son chemin dans le manifest.
     *
     * @param string $file Chemin relatif au répertoire de sortie (ex. `app-a1b2c3.css`).
     */
    public function asset_url( string $file ): string {
        return get_theme_file_uri( self::DIST_DIR . '/' . $file );
    }

    /**
     * Lit et décode le manifest, une seule fois.
     *
     * Un manifest absent (build jamais lancé) comme un manifest illisible
     * retournent un tableau vide : c'est aux appelants de dégrader proprement.
     * Sans le test sur `is_array()`, un fichier tronqué décodé en scalaire
     * traverserait la méthode et ferait échouer le type de retour — page blanche
     * là où l'on veut simplement un asset manquant.
     */
    private function load(): array {
        if ( null !== $this->manifest ) {
            return $this->manifest;
        }

        $path = get_theme_file_path( self::DIST_DIR . self::MANIFEST );

        $data = file_exists( $path )
            ? json_decode( (string) file_get_contents( $path ), true )
            : null;

        $this->manifest = is_array( $data ) ? $data : [];

        return $this->manifest;
    }
}
