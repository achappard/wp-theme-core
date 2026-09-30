<?php
/**
 * Support des fichiers SVG dans la médiathèque
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use DOMDocument;
use DOMElement;
use enshrined\svgSanitize\Sanitizer;
use Exception;

/**
 * Autorise l'upload de SVG dans la médiathèque, en assainissant chaque fichier
 * avant qu'il n'atteigne le dossier d'uploads.
 *
 * Un SVG est un document XML exécutable : il peut embarquer du JavaScript, charger
 * des ressources distantes ou déclarer des entités. WordPress le refuse par défaut
 * pour cette raison. La feature ne se contente donc pas d'ajouter le type MIME —
 * elle réécrit le fichier débarrassé de tout ce qui peut s'exécuter, et rejette
 * l'upload si le document n'est pas un SVG lisible.
 *
 * L'assainissement est confié à `enshrined/svg-sanitize` (celui de Safe SVG et de
 * WordPress VIP) : une liste blanche d'éléments et d'attributs, et de schémas d'URL.
 * Une liste noire maison laissait passer `java&#9;script:` — l'entité décodée par le
 * parseur, puis ignorée par le navigateur — ou un `href` sous un préfixe XLink
 * arbitraire.
 */
final class SvgSupport implements FeatureInterface {

    /** Type MIME des fichiers SVG. */
    private const MIME_TYPE = 'image/svg+xml';

    /**
     * Capacité requise pour déposer un SVG.
     *
     * `upload_files` (tout contributeur à la médiathèque) suffit : chaque fichier est
     * assaini avant écriture. Passer à `manage_options` pour réserver le SVG aux
     * administrateurs si le client édite lui-même ses médias.
     */
    private const REQUIRED_CAPABILITY = 'upload_files';

    /** Enregistre les hooks d'autorisation, d'assainissement et d'affichage. */
    public function register(): void {
        add_filter( 'upload_mimes', [ $this, 'allow_svg_mime' ] );
        add_filter( 'wp_check_filetype_and_ext', [ $this, 'resolve_svg_filetype' ], 10, 4 );
        add_filter( 'wp_handle_upload_prefilter', [ $this, 'sanitize_uploaded_file' ] );
        add_filter( 'wp_handle_sideload_prefilter', [ $this, 'sanitize_uploaded_file' ] );
        add_filter( 'wp_prepare_attachment_for_js', [ $this, 'add_svg_dimensions' ], 10, 2 );
        add_filter( 'wp_get_attachment_image_src', [ $this, 'fill_svg_image_src' ], 10, 2 );
        add_action( 'admin_head', [ $this, 'print_preview_styles' ] );
    }

    /**
     * Ajoute le type MIME SVG aux extensions autorisées, pour les seuls utilisateurs
     * disposant de la capacité requise.
     *
     * @param array<string, string> $mimes Extensions autorisées, indexées par motif d'extension.
     *
     * @return array<string, string>
     */
    public function allow_svg_mime( array $mimes ): array {
        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            return $mimes;
        }

        $mimes['svg'] = self::MIME_TYPE;

