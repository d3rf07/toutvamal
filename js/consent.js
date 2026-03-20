/**
 * consent.js — Gestion du consentement cookies (Clarity) — ToutVaMal.fr
 *
 * Logique :
 *  - tvm_consent=accepted  → Clarity chargé immédiatement, bannière masquée
 *  - tvm_consent=refused   → rien, pas de bannière
 *  - (absence de cookie)   → bannière affichée
 *
 * Plausible Analytics (cookie-free) n'est PAS géré ici : il tourne toujours.
 */

(function () {
  'use strict';

  /* ------------------------------------------------------------------ */
  /* Utilitaires cookies                                                  */
  /* ------------------------------------------------------------------ */

  function getCookie(name) {
    if (typeof document === 'undefined' || typeof document.cookie !== 'string') {
      console.warn('[consent] document.cookie non disponible');
      return null;
    }
    var pairs = document.cookie.split(';');
    for (var i = 0; i < pairs.length; i++) {
      var pair = pairs[i].trim();
      var eqIdx = pair.indexOf('=');
      if (eqIdx === -1) continue;
      var key = pair.slice(0, eqIdx).trim();
      if (key === name) {
        return decodeURIComponent(pair.slice(eqIdx + 1).trim());
      }
    }
    return null;
  }

  function setCookie(name, value, days) {
    if (typeof document === 'undefined') {
      console.warn('[consent] document non disponible, impossible de poser le cookie');
      return;
    }
    var expires = '';
    if (typeof days === 'number' && days > 0) {
      var d = new Date();
      d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
      expires = '; expires=' + d.toUTCString();
    }
    document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
  }

  /* ------------------------------------------------------------------ */
  /* Chargement de Clarity                                                */
  /* ------------------------------------------------------------------ */

  function loadClarity() {
    if (typeof window === 'undefined') {
      console.warn('[consent] window non disponible, Clarity non chargé');
      return;
    }
    /* Évite un double chargement si la fonction est appelée deux fois */
    if (window.__clarityLoaded) return;
    window.__clarityLoaded = true;

    (function (c, l, a, r, i, t, y) {
      c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
      t = l.createElement(r);
      t.async = 1;
      t.src = 'https://www.clarity.ms/tag/' + i;
      y = l.getElementsByTagName(r)[0];
      if (y && y.parentNode) {
        y.parentNode.insertBefore(t, y);
      } else {
        /* Fallback si getElementsByTagName retourne vide */
        console.warn('[consent] Impossible d\'insérer le script Clarity : pas de balise script trouvée');
        (l.head || l.body || l.documentElement).appendChild(t);
      }
    })(window, document, 'clarity', 'script', 'vnlp5ac95w');
  }

  /* ------------------------------------------------------------------ */
  /* Injection CSS de la bannière                                         */
  /* ------------------------------------------------------------------ */

  function injectBannerStyles() {
    if (typeof document === 'undefined') return;
    if (document.getElementById('tvm-consent-styles')) return;

    var css = [
      '#tvm-consent-banner {',
      '  position: fixed;',
      '  bottom: 0;',
      '  left: 0;',
      '  right: 0;',
      '  z-index: 9999;',
      '  background: #1a1a1a;',
      '  border-top: 2px solid #C41E3A;',
      '  padding: 1rem 1.5rem;',
      '  display: flex;',
      '  align-items: center;',
      '  gap: 1.25rem;',
      '  flex-wrap: wrap;',
      '  font-family: Inter, system-ui, sans-serif;',
      '  font-size: 0.875rem;',
      '  color: #ccc;',
      '  line-height: 1.5;',
      '  box-shadow: 0 -4px 24px rgba(0,0,0,0.5);',
      '  transform: translateY(100%);',
      '  animation: tvm-slide-up 0.35s ease forwards;',
      '}',
      '@media (prefers-reduced-motion: reduce) {',
      '  #tvm-consent-banner {',
      '    animation: none;',
      '    transform: translateY(0);',
      '  }',
      '}',
      '@keyframes tvm-slide-up {',
      '  from { transform: translateY(100%); }',
      '  to   { transform: translateY(0); }',
      '}',
      '#tvm-consent-text {',
      '  flex: 1;',
      '  min-width: 200px;',
      '}',
      '#tvm-consent-text strong {',
      '  color: #fff;',
      '  display: block;',
      '  margin-bottom: 0.25rem;',
      '}',
      '#tvm-consent-text a {',
      '  color: #C41E3A;',
      '  text-decoration: underline;',
      '  white-space: nowrap;',
      '}',
      '#tvm-consent-text a:hover {',
      '  color: #e02444;',
      '}',
      '#tvm-consent-actions {',
      '  display: flex;',
      '  align-items: center;',
      '  gap: 0.75rem;',
      '  flex-wrap: wrap;',
      '}',
      '#tvm-btn-accept {',
      '  background: #C41E3A;',
      '  color: #fff;',
      '  border: none;',
      '  padding: 0.5rem 1.25rem;',
      '  border-radius: 4px;',
      '  font-size: 0.875rem;',
      '  font-family: inherit;',
      '  cursor: pointer;',
      '  transition: background 0.2s;',
      '  white-space: nowrap;',
      '}',
      '#tvm-btn-accept:hover {',
      '  background: #e02444;',
      '}',
      '#tvm-btn-refuse {',
      '  background: transparent;',
      '  color: #ccc;',
      '  border: 1px solid #666;',
      '  padding: 0.5rem 1.25rem;',
      '  border-radius: 4px;',
      '  font-size: 0.875rem;',
      '  font-family: inherit;',
      '  cursor: pointer;',
      '  transition: border-color 0.2s, color 0.2s;',
      '  white-space: nowrap;',
      '}',
      '#tvm-btn-refuse:hover {',
      '  border-color: #999;',
      '  color: #fff;',
      '}',
      '@media (max-width: 600px) {',
      '  #tvm-consent-banner {',
      '    flex-direction: column;',
      '    align-items: flex-start;',
      '    gap: 1rem;',
      '  }',
      '  #tvm-consent-actions {',
      '    width: 100%;',
      '  }',
      '  #tvm-btn-accept, #tvm-btn-refuse {',
      '    flex: 1;',
      '    text-align: center;',
      '  }',
      '}'
    ].join('\n');

    var style = document.createElement('style');
    style.id = 'tvm-consent-styles';
    style.textContent = css;
    (document.head || document.documentElement).appendChild(style);
  }

  /* ------------------------------------------------------------------ */
  /* Création et affichage de la bannière                                 */
  /* ------------------------------------------------------------------ */

  function showBanner() {
    if (typeof document === 'undefined') return;

    injectBannerStyles();

    var banner = document.createElement('div');
    banner.id = 'tvm-consent-banner';
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', 'Gestion des cookies');

    banner.innerHTML = [
      '<div id="tvm-consent-text">',
      '  <strong>Ce site utilise des cookies d\'analyse comportementale.</strong>',
      '  Nous souhaiterions activer <strong>Microsoft Clarity</strong> (enregistrements de session,',
      '  cartes de chaleur) pour ameliorer votre experience. Plausible Analytics (sans cookie)',
      '  reste toujours actif.',
      '  <a href="/confidentialite.html">En savoir plus</a>',
      '</div>',
      '<div id="tvm-consent-actions">',
      '  <button id="tvm-btn-accept" type="button">Accepter</button>',
      '  <button id="tvm-btn-refuse" type="button">Refuser</button>',
      '</div>'
    ].join('');

    var btnAccept = banner.querySelector('#tvm-btn-accept');
    var btnRefuse = banner.querySelector('#tvm-btn-refuse');

    if (!btnAccept || !btnRefuse) {
      console.warn('[consent] Impossible de trouver les boutons dans la bannière');
      return;
    }

    btnAccept.addEventListener('click', function () {
      setCookie('tvm_consent', 'accepted', 180);
      hideBanner(banner);
      loadClarity();
    });

    btnRefuse.addEventListener('click', function () {
      setCookie('tvm_consent', 'refused', 180);
      hideBanner(banner);
    });

    (document.body || document.documentElement).appendChild(banner);
  }

  function hideBanner(banner) {
    if (!banner) return;
    banner.style.animation = 'none';
    banner.style.transition = 'opacity 0.25s, transform 0.25s';
    banner.style.opacity = '0';
    banner.style.transform = 'translateY(100%)';
    setTimeout(function () {
      if (banner.parentNode) {
        banner.parentNode.removeChild(banner);
      }
    }, 280);
  }

  /* ------------------------------------------------------------------ */
  /* Point d'entrée                                                       */
  /* ------------------------------------------------------------------ */

  function init() {
    var consent = getCookie('tvm_consent');

    if (typeof consent !== 'string') {
      /* Pas de cookie → afficher la bannière */
      showBanner();
      return;
    }

    if (consent === 'accepted') {
      loadClarity();
      return;
    }

    if (consent === 'refused') {
      /* Rien à faire */
      return;
    }

    /* Valeur inattendue : on affiche la bannière pour re-collecter le consentement */
    console.warn('[consent] Valeur inattendue pour tvm_consent:', consent);
    showBanner();
  }

  /* Lancement après que le DOM soit prêt */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
