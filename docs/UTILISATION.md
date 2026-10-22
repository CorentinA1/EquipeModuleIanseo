# Utilisation

Menu **Modules → Équipes** (compétition au règlement FR), trois pages avec chacune un rôle :
- **Tableau de bord** : où en sont les équipes, impressions, suppression, annulation, alertes ;
- **Composer des équipes** : l'assistant, en 4 étapes ;
- **Contrôles et historique** : archers inscrits plusieurs fois, journal, garde-fou.

Les trois pages ont les mêmes onglets en haut, et un lien **Mode d'emploi** qui ouvre le
[guide utilisateur illustré (PDF)](GUIDE-UTILISATEUR.pdf), livré avec le module.

> **Sauvegardez la compétition** (Compétition › Exports › Export de la compétition) avant d'appliquer une
> composition le jour de la compétition. Voir [À lire avant d'utiliser le module](AVERTISSEMENT.md).

## Les deux modes

| | Classe et division | Libre |
|---|---|---|
| Une équipe réunit | des archers d'une même catégorie : même division **et** même classe (ex. CL S1H) | des archers d'une même arme qui tirent **à la même distance sur le même blason**, quelle que soit leur classe (ex. CL S1H + S2H + U21H à 70 m) |
| Équipes par club | une par catégorie (règle d'IANSEO) | autant que voulu |
| Classement | **natif d'IANSEO** (Impressions › Classement par équipes) | **calculé par le module** (tableau de bord, PDF) |
| Écrit dans IANSEO | la case « Équipe » des archers | rien |

Dans les deux modes : une équipe compte les **3 meilleurs scores** (puis les 10, puis les 9) ; au-delà de 3 archers,
les autres sont remplaçants. Effectif réglable (3 à 4 par défaut).

**Blasons (mode libre)** : le trispot et le blason unique **de même taille** comptent comme les mêmes conditions de
tir (ex. trispot 40 cm et blason 40 cm à 18 m). 40 cm et 60 cm restent distincts.

**Équipes mixtes (mode libre)** : une équipe qui réunit au moins un homme et une femme (ex. 2 H + 1 F) va dans un
classement **« Mixte »**, à part des classements « Hommes » et « Femmes ». Un badge l'indique pendant la composition.

**Archers inscrits plusieurs fois** (même licence, plusieurs départs) : seul le **premier score**, celui du premier
départ, compte ; les autres inscriptions sont ignorées et leurs cases équipe doivent être à non. La page
**Contrôles et historique** les liste ; le tableau de bord signale les inscriptions encore cochées.

## Tableau de bord

La page d'entrée du module.

- **Par classe et division** : nombre d'équipes, et pour chaque catégorie les clubs qui ont une équipe. Boutons
  **Modifier ces équipes** (ouvre l'assistant directement sur la composition, avec ces catégories) et **Imprimer le
  classement (Ianseo)**.
- **Équipes libres** : même présentation, par classement (conditions de tir et sexe : Hommes, Femmes, Mixte) ; bouton
  **Imprimer le classement (PDF)**.
- **Détail des équipes** (replié) : archers, rang et score de chaque équipe (en gras, les 3 scores qui comptent une
  fois le tir commencé), et **Supprimer** sur chaque équipe, ou **Supprimer toutes les équipes libres**. Une
  confirmation dit ce qui est touché :
  - équipe libre : rien dans IANSEO ;
  - équipe classe et division : la case « Équipe » de ses archers est décochée et IANSEO recalcule son classement par
    équipes ; qualifications et duels ne changent pas.
- **Dernière application** : ce qui a été appliqué, quand et par qui, avec **Annuler cette application**, qui remet
  les choses comme avant le dernier geste (application ou suppression).
- Un bandeau signale les **inscriptions en défaut** (archers inscrits plusieurs fois avec une case équipe cochée sur
  un départ qui ne compte pas), avec un lien vers les contrôles.
- **Composer des équipes** : le grand bouton en bas ouvre l'assistant.

## Composer des équipes

Quatre étapes, affichées en haut de la page. À partir de l'étape 2, une ligne rappelle le mode (et les critères à
partir de l'étape 3), avec un lien **changer**.

### 1. Mode

Deux cartes, **Par classe et division** ou **Libre**, chacune avec un exemple : cliquer sur la bonne.

### 2. Catégories

- Cocher la ou les **armes** (divisions) et les **classes** (boutons « Toutes les femmes », « Tous les hommes »…). Le
  chiffre à côté donne le nombre d'inscrits. Pour une équipe mixte, cocher des classes des deux sexes.
- Régler le nombre d'**archers par équipe** (3 à 4 par défaut).
- Le cadre **Votre sélection** compte les archers et les catégories retenus.
- **Composer les équipes →**.

### 3. Composition

Les archers sont rangés par **catégorie** (classe et division) ou par **conditions de tir** (libre), puis par
**club**. Chaque club a ses équipes et une case **Non retenus**. **Comment faire ?** déplie l'aide.

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
- **Vérifier →**.

### 4. Vérifier et appliquer

Avant toute écriture :
- un résumé : nombre d'équipes, d'archers modifiés dans IANSEO (ou d'équipes libres remplacées), d'alertes ;
- les équipes et leur classement prévu (avec les scores si le tir a commencé ; en gras les 3 qui comptent) ;
- en classe et division : chaque archer dont la case « Équipe » va changer (**Voir le détail**), et les équipes
  actuellement classées qui disparaîtront ;
- en libre : les équipes libres déjà enregistrées qui seront remplacées (celles qui contiennent un archer listé) ;
- le rappel de sauvegarde, avec le bouton **Exporter la compétition**.

**Appliquer** (classe et division) ou **Enregistrer les équipes** (libre). Si le tir a commencé, cocher
« Je confirme » (classe et division).

Un écran de fin confirme ce qui a été fait, avec **Retour au tableau de bord**, **Imprimer le classement** et
**Composer d'autres équipes**. En cas d'erreur : **Annuler cette application** sur le tableau de bord.

## Contrôles et historique

- **Archers inscrits plusieurs fois** : pour chacun, ses inscriptions, celle qui compte, et ses cases « Équipe »,
  « Épreuves par équipes » et « Équipes mixtes ». Les inscriptions en défaut sont surlignées. Case « Équipe » :
  composer la catégorie dans l'assistant (classe et division), il la décoche ; les deux autres cases : à décocher dans
  IANSEO › Participants (le module ne touche pas aux finales).
- **Journal des applications** : chaque application, suppression ou annulation, avec la date, le poste et le détail
  des équipes (les 100 dernières).
- **Garde-fou** : l'état (vert, orange ou rouge) et le détail des vérifications.

---

[← Retour au README](../README.md)
