/**
 * RolTech GMAO — ui.js (Step 9b.3)
 * Vanilla JS: drawer, focus trap, inert, active state, toast auto-dismiss.
 * Rule 7: all listeners registered immediately (synchronously).
 * Turbo-compatible: re-sync on turbo:load, teardown on turbo:before-cache.
 * No duplicate listeners across Turbo visits (guarded flag).
 */
(function () {
  'use strict';

  /* ── State ─────────────────────────────────────────────────────────── */
  var _drawerOpen = false;
  var _drawerBtn  = null;
  var _listenersAttached = false;

  /* ── Helpers ─────────────────────────────────────────────────────────*/
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  /* ── Drawer ──────────────────────────────────────────────────────────*/
  function openDrawer() {
    var drawer = qs('#rtn-drawer');
    var btn    = qs('#rtn-drawer-btn');
    if (!drawer) return;
    _drawerOpen = true;
    drawer.hidden = false;
    drawer.removeAttribute('hidden');
    if (btn) btn.setAttribute('aria-expanded', 'true');
    // Inert background content
    var shell = qs('.rtn-shell-content');
    if (shell) shell.setAttribute('inert', '');
    var sidebar = qs('.rtn-sidebar');
    if (sidebar) sidebar.setAttribute('inert', '');
    var topbar = qs('.rtn-topbar');
    if (topbar) topbar.setAttribute('inert', '');
    // Move focus into drawer
    var firstFocusable = qs('#rtn-drawer-close, .rtn-drawer-link', drawer);
    if (firstFocusable) {
      setTimeout(function () { firstFocusable.focus(); }, 50);
    }
    document.body.classList.add('rtn-drawer-is-open');
  }

  function closeDrawer() {
    var drawer = qs('#rtn-drawer');
    var btn    = qs('#rtn-drawer-btn');
    if (!drawer) return;
    _drawerOpen = false;
    drawer.hidden = true;
    if (btn) {
      btn.setAttribute('aria-expanded', 'false');
      btn.focus();
    }
    // Release inert
    var shell = qs('.rtn-shell-content');
    if (shell) shell.removeAttribute('inert');
    var sidebar = qs('.rtn-sidebar');
    if (sidebar) sidebar.removeAttribute('inert');
    var topbar = qs('.rtn-topbar');
    if (topbar) topbar.removeAttribute('inert');
    document.body.classList.remove('rtn-drawer-is-open');
  }

  /* ── Active-nav sync ─────────────────────────────────────────────────*/
  function syncActiveNav() {
    var currentPath = window.location.pathname;
    qsa('.rtn-sidebar-link, .rtn-tab, .rtn-drawer-link').forEach(function (a) {
      if (!a.href) return;
      try {
        var linkPath = new URL(a.href, window.location.origin).pathname;
        var isActive = (linkPath === currentPath);
        if (a.classList.contains('rtn-sidebar-link')) {
          var item = a.closest('.rtn-sidebar-item');
          if (item) item.classList.toggle('rtn-sidebar-item--active', isActive);
        }
        if (a.classList.contains('rtn-tab')) {
          a.classList.toggle('rtn-tab--active', isActive);
        }
        if (a.classList.contains('rtn-drawer-link')) {
          a.classList.toggle('rtn-drawer-link--active', isActive);
        }
        if (isActive) {
          a.setAttribute('aria-current', 'page');
        } else {
          a.removeAttribute('aria-current');
        }
      } catch (e) { /* ignore invalid URLs */ }
    });
  }

  /* ── Staggered stat-card animation ──────────────────────────────────*/
  function animateStatCards() {
    if (prefersReducedMotion()) return;
    qsa('.rtn-stat-card').forEach(function (card, i) {
      card.style.animationDelay = (i * 50) + 'ms';
      card.classList.add('rtn-stat-card--animate');
    });
  }

  /* ── Toast auto-dismiss ──────────────────────────────────────────────*/
  function setupToastDismiss() {
    qsa('.rtn-toast').forEach(function (toast) {
      if (toast.dataset.rtnAutoDismiss) return;
      toast.dataset.rtnAutoDismiss = '1';
      setTimeout(function () {
        if (toast.parentNode) {
          toast.style.transition = 'opacity 200ms ease-out';
          toast.style.opacity = '0';
          setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 220);
        }
      }, 5000);
    });
  }

  /* ── Event delegation (registered once, survive Turbo) ──────────────*/
  function attachGlobalListeners() {
    if (_listenersAttached) return;
    _listenersAttached = true;

    // Open drawer via button click (delegated, works for both btn locations)
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('#rtn-drawer-btn');
      if (btn) { openDrawer(); return; }

      var closeBtn = e.target.closest('#rtn-drawer-close');
      if (closeBtn) { closeDrawer(); return; }

      var backdrop = e.target.closest('#rtn-drawer-backdrop');
      if (backdrop) { closeDrawer(); return; }
    });

    // Escape closes drawer
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && _drawerOpen) {
        closeDrawer();
      }
      // Basic focus trap inside drawer
      if (_drawerOpen && e.key === 'Tab') {
        var drawer = qs('#rtn-drawer');
        if (!drawer) return;
        var focusable = qsa(
          'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
          drawer
        ).filter(function (el) { return !el.closest('[inert]'); });
        if (focusable.length === 0) return;
        var first = focusable[0];
        var last  = focusable[focusable.length - 1];
        if (e.shiftKey) {
          if (document.activeElement === first) {
            e.preventDefault();
            last.focus();
          }
        } else {
          if (document.activeElement === last) {
            e.preventDefault();
            first.focus();
          }
        }
      }
    });
  }

  /* ── Per-visit init ──────────────────────────────────────────────────*/
  function init() {
    syncActiveNav();
    animateStatCards();
    setupToastDismiss();
    // Ensure drawer is closed on new page load
    var drawer = qs('#rtn-drawer');
    if (drawer && _drawerOpen) closeDrawer();
  }

  /* ── Bootstrap ───────────────────────────────────────────────────────*/
  // Attach global (delegated) listeners immediately — survives Turbo
  attachGlobalListeners();

  // Turbo-compatible: re-run per-visit work after each navigation
  document.addEventListener('turbo:load', init);

  // Teardown: close drawer and release inert before Turbo caches the page
  document.addEventListener('turbo:before-cache', function () {
    if (_drawerOpen) closeDrawer();
    // Remove animation classes so cached snapshot is clean
    qsa('.rtn-stat-card--animate').forEach(function (el) {
      el.classList.remove('rtn-stat-card--animate');
      el.style.animationDelay = '';
    });
  });

  // Also run on DOMContentLoaded for the very first page load
  // (turbo:load fires later on first load too, but DOMContentLoaded is earlier)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
