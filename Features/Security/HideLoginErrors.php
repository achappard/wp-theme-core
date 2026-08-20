<?php
/**
 * Neutralisation des messages d'erreur de connexion
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Security;

use Theme\Contracts\FeatureInterface;

/**
 * Remplace les messages d'erreur de connexion par une formule neutre et unique.
 *
 * Par défaut, WordPress distingue un identifiant inconnu (« l'identifiant X n'est pas
 * identifié ») d'un mot de passe erroné (« le mot de passe ne correspond pas à
 * l'identifiant X »). Cette différence confirme l'existence d'un compte : c'est un canal
 * d'énumération sur la page de connexion, qui rouvre ce que ferme `DisableUserEnumeration`
 * côté API. Un même message dans tous les cas d'échec supprime cette distinction.
 *
 * Le filtre `login_errors` ne reçoit que le HTML déjà rendu, sans les codes d'erreur : on ne
 * peut donc pas y trier « champ vide » de « identifiants faux ». Le durcissement est assumé —
 * toutes les erreurs du formulaire deviennent le même texte, un utilisateur légitime qui
 * oublie un champ voit un message un peu vague. C'est le compromis attendu de cette feature.
 *
 * Le formulaire « mot de passe oublié » n'est pas concerné : le core y répond déjà par une
 * confirmation générique, quel que soit le compte.
 */
final class HideLoginErrors implements FeatureInterface {

    /** Enregistre le hook de neutralisation des erreurs de connexion. */
    public function register(): void {
        add_filter( 'login_errors', [ $this, 'genericize' ] );
    }

    /**
     * Renvoie un message d'échec unique, qui ne révèle rien du compte visé.
     *
     * @return string
     */
    public function genericize(): string {
        return __( 'Identifiant ou mot de passe incorrect.', 'mon-theme' );
    }
}
