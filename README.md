# WC PawaPay Gateway Plugin

Un plugin WooCommerce qui intègre le paiement par Mobile Money via l'API PawaPay avec conversion automatique des devises (EUR/USD vers XOF/XAF).

## Fonctionnalités

- ✅ Intégration complète avec WooCommerce
- ✅ Support des paiements Mobile Money via PawaPay
- ✅ Conversion automatique EUR/USD → XOF/XAF
- ✅ Interface de sélection du pays et de l'opérateur
- ✅ Affichage des logos des opérateurs mobiles
- ✅ Compatible avec l'éditeur de blocs WooCommerce
- ✅ Support multi-pays (Bénin, Burkina Faso, Côte d'Ivoire, Cameroun, Mali, Niger, Sénégal, Togo)
- ✅ Mode Sandbox et Production

## Pays supportés

- 🇧🇯 Bénin (BJ)
- 🇧🇫 Burkina Faso (BF)
- 🇨🇮 Côte d'Ivoire (CI)
- 🇨🇲 Cameroun (CM)
- 🇲🇱 Mali (ML)
- 🇳🇪 Niger (NE)
- 🇸🇳 Sénégal (SN)
- 🇹🇬 Togo (TG)

## Devises supportées

- XOF (Franc CFA Ouest Africain)
- XAF (Franc CFA Centrafricain)
- EUR (Euro)
- USD (Dollar américain)

## Installation

1. Téléchargez le plugin depuis le repository
2. Allez dans votre administration WordPress > Extensions > Ajouter
3. Cliquez sur "Téléverser une extension" et sélectionnez le fichier ZIP
4. Activez l'extension
5. Allez dans WooCommerce > Réglages > Paiements
6. Activez et configurez "PawaPay"

## Configuration

### Paramètres requis

1. **API Token** : Récupérez votre token d'API depuis votre dashboard PawaPay
2. **Environnement** : Choisissez entre Sandbox (test) et Production
3. **Nom du marchand** : Le nom qui apparaîtra sur le relevé bancaire du client (max 22 caractères)

### Configuration des devises

Le plugin supporte automatiquement la conversion des devises :

- EUR → XOF/XAF
- USD → XOF/XAF

Assurez-vous que votre boutique WooCommerce utilise l'une des devises supportées.

## Hook et Filtres

Le plugin expose plusieurs hooks pour les développeurs :

### Actions

- `pawapay_before_payment_processing` - Avant le traitement du paiement
- `pawapay_after_payment_processing` - Après le traitement du paiement
- `pawapay_payment_success` - Lorsqu'un paiement réussit
- `pawapay_payment_failed` - Lorsqu'un paiement échoue

### Filtres

- `pawapay_supported_countries` - Modifier les pays supportés
- `pawapay_supported_currencies` - Modifier les devises supportées
- `pawapay_provider_list` - Modifier la liste des opérateurs
- `pawapay_payment_description` - Modifier la description du paiement

## Dépannage

### Le gateway n'apparaît pas

- Vérifiez que WooCommerce est activé
- Vérifiez que la devise de la boutique est supportée
- Vérifiez que le token API est correctement configuré

### Erreurs de conversion de devise

- Vérifiez la connexion internet pour l'API de taux de change
- Vérifiez que les devises source et cible sont supportées

### Problèmes avec l'éditeur de blocs

- Assurez-vous d'utiliser la dernière version de WooCommerce
- Videz le cache du site si nécessaire

## Journal des modifications

### Version 1.2.2

- Correction de la compatibilité avec les blocs WooCommerce
- Amélioration de la gestion des erreurs
- Optimisation des performances

### Version 1.2.1

- Ajout du support multi-pays
- Interface de sélection des opérateurs avec logos
- Conversion automatique des devises

### Version 1.1.0

- Version initiale avec support basic de PawaPay

## Roadmap

- [ ] Support des webhooks PawaPay pour les statuts de paiement
- [ ] Interface de gestion des transactions
- [ ] Rapports et analytics intégrés
- [ ] Support de plus de pays africains
- [ ] Intégration avec des systèmes de loyalty

## Support

Pour le support technique, veuillez :

1. Vérifier la documentation PawaPay : <https://docs.pawapay.io/v2/docs>
2. Créer une issue sur le repository GitHub
3. Contacter le support Ferray Digital Solutions

## Licence

Ce plugin est sous licence GPL v3.0. Voir le fichier `LICENSE` pour plus de détails.

## Contributeurs

- Développé par [Kabirou ALASSANE](https://kabiroualassane.link)
- Maintained par [Kabirou ALASSANE](https://kabiroualassane.link)

## Liens utiles

- [Documentation PawaPay](https://docs.pawapay.io/v2/docs)
- [Site WooCommerce](https://woocommerce.com/)
- [Site WordPress](https://wordpress.org/)

---

**Note** : Ce plugin nécessite une clé API PawaPay valide pour fonctionner. Inscrivez-vous sur [PawaPay](https://pawapay.io) pour obtenir vos identifiants API.
