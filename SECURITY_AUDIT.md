# ToutVaMal.fr -- Audit Securite Phase 5

**Date** : 2026-03-20
**Auditeur** : forge-bastion (RSSI La Forge)
**Perimetre** : Social Cards (OG/Twitter), Content-Security-Policy, Headers HTTP

---

## Resume

| Categorie | Status | Criticite |
|-----------|--------|-----------|
| Social Cards (Articles) | OK | N/A |
| Social Cards (Homepage) | OK | N/A |
| Social Cards (Pages statiques) | CORRIGE | Moyenne |
| Content-Security-Policy | CORRIGE | Haute |
| Headers HTTP | OK (renforce) | N/A |
| XSS via OG tags | OK (protege) | N/A |

---

## Vulnerabilites trouvees et corrigees

| # | Severite | OWASP | Description | Fichier | Recommandation | Status |
|---|----------|-------|-------------|---------|----------------|--------|
| 1 | Moyenne | A05 | `og:url` et `canonical` pointaient vers `/` sur equipe.html et a-propos.html au lieu de leur propre URL | equipe.html:15,45 | Corriger les URLs canoniques | CORRIGE |
| 2 | Basse | A05 | `og:type` etait `article` sur pages non-article (equipe, a-propos) | equipe.html:37, a-propos.html:37 | Changer en `website` | CORRIGE |
| 3 | Basse | A05 | Balises `article:published_time`, `article:section`, `article:tag` presentes sur des pages non-article | equipe.html, a-propos.html | Supprimer ces balises | CORRIGE |
| 4 | Haute | A05 | CSP absente (seulement `upgrade-insecure-requests`) | .htaccess | Ajouter un CSP complet | CORRIGE |
| 5 | Basse | A05 | X-Frame-Options a `SAMEORIGIN` au lieu de `DENY` | .htaccess | Passer a DENY | CORRIGE |
| 6 | Info | A05 | Header `X-XSS-Protection` obsolete (deprecie par les navigateurs modernes) | .htaccess | Supprime | CORRIGE |

---

## Headers HTTP

| Header | Present | Valeur | Conforme |
|--------|---------|--------|----------|
| Strict-Transport-Security | OUI | `max-age=31536000; includeSubDomains` | OUI |
| Content-Security-Policy | OUI | `default-src 'self'; script-src 'self' 'unsafe-inline' https://plausible.d3rf.com https://www.clarity.ms; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' https: data:; connect-src 'self' https://plausible.d3rf.com https://www.clarity.ms https://*.clarity.ms; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests` | OUI |
| X-Content-Type-Options | OUI | `nosniff` | OUI |
| X-Frame-Options | OUI | `DENY` | OUI |
| Referrer-Policy | OUI | `strict-origin-when-cross-origin` | OUI |
| Permissions-Policy | OUI | `geolocation=(), microphone=(), camera=()` | OUI |
| X-Powered-By | NON (supprime) | -- | OUI |
| X-XSS-Protection | NON (supprime, obsolete) | -- | OUI |

---

## Social Cards -- Etat par type de page

### Homepage (/)
| Tag | Present | Dynamique | Valeur |
|-----|---------|-----------|--------|
| og:title | OUI | OUI | "ToutVaMal.fr - C'ETAIT MIEUX AVANT..." |
| og:description | OUI | OUI | "Les pires nouvelles du jour..." |
| og:image | OUI | OUI | https://toutvamal.fr/images/og-default.jpg (1200x630) |
| og:url | OUI | OUI | https://toutvamal.fr/ |
| og:type | OUI | OUI | website |
| og:site_name | OUI | -- | ToutVaMal.fr |
| twitter:card | OUI | -- | summary_large_image |
| twitter:title | OUI | OUI | Idem og:title |
| twitter:description | OUI | OUI | Idem og:description |
| twitter:image | OUI | OUI | Idem og:image |

### Articles (/article.php?slug=...)
| Tag | Present | Dynamique | Exemple |
|-----|---------|-----------|---------|
| og:title | OUI | OUI | "Un opossum sauvage se cache..." |
| og:description | OUI | OUI | Extrait unique de l'article |
| og:image | OUI | OUI | Image specifique de l'article |
| og:url | OUI | OUI | URL canonique /articles/slug.html |
| og:type | OUI | OUI | article |
| article:published_time | OUI | OUI | Date de publication |
| article:modified_time | OUI | OUI | Date de modification |
| article:section | OUI | OUI | Categorie de l'article |
| twitter:card | OUI | -- | summary_large_image |

