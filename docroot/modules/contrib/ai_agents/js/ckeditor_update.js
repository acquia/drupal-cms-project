(function (Drupal, $) {

  /**
   * Add a method that works with Drupal's form ajax.
   */
  $.fn.agentUpdateCkEditor = function (newValue) {
    let id = $('#ai-ckeditor-response textarea').attr('id');
    let editors = Drupal.CKEditor5Instances.entries();
    for (let [key, editor] of editors) {
      if (editor.sourceElement.id == id) {
        editor.setData(newValue);
      }
    }
    return this;
  };

})(Drupal, jQuery);
