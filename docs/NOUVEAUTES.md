# Nouveautés de chaque version

## 0.6.1 — version de test

- **Pastille de mise à jour orange**, moins alarmante que le rouge.
- **Bouton « Mettre à jour » affiché d'office** : quand la pastille est là, la page « Mise à jour du module » vérifie
  d'elle-même la version disponible à l'ouverture et montre ses nouveautés et le bouton, sans cliquer sur « Vérifier ».

## 0.6.0 — version de test

- **Pastille « mise à jour disponible » dans le menu** (administrateur) : un point rouge sur le titre **Modules** et sur
  le nom du module, une pastille rouge **⬆** sur « Mise à jour du module », avec une bulle qui donne la version installée
  et la version disponible. Vérification en arrière-plan au plus une fois par jour (une heure après un échec), sans
  ralentir les pages ni les bloquer sans Internet ; la page de mise à jour vérifie toujours tout de suite.

## 0.5.1 — version de test

- **Guide utilisateur illustré (PDF)** dans `docs/GUIDE-UTILISATEUR.pdf` : le module en bref, le tableau de bord,
  l'assistant étape par étape, modifier, supprimer et annuler, les contrôles, questions fréquentes ; captures sur une
  compétition fictive. Le lien **Mode d'emploi** des onglets l'ouvre directement (il ouvrait la page GitHub).

## 0.5.0 — version de test

**Nouvelle organisation des pages.** L'assistant mêlait des impressions, des contrôles, le journal et l'annulation ;
chaque page a maintenant un seul rôle (menu **Modules → Équipes**, onglets en haut de chaque page) :

- **Tableau de bord** (remplace « Équipes créées », page d'entrée) : équipes des deux modes, résumées par catégorie
  ou par classement, avec **Modifier ces équipes** (l'assistant s'ouvre directement sur la composition) et les
  impressions ; détail, scores et suppression dans un cadre replié ; dernière application et **Annuler cette
  application** ; alerte des inscriptions en défaut.
- **Composer des équipes** : l'assistant seul, en **4 étapes** (Mode, Catégories, Composition, Vérifier et
  appliquer), puis un écran de fin. Le mode se choisit sur deux cartes avec un exemple ; un cadre « Votre sélection »
  compte les archers retenus ; l'aide de la composition est repliée (« Comment faire ? ») ; le rappel de sauvegarde,
  avec le bouton d'export, est juste avant « Appliquer ».
- **Contrôles et historique** : archers inscrits plusieurs fois, journal des applications, diagnostic du garde-fou.
- L'avertissement « module indépendant, sans garantie » passe en pied de page de chaque page ; le lien « Mode
  d'emploi » est dans les onglets.

Les calculs, l'application, l'annulation et les données ne changent pas. L'ancienne adresse `equipes.php` renvoie
vers le tableau de bord.

## 0.4.1 — version de test

- **Mise à jour du module sous Windows.** La mise à jour depuis IANSEO s'arrêtait sur « Écriture impossible :
  admin/update.php » : Windows refuse de remplacer la page en cours d'affichage. Le module écrit maintenant
  directement dans ce fichier. Pour passer à cette version depuis une version précédente sous Windows, une seule
  fois : décompresser le ZIP de la Release dans `Modules/Custom` en écrasant les fichiers.

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
