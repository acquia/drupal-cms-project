/**
 * @file Map JS
 *
 * JS Behaviors for rendering a map using leaflet (https://leafletjs.com/).
 * Tile usage policy for OpenStreetMap: https://operations.osmfoundation.org/policies/tiles/
 */

Drupal.behaviors.EurekaMap = {
  attach(context) {
    const maps = once('EurekaMap', '.js-map', context);

    // If there are no maps in the context, finish the execution.
    if (!maps.length) return;

    maps.forEach((map) => {
      const lat = map.dataset.mapLat;
      const lon = map.dataset.mapLon;
      const zoom = map.dataset.mapZoom;
      const text = map.dataset.mapPopup;
      const icon = L.icon({
        iconUrl: '/themes/custom/eureka/images/icons/map-pin.svg',
        iconSize: [21, 30],
      });

      // Build the map.
      const leaflet = L.map(map).setView([lat, lon], zoom);

      // Add the OpenStreetMaps tile layer.
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 25,
        attribution:
          '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      }).addTo(leaflet);

      // Add the marker
      const marker = L.marker([lat, lon], { icon }).addTo(leaflet);
      if (text) marker.bindPopup(text);
    });
  },
};