        return $mimes;
    }

    /**
     * Rétablit l'extension et le type MIME du fichier déposé.
     *
     * Depuis WP 4.7.1, `wp_check_filetype_and_ext()` compare l'extension au type MIME
     * réel détecté par `finfo`, qui voit un SVG comme du `text/plain` ou du `text/html` :
     * l'upload est alors vidé de son `ext` et de son `type`, puis refusé. On le rétablit
     * à partir du seul nom de fichier, mais uniquement si `svg` fait partie des types
     * autorisés — donc jamais pour un utilisateur qui n'a pas la capacité requise.
     *
     * @param array<string, mixed>       $data     Résultat de la détection (`ext`, `type`, `proper_filename`).
     * @param string|null                $file     Chemin du fichier temporaire.
     * @param string                     $filename Nom du fichier déposé.
     * @param array<string, string>|null $mimes    Types MIME autorisés pour cet utilisateur.
     *
     * @return array<string, mixed>
     */
    public function resolve_svg_filetype( array $data, ?string $file, string $filename, ?array $mimes ): array {
        if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
            return $data;
        }

        $check = wp_check_filetype( $filename, $mimes );

        if ( 'svg' === $check['ext'] ) {
            $data['ext']  = 'svg';
            $data['type'] = self::MIME_TYPE;
        }

        return $data;
    }

    /**
     * Assainit le SVG déposé avant son écriture définitive dans les uploads.
     *
     * Le fichier temporaire est réécrit sur place : ce qui atterrit dans la
     * médiathèque est toujours la version nettoyée, jamais l'original. Un document
     * illisible ou non conforme fait échouer l'upload avec un message explicite,
     * plutôt que d'être stocké tel quel.
     *
     * Branché sur les deux préfiltres : `wp_handle_upload()` (formulaire, upload
     * multipart de l'API REST) et `wp_handle_sideload()` (corps brut envoyé à
     * `POST /wp/v2/media`, `media_handle_sideload()`). Un seul des deux laisserait
     * l'autre chemin écrire le SVG sans nettoyage.
     *
     * @param array<string, mixed> $file Fichier en cours d'upload (`name`, `type`, `tmp_name`, `error`, `size`).
     *
     * @return array<string, mixed>
     */
    public function sanitize_uploaded_file( array $file ): array {
        if ( ! empty( $file['error'] ) ) {
            return $file;
        }

        $extension = strtolower( (string) pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) );

        if ( 'svg' !== $extension ) {
            return $file;
        }

        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            $file['error'] = __( 'Vous n’êtes pas autorisé à envoyer des fichiers SVG.', 'wp-theme-core' );

            return $file;
        }

        $path     = (string) ( $file['tmp_name'] ?? '' );
        $original = is_readable( $path ) ? file_get_contents( $path ) : false;

        if ( false === $original ) {
            $file['error'] = __( 'Le fichier SVG n’a pas pu être lu.', 'wp-theme-core' );

            return $file;
        }

        $sanitized = $this->sanitize_markup( $original );

        if ( null === $sanitized ) {
            $file['error'] = __( 'Ce fichier SVG a été refusé : il est invalide ou contient du code non autorisé.', 'wp-theme-core' );

            return $file;
        }

        if ( false === file_put_contents( $path, $sanitized ) ) {
            $file['error'] = __( 'Le fichier SVG n’a pas pu être nettoyé avant son enregistrement.', 'wp-theme-core' );
        }

        return $file;
    }

    /**
     * Renvoie le SVG débarrassé de tout ce qui peut s'exécuter, ou `null` si le
     * document n'est pas un SVG exploitable.
     *
     * Les déclarations d'entités sont rejetées en amont plutôt que filtrées : elles
     * n'ont aucun usage légitime dans un SVG exporté par un outil de dessin, et sont
     * le support des attaques XXE et « billion laughs ». Les références distantes
     * (URL http, `@import`, `url()` d'une feuille de style) sont retirées : elles
     * feraient fuiter chaque affichage vers un tiers.
     */
    private function sanitize_markup( string $content ): ?string {
        $content = trim( $content );

        if ( '' === $content || preg_match( '/<!ENTITY/i', $content ) || ! $this->is_svg_document( $content ) ) {
            return null;
        }

        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences( true );
        $sanitizer->removeXMLTag( true );

        // La bibliothèque lève une exception sur un `<use>` qui se référence lui-même ou
        // sur des `<use>` imbriqués au-delà de sa limite (parade « billion laughs ») : un refus.
        try {
            $markup = $sanitizer->sanitize( $content );
        } catch ( Exception ) {
            return null;
        }

        // Contrôle répété en sortie : une racine que la liste blanche ne reconnaît pas
        // (`<s:svg>`) est retirée avec tout son contenu, laissant un document vide.
        if ( ! is_string( $markup ) || ! $this->is_svg_document( $markup ) ) {
            return null;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . trim( $markup ) . "\n";
    }

    /**
     * Indique si le contenu est un document XML bien formé dont la racine est `<svg>`.
     *
     * La liste blanche retire les éléments inconnus, pas le document qui les porte :
     * sans ce contrôle, un fichier XHTML renommé en `.svg` en ressortirait vidé mais
     * accepté.
     */
    private function is_svg_document( string $content ): bool {
        $previous_state = libxml_use_internal_errors( true );

        $dom    = new DOMDocument();
        $loaded = $dom->loadXML( $content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );

        libxml_clear_errors();
        libxml_use_internal_errors( $previous_state );

        return $loaded
            && $dom->documentElement instanceof DOMElement
            && 'svg' === strtolower( $dom->documentElement->localName );
    }

    /**
     * Fournit à la médiathèque les dimensions et l'aperçu du SVG.
     *
     * WordPress dérive ces informations des métadonnées d'image générées à l'upload,
     * que le SVG n'a pas : sans ce filtre, la vignette est vide et le sélecteur de
     * média affiche une taille nulle.
     *
     * @param array<string, mixed> $response   Données de la pièce jointe transmises au JS.
     * @param \WP_Post             $attachment Pièce jointe concernée.
     *
     * @return array<string, mixed>
     */
    public function add_svg_dimensions( array $response, $attachment ): array {
        if ( self::MIME_TYPE !== get_post_mime_type( $attachment ) ) {
            return $response;
        }

        $url        = (string) wp_get_attachment_url( $attachment->ID );
        $dimensions = $this->read_dimensions( get_attached_file( $attachment->ID ) );

        $response['icon']  = $url;
        $response['image'] = [
            'src'    => $url,
            'width'  => $dimensions['width'],
            'height' => $dimensions['height'],
        ];
        $response['thumb'] = $response['image'];
        $response['sizes'] = [
            'full' => [
                'url'         => $url,
                'width'       => $dimensions['width'],
                'height'      => $dimensions['height'],
                'orientation' => $dimensions['height'] > $dimensions['width'] ? 'portrait' : 'landscape',
            ],
        ];

        return $response;
    }

    /**
     * Complète la largeur et la hauteur renvoyées par `wp_get_attachment_image_src()`.
     *
     * Sans elles, `wp_get_attachment_image()` sort un `<img>` sans dimensions : le SVG
     * s'étire alors à la taille de son conteneur et fait sauter la mise en page au chargement.
     *
     * @param array<int, mixed>|false $image         Tableau `[url, width, height, is_intermediate]`.
     * @param int                     $attachment_id Identifiant de la pièce jointe.
     *
     * @return array<int, mixed>|false
     */
    public function fill_svg_image_src( array|false $image, int $attachment_id ): array|false {
        if ( ! is_array( $image ) || self::MIME_TYPE !== get_post_mime_type( $attachment_id ) ) {
            return $image;
        }

        if ( ! empty( $image[1] ) && ! empty( $image[2] ) ) {
            return $image;
        }

        $dimensions = $this->read_dimensions( get_attached_file( $attachment_id ) );

        $image[1] = $dimensions['width'];
        $image[2] = $dimensions['height'];

        return $image;
    }

    /**
     * Lit les dimensions déclarées par le SVG, en repli sur son `viewBox` puis sur
     * un carré de 100 px quand le fichier n'en déclare aucune.
     *
     * @return array{width: int, height: int}
     */
    private function read_dimensions( string|false $path ): array {
        $fallback = [
            'width'  => 100,
            'height' => 100,
        ];

        if ( ! is_string( $path ) || ! is_readable( $path ) ) {
            return $fallback;
        }

        $content = file_get_contents( $path );

        if ( false === $content ) {
            return $fallback;
        }

        $previous_state = libxml_use_internal_errors( true );
        $svg            = simplexml_load_string( $content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );

        libxml_clear_errors();
        libxml_use_internal_errors( $previous_state );

        if ( false === $svg ) {
            return $fallback;
        }

        $attributes = $svg->attributes();
        $width      = isset( $attributes->width ) ? (float) $attributes->width : 0.0;
        $height     = isset( $attributes->height ) ? (float) $attributes->height : 0.0;

        if ( ( $width <= 0 || $height <= 0 ) && isset( $attributes->viewBox ) ) {
            $box = preg_split( '/[\s,]+/', trim( (string) $attributes->viewBox ) );

            if ( is_array( $box ) && 4 === count( $box ) ) {
                $width  = (float) $box[2];
                $height = (float) $box[3];
            }
        }

        if ( $width <= 0 || $height <= 0 ) {
            return $fallback;
        }

        return [
            'width'  => (int) round( $width ),
            'height' => (int) round( $height ),
        ];
    }

    /**
     * Contraint l'aperçu des SVG dans la médiathèque.
     *
     * Un SVG sans dimensions intrinsèques s'affiche sinon à la taille de son
     * conteneur, et déborde des vignettes de la grille comme de la colonne de la
     * vue liste.
     */
    public function print_preview_styles(): void {
        echo '<style>'
            . '.attachment .thumbnail img[src$=".svg"],'
            . '.media-icon img[src$=".svg"],'
            . '.wp_attachment_image img[src$=".svg"],'
            . '.attachment-info .thumbnail img[src$=".svg"]'
            . '{ width: 100%; height: auto; max-height: 100%; object-fit: contain; }'
            . '</style>';
    }
}
