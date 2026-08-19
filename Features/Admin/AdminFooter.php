<?php
/**
 * Pied de page de l'administration
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Admin;

use Theme\Contracts\FeatureInterface;

/** Remplace le texte du pied de page de l'administration par les crédits de l'agence. */
final class AdminFooter implements FeatureInterface {

    /** Nom de l'agence affiché dans le footer admin. */
    private const AGENCY_NAME = 'Deefuse';

    /** URL du site de l'agence. */
    private const AGENCY_URL = 'https://www.deefuse.fr';

    /** Courte description de l'agence. */
    private const AGENCY_DESC = 'Agence digitale';

    /** Enregistre le filtre de personnalisation du pied de page admin. */
    public function register(): void {
        add_filter( 'admin_footer_text', [ $this, 'replace_footer_text' ] );
    }

    /**
     * Retourne le texte de crédit affiché en bas de chaque page de l'administration.
     *
     * Le lien est assemblé hors de la chaîne traduisible : le traducteur ne
     * manipule que des marqueurs, jamais de balises. Une traduction fautive peut
     * au pire déplacer ou omettre un marqueur, pas produire du HTML cassé.
     */
    public function replace_footer_text(): string {
        $link = sprintf(
            '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
            esc_url( self::AGENCY_URL ),
            esc_html( self::AGENCY_NAME )
        );

        return sprintf(
            /* translators: 1: lien vers le site de l'agence, 2: description de l'agence */
            __( 'Une création %1$s — %2$s', 'mon-theme' ),
            $link,
            esc_html( self::AGENCY_DESC )
        );
    }
}
