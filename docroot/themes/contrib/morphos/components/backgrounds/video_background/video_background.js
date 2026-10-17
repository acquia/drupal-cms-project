/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/backgrounds/video_background/video_background.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * Video Background JavaScript.
 *
 * Creates background videos using VideoJS for YouTube, Vimeo, and local
 * video files. Shows placeholder in Canvas editor mode.
 */

((Drupal, once) => {
  const VIDEO_RATIO = 16 / 9;

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
   * Derive VideoJS source config from a URL.
   *
   * Detects YouTube, Vimeo, or local video URLs and returns the appropriate
   * source, tech order, and tech-specific options for VideoJS.
   *
   * @param {string} url - The video URL.
   * @return {Object} VideoJS config with sources, techOrder, and extras.
   */
  const deriveSourceConfig = (url) => {
    // YouTube.
    const ytMatch = url.match(
      /(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
    );
    if (ytMatch) {
      return {
        techOrder: ['youtube'],
        sources: [
          {
            type: 'video/youtube',
            src: 'https://www.youtube.com/watch?v=' + ytMatch[1],
          },
        ],
        youtube: {
          disablekb: 1,
          playsinline: 1,
          modestBranding: 1,
          rel: 0,
          showinfo: 0,
          iv_load_policy: 3,
          fs: 0,
          controls: 0,
        },
      };
    }

    // Vimeo.
    const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch) {
      return {
        techOrder: ['Vimeo'],
        sources: [
          {
            type: 'video/vimeo',
            src: 'https://vimeo.com/' + vimeoMatch[1],
          },
        ],
      };
    }

    // Local / direct file.
    return {
      sources: [{ src: url }],
    };
  };

  /**
   * Size a media element to cover its container, maintaining 16:9 aspect ratio.
   *
   * @param {HTMLElement} container - The container element.
   * @param {HTMLElement} media - The iframe or video-js element to size.
   */
  const sizeMedia = (container, media) => {
    const cw = container.offsetWidth;
    const ch = container.offsetHeight;
    if (!cw || !ch) {
      return;
    }

    const containerRatio = cw / ch;

    let w, h;
    if (containerRatio > VIDEO_RATIO) {
      w = cw;
      h = cw / VIDEO_RATIO;
    } else {
      h = ch;
      w = ch * VIDEO_RATIO;
    }

    media.style.width = w + 'px';
    media.style.height = h + 'px';
    media.style.left = (cw - w) / 2 + 'px';
    media.style.top = (ch - h) / 2 + 'px';
  };

  /**
   * Create a VideoJS background player for any supported video URL.
   *
   * @param {HTMLElement} container - The media container element.
   * @param {string} url - The video URL.
   * @return {Object} The VideoJS player instance.
   */
  const createPlayer = (container, url) => {
    const videoEl = document.createElement('video');
    videoEl.className = 'video-js';
    videoEl.setAttribute('playsinline', '');
    container.appendChild(videoEl);

    const sourceConfig = deriveSourceConfig(url);

    const player = videojs(
      videoEl,
      Object.assign({}, sourceConfig, {
        controls: false,
        autoplay: true,
        muted: true,
        loop: true,
        preload: 'auto',
        loadingSpinner: false,
        bigPlayButton: false,
        textTrackDisplay: false,
        errorDisplay: false,
      }),
    );

    // Fix YouTube loop flash: manually seek to start instead of relying on
    // YouTube's playlist-based loop which causes a black flash.
    if (sourceConfig.techOrder && sourceConfig.techOrder[0] === 'youtube') {
      player.on('ended', () => {
        player.currentTime(0);
        player.play();
      });
    }

    sizeMedia(container, player.el());

    return player;
  };

  /**
   * Resize all media elements inside a wrapper to cover the container.
   * Uses DOM children instead of class selectors.
   *
   * @param {HTMLElement} wrapper - The video-background element.
   */
  const resizeAll = (wrapper) => {
    const container = wrapper.querySelector('[data-video-background-media]');
    if (!container) {
      return;
    }

    // Size VideoJS player element to cover the container.
    const vjsEl = container.querySelector('.video-js');
    if (vjsEl) {
      sizeMedia(container, vjsEl);
    }
  };

  Drupal.behaviors.videoBackgroundModifier = {
    attach: (context) => {
      if (isCanvasEditor()) {
        return;
      }

      once('video-background-init', '[data-video-background]', context).forEach(
        (wrapper) => {
          const url = wrapper.dataset.videoBackgroundUrl || '';
          const mediaQuery = wrapper.dataset.backgroundMediaQuery || '';

          if (!url) {
            return;
          }

          let playerInstance = null;

          /**
           * Initialize the video background player.
           */
          const initPlayer = () => {
            removePlayer();

            const mediaContainer = document.createElement('div');
            mediaContainer.className = 'video-background__media';
            mediaContainer.setAttribute('data-video-background-media', '');
            wrapper.appendChild(mediaContainer);

            playerInstance = createPlayer(mediaContainer, url);
          };

          /**
           * Remove the video player and dispose VideoJS instance.
           */
          const removePlayer = () => {
            if (playerInstance) {
              playerInstance.dispose();
              playerInstance = null;
            }
            const existing = wrapper.querySelector(
              '[data-video-background-media]',
            );
            if (existing) {
              existing.remove();
            }
          };

          // Observe container size changes to keep video covering the area.
          const ro = new ResizeObserver(() => resizeAll(wrapper));
          ro.observe(wrapper);
          wrapper._videoBgRo = ro;

          // Handle media query.
          if (mediaQuery) {
            const mql = window.matchMedia(mediaQuery);

            const handleMediaChange = (e) => {
              if (e.matches) {
                initPlayer();
              } else {
                removePlayer();
              }
            };

            if (mql.matches) {
              initPlayer();
            }

            mql.addEventListener('change', handleMediaChange);

            wrapper._videoBgMql = mql;
            wrapper._videoBgHandler = handleMediaChange;
          } else {
            initPlayer();
          }

          wrapper._videoBgRemovePlayer = removePlayer;
        },
      );
    },

    detach: (context) => {
      once
        .remove('video-background-init', '[data-video-background]', context)
        .forEach((wrapper) => {
          if (wrapper._videoBgRo) {
            wrapper._videoBgRo.disconnect();
            delete wrapper._videoBgRo;
          }

          if (wrapper._videoBgMql && wrapper._videoBgHandler) {
            wrapper._videoBgMql.removeEventListener(
              'change',
              wrapper._videoBgHandler,
            );
            delete wrapper._videoBgMql;
            delete wrapper._videoBgHandler;
          }

          if (wrapper._videoBgRemovePlayer) {
            wrapper._videoBgRemovePlayer();
            delete wrapper._videoBgRemovePlayer;
          }
        });
    },
  };
})(Drupal, once);
