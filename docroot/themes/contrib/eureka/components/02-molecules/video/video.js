/**
 * Video JS
 * Controls the video play / pause functionality
 *
 */
Drupal.behaviors.video = {
  attach(context) {
    const videos = context.querySelectorAll('.video');

    if (!videos.length) {
      return;
    }

    const localVideo = context.querySelectorAll('.js-local-video');
    const YTvideos = context.querySelectorAll('.js-ytvideo');
    const vimeoVideos = context.querySelectorAll('.js-vimeovideo');

    if (vimeoVideos.length) {
      // Loads vimeo API.
      Drupal.behaviors.video.loadVimeoAPI();

      vimeoVideos.forEach((video) => {
        // If there is a play button, get the vimeo specific play button and control video specific play/pause.
        const videoPlayButton =
          video.parentNode.parentNode.querySelector('.button');
        videoPlayButton.addEventListener('click', () => {
          Drupal.behaviors.video.playPause(
            videoPlayButton,
            new Vimeo.Player(video.id),
          );
        });
      });
    }

    // Local video play & pause buttons
    if (localVideo) {
      localVideo.forEach((video) => {
        // If there is a play button, get the local video specific play button and control video specific play/pause.
        const videoPlayButton =
          video.parentNode.parentNode.querySelector('.button');
        videoPlayButton.addEventListener('click', () => {
          Drupal.behaviors.video.playPause(videoPlayButton, video);
        });
      });
    }

    // Youtube video
    if (YTvideos.length) {
      // Empty object for storing the video objects.
      drupalSettings.videoPlayers = drupalSettings.videoPlayers || [];

      Drupal.behaviors.video.loadYTAPI();

      // Load the videos whenever the API is loaded
      window.addEventListener('YoutubeReady', () => {
        Drupal.behaviors.video.loadVideos(YTvideos);
      });
    }
  },

  /**
   * Add play / pause behaviour.
   * @param {HTMLElement} button - The play / pause toggle
   * @param {HTMLElement} video - The video element
   */
  playPause(button, video) {
    if (button.classList.contains('playing')) {
      video.pause();
      button.classList.remove('playing');
    } else {
      video.play();
      button.classList.add('playing');
    }
  },

  /**
   * Loads the YouTube API
   */
  loadYTAPI() {
    const script = document.createElement('script');
    script.src = '//www.youtube.com/player_api';
    script.defer = true;
    script.async = true;
    const before = document.getElementsByTagName('script')[0];
    before.parentNode.insertBefore(script, before);
    // Fire an event when API is loaded.
    script.onload = () => {
      const YTEvent = new Event('YoutubeReady');
      window.dispatchEvent(YTEvent);
    };
  },

  /**
   * Loads the Vimeo API
   */
  loadVimeoAPI() {
    const script = document.createElement('script');
    script.src = 'https://player.vimeo.com/api/player.js';
    script.defer = true;
    script.async = true;
    const before = document.getElementsByTagName('script')[0];
    before.parentNode.insertBefore(script, before);
    // Fire an event when API is loaded.
    script.onload = () => {
      const VimeoEvent = new Event('VimeoReady');
      window.dispatchEvent(VimeoEvent);
    };
  },

  /**
   * Loads the video players
   * @param {NodeList} videos - The video list
   */
  loadVideos(videos) {
    window.YT.ready(() => {
      videos.forEach((video, index) => {
        // Save actual video ID.
        const videoID = video.id;
        // Override video ID with a unique ID in case a video appears several times.
        video.id = `id-${video.id}-${index}`;
        // Start the youtube player.
        const player = new YT.Player(video.id, {
          videoId: videoID,
          playerVars: {
            controls: 0,
            playsinline: 1,
            modestbranding: 1,
            rel: 0,
          },
          events: {
            // Function that controls play & pause buttons for youtube.
            onReady: () => {
              const videoPlay = document.querySelectorAll(
                '.video__controls__button',
              );

              // @params button and image.
              function YTplayPause(button) {
                if (button.classList.contains(videoID)) {
                  if (player.getPlayerState() === 1) {
                    player.pauseVideo();
                    button.classList.remove('playing');
                  } else {
                    player.playVideo();
                    button.classList.add('playing');
                  }
                }
              }

              if (videoPlay) {
                videoPlay.forEach((button) => {
                  button.addEventListener('click', () => {
                    YTplayPause(button);
                  });
                });
              }
            },
          },
        });

        // Store player in the players array.
        drupalSettings.videoPlayers.push(player);
        // Add lazy loading to the iframe.
        const iframe = document.getElementById(video.id);
        iframe.setAttribute('loading', 'lazy');
      });
    });
  },
};
