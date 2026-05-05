/**
 * Author & Share: copy-link and set share URLs for social buttons.
 * Each link can use a dedicated data attribute; otherwise falls back to data-share-url or current page.
 */
(function () {
  function getBaseUrl(container) {
    const url = container.getAttribute('data-share-url');
    if (url && url.trim()) return url.trim();
    if (typeof window !== 'undefined' && window.location) return window.location.href;
    return '';
  }

  function getCopyUrl(container) {
    const url = container.getAttribute('data-link-copy-url');
    if (url && url.trim()) return url.trim();
    return getBaseUrl(container);
  }

  function buildShareUrls(pageUrl) {
    const encoded = encodeURIComponent(pageUrl);
    return {
      linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url=' + encoded,
      x: 'https://twitter.com/intent/tweet?url=' + encoded,
      facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + encoded
    };
  }

  function init(container) {
    const baseUrl = getBaseUrl(container);
    const copyUrl = getCopyUrl(container);
    const urls = buildShareUrls(baseUrl);

    const copyBtn = container.querySelector('[data-action="copy-link"]');
    if (copyBtn) {
      copyBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!copyUrl) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(copyUrl).then(function () {
            var label = copyBtn.getAttribute('aria-label') || 'Copy link';
            copyBtn.setAttribute('aria-label', 'Link copied');
            setTimeout(function () { copyBtn.setAttribute('aria-label', label); }, 2000);
          });
        }
      });
    }

    var linkedinUrl = container.getAttribute('data-link-linkedin-url');
    var xUrl = container.getAttribute('data-link-x-url');
    var facebookUrl = container.getAttribute('data-link-facebook-url');

    const linkedinBtn = container.querySelector('[data-action="share-linkedin"]');
    if (linkedinBtn) linkedinBtn.href = (linkedinUrl && linkedinUrl.trim()) ? linkedinUrl.trim() : urls.linkedin;

    const xBtn = container.querySelector('[data-action="share-x"]');
    if (xBtn) xBtn.href = (xUrl && xUrl.trim()) ? xUrl.trim() : urls.x;

    const fbBtn = container.querySelector('[data-action="share-facebook"]');
    if (fbBtn) fbBtn.href = (facebookUrl && facebookUrl.trim()) ? facebookUrl.trim() : urls.facebook;
  }

  function run() {
    document.querySelectorAll('.author-share').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
