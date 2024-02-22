import {maybeAddBgColorModifiers} from '@/assets/scripts/shared-container/methods/modifiers/bg-colors';
import {hasBg} from "@/assets/scripts/shared-container/methods/has-bg";

export function maybeAddBgModifiers(props) {
    let modifiers = [];

    if (hasBg(props)) {
        modifiers.push('has-bg');
    }

    const bgColors = maybeAddBgColorModifiers(props);

    return [...modifiers, ...bgColors];
}