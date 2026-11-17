(function () {
  'use strict';
  var endpoint = 'https://cpanel.smarternowhosting.co.ke/collect.php';
  var storageKey = 'smartnow_analytics_consent';
  var bannerId = 'smartnow-privacy-banner';
  function visitorId() {
    var match = document.cookie.match(/(?:^|; )sn_vid=([a-f0-9]{32})/);
    if (match) return match[1];
    var bytes = new Uint8Array(16);
    if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(bytes);
    else for (var i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    var id = Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    document.cookie = 'sn_vid=' + id + '; Max-Age=15552000; Path=/; Secure; SameSite=Lax';
    return id;
  }
  function record() {
    var payload = JSON.stringify({event:'page_view',visitor_id:visitorId(),page:window.location.pathname,referrer:document.referrer || ''});
    if (navigator.sendBeacon) navigator.sendBeacon(endpoint, new Blob([payload], {type:'text/plain;charset=UTF-8'}));
    else fetch(endpoint, {method:'POST',mode:'cors',keepalive:true,headers:{'Content-Type':'text/plain;charset=UTF-8'},body:payload}).catch(function () {});
  }
  function showBanner() {
    if (document.getElementById(bannerId)) return;
    var box = document.createElement('div');
    box.id = bannerId;
    box.setAttribute('role','dialog');
    box.setAttribute('aria-label','Privacy and analytics choice');
    box.style.cssText = 'position:fixed;z-index:99999;left:16px;right:16px;bottom:16px;max-width:760px;margin:auto;background:#fff;color:#172033;border:1px solid #dce3ed;border-radius:14px;padding:18px 20px;box-shadow:0 12px 40px #10223a30;font:14px/1.5 system-ui,sans-serif';
    box.innerHTML = '<strong style="display:block;font-size:15px;margin-bottom:5px">Your privacy choices</strong><p style="margin:0 0 12px;color:#536174">With your permission, SmartNow uses a first-party identifier to count visits, understand popular pages and improve the website. We do not use this to identify you by name, infer age or gender, or store your full IP address. You can decline and still use the website. <a href="/privacy.html" style="color:#2457d6">Privacy information</a>.</p><div style="display:flex;gap:9px;flex-wrap:wrap"><button type="button" data-choice="yes" style="border:0;border-radius:8px;background:#2457d6;color:#fff;padding:9px 14px;font-weight:700">Allow analytics</button><button type="button" data-choice="no" style="border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#172033;padding:9px 14px;font-weight:700">Decline</button></div>';
    box.addEventListener('click', function (event) {
      var choice = event.target && event.target.getAttribute('data-choice');
      if (!choice) return;
      try { localStorage.setItem(storageKey, choice); } catch (e) {}
      box.remove();
      if (choice === 'yes') record();
    });
    document.body.appendChild(box);
  }
  var consent = null;
  try { consent = localStorage.getItem(storageKey); } catch (e) {}
  if (consent === 'yes') record();
  else if (consent !== 'no') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', showBanner);
    else showBanner();
  }
}());