import { ComponentType, ComponentInstance } from "../../lib/component.js";
import currentlyInCanvasEditor from "../../lib/currentlyInCanvasEditor.js";

class Video extends ComponentInstance {
  #isPlaying = false;

  init() {
    if (currentlyInCanvasEditor()) {
      // In Canvas editor, don't initialize video controls
      return;
    }

    // Get references to the video element and controls
    this.videoElement = this.el.querySelector("video");
    this.playPauseButton = this.el.querySelector(".video-controls button");

    // If no controls are present, exit early
    if (!this.playPauseButton || !this.videoElement) {
      return;
    }

    // Get the icon elements for toggling
    this.playIcon = this.playPauseButton.querySelector(".video-icon-play");
    this.pauseIcon = this.playPauseButton.querySelector(".video-icon-pause");

    // Set initial state based on video autoplay attribute
    this.#isPlaying = this.videoElement.autoplay;

    // Update icon to match initial state
    this.updateIcon();

    // Add click listener to play/pause button
    this.playPauseButton.addEventListener("click", () => {
      this.togglePlayPause();
    });

    // Listen to video events to keep state in sync
    this.videoElement.addEventListener("play", () => {
      this.#isPlaying = true;
      this.updateIcon();
    });

    this.videoElement.addEventListener("pause", () => {
      this.#isPlaying = false;
      this.updateIcon();
    });

    // Handle video ended event
    this.videoElement.addEventListener("ended", () => {
      this.#isPlaying = false;
      this.updateIcon();
    });
  }

  togglePlayPause() {
    if (this.#isPlaying) {
      this.videoElement.pause();
    } else {
      this.videoElement.play();
    }
  }

  updateIcon() {
    // Toggle icon visibility based on playing state
    if (this.#isPlaying) {
      this.playIcon.classList.add("hidden");
      this.pauseIcon.classList.remove("hidden");
    } else {
      this.playIcon.classList.remove("hidden");
      this.pauseIcon.classList.add("hidden");
    }

    // Update aria-label for accessibility
    const label = this.#isPlaying ? "Pause video" : "Play video";
    this.playPauseButton.setAttribute("aria-label", label);
  }

  get isPlaying() {
    return this.#isPlaying;
  }
}

new ComponentType(Video, "video", ".video-component");
