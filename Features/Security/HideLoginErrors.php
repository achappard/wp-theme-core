<?php
/**
 * Neutralisation des messages du flux de connexion
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Security;

use Theme\Contracts\FeatureInterface;
use WP_Error;

/**
 * Empêche le flux de connexion de révéler quels comptes existent.
 *
 * Deux points de fuite, deux traitements :
 *
 * 1. **Écran de connexion.** WordPress distingue un identifiant inconnu (« l'identifiant X
 *    n'est pas identifié ») d'un mot de passe erroné (« le mot de passe ne correspond pas à
 *    l'identifiant X ») : cette différence confirme l'existence d'un compte. Un même message
 *    dans tous les cas supprime la distinction. Le filtre `login_errors` est appliqué dans
 *    `login_header()`, partagée par *toutes* les actions de wp-login.php ; on ne réécrit donc
 *    que lorsque l'action courante est bien `login`, sans quoi le message contaminerait le
 *    mot de passe oublié, la réinitialisation et l'inscription.
 *
 * 2. **Mot de passe oublié.** Pour un compte inexistant, le core répond « Il n'y a pas de
 *    compte avec cet identifiant… », là où un compte existant redirige vers « Consultez votre
 *    e-mail » : même canal d'énumération, autre formulaire. Sur `lostpassword_post`, on rend
 *    les deux cas indistinguables — compte inexistant comme existant aboutissent à la même
 *    confirmation, sans qu'aucun e-mail ne parte pour un compte qui n'existe pas.
 *
 * Le durcissement de l'écran de connexion est assumé : `login_errors` ne reçoit que le HTML
 * déjà rendu, sans les codes d'erreur, donc « champ vide » y devient aussi le message
 * générique. C'est le compromis retenu pour ce formulaire.
 */
final class HideLoginErrors implements FeatureInterface {

    /** Enregistre les hooks de neutralisation du flux de connexion. */
    public function register(): void {
        add_filter( 'login_errors', [ $this, 'genericize' ] );
        add_action( 'lostpassword_post', [ $this, 'neutralize_lostpassword' ], 10, 2 );
    }

    /**
     * Remplace le message d'échec de l'écran de connexion par une formule unique.
     *
     * Restreint à l'action `login` : `login_errors` est partagé par toutes les pages de
     * wp-login.php (`login_header()`), et le message ne doit toucher que la connexion.
     * `wp-login.php` expose l'action courante dans la variable globale `$action`.
     *
     * @param string $errors Messages d'erreur déjà rendus en HTML.
     * @return string
     */
    public function genericize( string $errors ): string {
        if ( 'login' !== ( $GLOBALS['action'] ?? 'login' ) ) {
            return $errors;
        }

        return __( 'Identifiant ou mot de passe incorrect.', 'mon-theme' );
    }

    /**
     * Rend indistinguables les demandes de réinitialisation, que le compte existe ou non.
     *
     * `retrieve_password()` signale l'absence de compte (`invalid_email` pour une adresse,
     * `invalidcombo` pour un identifiant) et affiche ce message, alors qu'un compte existant
     * mène à la confirmation « Consultez votre e-mail ». Pour fermer cette énumération, un
     * compte inexistant doit aboutir à la même confirmation.
     *
     * Ce hook est tiré avant l'ajout de `invalidcombo` : il suffit de retirer l'erreur de
     * non-existence liée à une adresse, puis, si rien d'autre ne cloche (un champ vide reste
     * une erreur légitime, laissée au core), de rediriger vers la confirmation sans envoyer
     * d'e-mail — le core, lui, l'enverra pour un compte réel. Un compte existant sort
     * immédiatement et suit le flux normal.
     *
     * @param WP_Error      $errors    Erreurs accumulées par `retrieve_password()`.
     * @param \WP_User|false $user_data Compte trouvé, ou `false` s'il n'existe pas.
     * @return void
     */
    public function neutralize_lostpassword( WP_Error $errors, $user_data ): void {
        if ( $user_data ) {
            return;
        }

        $errors->remove( 'invalid_email' );

        if ( $errors->has_errors() ) {
            return;
        }

        wp_safe_redirect( 'wp-login.php?checkemail=confirm' );
        exit;
    }
}
