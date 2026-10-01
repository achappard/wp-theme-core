<?php
/**
 * Conversion des images en WebP à l'upload
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use GdImage;
use Imagick;
use Throwable;
use WP_Image_Editor;

/**
 * Convertit en WebP les JPEG et PNG déposés dans la médiathèque, **avant** que
 * WordPress n'en génère les miniatures : le fichier principal, la version `-scaled`
 * et toutes les tailles intermédiaires sortent en WebP, et l'original est supprimé.
 *
 * **Pourquoi `wp_handle_upload`.** Le filtre s'applique juste après le déplacement du
 * fichier dans `uploads/`, avant la création de la pièce jointe et l'appel à
 * `wp_generate_attachment_metadata()`. Il est commun aux deux chemins d'upload :
 * `upload` (Médias → Ajouter) et `sideload`, celui de l'API REST — donc de l'éditeur
 * de blocs. Renvoyer le chemin, l'URL et le type du `.webp` suffit : tout ce qui suit
 * travaille sur lui.
 *
 * **Qualité : un master aussi fidèle que possible.** Les miniatures sont réencodées
 * par le core de toute façon ; seule la qualité du fichier principal est donc réglée
 * ici. JPEG → WebP à `$quality` (90 : une seconde compression imperceptible). Les
 * miniatures gardent la qualité par défaut du core.
 *
 * **PNG : sans perte, sauf si c'est une photo.** Un PNG est encodé deux fois : sans perte
 * (référence) et à `$quality`. La version avec perte n'est retenue que si elle allège le
 * fichier d'au moins `$png_lossy_min_saving`. Mesuré à l'intégration : une photo
 * enregistrée en PNG perd 92 % de son poids avec perte contre 25 % sans perte, alors
 * qu'une capture, un logo ou un aplat est déjà divisé par 3 ou 4 sans perte — et que la
 * version avec perte y est même plus lourde. Le seuil départage les deux familles sans
 * rien demander à l'éditeur : les graphismes restent pixel pour pixel identiques,
 * transparence comprise. `WP_Image_Editor` n'exposant pas d'option lossless, l'encodage
 * sans perte passe par Imagick ou GD en direct.
 *
 * **Le traitement des médias côté navigateur est coupé.** Depuis WordPress 7.1,
 * l'éditeur de blocs redimensionne les images dans le navigateur dès que la page est
 * servie en HTTPS ou sur `localhost` : les miniatures seraient alors produites hors de
 * PHP, sans passer par cette conversion. Le filtre
 * `wp_client_side_media_processing_enabled` ramène tout le traitement sur le serveur.
 *
 * **Orientation.** Le WebP produit ne conserve pas l'EXIF : la rotation EXIF des
 * photos de smartphone est donc appliquée aux pixels avant la sauvegarde, faute de
 * quoi les portraits sortiraient couchés. Les autres métadonnées IPTC / EXIF (légende,
 * crédit, appareil, lues par `wp_read_image_metadata()`) sont perdues — assumé.
 *
 * Une conversion impossible (format non supporté par le serveur, fichier illisible)
 * ne bloque jamais l'upload : l'original est alors conservé tel quel.
 */
final class ConvertUploadsToWebp implements FeatureInterface {

    /** Type MIME de sortie. */
    private const WEBP = 'image/webp';

    /** Types MIME convertis par défaut. GIF exclu : WordPress n'en conserve pas l'animation. */
    private const DEFAULT_MIME_TYPES = [ 'image/jpeg', 'image/png' ];

    /** Qualité WebP du fichier principal encodé avec perte, par défaut. */
    private const DEFAULT_QUALITY = 90;

    /** Gain minimal exigé d'un PNG encodé avec perte, par défaut (0.5 = poids divisé par deux). */
    private const DEFAULT_PNG_LOSSY_MIN_SAVING = 0.5;

    /**
     * @param string[] $mime_types           Types MIME convertis.
     * @param int      $quality              Qualité WebP du fichier principal encodé avec perte (1-100) :
     *                                       tout JPEG, et les PNG photographiques.
     * @param float    $png_lossy_min_saving Gain de poids minimal (0-1) pour qu'un PNG soit gardé avec
     *                                       perte plutôt que sans perte. `1.0` impose le sans perte.
     */
    public function __construct(
        private readonly array $mime_types = self::DEFAULT_MIME_TYPES,
        private readonly int $quality = self::DEFAULT_QUALITY,
        private readonly float $png_lossy_min_saving = self::DEFAULT_PNG_LOSSY_MIN_SAVING,
    ) {}

