# Changelog

Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnement
[SemVer](https://semver.org/lang/fr/).

Chaque entrée porte une ligne **Impact projet**, seule information qu'on ne retrouve plus en relisant
le diff six mois plus tard :

- `aucun` — `composer update` suffit
- `à vérifier visuellement` — le rendu peut changer, passer la checklist de recette
- `nécessite une modification du provider` — le projet doit être modifié à la main

---

## [1.0.0] — 2026-08-21

Première version : extraction du socle depuis un thème client, dont l'historique complet est
conservé (34 commits, de l'initialisation à l'extraction).

### Ajouté

- `Kernel` — initialisation de Timber, échappement Twig automatique, chargement des providers dans
  l'ordre reçu, enregistrement du text-domain `wp-theme-core`
- `ThemeContext` — `slug`, `text_domain`, `asset_prefix`, `block_namespace`, `view_paths`, `handle()`
- `Contracts\FeatureInterface`, `Contracts\ServiceProviderInterface`
- `Support\ViteManifest`, `Support\ViteDevServer`
- 27 features : 5 `Security`, 6 `Front`, 14 `Admin`, 1 `Setup`, 1 `Seo`

### Notes

- L'ordre des providers appartient au thème (`functions.php`), pas au socle
- Les features sont `final` : la surcharge passe par le retrait, le paramétrage ou le remplacement
- `palette` et `icon_colors` d'`AdminColorScheme`, et les trois arguments d'`AdminFooter`, n'ont pas
  de valeur par défaut — les omettre lève une `ArgumentCountError` au démarrage plutôt que de livrer
  un site aux couleurs d'un autre client
- Aucune feature d'internationalisation du thème : WordPress s'en charge depuis les en-têtes de
  `style.css`, avant même l'inclusion de `functions.php`

**Impact projet :** aucun — version initiale.
