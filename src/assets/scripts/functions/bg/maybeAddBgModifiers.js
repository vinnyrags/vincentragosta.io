import {maybeAddBgColorModifiers} from '@/assets/scripts/functions/bg/maybeAddBgColorModifiers';
import {hasBg} from "@/assets/scripts/functions/bg/hasBg";

export function maybeAddBgModifiers(props) {
    let modifiers = [];

    if (hasBg(props)) {
        modifiers.push('has-bg');
    }

    const bgColors = maybeAddBgColorModifiers(props);

    return [...modifiers, ...bgColors];
}