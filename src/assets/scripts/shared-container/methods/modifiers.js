import {maybeAddBgModifiers} from "@/assets/scripts/shared-container/methods/modifiers/bg";
import {maybeAddColorModifiers} from "@/assets/scripts/shared-container/methods/modifiers/colors";
import {maybeAddHorizontalAlignmentModifiers} from "@/assets/scripts/shared-container/methods/modifiers/halignment";
import {maybeAddVerticalAlignmentModifiers} from "@/assets/scripts/shared-container/methods/modifiers/valignment";
import {maybeAddPaddingModifiers} from "@/assets/scripts/shared-container/methods/modifiers/padding";
import {maybeAddMarginBottomModifiers} from "@/assets/scripts/shared-container/methods/modifiers/margin-bottom";
import {maybeAddVerticalPaddingModifiers} from "@/assets/scripts/shared-container/methods/modifiers/vpad";
import {maybeAddHorizontalPaddingModifiers} from "@/assets/scripts/shared-container/methods/modifiers/hpad";


// TODO fold hpad, vpad, pad, margin into spacing file
// TODO fold alignment into alignment file
export function modifiers(props) {
    return [
        ...(maybeAddBgModifiers(props)),
        ...(maybeAddColorModifiers(props)),

        ...(maybeAddHorizontalAlignmentModifiers(props)),
        ...(maybeAddVerticalAlignmentModifiers(props)),

        ...(maybeAddPaddingModifiers(props)),
        ...(maybeAddVerticalPaddingModifiers(props)),
        ...(maybeAddMarginBottomModifiers(props)),
        ...(maybeAddHorizontalPaddingModifiers(props))
    ];
}