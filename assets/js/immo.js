/* Kit „immobilien“: Filter sendet bei Änderung ab (ohne JS: Button „Filtern“), „Alle Fotos“ öffnet die Lightbox des Kerns
   am ersten Bild. Kein Tracking, keine externen Anfragen. */
(() => {
  for (const f of document.querySelectorAll('form[data-immo-filter]')) {
    f.addEventListener('change', (ev) => {
      // Zahlenfelder erst beim Verlassen bzw. mit Enter – sonst springt die Seite beim Tippen
      if (ev.target.matches('input[type=number]') && document.activeElement === ev.target) return;
      // Leere Felder nicht in die Adresse schreiben
      for (const el of f.elements) if (el.name && !el.value && !(el.type === 'radio' && el.checked)) el.disabled = true;
      f.requestSubmit ? f.requestSubmit() : f.submit();
    });
    f.addEventListener('submit', () => { for (const el of f.elements) if (el.name && !el.value) el.disabled = true; });
  }
  for (const btn of document.querySelectorAll('[data-immo-gallery]')) {
    btn.addEventListener('click', () => btn.closest('.pd__gallery')?.querySelector('.pd__shot')?.click());
  }
})();
