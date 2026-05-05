import { BASE_URL } from './constants';

// This is the single source of truth for all projects that have been loaded from
// the backend. It is keyed by fully qualified project ID, and shared by all
// QueryManager instances.
const cache = {};

// All instances of QueryManager.
const instances = new Set();

function getFromCache (projects) {
  return Array.from(projects)
    .map(id => cache[id])
    .filter(item => typeof item === 'object');
}

async function doLoad (instance) {
  // We're going to query the backend, so reset the instance's internal state.
  instance.list.clear();
  instance.count = 0;

  const response = await fetch(`${BASE_URL}project-browser/data/project?${instance.queryString}`);
  if (!response.ok) {
    return;
  }

  const { error, list, totalResults } = await response.json();
  if (error && error.length) {
    new Drupal.Message().add(error, { type: 'error' });
  }

  // Store the IDs of the projects we've just fetched.
  list.forEach(({ id }) => {
    instance.list.add(id);
  });
  instance.count = instance.paginated ? totalResults : list.length;

  // Update the static cache with the data we've received.
  list.forEach((project) => {
    cache[project.id] = project;
  });

  // Notify the subscribers.
  Array.from(instance.subscribers).forEach((callback) => {
    callback(list);
  });
}

// Allow cached projects to be updated via AJAX.
Drupal.AjaxCommands.prototype.refresh_projects = () => {
  Array.from(instances).forEach(doLoad);
};

/**
 * Handles fetching and temporarily caching project data from the backend.
 *
 * This implements a volatile, centralized caching mechanism, ensuring that
 * all instances of the Project Browser on a single page share a consistent
 * source of truth for project data.
 *
 * The cache lives in memory and is reset upon page reload.
 */
export default class {
  constructor (paginated) {
    // If pagination is disabled, then the number of results returned from the
    // first page is, effectively, the total number of results.
    this.paginated = paginated;
    // A list of project IDs that were returned by the last query. These are
    // only the project IDs; the most current data for each of them is stored
    // in the static cache.
    this.list = new Set();
    // The subscribers that are listening for changes in the projects.
    this.subscribers = new Set();
    // The total (i.e., not paginated) number of results returned by the most
    // recent query.
    this.count = 0;
    // The most recent query string that this instance sent to the backend.
    this.queryString = null;

    instances.add(this);
  }

  subscribe (callback) {
    this.subscribers.add(callback);

    // The store contract requires us to immediately call the new subscriber.
    callback(
      Array.from(this.list).map(id => cache[id])
    );

    // The store contract requires us to return an unsubscribe function.
    return () => {
      this.subscribers.delete(callback);
    };
  }

  /**
   * Fetch projects from the backend and store them in memory.
   *
   * @param {Object} filters - The filters to apply in the request.
   * @param {Number} page - The current page number.
   * @param {Number} pageSize - Number of items per page.
   * @param {String} sort - Sorting method.
   * @param {String} source - Data source.
   * @return {Promise<Object>} - The list of project objects.
   */
  async load(filters, page, pageSize, sort, source) {
    // Encode the current filter values as URL parameters.
    const searchParams = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (typeof value === 'boolean') {
        value = Number(value).toString();
      }
      searchParams.set(key, value);
    });
    searchParams.set('page', page);
    searchParams.set('limit', pageSize);
    searchParams.set('sort', sort);
    searchParams.set('source', source);

    const queryString = searchParams.toString();

    if (this.queryString !== queryString) {
      this.queryString = queryString;
      await doLoad(this);
    }
  }
}
