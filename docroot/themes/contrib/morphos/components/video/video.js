/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/video/video.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * Video component JavaScript.
 *
 * Initializes VideoJS for YouTube/MP4 URLs and uses a direct iframe embed
 * for Vimeo URLs. Skips initialization in Canvas editor mode.
 */

((Drupal, once) => {
  /**
   * Check if we're in Canvas editor mode (not preview mode).
   */
  const isCanvasEditor = () => {
    try {
      return (
        window?.parent?.drupalSettings?.canvas &&
        !window.parent.document.body.querySelector(
          '[class^=_PagePreviewIframe]',
        )
      );
    } catch (e) {
      return false;
    }
  };

  /**
   * Extract YouTube video ID from various URL formats.
   */
  const extractYouTubeId = (url) => {
    const patterns = [
      /(?:youtube\.com\/watch\?v=|youtube\.com\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
      /youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/,
    ];
    for (const pattern of patterns) {
      const match = url.match(pattern);
      if (match) {
        return match[1];
      }
    }
    return null;
  };

  /**
   * Extract Vimeo video ID from URL.
   */
  const extractVimeoId = (url) => {
    const match = url.match(/vimeo\.com\/(\d+)/);
    return match ? match[1] : null;
  };

  /**
   * Create a Vimeo iframe embed.
   */
  const createVimeoEmbed = (videoId) => {
    const iframe = document.createElement('iframe');
    iframe.className = 'video__vimeo-iframe';
    iframe.setAttribute('frameborder', '0');
    iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
    iframe.setAttribute('allowfullscreen', '');
    iframe.setAttribute('title', 'Vimeo video player');
    iframe.src = `https://player.vimeo.com/video/${videoId}`;
    return iframe;
  };

  /**
   * Initialize VideoJS for a YouTube or MP4 URL.
   */
  const initVideoJs = (container, url, youtubeId) => {
    const videoEl = document.createElement('video');
    videoEl.className = 'video-js vjs-big-play-centered';
    videoEl.setAttribute('controls', '');
    videoEl.setAttribute('preload', 'auto');
    videoEl.setAttribute('playsinline', '');
    container.appendChild(videoEl);

    const options = {
      controls: true,
      responsive: true,
    };

    if (youtubeId) {
      options.techOrder = ['youtube'];
      options.sources = [
        {
          type: 'video/youtube',
          src: `https://www.youtube.com/watch?v=${youtubeId}`,
        },
      ];
    } else {
      options.techOrder = ['html5'];
      options.sources = [
        {
          type: 'video/mp4',
          src: url,
        },
      ];
    }

    const player = videojs(videoEl, options);
    container._videoJsPlayer = player;
  };

  Drupal.behaviors.video = {
    attach: (context) => {
      if (isCanvasEditor()) {
        return;
      }

      once('video-init', '.video[data-video-url]', context).forEach(
        (container) => {
          const url = container.dataset.videoUrl || '';

          if (!url) {
            return;
          }

          const youtubeId = extractYouTubeId(url);
          const vimeoId = extractVimeoId(url);

          if (vimeoId) {
            const iframe = createVimeoEmbed(vimeoId);
            container.appendChild(iframe);
          } else {
            initVideoJs(container, url, youtubeId);
          }
        },
      );
    },

    detach: (context) => {
      once
        .remove('video-init', '.video[data-video-url]', context)
        .forEach((container) => {
          if (container._videoJsPlayer) {
            container._videoJsPlayer.dispose();
            delete container._videoJsPlayer;
          }

          const iframe = container.querySelector('.video__vimeo-iframe');
          if (iframe) {
            iframe.remove();
          }
        });
    },
  };
})(Drupal, once);
