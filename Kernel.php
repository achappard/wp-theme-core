<?php
/**
 * Noyau du thème : initialisation de Timber et chargement des providers
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore;

use Deefuse\ThemeCore\Contracts\ServiceProviderInterface;
use InvalidArgumentException;
use Timber\Timber;

class Kernel {
    /**
     * Instance unique de la classe
     */
    private static ?Kernel $instance = null;

    /**
     * Constructeur privé pour le pattern Singleton
     *
     * @param ThemeContext           $context   Identité du thème.
     * @param class-string<ServiceProviderInterface>[] $providers Providers, dans leur ordre de chargement.
     */
    private function __construct(
        private readonly ThemeContext $context,
        private readonly array $providers,
    ) {
        $this->init_timber();
        $this->load_features();
    }

    /**
     * Démarre le thème
     *
     * L'ordre des providers est décidé par l'appelant (`functions.php`) et non ici :
     * le thème fournit un catalogue de features, le projet choisit lesquelles il
     * enregistre et dans quel ordre.
     *
     * @param ThemeContext $context   Identité du thème.
     * @param class-string<ServiceProviderInterface>[] $providers Providers, dans leur ordre de chargement.
     */
    public static function boot( ThemeContext $context, array $providers ): Kernel {
        return self::$instance ??= new self( $context, $providers );
    }

    /**
     * Récupère l'instance du thème, ou null s'il n'a pas encore démarré
     */
    public static function get_instance(): ?Kernel {
        return self::$instance;
    }

    /**
     * Identité du thème
     */
    public function context(): ThemeContext {
        return $this->context;
    }

    /**
     * Initialise Timber
     */
    private function init_timber(): void {
        Timber::$dirname = $this->context->view_paths;
        Timber::init();
        add_filter( 'timber/twig/environment/options', [ $this, 'set_twig_options' ] );
    }

    /**
     * Configure l'environnement Twig
     *
     * Active l'échappement automatique (Timber le désactive par défaut) : toute
     * sortie `{{ }}` est échappée sauf mention explicite `|raw`, réservée aux
     * valeurs qui contiennent volontairement du HTML (post.content, post.excerpt…).
     */
    public function set_twig_options( array $options ): array {
        $options['autoescape'] = 'html';

        return $options;
    }

    /**
     * Charge toutes les features du thème, provider par provider
     *
     * L'ordre reçu fait foi, à l'intérieur d'un provider comme entre providers.
     * Une feature s'ajoute dans le provider correspondant à son domaine, jamais ici.
     *
     * @throws InvalidArgumentException Si un provider n'honore pas le contrat — mieux
     *                                  vaut une erreur nette au démarrage qu'un appel
     *                                  statique sur une classe inattendue.
     */
    private function load_features(): void {
        foreach ( $this->providers as $provider ) {
            if ( ! is_subclass_of( $provider, ServiceProviderInterface::class, true ) ) {
                throw new InvalidArgumentException(
                    sprintf( '%s n\'implémente pas ServiceProviderInterface.', (string) $provider )
                );
            }

            foreach ( $provider::features( $this->context ) as $feature ) {
                $feature->register();
            }
        }
    }
}
