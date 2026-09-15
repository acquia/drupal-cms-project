<script>
  import InstallationManager from './InstallListProcessor';
  import Loading from './Loading.svelte';
  import LoadingEllipsis from './Project/LoadingEllipsis.svelte';

  const { Drupal } = window;

  let isInstalling = false;
  let installListLength = 0;

  window.addEventListener('install-selection-changed', ({ detail }) => {
    installListLength = detail.length;
  });
  window.addEventListener('install-start', () => {
    isInstalling = true;
  });
  window.addEventListener('install-end', () => {
    isInstalling = false;
  });

  const handleClick = async () => {
    await InstallationManager.process();
  };

  function clearSelection() {
    InstallationManager.deselectAll();
  }
</script>

<div
  class="views-bulk-actions pb-install_bulk_actions"
  data-drupal-sticky-vbo={installListLength !== 0}
>
  <div
    class="views-bulk-actions__item
  views-bulk-actions__item--status"
  >
    {#if installListLength === 0}
      {Drupal.t('No projects selected')}
    {:else}
      {Drupal.formatPlural(
        installListLength,
        '1 project selected',
        '@count projects selected',
      )}
    {/if}
  </div>
  <button
    class="project__action_button install_button_common install_button button--small button button--primary"
    on:click={handleClick}
  >
    {#if isInstalling}
      <Loading />
      <LoadingEllipsis
        message={Drupal.formatPlural(
          installListLength,
          'Installing 1 project',
          'Installing @count projects',
        )}
      />
    {:else}
      {Drupal.t('Install selected projects')}
    {/if}
  </button>
  {#if installListLength !== 0 && !isInstalling}
    <button class="button clear_button" on:click={clearSelection}>
      {Drupal.t('Clear selection')}
    </button>
  {/if}
</div>
