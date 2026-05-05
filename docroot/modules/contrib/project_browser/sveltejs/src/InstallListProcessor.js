import { openPopup } from './util';
import { BASE_URL } from './constants';

const {
  Drupal,
  drupalSettings: {
    project_browser: { currentPath, maxSelections },
  }
} = window;

const selections = new Set();

function onSelectionChanged () {
  const event = new CustomEvent('install-selection-changed', {
    detail: Array.from(selections),
  });
  window.dispatchEvent(event);
}

function deselectAll() {
  selections.clear();
  onSelectionChanged();
}

async function handleError (errorResponse) {
  // The error can take on many shapes, so it should be normalized.
  let err = '';
  if (typeof errorResponse === 'string') {
    err = errorResponse;
  } else {
    err = await errorResponse.text();
  }
  try {
    // See if the error string can be parsed as JSON. If not, the block
    // is exited before the `err` string is overwritten.
    const parsed = JSON.parse(err);
    err = parsed;
  } catch {
    // The catch behavior is established before the try block.
  }

  const errorMessage = err.message || err;

  // The popup function expects an element, so a div containing the error
  // message is created here for it to display in a modal.
  const div = document.createElement('div');

  const currentUrl =
    window.location.pathname + window.location.search + window.location.hash;

  if (err.unlock_url) {
    try {
      const unlockUrl = new URL(err.unlock_url, BASE_URL);
      unlockUrl.searchParams.set('destination', currentUrl);

      const updatedMessage = errorMessage.replace(
        '[+ unlock link]',
        `<a href="${
          unlockUrl.pathname + unlockUrl.search
        }" id="unlock-link">${Drupal.t('unlock link')}</a>`,
      );

      div.innerHTML += `<p>${updatedMessage}</p>`;
    } catch {
      div.innerHTML += `<p>${errorMessage}</p>`;
    }
  } else {
    div.innerHTML += `<p>${errorMessage}</p>`;
  }

  openPopup(div, Drupal.t('Error while installing package(s)'));
}

/**
 * Actives already-downloaded projects.
 *
 * @param {string[]} projectIds
 *   An array of project IDs to activate.
 *
 * @return {Promise<void>}
 *   A promise that resolves when the project is activated.
 */
async function activateProject (projectIds) {
  // Remove any existing errors for each project individually.
  const messenger = new Drupal.Message();
  const messageId = 'activation_error';
  if (messenger.select(messageId)) {
    messenger.remove(messageId);
  }

  await new Drupal.Ajax(
    null,
    document.createElement('div'),
    {
      url: `${BASE_URL}admin/modules/project_browser/activate?projects=${projectIds.join(',')}&destination=${window.location.pathname}`,
    },
  ).execute();
}

/**
 * Performs the requests necessary to download and activate project via Package Manager.
 *
 * @param {string[]} projectIds
 *   An array of project IDs to download and activate.
 *
 * @return {Promise<void>}
 *   Returns a promise that resolves once the download and activation process is complete.
 */
async function doRequests (projectIds) {
  const beginInstallUrl = `${BASE_URL}admin/modules/project_browser/install-begin?redirect=${currentPath}`;
  const beginInstallResponse = await fetch(beginInstallUrl);
  if (!beginInstallResponse.ok) {
    await handleError(beginInstallResponse);
  } else {
    const { sandboxId } = await beginInstallResponse.json();

    // The process of adding a module is separated into four stages, each
    // with their own endpoint. When one stage completes, the next one is
    // requested.
    const installSteps = [
      {
        url: `${BASE_URL}admin/modules/project_browser/install-require/${sandboxId}`,
        method: 'POST',
      },
      {
        url: `${BASE_URL}admin/modules/project_browser/install-apply/${sandboxId}`,
        method: 'GET',
      },
      {
        url: `${BASE_URL}admin/modules/project_browser/install-post_apply/${sandboxId}`,
        method: 'GET',
      },
      {
        url: `${BASE_URL}admin/modules/project_browser/install-destroy/${sandboxId}`,
        method: 'GET',
      },
    ];

    // eslint-disable-next-line no-restricted-syntax,guard-for-in
    for (const step of installSteps) {
      const options = {
        method: step.method,
      };

      // Additional options need to be added when the request method is POST.
      // This is specifically required for the `install-require` step.
      if (step.method === 'POST') {
        options.headers = {
          'Content-Type': 'application/json',
        };

        // Set the request body to include the project(s) id as an array.
        options.body = JSON.stringify(projectIds);
      }
      // eslint-disable-next-line no-await-in-loop
      const stepResponse = await fetch(step.url, options);
      if (!stepResponse.ok) {
        // eslint-disable-next-line no-await-in-loop
        const errorMessage = await stepResponse.text();
        // eslint-disable-next-line no-console
        console.warn(
          `failed request to ${step.url}: ${errorMessage}`,
          stepResponse,
        );
        // eslint-disable-next-line no-await-in-loop
        await handleError(errorMessage);
        return;
      }
    }
    await activateProject(projectIds);
  }
}

async function processInstallList () {
  const projectsToActivate = [];
  const projectsToDownloadAndActivate = [];
  if (selections.size === 0) {
    const messageElement = document.querySelector('[data-drupal-message-id="install_message"]');

    if (!messageElement) {
      // If the message does not exist, create a new one.
      new Drupal.Message().add(Drupal.t('No projects selected'), { type: 'error', id: 'install_message' });
    } else if (messageElement.classList.contains('visually-hidden')) {
      // If the message exists but is visually hidden, remove the class and reset opacity.
      messageElement.classList.remove('visually-hidden');
      messageElement.style.opacity = 1;
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  Array.from(selections).forEach(({ status, id }) => {
    if (status === 'absent') {
      projectsToDownloadAndActivate.push(id);
    } else if (status === 'present') {
      projectsToActivate.push(id);
    }
  });

  window.dispatchEvent(new CustomEvent('install-start'));
  if (projectsToActivate.length > 0) {
    await activateProject(projectsToActivate);
  }
  if (projectsToDownloadAndActivate.length > 0) {
    await doRequests(projectsToDownloadAndActivate);
  }
  window.dispatchEvent(new CustomEvent('install-end'));
  deselectAll();
}

export default Object.freeze({

  maxSelections,

  multiple: maxSelections === null || maxSelections > 1,

  add (project) {
    if (!selections.has(project)) {
      selections.add(project);
      onSelectionChanged();
    }
  },

  deselectAll,

  isFull () {
    return selections.size === this.maxSelections;
  },

  remove (project) {
    if (selections.has(project)) {
      selections.delete(project);
      onSelectionChanged();
    }
  },

  async process () {
    await processInstallList();
  },

});