### Pages statiques (equipe.html, a-propos.html) -- CORRIGE
| Tag | Present | Correct |
|-----|---------|---------|
| og:title | OUI | Titre specifique a chaque page |
| og:description | OUI | Description specifique |
| og:image | OUI | Image par defaut du site |
| og:url | OUI (CORRIGE) | URL propre de la page (etait `/` avant) |
| og:type | OUI (CORRIGE) | `website` (etait `article` avant) |
| canonical | OUI (CORRIGE) | URL propre (etait `/` avant) |
| article:* | NON (CORRIGE) | Supprimees (n'avaient pas lieu d'etre) |

---

## Analyse CSP

### Justification des directives
| Directive | Valeur | Justification |
|-----------|--------|---------------|
| `default-src 'self'` | Restrictif | Bloque tout par defaut sauf meme origine |
| `script-src 'self' 'unsafe-inline'` | + Plausible + Clarity | `unsafe-inline` necessaire car consent.js cree des scripts via createElement. Plausible et Clarity sont les seuls tiers autorises. |
| `style-src 'self' 'unsafe-inline'` | + Google Fonts | `unsafe-inline` necessaire car consent.js injecte du CSS via createElement('style'). Google Fonts pour les polices. |
| `font-src 'self'` | + Google Fonts static | Polices Web depuis gstatic.com |
| `img-src 'self' https: data:` | Permissif | Les articles peuvent avoir des images de sources variees. `data:` pour les SVG inline. |
| `connect-src 'self'` | + Plausible + Clarity | Analytics et tracking uniquement vers ces domaines |
| `frame-ancestors 'none'` | Restrictif | Empeche le clickjacking (renforce X-Frame-Options: DENY) |
| `base-uri 'self'` | Restrictif | Protege contre les attaques base-tag injection |
| `form-action 'self'` | Restrictif | Formulaires uniquement vers le meme domaine |
| `upgrade-insecure-requests` | Standard | Force HTTPS pour toutes les ressources |

### Risques residuels
- `'unsafe-inline'` dans script-src est necessaire mais affaiblit la protection XSS. Evolution recommandee : migrer vers des nonces ou hashes pour le script consent.js.
- `img-src https:` est large mais necessaire car les images d'articles peuvent provenir de sources variees.

---

## Tests de securite effectues

| Test | Resultat |
|------|----------|
| XSS via slug article (`?slug=test"><script>alert(1)</script>`) | BLOQUE -- renvoie page "Article non trouve", pas d'injection |
| Homepage HTTP 200 | OK |
| Article HTTP 200 | OK |
| Pages statiques HTTP 200 | OK |
| Archives HTTP 200 | OK |
| Categories HTTP 200 | OK |
| CSS charge correctement | OK |
| JS charge correctement | OK |
| Image OG par defaut existe | OK (200, 12977 bytes) |

---

## Fichiers modifies

| Fichier | Modification | Backup |
|---------|-------------|--------|
| `.htaccess` | CSP ajoute, X-Frame-Options DENY, X-XSS-Protection supprime | `.htaccess.backup.phase5` |
| `equipe.html` | og:url, og:type, canonical corriges, article:* supprimes | `equipe.html.backup.phase5` |
| `a-propos.html` | og:url, og:type, canonical corriges, article:* supprimes | `a-propos.html.backup.phase5` |

---

## Recommandations futures

| Priorite | Recommandation |
|----------|----------------|
| Moyenne | Migrer `'unsafe-inline'` vers nonces CSP pour script-src (necessite modification de consent.js et header.php) |
| Basse | Ajouter `report-uri` ou `report-to` au CSP pour monitorer les violations |
| Basse | Ajouter `Cross-Origin-Embedder-Policy` et `Cross-Origin-Opener-Policy` headers |
| Info | Les pages statiques .html sont pre-generees : tout changement dans header.php necessite une regeneration manuelle |

---

## Score global : 92/100

**Justification** : Tous les headers de securite sont en place, le CSP est complet et adapte, les social cards sont dynamiques et correctes sur toutes les pages. Points de deduction : `'unsafe-inline'` dans script-src (-5), `img-src https:` large (-3).
