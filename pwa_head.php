<?php /* Included inside <head> of every root-level page by pwa_setup.php */ ?>
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#c4517a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Precious Satin">
<link rel="apple-touch-icon" href="images/logo/precious.jpg">
<link rel="icon" type="image/jpeg" href="images/logo/precious.jpg">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); });
}
var _psbPrompt;
window.addEventListener('beforeinstallprompt', function (e) {
  e.preventDefault(); _psbPrompt = e;
  var b = document.createElement('button');
  b.textContent = 'Install app';
  b.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:9999;padding:10px 18px;border:0;border-radius:24px;background:#c4517a;color:#fff;font:500 14px Jost,sans-serif;box-shadow:0 4px 14px rgba(196,81,122,.4);cursor:pointer';
  b.onclick = function () { _psbPrompt.prompt(); b.remove(); };
  (document.body || document.documentElement).appendChild(b);
});
</script>
<script>
// Back button: only inside the installed app (no browser back button there). Hidden on the home page.
(function () {
  var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
  if (!standalone) return;
  var page = (location.pathname.split('/').pop() || 'index.php').toLowerCase();
  if ((page === 'index.php' || page === '') && !location.search) return;
  var goHome = /thankyou|place_order|save_gcash|gcash_cancel|logout/.test(page);   // never go "back" into a submitted form
  function add() {
    var b = document.createElement('button');
    b.type = 'button'; b.setAttribute('aria-label', 'Go back');
    b.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>';
    b.style.cssText = 'position:fixed;left:16px;bottom:calc(16px + env(safe-area-inset-bottom,0px));z-index:9998;width:46px;height:46px;border:0;border-radius:50%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e8729a,#c4517a);color:#fff;box-shadow:0 4px 14px rgba(196,81,122,.4);cursor:pointer';
    b.onclick = function () { if (!goHome && history.length > 1) history.back(); else location.href = 'index.php'; };
    document.body.appendChild(b);
  }
  if (document.body) add(); else document.addEventListener('DOMContentLoaded', add);
})();
</script>