    /** Enregistre la conversion à l'upload et ses filets de sécurité. */
    public function register(): void {
        add_filter( 'wp_handle_upload', [ $this, 'convert' ], 10, 2 );
        add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );
        add_filter( 'image_editor_output_format', [ $this, 'output_format' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Convertit au format WebP les images JPEG et PNG ajoutées à la médiathèque, miniatures comprises, pour alléger les pages.', 'wp-theme-core' );
    }

    /**
     * Force le WebP pour toute taille générée plus tard à partir d'un JPEG ou d'un PNG
     * resté dans la médiathèque (import antérieur, conversion qui aurait échoué).
     *
     * @param array<string, string> $formats Correspondances type source → type de sortie.
     * @return array<string, string>
     */
    public function output_format( array $formats ): array {
        foreach ( $this->mime_types as $mime_type ) {
            $formats[ $mime_type ] = self::WEBP;
        }

        return $formats;
    }

    /**
     * Remplace le fichier déposé par sa version WebP.
     *
     * @param array{file: string, url: string, type: string, error?: string} $upload  Fichier déposé.
     * @param string                                                          $context `upload` ou `sideload`.
     * @return array{file: string, url: string, type: string, error?: string}
     */
    public function convert( array $upload, string $context ): array {
        if ( isset( $upload['error'] )
            || ! in_array( $upload['type'], $this->mime_types, true )
            || ! wp_image_editor_supports( [ 'mime_type' => self::WEBP ] )
        ) {
            return $upload;
        }

        $source = $upload['file'];
        $dir    = dirname( $source );

        /*
         * La source est écartée sous un nom neutre avant de calculer le nom cible :
         * `wp_unique_filename()` tient pour conflictuels deux fichiers de même nom dont
         * l'un est converti vers le format de l'autre (cf. `output_format()`). Laissée
         * en place, `photo.jpg` ferait nommer la conversion `photo-1.webp`.
         */
        $pending = $source . '.pending';

        if ( ! @rename( $source, $pending ) ) {
            return $upload;
        }

        $target = $dir . '/' . wp_unique_filename( $dir, pathinfo( $source, PATHINFO_FILENAME ) . '.webp' );

        $converted = 'image/png' === $upload['type']
            ? $this->convert_png( $pending, $target )
            : $this->convert_lossy( $pending, $target, $upload['type'] );

        if ( ! $converted || ! is_file( $target ) ) {
            rename( $pending, $source );

            return $upload;
        }

        wp_delete_file( $pending );

        return [
            'file' => $target,
            'url'  => dirname( $upload['url'] ) . '/' . basename( $target ),
            'type' => self::WEBP,
        ];
    }

    /**
     * Conversion avec perte (JPEG), par l'éditeur d'images du core.
     *
     * `maybe_exif_rotate()` redresse les pixels selon l'orientation EXIF et, sous
     * Imagick, remet le drapeau d'orientation à zéro : l'image est droite sans EXIF.
     *
     * Le type MIME est transmis explicitement : la source porte à ce stade une
     * extension `.pending`, dont `wp_get_image_editor()` ne saurait rien déduire.
     */
    private function convert_lossy( string $source, string $target, string $mime_type ): bool {
        $editor = wp_get_image_editor( $source, [ 'mime_type' => $mime_type ] );

        if ( ! $editor instanceof WP_Image_Editor ) {
            return false;
        }

        $editor->maybe_exif_rotate();
        $editor->set_quality( $this->quality );

        return ! is_wp_error( $editor->save( $target, self::WEBP ) );
    }

    /**
     * Conversion d'un PNG : sans perte, ou avec perte si le gain l'emporte nettement.
     *
     * Le candidat avec perte est écrit à côté de la cible, puis la remplace ou disparaît.
     * Son échec éventuel n'empêche rien : la version sans perte est déjà en place.
     */
    private function convert_png( string $source, string $target ): bool {
        if ( ! $this->convert_lossless( $source, $target ) ) {
            return false;
        }

        if ( $this->png_lossy_min_saving >= 1.0 ) {
            return true;
        }

        $candidate = substr( $target, 0, -strlen( '.webp' ) ) . '.lossy-candidate.webp';

        if ( ! $this->convert_lossy( $source, $candidate, 'image/png' ) || ! is_file( $candidate ) ) {
            wp_delete_file( $candidate );

            return true;
        }

        clearstatcache();

        if ( filesize( $candidate ) <= filesize( $target ) * ( 1 - $this->png_lossy_min_saving ) ) {
            return rename( $candidate, $target );
        }

        wp_delete_file( $candidate );

        return true;
    }

    /** Conversion sans perte (PNG) : Imagick si disponible, GD sinon. */
    private function convert_lossless( string $source, string $target ): bool {
        if ( class_exists( Imagick::class ) && Imagick::queryFormats( 'WEBP' ) ) {
            try {
                $image = new Imagick( $source );
                $image->setImageFormat( 'webp' );
                $image->setOption( 'webp:lossless', 'true' );
                $written = $image->writeImage( $target );
                $image->clear();

                return $written;
            } catch ( Throwable ) {
                // Repli sur GD ci-dessous.
            }
        }

        if ( ! function_exists( 'imagewebp' ) || ! defined( 'IMG_WEBP_LOSSLESS' ) ) {
            return false;
        }

        $image = @imagecreatefrompng( $source );

        if ( ! $image instanceof GdImage ) {
            return false;
        }

        // Un PNG en palette doit passer en couleurs vraies pour que GD écrive l'alpha.
        imagepalettetotruecolor( $image );
        imagealphablending( $image, false );
        imagesavealpha( $image, true );

        return imagewebp( $image, $target, IMG_WEBP_LOSSLESS );
    }
}
