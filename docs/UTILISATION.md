# Utilisation

Menu **Modules → Équipes** (compétition au règlement FR) :
- **Assistant de composition** : composer les équipes en 3 étapes ;
- **Équipes créées** : voir les équipes, les supprimer, éditer les classements.

> **Sauvegardez la compétition** (Compétition › Exports › Export de la compétition) avant d'appliquer une
> composition le jour de la compétition. Voir [À lire avant d'utiliser le module](AVERTISSEMENT.md).

## Les deux modes

| | Classe et division | Libre |
|---|---|---|
| Une équipe réunit | des archers d'une même catégorie : même division **et** même classe (ex. CL S1H) | des archers d'une même arme qui tirent **à la même distance sur le même blason**, quelle que soit leur classe (ex. CL S1H + S2H + U21H à 70 m) |
| Équipes par club | une par catégorie (règle d'IANSEO) | autant que voulu |
| Classement | **natif d'IANSEO** (Impressions › Classement par équipes) | **calculé par le module** (page Équipes créées, PDF) |
| Écrit dans IANSEO | la case « Équipe » des archers | rien |

Dans les deux modes : une équipe compte les **3 meilleurs scores** (puis les 10, puis les 9) ; au-delà de 3 archers,
les autres sont remplaçants. Effectif réglable (3 à 4 par défaut).

**Blasons (mode libre)** : le trispot et le blason unique **de même taille** comptent comme les mêmes conditions de
tir (ex. trispot 40 cm et blason 40 cm à 18 m). 40 cm et 60 cm restent distincts.

**Équipes mixtes (mode libre)** : une équipe qui réunit au moins un homme et une femme (ex. 2 H + 1 F) va dans un
classement **« Mixte »**, à part des classements « Hommes » et « Femmes ». Un badge l'indique pendant la composition.

**Archers inscrits plusieurs fois** (même licence, plusieurs départs) : seul le **premier score**, celui du premier
départ, compte ; les autres inscriptions sont ignorées et leurs cases équipe doivent être à non. L'étape 1 de
l'assistant affiche le contrôle « Inscrits plusieurs fois » et signale les inscriptions encore cochées.

## Assistant de composition

**Classements** (en tête de la page principale) : « Classement par équipes (Ianseo) », l'impression native des équipes
classe et division ; « Classement des équipes libres (PDF) », le classement fait par le module ; « Voir les équipes
créées ».

### 1. Critères

- Choisir le **mode**.
- Cocher la ou les **divisions** et les **classes** (boutons « Toutes les femmes », « Tous les hommes »…). Le chiffre
  à côté donne le nombre d'inscrits. Pour une équipe mixte, cocher des classes des deux sexes.
- Régler le nombre d'**archers par équipe** (3 à 4 par défaut).
- **Lister les archers →**.

### 2. Composition

Les archers sont rangés par **catégorie** (classe et division) ou par **conditions de tir** (libre), puis par
**club**. Chaque club a ses équipes et une case **Non retenus**.

- **Proposition automatique** : équipes de 3 (le reste en remplaçants jusqu'à l'effectif maximum, sinon non
  retenus), par ordre d'inscription avant le tir, par score ensuite. En mode libre, hommes et femmes sont séparés.
- **Déplacer un archer** : le glisser-déposer, ou toucher l'archer puis la case (tablette).
- **Plusieurs archers à la fois** : **Ctrl + clic** (Cmd sur Mac) sur chacun d'eux, puis un clic sur la case, ou le
  glisser-déposer de l'un d'eux : toute la sélection part ensemble. La sélection reste dans un même club et une même
  catégorie (un Ctrl + clic ailleurs recommence la sélection) ; **Échap** désélectionne tout.
- **+ Équipe** / **×** (mode libre) : ajouter ou supprimer une équipe du club.
- Au départ : en classe et division, les archers déjà cochés « Équipe » (moins de 3 dans le club = non retenus) ; en
  libre, les équipes libres déjà enregistrées.
- Alertes sur chaque équipe : moins de 3 archers (équipe non classée), effectif hors limites.

### 3. Aperçu puis application

L'aperçu montre, avant toute écriture :
- les équipes et leur classement prévu (avec les scores si le tir a commencé ; en gras les 3 qui comptent) ;
- en classe et division : chaque archer dont la case « Équipe » va changer, et les équipes actuellement classées
  qui disparaîtront ;
- en libre : les équipes libres déjà enregistrées qui seront remplacées (celles qui contiennent un archer listé).

**Appliquer** (classe et division) ou **Enregistrer les équipes** (libre). Si le tir a commencé, cocher
« Je confirme » (classe et division).

Après l'application : liens vers **Voir les équipes créées** et vers le classement. **Annuler cette application**
remet les choses comme avant le dernier geste.

## Équipes créées

- **Classe et division** : pour chaque catégorie, les clubs qui ont au moins 3 archers cochés « Équipe », avec le
  rang et le score d'IANSEO ; bouton **Éditer le classement par équipes (IANSEO)**.
- **Libres** : un classement par conditions de tir et par sexe (Hommes, Femmes, Mixte) ; bouton **Classement des
  équipes libres (PDF)**.
- **Supprimer** sur chaque équipe, et **Supprimer toutes les équipes libres**. Une confirmation dit ce qui est touché :
  - équipe libre : rien dans IANSEO ;
  - équipe classe et division : la case « Équipe » de ses archers est décochée et IANSEO recalcule son classement par
    équipes ; qualifications et duels ne changent pas.
- Avertissement si des archers inscrits plusieurs fois ont encore une case équipe cochée (« Équipe », « Épreuves par
  équipes » ou « Équipes mixtes ») sur un départ qui ne compte pas.

## Journal

En bas de l'assistant, **Afficher le journal** : chaque application, suppression ou annulation, avec la date, le poste
et le détail des équipes.

---

[← Retour au README](../README.md)
