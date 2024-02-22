import {maybeAddBgModifiers} from "@/assets/scripts/shared-container/methods/modifiers/bg";
import {maybeAddColorModifiers} from "@/assets/scripts/shared-container/methods/modifiers/colors";
import {maybeAddAlignmentModifiers} from "@/assets/scripts/shared-container/methods/modifiers/alignment";
import {maybeAddPaddingModifiers} from "@/assets/scripts/shared-container/methods/modifiers/padding";
import {maybeAddVerticalPaddingModifiers} from "@/assets/scripts/shared-container/methods/modifiers/vpad";
import {maybeAddHorizontalPaddingModifiers} from "@/assets/scripts/shared-container/methods/modifiers/hpad";

export function modifiers(props) {
    return [
        ...(maybeAddBgModifiers(props)),
        ...(maybeAddColorModifiers(props)),
        ...(maybeAddAlignmentModifiers(props)),
        ...(maybeAddPaddingModifiers(props)),
        ...(maybeAddVerticalPaddingModifiers(props)),
        ...(maybeAddHorizontalPaddingModifiers(props))
    ];
}