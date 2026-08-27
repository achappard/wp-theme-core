# Changelog

Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnement
[SemVer](https://semver.org/lang/fr/).

Chaque entrée porte une ligne **Impact projet**, seule information qu'on ne retrouve plus en relisant
le diff six mois plus tard :

- `aucun` — `composer update` suffit
- `à vérifier visuellement` — le rendu peut changer, passer la checklist de recette
- `nécessite une modification du provider` — le projet doit être modifié à la main

---

## [1.5.0] — 2026-08-27

### Ajouté

- `Features\Seo\DocumentTitle` accepte désormais un séparateur au constructeur (`$separator`), au
  lieu d'une constante figée

### Modifié

- Le séparateur par défaut passe de `★` à `/`

**Impact projet :** à vérifier visuellement — `composer update` suffit à faire fonctionner le code,
mais tout projet qui instancie `new DocumentTitle()` sans argument voit son `<title>` changer de
séparateur. Passer `new DocumentTitle( separator: '★' )` pour conserver l'ancien rendu.

---

## [1.4.0] — 2026-08-25

### Ajouté

- `Features\Front\Favicons` — expose au contexte Twig global, sous la clé `favicons`, les URLs du
  jeu de favicons (mode clair et sombre) et les couleurs de marque `theme_color_light`/
  `theme_color_dark`. Ne génère aucun fichier ni aucun HTML : les fichiers sont produits par
  `bin/generate-favicons.mjs` (thème), le rendu par `partials/favicon.twig` (thème)

### Notes

- **Front uniquement** — aucune icône d'administration ni de page de connexion. Sans rapport avec
  `Admin\DisableSiteIcon`, qui masque un champ de réglage indépendant
- `theme_color_light`/`theme_color_dark` n'ont pas de valeur par défaut, comme
  `AdminColorScheme::$palette` : les omettre lève une `ArgumentCountError` au démarrage
- La variante sombre (`favicon-dark.svg`) est détectée par présence de fichier sur disque, jamais
  par un booléen déclaré au constructeur : évite qu'un flag diverge du contenu réel de
  `dist/assets/favicons/` généré par le script Node
- Sans build disponible (`npm run favicons:generate`/`npm run build` jamais lancé), le contexte
  expose `favicons: null` — au template de s'effacer
- URLs résolues via `Support\ViteManifest::asset_url()`, déjà générique pour tout fichier statique
  sous `dist/assets/` — aucune nouvelle classe de résolution

**Impact projet :** nécessite une modification du provider — ajouter
`new Favicons( theme_color_light: …, theme_color_dark: … )` à `FrontServiceProvider` — **et** le
portage manuel de `bin/generate-favicons.mjs`, `bin/favicons.config.mjs`,
`partials/favicon.twig` (+ son inclusion dans `base.twig`), et les sources SVG
(`resources/images/favicon-light.svg`), le socle ne les fournissant pas. Sans ces gestes la feature
reste inerte.

---

## [1.3.0] — 2026-08-24

### Ajouté

- `Features\Setup\Menus` — déclare les emplacements de menus du thème auprès de WordPress et
  injecte, pour chacun, l'objet `Timber\Menu` correspondant dans le contexte Twig global, sous la
  clé `{emplacement}_menu`

### Notes

- **Les libellés sont fournis par une closure, jamais un tableau.** `functions.php` — donc les
  providers — s'exécute avant `after_setup_theme`. Un `__()` évalué à cet endroit fait charger le
  catalogue de traduction trop tôt et déclenche le `_doing_it_wrong` de WordPress 6.7 dès qu'un
  `.mo` existe pour la locale courante (cf. `_load_textdomain_just_in_time()` dans
  `wp-includes/l10n.php`). La closure diffère cette évaluation jusqu'à `register_locations()`,
  appelée sur `after_setup_theme`. Le text-domain reste un littéral à l'intérieur : `wp i18n
  make-pot` continue de l'extraire normalement
- **`Timber::get_menu_by( 'location', … )` et non `Timber::get_menu( $location )`** :
  `MenuFactory::from()` retombe sur une résolution par slug puis par nom si aucun menu n'est
  affecté à l'emplacement, avec le risque documenté par la classe elle-même de retourner un menu
  dont le slug ou le nom coïncide avec l'emplacement demandé
- **Les tirets d'un emplacement sont normalisés en underscore** dans la clé de contexte
  (`main-nav` → `main_nav_menu`) : Twig lirait `{{ main-nav_menu }}` comme une soustraction
- Aucun défaut : `locations` est obligatoire, une `ArgumentCountError` sanctionne l'omission — les
  emplacements et leurs libellés appartiennent au projet, rien n'est deviné à sa place

**Impact projet :** nécessite une modification du provider — ajouter `new Menus( locations: … )` à
`SetupServiceProvider`. Sans cela la feature reste inerte.

---

## [1.2.0] — 2026-08-22

### Ajouté

- `Features\Admin\ShowHidePageTitle` — interrupteur « Afficher le titre » dans le panneau de
  document de Gutenberg, qui masque le champ titre du canvas. **Réglage global** : l'état vit dans
  une option (`deefuse_hide_post_title` par défaut) et vaut pour tous les types déclarés. Trois
  paramètres : `post_types` (défaut `['page']`), `option`, `capability` (défaut `edit_posts`)
- Route REST `deefuse/v1/hide-post-title` — écriture du réglage, permission portée par `capability`
- `assets/js/` dans le package — le script du panneau, **lu sur disque**, jamais servi par une URL

### Notes

- **Périmètre strictement éditorial** : le socle ne lit l'option nulle part ailleurs, le rendu
  public reste l'affaire des templates du thème
- **Une route REST plutôt que `register_setting()`** : `/wp/v2/settings` exige `manage_options`,
  écrit en dur dans `WP_REST_Settings_Controller` et qu'aucun filtre n'abaisse — y adosser l'option
  interdirait le réglage à toute personne n'administrant pas le site
- L'écriture est **immédiate**, non différée jusqu'au « Mettre à jour » : ce bouton enregistre le
  post, pas les réglages du site
- Le script n'est pas enfilé par URL : `vendor/` n'est pas garanti accessible en HTTP d'un
  hébergement à l'autre, et une balise pointant dessus marcherait en local pour échouer en
  production sans message. Il part donc en inline, sur un handle sans source
- La feuille n'est pas un fichier mais quatre lignes construites depuis la constante `BODY_CLASS` :
  un fichier CSS aurait redit cette classe en dur, sans que rien ne vérifie qu'elle la suive
- Le script pose la feuille lui-même dans le document de l'éditeur **et** dans celui du canvas
  iframé, plutôt que par `add_editor_style()` : le core y réécrit les sélecteurs en les préfixant de
  `.editor-styles-wrapper`, ce qui déplacerait la classe conditionnelle et casserait la règle
- Il surveille le montage du canvas (`MutationObserver`) et s'applique dès son chargement, sans
  attendre le rendu de la barre latérale : la première passe a lieu avant que l'iframe n'existe, et
  l'état ne changeant pas au rechargement, rien ne la rejouerait
- Écrit sans JSX ni imports, sur `window.wp` : le socle n'a pas de build, et n'en gagne pas un
- Comme `languages/`, `assets/js/` doit être commité et présent dans le tag — l'installation d'un
  package Composer ne déclenche aucune étape de build

> ⚠️ **Tag republié.** Une première v1.2.0 stockait l'état dans une post meta `_hide_post_title`, et
> exposait un paramètre `meta_key`. Le tag a été déplacé sur la présente version plutôt que
> d'ouvrir une 1.3.0 pour une API que rien n'avait consommée. Un poste ayant installé la première
> v1.2.0 garde l'ancien code sans aucun signal : y forcer une réinstallation
> (`rm -rf vendor/deefuse/wp-theme-core && composer install`) est le seul moyen de s'aligner.

**Impact projet :** nécessite une modification du provider — ajouter `new ShowHidePageTitle( $context )`
à `AdminServiceProvider`. Sans cela la feature reste inerte.

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
