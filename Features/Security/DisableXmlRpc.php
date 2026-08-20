<?php
/**
 * Fermeture de l'API XML-RPC
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Security;

use Theme\Contracts\FeatureInterface;

/**
 * Coupe intégralement l'API XML-RPC (`xmlrpc.php`).
 *
 * Cette API distante, antérieure à REST, n'a aucun usage sur ce projet : ni client de
 * publication externe, ni pingback voulu. Elle reste pourtant une surface d'attaque active —
 * un second canal d'authentification hors de `wp-login.php`, amplifiable via `system.multicall`
 * (des centaines de mots de passe testés en une requête), et un `pingback.ping` qui fait du
 * serveur un intermédiaire de requêtes sortantes. On la ferme donc entièrement.
 *
 * Le filtre `xmlrpc_enabled` du core ne suffit pas : de son propre aveu (docblock de
 * `wp_xmlrpc_server::set_is_enabled()`), il ne désactive que les méthodes authentifiées et
 * laisse répondre les pingbacks. La fermeture se fait donc à la racine : sur `wp_loaded`, qui
 * s'exécute à la fin de `wp-load.php` — donc avant que `xmlrpc.php` n'instancie son serveur ou
 * ne serve le RSD — on renvoie un 403 et on arrête là toute requête portant la constante
 * `XMLRPC_REQUEST`. Aucune méthode n'est plus atteignable, `pingback.ping` compris.
 *
 * L'en-tête `X-Pingback`, qui annonçait l'endpoint dans chaque réponse d'article, est retiré :
 * il ne pointerait plus que vers une porte fermée.
 */
final class DisableXmlRpc implements FeatureInterface {

    /** Enregistre les hooks de fermeture de XML-RPC. */
    public function register(): void {
        add_action( 'wp_loaded', [ $this, 'block_endpoint' ] );
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'wp_headers', [ $this, 'remove_pingback_header' ] );
    }

    /**
     * Ferme l'endpoint `xmlrpc.php` avant tout traitement.
     *
     * `xmlrpc.php` définit la constante `XMLRPC_REQUEST` puis charge `wp-load.php`, à la fin
     * duquel `wp_loaded` se déclenche : la constante est donc déjà posée ici, et le serveur
     * XML-RPC (comme le bloc RSD) n'est pas encore atteint. Un 403 suivi d'un `exit` neutralise
     * la requête sans qu'aucune méthode ne soit évaluée. Le hook est transparent pour toute
     * autre requête, qui ne porte pas cette constante.
     */
    public function block_endpoint(): void {
        if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
            return;
        }

        status_header( 403 );
        header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
        exit( 'XML-RPC services are disabled on this site.' );
    }

    /**
     * Retire l'en-tête `X-Pingback` des réponses.
     *
     * Le core l'ajoute sur chaque contenu autorisant les pings (`WP_::send_headers()`), pour
     * annoncer `xmlrpc.php` comme point d'entrée des pingbacks. L'endpoint étant fermé, cet
     * en-tête ne désigne plus qu'une porte close : autant ne pas l'exposer.
     *
     * @param array $headers En-têtes HTTP sur le point d'être envoyés.
     * @return array
     */
    public function remove_pingback_header( array $headers ): array {
        unset( $headers['X-Pingback'] );

        return $headers;
    }
}
