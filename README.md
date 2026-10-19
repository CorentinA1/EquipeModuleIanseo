# Équipes — assistant de composition des équipes pour IANSEO

**Version 0.4.0** (en test)

Module IANSEO qui aide le greffe à composer les **équipes de club** d'une compétition FFTA simple (sans épreuve par
équipes), puis à éditer leur classement :

- **par classe et division** : le classement par équipes **natif d'IANSEO** (une équipe par club et par catégorie,
  3 meilleurs scores) ; l'assistant choisit les archers de chaque équipe ;
- **libre** : des équipes qui mêlent les classes d'une même arme tirant à la même distance sur le même blason
  (ex. S1H et U21H en arc classique à 70 m), classées par le module.

L'assistant liste les archers, **propose** les équipes, on les ajuste à la souris ou au doigt, on vérifie l'aperçu,
puis on applique. La page « Équipes créées » montre le résultat et ouvre le classement.

> **Module indépendant, sans garantie ni SAV.** Il n'est pas développé par l'équipe IANSEO (ianseo.net) ni par la
> FFTA, et peut contenir des bugs. **Sauvegardez la compétition** avant de commencer et après chaque application
> (Compétition › Exports › Export de la compétition). Voir [À lire avant d'utiliser le module](docs/AVERTISSEMENT.md).

## Installation rapide

1. Télécharger `EQUIPE-vX.Y.Z.zip` dans les **Releases** du dépôt.
2. Le décompresser dans `Modules/Custom/` d'IANSEO : le dossier doit s'appeler `EQUIPE`.
3. Ouvrir une compétition au règlement FR : **Modules → Équipes → Assistant de composition**.

Ensuite, les mises à jour se font depuis IANSEO : **Modules → Équipes → Mise à jour du module** (administrateur).

## Documentation

- [À lire avant d'utiliser le module](docs/AVERTISSEMENT.md) : module indépendant, sauvegardes, licences
- [Installation, mise à jour et désinstallation](docs/INSTALLATION.md)
- [Utilisation](docs/UTILISATION.md) : les deux modes, l'assistant, la page des équipes
- [Données : comment les équipes sont enregistrées](docs/DONNEES.md) : ce que le module écrit, et ce qu'il ne touche jamais
- [Nouveautés de chaque version](docs/NOUVEAUTES.md)

## Licence

Licence non commerciale : voir [LICENSE](LICENSE). IANSEO a sa propre licence (FSL-1.1-ALv2), que son utilisation
avec ce module doit respecter : voir [Licences](docs/AVERTISSEMENT.md#licences).
