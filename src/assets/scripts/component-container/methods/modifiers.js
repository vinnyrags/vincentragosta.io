import {maybeAddBgModifiers} from "@/assets/scripts/component-container/methods/modifiers/background";
import {maybeAddColorModifiers} from "@/assets/scripts/component-container/methods/modifiers/colors";
import {maybeAddAlignmentModifiers} from "@/assets/scripts/component-container/methods/modifiers/alignment";
import {maybeAddVerticalPaddingModifiers} from "@/assets/scripts/component-container/methods/modifiers/vpad";

export function componentContainerModifiers(props) {
    const bgModifiers = maybeAddBgModifiers(props);
    const colorModifiers = maybeAddColorModifiers(props);
    const alignmentModifiers = maybeAddAlignmentModifiers(props);
    const vpadModifiers = maybeAddVerticalPaddingModifiers(props);
    return [...bgModifiers, ...colorModifiers, ...alignmentModifiers, ...vpadModifiers];
}