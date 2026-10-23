# Installation, mise à jour et désinstallation

Il faut une installation IANSEO qui fonctionne (PHP 8, MySQL ou MariaDB). Lisez d'abord
[À lire avant d'utiliser le module](AVERTISSEMENT.md) : module indépendant d'IANSEO, sans garantie, sauvegardes.

Le module se place dans `Modules/Custom/` d'IANSEO, seul dossier que les mises à jour d'IANSEO ne réécrivent pas.
Nom conseillé du dossier : `EQUIPE` (le module calcule ses liens à partir du nom de son dossier : `Equipe` marche
aussi, mais sous Linux la casse compte).

Le module ne crée **aucune table** et ne modifie **aucun fichier d'IANSEO** : l'installer, c'est seulement copier son
dossier.

## Windows

1. Télécharger `EQUIPE-vX.Y.Z.zip` dans les [Releases](https://github.com/CorentinA1/EquipeModuleIanseo/releases).
2. Le décompresser dans `C:\ianseo\htdocs\Modules\Custom\` : on obtient
   `C:\ianseo\htdocs\Modules\Custom\EQUIPE\menu.php`.

Avec **Code → Download ZIP** sur GitHub, renommer en `EQUIPE` le dossier obtenu (`EquipeModuleIanseo-main`).

## Linux (ligne de commande)

Adapter le chemin à votre installation (par exemple `/var/www/html/ianseo`) :

```bash
cd /var/www/html/ianseo/Modules/Custom
sudo curl -L -o equipe.tar.gz https://github.com/CorentinA1/EquipeModuleIanseo/archive/refs/heads/main.tar.gz
sudo tar -xzf equipe.tar.gz && sudo mv EquipeModuleIanseo-main EQUIPE && sudo rm equipe.tar.gz
sudo chown -R www-data: EQUIPE    # pour la mise à jour depuis IANSEO
```

`git clone https://github.com/CorentinA1/EquipeModuleIanseo.git EQUIPE` marche aussi, mais une copie Git se met à jour avec `git pull` :
la page « Mise à jour du module » la refuse (dossier `.git`).

## Premier lancement

Ouvrir une compétition au **règlement FR** : le menu **Modules → Équipes** apparaît, avec **Tableau de bord**,
**Composer des équipes** et **Contrôles et historique** (droit « Participants » en lecture au minimum ; en écriture
pour appliquer).

Sur la page **Contrôles et historique**, le **garde-fou** indique s'il reconnaît la version d'IANSEO :
- **vert** : version validée, tout est autorisé ;
- **orange** : version inconnue mais le code surveillé est identique : autorisé, avec un avertissement ;
- **rouge** : quelque chose a changé dans IANSEO : le module passe en **lecture seule** et n'écrit plus rien.

## Mise à jour

**Pastille dans le menu** (administrateur) : quand une version plus récente est publiée, un point orange apparaît sur
le titre **Modules** et sur le nom du module, et une pastille orange **⬆** sur « Mise à jour du module » ; au survol, une
bulle donne la version installée et la version disponible. La vérification se fait en arrière-plan, sans ralentir les
pages, au plus une fois par jour (une heure après un échec) ; ouvrir la page de mise à jour vérifie tout de suite. Sans
Internet, pas de pastille : la dernière information connue est gardée. Quand la pastille est affichée, la page de
mise à jour montre d'office la version disponible, ses nouveautés et le bouton **Mettre à jour**.

**Depuis IANSEO** (administrateur) : **Modules → Équipes → Mise à jour du module**, puis « Vérifier les mises à
jour ». La page affiche les nouveautés de la version disponible et l'installe en un clic.

- **Sauvegardez vos compétitions avant** (Compétition › Exports › Export de la compétition).
- Tous les fichiers sont téléchargés avant d'en remplacer un seul : en cas d'échec, rien n'est modifié.
- Le serveur web doit pouvoir **écrire dans le dossier du module**, et PHP doit pouvoir joindre GitHub en https
  (certificats SSL configurés : `curl.cainfo` / `openssl.cafile` dans `php.ini`). Sinon la page affiche « GitHub
  injoignable ».
- `module.json` (d'où vient le module) est gardé tel quel. Pour suivre un dépôt **privé** (copie personnelle), créer
  `module.local.php` à côté : `<?php return ['github_token' => '…'];` (jeton GitHub « fine-grained », lecture
  seule, ce dépôt seulement). Ce fichier PHP ne se lit pas depuis un navigateur et n'est jamais remplacé.
- Une copie Git (dossier `.git`) se met à jour avec `git pull`, pas depuis cette page.

Sans accès à GitHub depuis le serveur : remplacer les fichiers du dossier par ceux du ZIP de la nouvelle version.

Les compositions sont gardées : elles sont dans la base d'IANSEO, pas dans le dossier du module.

## Désinstallation

Supprimer le dossier `Modules/Custom/EQUIPE` (rappel aussi en bas de la page « Mise à jour du module »). Le menu disparaît, IANSEO continue de fonctionner normalement.

Ce qui reste dans les compétitions (voir [Données](DONNEES.md)) :
- la case « Équipe » de chaque archer, telle que le module l'a laissée : c'est une donnée normale d'IANSEO, que l'on
  peut changer dans la fiche de l'archer ; le classement par équipes d'IANSEO continue d'en tenir compte ;
- les réglages du module (équipes libres, journal, dernière annulation possible), dans la table `ModulesParameters`
  sous le nom de module `Equipe`. Ils ne servent plus à rien sans le module. Pour les retirer (facultatif) :

```sql
DELETE FROM ModulesParameters WHERE MpModule = 'Equipe';
```

---

[← Retour au README](../README.md)
