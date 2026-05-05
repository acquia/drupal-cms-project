/*!
 * Project Name: Morphos (morpht/morphos)
 * File: lib/videojs-vimeo-tech.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * VideoJS Vimeo Tech — registers a "Vimeo" tech for VideoJS 8+.
 *
 * Requires the Vimeo Player SDK (window.Vimeo.Player) and VideoJS (window.videojs)
 * to be loaded before this script.
 */

(function () {
  'use strict';

  if (typeof videojs === 'undefined') return;
  if (typeof Vimeo === 'undefined' || typeof Vimeo.Player === 'undefined')
    return;

  var Tech = videojs.getTech('Tech');
  var createTimeRange =
    (videojs.time && videojs.time.createTimeRanges) || videojs.createTimeRanges;

  class VimeoTech extends Tech {
    constructor(options, ready) {
      super(options, ready);

      this.setPoster(options.poster || '');

      var source = options.source;
      this.src_ = source ? source.src : '';

      var div = document.createElement('div');
      div.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;';
      this.el().appendChild(div);

      var isMuted = options.muted || false;

      var playerOptions = Object.assign(
        {
          url: this.src_,
          autoplay: options.autoplay || false,
          muted: isMuted,
          loop: options.loop || false,
          controls: false,
          responsive: true,
        },
        isMuted ? { volume: 0 } : {},
        options.vimeo || {},
      );

      this.vimeoPlayer_ = new Vimeo.Player(div, playerOptions);

      var self = this;
      this.vimeoPlayer_.ready().then(function () {
        if (isMuted) {
          self.vimeoPlayer_.setMuted(true);
          self.vimeoPlayer_.setVolume(0);
        }
        self.triggerReady();
      });

      this.ended_ = false;
      this.currentTime_ = 0;
      this.duration_ = 0;

      this.vimeoPlayer_.on('play', function () {
        if (isMuted) {
          self.vimeoPlayer_.setMuted(true);
          self.vimeoPlayer_.setVolume(0);
        }
        self.ended_ = false;
        self.trigger('play');
        self.trigger('playing');
      });

      this.vimeoPlayer_.on('pause', function () {
        self.trigger('pause');
      });

      this.vimeoPlayer_.on('ended', function () {
        self.ended_ = true;
        self.trigger('ended');
      });

      this.vimeoPlayer_.on('timeupdate', function (data) {
        self.currentTime_ = data.seconds;
        self.duration_ = data.duration;
        self.trigger('timeupdate');
      });

      this.vimeoPlayer_.on('loaded', function () {
        self.trigger('loadedmetadata');
        self.trigger('loadeddata');
        self.trigger('canplay');
      });

      this.vimeoPlayer_.on('error', function (err) {
        self.trigger('error', err);
      });
    }

    createEl() {
      var el = document.createElement('div');
      el.className = 'vjs-tech vjs-tech-vimeo';
      el.style.cssText =
        'position:absolute;inset:0;width:100%;height:100%;overflow:hidden;';
      return el;
    }

    dispose() {
      if (this.vimeoPlayer_) {
        this.vimeoPlayer_.destroy();
      }
      super.dispose();
    }

    play() {
      return this.vimeoPlayer_ ? this.vimeoPlayer_.play() : Promise.resolve();
    }

    pause() {
      return this.vimeoPlayer_ ? this.vimeoPlayer_.pause() : Promise.resolve();
    }

    paused() {
      return this.vimeoPlayer_
        ? this.vimeoPlayer_.getPaused()
        : Promise.resolve(true);
    }

    currentTime(seconds) {
      if (typeof seconds !== 'undefined' && this.vimeoPlayer_) {
        this.vimeoPlayer_.setCurrentTime(seconds);
      }
      return this.currentTime_ || 0;
    }

    duration() {
      return this.duration_ || 0;
    }

    volume(val) {
      if (typeof val !== 'undefined' && this.vimeoPlayer_) {
        this.vimeoPlayer_.setVolume(val);
      }
      return this.vimeoPlayer_
        ? this.vimeoPlayer_.getVolume()
        : Promise.resolve(1);
    }

    muted(muted) {
      if (typeof muted !== 'undefined' && this.vimeoPlayer_) {
        this.vimeoPlayer_.setMuted(muted);
      }
      return this.vimeoPlayer_
        ? this.vimeoPlayer_.getMuted()
        : Promise.resolve(false);
    }

    ended() {
      return this.ended_ || false;
    }

    seeking() {
      return false;
    }

    playbackRate() {
      return 1;
    }

    defaultPlaybackRate() {
      return 1;
    }

    buffered() {
      var dur = this.duration_ || 0;
      var cur = this.currentTime_ || 0;
      if (dur > 0) {
        return createTimeRange(0, cur);
      }
      return createTimeRange();
    }

    seekable() {
      var dur = this.duration_ || 0;
      if (dur > 0) {
        return createTimeRange(0, dur);
      }
      return createTimeRange();
    }

    played() {
      var cur = this.currentTime_ || 0;
      if (cur > 0) {
        return createTimeRange(0, cur);
      }
      return createTimeRange();
    }

    networkState() {
      return 2;
    }

    readyState() {
      return 4;
    }

    videoWidth() {
      return 1920;
    }

    videoHeight() {
      return 1080;
    }

    currentSrc() {
      return this.src_ || '';
    }

    src(source) {
      if (typeof source !== 'undefined') {
        this.src_ = source.src || source;
      }
      return this.src_ || '';
    }

    preload() {
      return 'auto';
    }

    error() {
      return null;
    }

    setAutoplay(val) {
      // Autoplay is configured at embed time; no runtime API.
    }

    setLoop(val) {
      if (this.vimeoPlayer_) {
        this.vimeoPlayer_.setLoop(val);
      }
    }

    setMuted(val) {
      if (this.vimeoPlayer_) {
        this.vimeoPlayer_.setMuted(val);
      }
    }

    setVolume(val) {
      if (this.vimeoPlayer_) {
        this.vimeoPlayer_.setVolume(val);
      }
    }

    setPlaybackRate(val) {
      // Vimeo does not support runtime playback rate changes.
    }

    setCrossOrigin() {}
    setPlaysinline() {}

    supportsFullScreen() {
      return true;
    }

    controls() {
      return false;
    }
  }

  VimeoTech.isSupported = function () {
    return true;
  };

  VimeoTech.canPlayType = function (type) {
    return type === 'video/vimeo' ? 'maybe' : '';
  };

  VimeoTech.canPlaySource = function (srcObj) {
    return VimeoTech.canPlayType(srcObj.type);
  };

  videojs.registerTech('Vimeo', VimeoTech);
})();
