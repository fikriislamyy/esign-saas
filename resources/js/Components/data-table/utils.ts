import { isRef } from "vue";

export function valueUpdater(updaterOrValue, ref) {
    if (typeof updaterOrValue === "function") {
        ref.value = updaterOrValue(ref.value);
    } else {
        ref.value = updaterOrValue;
    }
}
