# Configuration CRON - ToutVaMal.fr

## Accès hPanel
1. Aller sur https://hpanel.hostinger.com
2. Sélectionner toutvamal.fr
3. Avancé → Tâches planifiées (Cron Jobs)

## Tâche à créer

**Génération automatique d'articles** (toutes les 3 heures)
- **Fréquence** : `0 */3 * * *` (à 0h, 3h, 6h, 9h, 12h, 15h, 18h, 21h)
- **Commande** : `/usr/bin/php /home/u443792660/domains/toutvamal.fr/public_html/cron/auto-generate.php`

## Test manuel
```bash
ssh toutvamal "cd /home/u443792660/domains/toutvamal.fr/public_html && php cron/auto-generate.php"
```

## Logs
Les logs sont dans :
- `/home/u443792660/domains/toutvamal.fr/public_html/logs/app.log`
- `/home/u443792660/domains/toutvamal.fr/public_html/logs/error.log`
