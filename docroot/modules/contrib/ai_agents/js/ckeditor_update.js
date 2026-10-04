(function (Drupal, $) {
  /**
   * Add a method that works with Drupal's form ajax.
   */
  $.fn.agentUpdateCkEditor = function (newValue) {
    const id = $('#ai-ckeditor-response textarea').attr('id');
    Drupal.CKEditor5Instances.forEach((editor) => {
      if (editor.sourceElement.id === id) {
        editor.setData(newValue);
      }
    });
    return this;
  };
})(Drupal, jQuery);
