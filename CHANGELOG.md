# Changelog

Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnement
[SemVer](https://semver.org/lang/fr/).

Chaque entrée porte une ligne **Impact projet**, seule information qu'on ne retrouve plus en relisant
le diff six mois plus tard :

- `aucun` — `composer update` suffit
- `à vérifier visuellement` — le rendu peut changer, passer la checklist de recette
- `nécessite une modification du provider` — le projet doit être modifié à la main

---

## [1.1.0] — 2026-08-22

### Ajouté

- `Features\Admin\EditorStyles` — applique au canvas de l'éditeur de blocs la feuille
  `resources/scss/editor-gutenberg.scss`, résolue par le manifest Vite. Pose
  `add_theme_support( 'editor-styles' )`, sans lequel `get_block_editor_theme_styles()` ignore
  la feuille : `add_editor_style()` ne déclare que `editor-style`, le support de l'éditeur classique
- `Support\ViteManifest::entry_relative_path()` — chemin d'un fichier émis relatif à la racine du
  thème. `add_editor_style()` traite toute URL absolue par `wp_remote_get()` : une URL y ferait
  appeler le site par lui-même en HTTP à chaque ouverture de l'éditeur

### Notes

- La feuille est **toujours servie depuis `dist/assets/`**, comme `admin-colors.scss` : WordPress lit
  le fichier sur disque et inline son contenu. Aucun HMR dans le canvas — modifier les styles de
  l'éditeur impose un `npm run build`
- Sans build disponible, `EditorStyles` ne déclare rien, pas même le support : `editor-styles` fait
  aussi passer le sous-menu Apparence de « Compositions » à « Design » sur un thème classique
- Le core préfixe les sélecteurs par `.editor-styles-wrapper` et inline le CSS dans la page de
  l'éditeur : ne pas importer Bootstrap en entier dans cette feuille
- Une feuille qui ne produit **aucune règle** ne fait émettre aucun `.css` par Vite : le manifest
  pointe alors vers le module JavaScript fantôme émis à la place. `EditorStyles` vérifie donc
  l'extension avant de déclarer quoi que ce soit — sans quoi le core inlinerait ce JS dans le canvas

**Impact projet :** nécessite une modification du provider — ajouter `new EditorStyles()` à
`AdminServiceProvider`, créer `resources/scss/editor-gutenberg.scss` et déclarer l'entrée
`'editor-gutenberg'` dans `vite.config.js`. Sans ces trois gestes, la feature reste inerte.

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
