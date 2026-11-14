import ContextPad from "diagram-js/lib/features/context-pad/ContextPad";

class CustomContextPad extends ContextPad {
    getEntries(target) {
        let entries = super.getEntries(target);
        if (Drupal.bpmn_io !== undefined) {
            Drupal.bpmn_io.alterContextPadEntries(target, entries);
        }
        return entries;
    }
}

export default {
    contextPad: [ 'type', CustomContextPad ]
};
