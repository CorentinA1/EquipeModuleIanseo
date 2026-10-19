# À lire avant d'utiliser le module

## Un module indépendant

Le module Équipes est un **module complémentaire indépendant**. Il **n'est pas développé par l'équipe IANSEO**
([ianseo.net](https://www.ianseo.net)), ni par la FFTA. Ni l'une ni l'autre ne l'ont validé ni ne l'assurent.
Les noms IANSEO et FFTA servent seulement à dire avec quel logiciel il fonctionne et quel règlement il suit.

Pour une question sur IANSEO lui-même, adressez-vous à l'équipe IANSEO. Pour une question sur ce module, pas à elle :
elle n'en est pas responsable.

## Pas de garantie, pas de support

Le module est fourni **tel quel, sans garantie** (voir [LICENSE](../LICENSE)) et **sans service après-vente** :
personne n'est tenu de répondre aux questions, de corriger un défaut ou d'assurer un suivi.

Il lit la base de données d'IANSEO et y écrit **une seule chose** : la case « Équipe » des archers (voir
[Données](DONNEES.md)). Il a été conçu pour ne rien casser (garde-fou, transactions, aperçu avant toute écriture,
annulation), mais il peut contenir des **bugs**. Une mise à jour d'IANSEO peut aussi changer ce sur quoi il
s'appuie : le garde-fou le détecte et passe alors le module en lecture seule.

## Sauvegardez vos compétitions

C'est indispensable : **avant de commencer, après chaque application d'une composition, le jour J et avant toute
mise à jour** d'IANSEO ou du module (Modules → Équipes → Mise à jour du module). Le même rappel s'affiche en tête de
l'assistant.

- **Export de la compétition** (IANSEO : **Compétition › Exports › Export de la compétition**) : un fichier qui
  contient la compétition, ses archers (avec leur case « Équipe »), leurs scores, et les réglages des modules, donc
  les **équipes libres**. On le recharge avec **Compétition › Import d'une compétition**.

Gardez les sauvegardes sur un autre support que l'ordinateur de la compétition (clé USB, autre ordinateur).

## Licences

- **IANSEO** est distribué par ses auteurs sous la licence **Functional Source License 1.1, ALv2 Future License**
  (FSL-1.1-ALv2), fournie avec IANSEO (`LICENSE.txt`) et consultable sur
  [spdx.org/licenses/FSL-1.1-ALv2](https://spdx.org/licenses/FSL-1.1-ALv2.html). Elle permet d'utiliser, de
  modifier et de compléter le logiciel pour tout usage autre qu'un « usage concurrent », c'est-à-dire un produit ou
  service **commercial** qui remplacerait IANSEO ou offrirait les mêmes fonctions. Elle ne donne aucun droit sur le
  nom et les marques d'IANSEO, au-delà d'indiquer l'origine du logiciel.
- **Ce module** :
  - ne contient **aucun fichier ni code d'IANSEO** : il appelle les fonctions d'IANSEO déjà installé, dans le
    dossier `Modules/Custom/` prévu pour les modules ;
  - est **gratuit et non commercial**, et ne remplace pas IANSEO : il en a besoin pour fonctionner ;
  - est distribué sous sa propre licence, non commerciale ([LICENSE](../LICENSE)). Son utilisation avec IANSEO doit
    aussi respecter la licence d'IANSEO.

---

[← Retour au README](../README.md)
