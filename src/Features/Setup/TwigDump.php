<?php
/**
 * Fonction Twig dump() pour l'inspection de variables en debug
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Setup;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * Remplace le `dump()` natif de Twig (`Twig\Extension\DebugExtension`, basé sur
 * `var_dump`) par un rendu HTML lisible via Symfony VarDumper.
 *
 * `symfony/var-dumper` est une dépendance de dev du projet consommateur (jamais du
 * socle en transitif) : la feature se neutralise silencieusement si la classe est
 * absente — cas normal d'un `composer install --no-dev` en production.
 *
 * Le `dump()` du socle prend le pas sur celui de Twig même quand le mode debug est
 * actif (Timber fixe l'option `debug` de Twig sur `WP_DEBUG`) : les fonctions
 * ajoutées via `Environment::addFunction()` passent par une extension de staging,
 * initialisée après les extensions déclarées (dont `DebugExtension`) — le dernier
 * enregistré gagne, sans conflit de nom ni exception à intercepter.
 */
final class TwigDump implements FeatureInterface {

    /** Enregistre le filtre d'ajout de fonctions Twig. */
    public function register(): void {
        add_filter( 'timber/twig', [ $this, 'add_dump_function' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Outil de développement : affiche lisiblement les variables dans les templates, sans effet en production.', 'wp-theme-core' );
    }

    /**
     * Ajoute `dump()` à l'environnement Twig, si Symfony VarDumper est disponible.
     *
     * @param Environment $twig Environnement Twig de Timber.
     * @return Environment Environnement enrichi.
     */
    public function add_dump_function( Environment $twig ): Environment {
        if ( ! class_exists( VarCloner::class ) ) {
            return $twig;
        }

        $twig->addFunction( new TwigFunction(
            'dump',
            function ( array $context, mixed ...$vars ): string {
                $cloner = new VarCloner();
                $dumper = new HtmlDumper();
                $targets = empty( $vars ) ? array_values( $context ) : $vars;

                ob_start();
                foreach ( $targets as $var ) {
                    $dumper->dump( $cloner->cloneVar( $var ) );
                }
                return (string) ob_get_clean();
            },
            [ 'is_safe' => [ 'html' ], 'needs_context' => true ]
        ) );

        return $twig;
    }
}
