/**
 * Video SDC: thumbnail overlay + play/pause behavior.
 * - Remote (YouTube/Vimeo): play → hide thumbnail, play; pause → show thumbnail; play again → resume from position.
 * - Local: play → hide thumbnail; pause/ended → show thumbnail; play again → resume from position.
 * YouTube uses IFrame API so we can show thumbnail on pause and resume without reloading.
 */

(function () {
  function getYoutubeVideoId(url) {
    if (!url || typeof url !== 'string') return '';
    const u = url.trim();
    const match = u.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
    return match ? match[1] : '';
  }

  function isVimeoUrl(url) {
    if (!url || typeof url !== 'string') return false;
    return /vimeo\.com\/(?:video\/)?(\d+)/.test(url.trim());
  }

  function toVimeoEmbedUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const match = url.trim().match(/vimeo\.com\/(?:video\/)?(\d+)/);
    return match ? 'https://player.vimeo.com/video/' + match[1] + '?autoplay=1' : '';
  }

  function loadYoutubeApi(callback) {
    if (window.YT && window.YT.Player) {
      callback();
      return;
    }
    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    const firstScript = document.getElementsByTagName('script')[0];
    firstScript.parentNode.insertBefore(tag, firstScript);
    window.onYouTubeIframeAPIReady = function () {
      window.onYouTubeIframeAPIReady = null;
      callback();
    };
  }

  function initVideo(container) {
    if (container.getAttribute('data-video-initialized') === 'true') return;
    container.setAttribute('data-video-initialized', 'true');

    const source = container.getAttribute('data-video-source');
    const videoUrl = container.getAttribute('data-video-url');
    const hasThumbnail = container.getAttribute('data-has-thumbnail') === 'true';

    const mediaEl = container.querySelector('.video__media');
    const thumbnailWrap = container.querySelector('[data-video-thumbnail]');
    const playBtn = container.querySelector('[data-video-play]');
    const videoEl = container.querySelector('.video__element');
    const embedEl = container.querySelector('[data-video-remote]');

    if (!mediaEl) return;

    function showThumbnail() {
      if (thumbnailWrap) {
        thumbnailWrap.removeAttribute('data-hidden');
      }
      mediaEl.setAttribute('aria-hidden', 'true');
    }

    function hideThumbnail() {
      if (thumbnailWrap) {
        thumbnailWrap.setAttribute('data-hidden', '');
      }
      mediaEl.setAttribute('aria-hidden', 'false');
    }

    var ytPlayer = null;
    var youtubeId = getYoutubeVideoId(videoUrl);
    var isYoutube = !!youtubeId;

    function playRemote() {
      if (!embedEl || !videoUrl) return;

      if (isYoutube) {
        if (ytPlayer) {
          ytPlayer.playVideo();
          hideThumbnail();
          return;
        }
        loadYoutubeApi(function () {
          if (ytPlayer) {
            ytPlayer.playVideo();
            hideThumbnail();
            return;
          }
          if (!embedEl.id) {
            embedEl.id = 'yt-player-' + Math.random().toString(36).slice(2, 11);
          }
          ytPlayer = new window.YT.Player(embedEl.id, {
            videoId: youtubeId,
            width: '100%',
            height: '100%',
            playerVars: {
              enablejsapi: 1,
              origin: window.location.origin,
              rel: 0
            },
            events: {
              onReady: function (event) {
                event.target.playVideo();
                hideThumbnail();
              },
              onStateChange: function (event) {
                if (event.data === window.YT.PlayerState.PAUSED) {
                  showThumbnail();
                }
              }
            }
          });
        });
        return;
      }

      if (isVimeoUrl(videoUrl)) {
        var vimeoEmbed = embedEl.querySelector('iframe');
        if (vimeoEmbed) {
          hideThumbnail();
          return;
        }
        var iframe = document.createElement('iframe');
        iframe.setAttribute('src', toVimeoEmbedUrl(videoUrl));
        iframe.setAttribute('title', 'Video player');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
        iframe.setAttribute('allowfullscreen', '');
        iframe.style.position = 'absolute';
        iframe.style.inset = '0';
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = '0';
        embedEl.appendChild(iframe);
        hideThumbnail();
        return;
      }

      var fallbackIframe = embedEl.querySelector('iframe');
      if (!fallbackIframe) {
        var f = document.createElement('iframe');
        f.setAttribute('src', videoUrl);
        f.setAttribute('title', 'Video player');
        f.setAttribute('allowfullscreen', '');
        f.style.position = 'absolute';
        f.style.inset = '0';
        f.style.width = '100%';
        f.style.height = '100%';
        f.style.border = '0';
        embedEl.appendChild(f);
      }
      hideThumbnail();
    }

    function playLocal() {
      if (!videoEl) return;
      videoEl.play().then(function () {
        hideThumbnail();
      }).catch(function () {});
    }

    function onPlayClick() {
      if (source === 'remote') {
        playRemote();
      } else {
        playLocal();
      }
    }

    if (playBtn) {
      playBtn.addEventListener('click', onPlayClick);
    }

    if (videoEl && hasThumbnail) {
      videoEl.addEventListener('pause', function () {
        showThumbnail();
      });
      videoEl.addEventListener('ended', function () {
        showThumbnail();
      });
    }

    if (!hasThumbnail && mediaEl) {
      mediaEl.setAttribute('aria-hidden', 'false');
    }

    if (!hasThumbnail && source === 'remote' && embedEl && videoUrl) {
      if (isYoutube) {
        loadYoutubeApi(function () {
          if (!embedEl.querySelector('iframe')) {
            playRemote();
          }
        });
      } else {
        playRemote();
      }
    }
  }

  function init() {
    var containers = document.querySelectorAll('.video__container[data-video-url]');
    containers.forEach(initVideo);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  if (typeof Drupal !== 'undefined' && Drupal.behaviors) {
    Drupal.behaviors.videoSdc = {
      attach: function (context) {
        var containers = context.querySelectorAll ? context.querySelectorAll('.video__container[data-video-url]') : [];
        containers.forEach(initVideo);
      }
    };
  }
})();
