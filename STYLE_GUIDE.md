# 🎨 ToutVaMal - Guide de Style Officiel

**IMPORTANT** : Ce fichier définit le style visuel de ToutVaMal. À consulter AVANT toute création de page, email, ou composant.

---

## 🎨 PALETTE DE COULEURS

```css
:root {
    /* Couleurs principales */
    --noir: #0a0a0a;           /* Fond principal */
    --blanc: #ffffff;          /* Texte principal */
    --rouge: #C41E3A;          /* Accent, CTA, logo "VA" */
    --rouge-sombre: #991B1B;   /* Hover sur rouge */

    /* Gris */
    --gris-100: #f5f5f5;
    --gris-200: #e5e5e5;
    --gris-300: #d4d4d4;
    --gris-400: #a3a3a3;
    --gris-500: #737373;       /* Texte secondaire */
    --gris-600: #525252;
    --gris-700: #404040;
    --gris-800: #262626;
    --gris-900: #171717;
}
```

## 🔤 TYPOGRAPHIE

```css
/* Titres */
font-family: 'Playfair Display', Georgia, serif;

/* Corps de texte */
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

/* Logo */
font-family: Georgia, serif;
font-weight: 900;
/* TOUT = blanc, VA = rouge (#C41E3A), MAL = blanc */
```

## 📐 STRUCTURE DES PAGES

### Fond
- **Couleur** : `#0a0a0a` (noir profond)
- **Pas de dégradés complexes** sur le fond principal

### Conteneur principal
```css
max-width: 1200px;
margin: 0 auto;
padding: 0 1rem;
```

### Header
- Fond : `#0a0a0a`
- Logo centré avec tagline
- Navigation simple

## 🔘 BOUTONS (CTA)

```css
/* Bouton principal */
background: #C41E3A;
color: #ffffff;
padding: 1rem 2rem;
border-radius: 8px;
font-weight: 600;
border: none;

/* Hover */
background: #991B1B;
```

**PAS DE** :
- Dégradés sur les boutons
- Ombres excessives
- Border-radius > 8px

## 📧 EMAILS

### Structure
1. **Header** : Logo TOUTVAMAL + tagline (fond noir simple)
2. **CTA en haut** : Bouton visible SANS scroller
3. **Contenu** : Texte sarcastique
4. **Footer** : Mentions légales

### Couleurs emails
- Fond : `#0a0a0a`
- Blocs de contenu : `#111111` ou `#1a1a1a`
- Texte : `#ffffff` (titres), `#9ca3af` (corps)
- Liens/CTA : `#C41E3A`

### Style du CTA email
```css
background: #C41E3A;  /* PAS de dégradé */
color: #ffffff;
padding: 16px 32px;
border-radius: 8px;
font-weight: 600;
text-decoration: none;
```

## ✍️ TON ÉDITORIAL

### Principes
- **Cynique** mais pas méchant
- **Sarcastique** avec autodérision
- **Références** à la nostalgie ("c'était mieux avant")
- **Catastrophisme** humoristique

### Exemples de phrases types
- "C'était mieux avant, et ce sera pire demain"
- "Votre dose hebdomadaire de désespoir"
- "Bienvenue dans la déprime"
- "Vous ne recevrez rien d'autre. Comme le bonheur."
- "Probablement dans un moment de faiblesse existentielle"

### À éviter
- Humour trop noir/choquant
- Insultes directes
- Politique partisane explicite

## 📱 RESPONSIVE

- Mobile first
- Breakpoints : 768px, 1024px
- Pas de texte trop petit (min 14px sur mobile)

## ⚠️ ANTI-PATTERNS (À NE PAS FAIRE)

1. ❌ Dégradés complexes (sauf subtle sur header)
2. ❌ Ombres portées excessives
3. ❌ Couleurs vives autres que le rouge
4. ❌ Border-radius > 12px
5. ❌ Animations flashy
6. ❌ Fonds blancs ou clairs
7. ❌ Polices fantaisie

---

**Dernière mise à jour** : 28/12/2025
