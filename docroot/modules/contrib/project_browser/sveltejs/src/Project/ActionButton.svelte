<script>
  import { PACKAGE_MANAGER } from '../constants';
  import { openPopup } from '../util';
  import ProjectStatusIndicator from './ProjectStatusIndicator.svelte';
  import LoadingEllipsis from './LoadingEllipsis.svelte';
  import DropButton from './DropButton.svelte';
  import ProjectButtonBase from './ProjectButtonBase.svelte';
  import InstallationManager from '../InstallListProcessor';
  import ProjectIcon from './ProjectIcon.svelte';

  // eslint-disable-next-line import/no-mutable-exports,import/prefer-default-export
  export let project;
  let installListFull;
  let isInInstallList = false;
  let isInstalling = false;

  window.addEventListener('install-selection-changed', ({ detail }) => {
    isInInstallList = detail.includes(project);
    installListFull = InstallationManager.isFull();
  });
  window.addEventListener('install-start', () => {
    isInstalling = true;
  });
  window.addEventListener('install-end', () => {
    isInstalling = false;
  });

  const { once, Drupal } = window;

  const onClick = async () => {
    if (InstallationManager.multiple) {
      if (isInInstallList) {
        InstallationManager.remove(project);
      } else {
        InstallationManager.add(project);
      }
    } else {
      InstallationManager.add(project);
      await InstallationManager.process();
    }
  };

  /**
   * Finds [data-copy-command] buttons and adds copy functionality to them.
   */
  function enableCopyButtons() {
    setTimeout(() => {
      once('copyButton', '[data-copy-command]').forEach((copyButton) => {
        // If clipboard is not supported (likely due to non-https), then hide the
        // button and do not bother with event listeners
        if (!navigator.clipboard) {
          // copyButton.hidden = true;
          // return;
        }
        copyButton.addEventListener('click', (e) => {
          // The copy button must be contained in a div
          const container = e.target.closest('div');
          // The only <textarea> within the parent div should have its value set
          // to the command that should be copied.
          const input = container.querySelector('textarea');

          // Make the input value the selected text
          input.select();
          input.setSelectionRange(0, 99999);
          navigator.clipboard.writeText(input.value);
          Drupal.announce(Drupal.t('Copied text to clipboard'));

          // Create a "receipt" that will visually show the text has been copied.
          const receipt = document.createElement('div');
          receipt.textContent = Drupal.t('Copied');
          receipt.classList.add('copied-action');
          receipt.style.opacity = '1';
          input.insertAdjacentElement('afterend', receipt);
          // eslint-disable-next-line max-nested-callbacks
          setTimeout(() => {
            // Remove the receipt after 1 second.
            receipt.remove();
          }, 1000);
        });
      });
    });
  }

  function getCommandsPopupMessage() {
    const div = document.createElement('div');
    div.innerHTML = `${project.commands}<style>.action-link { margin: 0 2px; padding: 0.25rem 0.25rem; border: 1px solid; }</style>`;
    enableCopyButtons();
    return div;
  }
</script>

<div class="pb-actions">
  {#if !project.is_compatible}
    <ProjectStatusIndicator {project} statusText={Drupal.t('Not compatible')} />
  {:else if project.status === 'active'}
    <ProjectStatusIndicator {project} statusText={Drupal.t('Installed')}>
      <ProjectIcon type="installed" />
    </ProjectStatusIndicator>
    {#if project.tasks.length > 0}
      <DropButton tasks={project.tasks} />
    {/if}
  {:else}
    <span>
      {#if PACKAGE_MANAGER}
        <ProjectButtonBase
          disabled={isInstalling || (!isInInstallList && installListFull)}
          click={onClick}
        >
          {#if isInstalling && isInInstallList}
            <LoadingEllipsis />
          {:else if InstallationManager.multiple}
            {#if isInInstallList}
              {@html Drupal.t(
                'Deselect <span class="visually-hidden">@title</span>',
                { '@title': project.title },
              )}
            {:else}
              {@html Drupal.t(
                'Select <span class="visually-hidden">@title</span>',
                { '@title': project.title },
              )}
            {/if}
          {:else}
            {@html Drupal.t(
              'Install <span class="visually-hidden">@title</span>',
              { '@title': project.title },
            )}
          {/if}
        </ProjectButtonBase>
      {:else if project.commands}
        {#if project.commands.match(/^https?:\/\//)}
          <a href={project.commands} target="_blank" rel="noreferrer"
            ><ProjectButtonBase>{Drupal.t('Install')}</ProjectButtonBase></a
          >
        {:else}
          <ProjectButtonBase
            aria-haspopup="dialog"
            click={() => openPopup(getCommandsPopupMessage(), project.title)}
          >
            {@html Drupal.t(
              'View Commands <span class="visually-hidden">for @title</span>',
              { '@title': project.title },
            )}
          </ProjectButtonBase>
        {/if}
      {/if}
    </span>
  {/if}
</div>
