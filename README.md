# ToutVaMal.fr 🎭

Site satirique français automatisé. Transforme les actualités insolites en catastrophes hilarantes.

## Architecture

```
┌─────────────────┐     ┌──────────────┐     ┌─────────────────┐
│   RSS Insolite  │ ──▶ │    n8n       │ ──▶ │  ToutVaMal.fr   │
│  (4 sources)    │     │  Workflow    │     │   (Hostinger)   │
└─────────────────┘     └──────────────┘     └─────────────────┘
```

## Sources RSS
- 20 Minutes Insolite
- Europe1 Insolite
- 7sur7 Insolite (Belgique)
- Sud Ouest Insolite

## Catégories thématiques
- 💸 EFFONDREMENT ÉCONOMIQUE
- 👥 DÉCLIN SOCIÉTAL
- 🏛️ CHAOS POLITIQUE
- 🏥 CRISE SANITAIRE
- 🌍 DÉSASTRE ÉCOLOGIQUE
- 💻 FIASCO TECHNOLOGIQUE
- ⚖️ SCANDALE MORAL
- 🎭 NAUFRAGE CULTUREL

## Stack technique
- **Frontend**: HTML/CSS/JS statique
- **Backend**: n8n (workflow automation)
- **IA**: Mistral Large (OpenRouter) + Replicate (images)
- **Hébergement**: Hostinger Business

## Déploiement
Le workflow n8n génère automatiquement un article toutes les heures.

Webhook manuel: `POST https://n8n.d3rf.com/webhook/toutvamal-generate`

## Structure
```
├── index.html          # Page d'accueil avec filtres
├── a-propos.html       # Page À propos
├── contact.html        # Page Contact
├── equipe.html         # Page Équipe
├── articles/           # Articles HTML
│   ├── liste.json      # Index des articles
│   └── images/         # Images générées
├── api/
│   └── articles.json   # API JSON
└── journalistes.json   # Personnages fictifs
```

---
*Toute ressemblance avec la réalité serait purement catastrophique. © 2025*
