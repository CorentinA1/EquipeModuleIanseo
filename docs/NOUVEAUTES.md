# Nouveautés de chaque version

## 0.4.0 — version de test

- **Première version publique**, sur https://github.com/CorentinA1/EquipeModuleIanseo (ZIP dans les Releases).
- **Mise à jour depuis IANSEO** (administrateur) : **Modules → Équipes → Mise à jour du module**. Vérifie la version
  disponible, affiche ses nouveautés, installe en un clic ; rappel de la désinstallation.
- **Avertissement** en tête de l'assistant : module indépendant d'IANSEO, sans garantie ni SAV ; sauvegarder la
  compétition avant de commencer et après chaque application. Lien vers le mode d'emploi.

## 0.3.1 — version de test

- **Garde-fou** : les noms de tables sont comparés sans tenir compte de la casse. Sur un serveur MySQL sous Windows
  (`lower_case_table_names=1`), toutes les colonnes étaient déclarées absentes et le module passait en lecture seule.
- **Couleurs** : les pages suivent la palette et le mode sombre du module THEME d'IANSEO.

## 0.3.0 — version de test

- **Sélection multiple** pendant la composition : Ctrl + clic (Cmd sur Mac) sur plusieurs archers d'un même club, puis
  un clic sur une case ou un glisser-déposer : ils sont déplacés ensemble. Échap désélectionne.
- **Classements** en tête de la page principale : classement par équipes d'IANSEO et classement des équipes libres
  (PDF), plus l'accès aux équipes créées.

## 0.2.0 — version de test

**Archers inscrits plusieurs fois : contrôle des cases équipe.** Seul le premier score de l'archer (premier départ)
compte pour les équipes. L'assistant affiche, pour toute la compétition, ces archers avec leurs départs et leurs trois
cases équipe (« Équipe », « Épreuves par équipes », « Équipes mixtes ») et signale les inscriptions qui ne comptent pas
mais restent cochées ; même contrôle sur la page Équipes créées. Le premier départ se lit sur la date du départ quand
elle est saisie dans IANSEO, sinon sur son numéro. Lecture seule : le module ne décoche toujours que la case « Équipe ».

## 0.1.0 — version de test

Première version, en test (dépôt privé).

**Assistant de composition** (Modules → Équipes) :
- deux modes : **classe et division** (classement par équipes natif d'IANSEO, le module ne fait que cocher ou
  décocher la case « Équipe » des archers) et **libre** (classes mêlées d'une même arme, même distance et même blason ;
  trispot et blason unique de même taille confondus ; équipes classées par le module) ;
- proposition automatique, ajustement au glisser-déposer ou au clic, aperçu avant toute écriture (archers modifiés,
  classement prévu), confirmation si le tir a commencé, annulation du dernier geste, journal ;
- équipes **mixtes** (au moins un homme et une femme) dans un classement « Mixte » à part ;
- archers **inscrits plusieurs fois** : seul le premier départ compte.

**Équipes créées** : équipes des deux modes, suppression d'une équipe (ou de toutes les équipes libres), lien vers
le classement par équipes d'IANSEO et PDF du classement des équipes libres.

**Sécurité** : garde-fou (version d'IANSEO, code de `MakeTeams()`, colonnes de la base) qui passe le module en
lecture seule s'il voit un écart ; aucune table créée, aucun fichier d'IANSEO modifié ; équipes libres retrouvées
même si IANSEO renumérote les archers (import d'une compétition).

---

[← Retour au README](../README.md)
