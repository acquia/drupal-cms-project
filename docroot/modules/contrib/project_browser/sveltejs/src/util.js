// eslint-disable-next-line import/prefer-default-export
export const numberFormatter = new Intl.NumberFormat(navigator.language);

const { bodyScrollLock, Drupal } = window;

export function openPopup (messageElement, title) {
  const isModuleDetail = messageElement.firstElementChild.classList.contains('pb-detail-modal');

  const popupModal = Drupal.dialog(messageElement, {
    title,
    classes: { 'ui-dialog': isModuleDetail ? 'project-browser-detail-modal' : 'project-browser-popup' },
    width: '90vw',
    close: () => {
      document.querySelector('.ui-dialog').remove();
      bodyScrollLock.clearBodyLocks()
    }
  });
  popupModal.showModal();
  const modalElement = document.querySelector('.project-browser-detail-modal');
  if (modalElement) {
    modalElement.focus();
  }
};
