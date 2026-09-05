<script context="module">
  import Search from './Search/Search.svelte';
  import ProcessInstallListButton from './ProcessInstallListButton.svelte';

  export { Search };
</script>

<script>
  import { getContext } from 'svelte';
  import { PACKAGE_MANAGER } from './constants';
  import InstallationManager from './InstallListProcessor';

  const { Drupal } = window;
  const pageSize = getContext('pageSize');
  const mediaQueryValues = getContext('mediaQueryValues');

  export let pageIndex = 0;
  export let toggleView;
  export let rows;
  export let labels = {
    empty: Drupal.t('No projects found'),
  };

  let mqMatches;
  mediaQueryValues.subscribe((mqlMap) => {
    mqMatches = mqlMap.get('(min-width: 1200px)');
  });

  $: isDesktop = mqMatches;
  $: filteredRows = rows;
  $: visibleRows = filteredRows
    ? filteredRows.slice(pageIndex, pageIndex + $pageSize)
    : [];
</script>

<!--Aligns Category filter and Grid cards side by side-->
<slot name="head" />
<div class="pb-layout">
  <aside class="pb-layout__aside">
    <slot name="left" />
  </aside>
  <div class="pb-layout__main">
    {#if visibleRows.length === 0}
      <div>{@html labels.empty}</div>
    {:else}
      <ul
        class="pb-projects-{isDesktop ? toggleView.toLowerCase() : 'list'}"
        aria-label={Drupal.t('Projects')}
      >
        <slot rows={visibleRows} />
      </ul>
      {#if PACKAGE_MANAGER && InstallationManager.multiple}
        <ProcessInstallListButton />
      {/if}
    {/if}
    <slot name="foot" />
  </div>
</div>

<slot name="bottom" />
