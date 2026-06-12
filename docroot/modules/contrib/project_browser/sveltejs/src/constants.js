export const BASE_URL = `${window.location.protocol}//${window.location.host}${drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix}`;
export const FULL_MODULE_PATH = `${window.location.protocol}//${window.location.host}${drupalSettings.path.baseUrl}${drupalSettings.project_browser.module_path}`;
export const DARK_COLOR_SCHEME =
  matchMedia('(forced-colors: active)').matches &&
  matchMedia('(prefers-color-scheme: dark)').matches;
export const PACKAGE_MANAGER = drupalSettings.project_browser.package_manager;
