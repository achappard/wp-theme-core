<?php
/**
 * Classe principale du thème
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme;

use Theme\Contracts\FeatureInterface;
use Timber\Timber;

class Theme {
    /**
     * Instance unique de la classe
     */
    private static ?Theme $instance = null;

    /**
     * Domaine de traduction du thème
     */
    private static string $textdomain = 'mon-theme';

    /**
     * Liste des features enregistrées
     *
     * @var FeatureInterface[]
     */
    private array $features = [];

    /**
     * Constructeur privé pour le pattern Singleton
     */
    private function __construct() {
        $this->init_timber();
        $this->load_features();
    }

    /**
     * Récupère l'instance unique de la classe
     */
    public static function get_instance(): Theme {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialise Timber
     */
    private function init_timber(): void {
        Timber::$dirname    = [ 'resources/views' ];
        Timber::$autoescape = false;

        add_filter( 'timber/context', [ $this, 'add_twig_context' ] );
    }

    /**
     * Ajoute les données globales au contexte Twig
     */
    public function add_twig_context( array $context ): array {
        $context['textdomain'] = self::$textdomain;

        return $context;
    }

    /**
     * Charge toutes les features du thème
     *
     * Ajouter ici les instanciations, groupées par catégorie :
     * Setup, Front, Security, Blocks, PostTypes, Admin, Seo.
     */
    private function load_features(): void {
        // Aucune feature enregistrée pour le moment.
    }

    /**
     * Enregistre une feature
     */
    private function register_feature( FeatureInterface $feature ): void {
        $feature->register();
        $this->features[] = $feature;
    }

    /**
     * Récupère le domaine de traduction du thème
     */
    public static function getTextdomain(): string {
        return self::$textdomain;
    }
}
