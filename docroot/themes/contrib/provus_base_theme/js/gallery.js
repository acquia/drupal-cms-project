// photo gallery
(function ($, Drupal) {
  function runPhotoGallery(){
    var elements = document.getElementsByClassName('gallery-container');
    for (let item of elements) {
      lightGallery(item, {
        autoplayFirstVideo: false,
        pager: false,
        plugins: [
          lgThumbnail,
        ],
        allowMediaOverlap: true,
        toggleThumb: false,
        mobileSettings: {
          download: true,
          rotate: false,
        },
      })
    }
  }

  runPhotoGallery();
})(jQuery, Drupal);
