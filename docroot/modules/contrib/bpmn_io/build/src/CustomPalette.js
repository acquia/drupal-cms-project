import Palette from 'diagram-js/lib/features/palette/Palette';

class CustomPalette extends Palette {
    _toggleState(state) {
        return super._toggleState({ ...state, twoColumn: false });
    }
    getEntries() {
        let entries = super.getEntries();
        if (Drupal.bpmn_io !== undefined) {
            Drupal.bpmn_io.alterPaletteEntries(entries);
        }
        return entries;
    }
}

export default {
    __init__: [ 'palette' ],
    palette: [ 'type', CustomPalette ]
};
