# Données : comment les équipes sont enregistrées

En bref : le module **ne crée aucune table**, **ne modifie aucun fichier d'IANSEO**, et n'écrit dans la base que
**deux choses** :

| Ce qui est écrit | Où | Comment | Mode |
|---|---|---|---|
| La case « Équipe » des archers | `Entries.EnTeamClEvent` (0 ou 1) | requête `UPDATE` du module, dans une transaction, puis **calcul natif d'IANSEO** `MakeTeams()` | Classe et division |
| Les équipes libres, le journal, la dernière annulation | `ModulesParameters`, nom de module `Equipe` | **fonction native d'IANSEO** `setModuleParameter()` | Libre (et journal pour tout) |

Tout le reste est seulement **lu**.

## Mode « classe et division » : le classement natif d'IANSEO

### Ce que fait IANSEO, sans le module

IANSEO a un **classement par équipes « division/classe »** (menu Impressions, « Classement par équipes ») calculé par
sa fonction `MakeTeams()` :
- il prend les archers dont la **case « Équipe »** est cochée (`EnTeamClEvent = 1`), qui ont un score ;
- il forme **une équipe par club et par catégorie** (division + classe, par exemple `CLS1H`) avec les
  **3 meilleurs scores** ;
- il écrit le résultat dans ses tables `Teams` et `TeamComponent`.

Cette case est **cochée par défaut** à l'inscription : sans rien faire, chaque club a déjà une équipe par catégorie.
IANSEO refait ce calcul tout seul à chaque saisie de score.

### Ce que fait le module

Il ne fait **que choisir quels archers ont la case « Équipe » cochée** :
1. l'assistant calcule la composition, montre l'aperçu (archers modifiés, classement prévu) ;
2. à l'application, il écrit `EnTeamClEvent = 1` pour les archers de l'équipe et `0` pour les autres archers de la
   catégorie et du club. C'est **exactement ce que l'on ferait à la main** en cochant ou décochant la case dans la
   fiche de chaque archer (Participants). L'écriture se fait par une requête du module (IANSEO n'a pas de fonction
   prévue pour cela hors de ses pages), dans une **transaction** : tout ou rien, relu et vérifié avant d'être validé ;
3. il appelle ensuite **la fonction d'IANSEO `MakeTeams()`**, la même que celle qu'IANSEO appelle après une saisie de
   score : c'est **IANSEO** qui recalcule et écrit `Teams` / `TeamComponent`. Le module n'écrit jamais dans ces
   tables ;
4. le classement s'imprime avec **l'impression native d'IANSEO** (`Qualification/PrnTeam.php`).

### Ce que la case « Équipe » ne touche pas

Vérifié dans tout le code d'IANSEO : dans les calculs, `EnTeamClEvent` ne sert **qu'au** classement par équipes
division/classe. Ne dépendent **pas** de cette case :

| Fonction d'IANSEO | Case utilisée | Touchée par le module ? |
|---|---|---|
| Qualification et classement individuels | `EnIndClEvent` | non |
| Duels individuels, finales | `EnIndFEvent` | non |
| Épreuves par équipes, duels par équipes | `EnTeamFEvent` | non |
| Épreuves par équipes mixtes | `EnTeamMixEvent` | non |
| Scores, flèches, cibles, départs | `Qualifications`, `Session`… | non (lecture seule) |

La case « Équipe » apparaît aussi, en affichage seulement, sur les accréditations et certaines listes de départ.

Contrôle fait sur une compétition de test, par empreintes des tables avant et après une suppression d'équipe :
`Qualifications`, `Entries` (hors cette case), `Individuals`, `Finals`, `TeamFinals` et les équipes d'épreuves sont
**identiques** ; seul le classement par équipes division/classe change.

## Mode « libre » : des équipes gardées par le module

IANSEO ne sait pas classer une équipe qui mêle plusieurs classes (S1H + U21H par exemple). Ces équipes sont donc
**gardées par le module** et **classées par lui** :
- elles sont enregistrées avec la **fonction native d'IANSEO** `setModuleParameter()`, dans la table
  `ModulesParameters` (module `Equipe`, paramètre `Libres`), pour la compétition ouverte ;
- chaque archer y est noté avec son numéro d'inscription **et** son identité (licence, arme, classe, nom, prénom) :
  si IANSEO renumérote les archers (import d'une compétition exportée), le module les retrouve ;
- le classement (3 meilleurs scores, puis 10, puis 9) est **calculé à l'affichage** à partir des scores
  d'IANSEO ; il n'est écrit nulle part ;
- **rien n'est écrit dans les tables d'IANSEO** : les classements natifs, la case « Équipe », les qualifications
  et les duels ne changent pas.

Ces réglages sont inclus dans **l'export de la compétition** d'IANSEO.

## Autres données du module (`ModulesParameters`, module `Equipe`)

| Paramètre | Contenu |
|---|---|
| `Libres` | Les équipes libres de la compétition (club, archers). |
| `Undo` | La dernière application ou suppression, pour « Annuler cette application » (un seul niveau). |
| `Log` | Le journal : date, poste ou utilisateur, action, détail (500 lignes au plus). |

## Archers inscrits plusieurs fois

Un archer inscrit sur plusieurs départs (même licence) ne compte pour les équipes que sur son **premier score**, donc
son **premier départ** : date de début du départ dans IANSEO quand tous ses départs en ont une, sinon numéro du départ
(à égalité, la première inscription). Ses autres inscriptions doivent avoir **toutes leurs cases équipe à non** :
« Équipe » (classement division/classe), « Épreuves par équipes » et « Équipes mixtes ».

**Contrôle** (lecture seule, toute la compétition) : l'assistant (étape 1) liste ces archers, leurs départs et leurs
trois cases équipe, et signale les inscriptions en défaut ; la page Équipes créées affiche aussi un bandeau.
**Correction** : en mode classe et division, le module **décoche** la case « Équipe » de ces inscriptions dans les
catégories traitées, sinon IANSEO les compterait. Les cases « Épreuves par équipes » et « Équipes mixtes » servent aux
finales : le module n'y touche pas, elles se décochent dans IANSEO › Participants.

## Les protections

- **Garde-fou** avant toute page : version d'IANSEO, code de `MakeTeams()` (empreinte), colonnes de la base,
  absence de surcharge par le règlement, calcul des équipes « standard ». S'il voit un écart : **lecture seule**.
- **Aperçu obligatoire** : rien n'est écrit sans un aperçu ; l'application est refusée si les inscriptions ont changé
  entre-temps.
- **Tir commencé** : case « Je confirme » obligatoire en mode classe et division.
- **Liste des participants verrouillée** dans IANSEO : le mode classe et division est en lecture seule.
- **Droits** : droit « Participants » en écriture pour appliquer ou supprimer.
- **Annulation** du dernier geste et **journal** de toutes les actions.

## En cas de doute pendant une compétition

- **Annuler cette application** (assistant) remet les archers comme avant le dernier geste.
- Le classement natif se recalcule à tout moment avec la page d'IANSEO `Qualification/MakeTeams.php`
  (« Calculer les équipes »).
- Supprimer le dossier du module n'affecte pas IANSEO : les cases « Équipe » restent ce qu'elles sont, et IANSEO
  continue de classer les équipes avec.

---

[← Retour au README](../README.md)
