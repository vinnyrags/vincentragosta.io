import {maybeAddBgModifiers} from "@/assets/scripts/component-container/methods/modifiers/bg";
import {maybeAddColorModifiers} from "@/assets/scripts/component-container/methods/modifiers/colors";
import {maybeAddAlignmentModifiers} from "@/assets/scripts/component-container/methods/modifiers/alignment";
import {maybeAddPaddingModifiers} from "@/assets/scripts/component-container/methods/modifiers/padding";
import {maybeAddVerticalPaddingModifiers} from "@/assets/scripts/component-container/methods/modifiers/vpad";
import {maybeAddHorizontalPaddingModifiers} from "@/assets/scripts/component-container/methods/modifiers/hpad";

export function componentContainerModifiers(props) {
    return [
        ...(maybeAddBgModifiers(props)),
        ...(maybeAddColorModifiers(props)),
        ...(maybeAddAlignmentModifiers(props)),
        ...(maybeAddPaddingModifiers(props)),
        ...(maybeAddVerticalPaddingModifiers(props)),
        ...(maybeAddHorizontalPaddingModifiers(props))
    ];
}