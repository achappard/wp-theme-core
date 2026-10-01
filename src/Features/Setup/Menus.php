<?php
/**
 * Emplacements de menus déclarés auprès de WordPress et exposés à Twig
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Setup;

use Closure;
use Deefuse\ThemeCore\Contracts\FeatureInterface;
use InvalidArgumentException;
use Timber\Timber;

/**
 * Déclare les emplacements de menus du thème et injecte l'objet `Timber\Menu`
 * correspondant à chacun dans le contexte Twig global.
 *
 * **Les libellés sont fournis par une closure, jamais un tableau.** `functions.php`
 * — donc les providers — s'exécute avant `after_setup_theme`. Un `__()` évalué à cet
 * endroit fait charger le catalogue de traduction trop tôt et déclenche le
 * `_doing_it_wrong` de WordPress 6.7 dès qu'un `.mo` existe pour la locale courante
 * (cf. `_load_textdomain_just_in_time()` dans wp-includes/l10n.php). La closure
 * diffère cette évaluation jusqu'à `register_locations()`, appelée sur
 * `after_setup_theme`. Le text-domain passé à `__()` à l'intérieur reste un littéral :
 * `wp i18n make-pot` continue de l'extraire normalement.
 *
 * **Chaque emplacement produit une clé de contexte `{emplacement}_menu`**, les tirets
 * remplacés par des underscores (`{{ main-nav_menu }}` serait lu comme une soustraction
 * par Twig). Un emplacement sans menu affecté vaut `null` : aux templates de garder la
 * main avec un `{% if %}`.
 *
 * @example
 * ```php
 * new Menus(
 *     locations: static fn(): array => [
 *         'primary' => __( 'Menu principal', 'mon-theme' ),
 *         'footer'  => __( 'Menu pied de page', 'mon-theme' ),
 *     ],
 * ),
 * ```
 * ```twig
 * {% if primary_menu %}
 *     {% include 'partials/navigation.twig' with { menu: primary_menu } %}
 * {% endif %}
 * ```
 */
final class Menus implements FeatureInterface {

    /** Emplacements résolus, mémoïsés après le premier appel à la closure. */
    private ?array $resolved = null;

    /**
     * @param Closure(): array<string, string> $locations Emplacements => libellés,
     *                                                     déjà traduits.
     */
    public function __construct(
        private readonly Closure $locations,
    ) {}

    /** Enregistre les hooks WordPress de la feature. */
    public function register(): void {
        add_action( 'after_setup_theme', [ $this, 'register_locations' ] );
        add_filter( 'timber/context', [ $this, 'add_menus_to_context' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Déclare les emplacements de menus du thème (Apparence › Menus).', 'wp-theme-core' );
    }

    /** Déclare les emplacements de menus auprès de WordPress. */
    public function register_locations(): void {
        $locations = $this->resolve_locations();

        if ( [] === $locations ) {
            return;
        }

        register_nav_menus( $locations );
    }

    /**
     * Injecte, pour chaque emplacement déclaré, l'objet `Timber\Menu` correspondant
     * dans le contexte global Twig.
     *
     * `Timber::get_menu_by( 'location', … )` et non `Timber::get_menu( $location )` :
     * ce dernier retombe sur une résolution par slug puis par nom si aucun menu n'est
     * affecté à l'emplacement, avec le risque documenté par `MenuFactory::from()` de
     * retourner un menu dont le slug ou le nom coïncide avec l'emplacement demandé.
     *
     * @param  array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function add_menus_to_context( array $context ): array {
        foreach ( array_keys( $this->resolve_locations() ) as $location ) {
            $context[ self::context_key( $location ) ] = Timber::get_menu_by( 'location', $location );
        }

        return $context;
    }

    /**
     * Résout la closure et valide son résultat, une seule fois.
     *
     * @return array<string, string>
     *
     * @throws InvalidArgumentException Si une clé ou une valeur n'est pas une chaîne
     *                                  non vide — mieux vaut un échec net au démarrage
     *                                  qu'un emplacement WordPress muet ou sans libellé.
     */
    private function resolve_locations(): array {
        if ( null !== $this->resolved ) {
            return $this->resolved;
        }

        $locations = ( $this->locations )();

        foreach ( $locations as $location => $label ) {
            if ( ! is_string( $location ) || '' === $location ) {
                throw new InvalidArgumentException(
                    'Menus : chaque emplacement doit être identifié par une chaîne non vide.'
                );
            }

            if ( ! is_string( $label ) || '' === $label ) {
                throw new InvalidArgumentException(
                    sprintf( 'Menus : l\'emplacement "%s" doit avoir un libellé non vide.', $location )
                );
            }
        }

        return $this->resolved = $locations;
    }

    /** Construit la clé de contexte Twig d'un emplacement. */
    private static function context_key( string $location ): string {
        return str_replace( '-', '_', $location ) . '_menu';
    }
}
