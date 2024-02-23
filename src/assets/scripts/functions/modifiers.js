import {maybeAddBgModifiers} from "@/assets/scripts/functions/bg/maybeAddBgModifiers";
import {maybeAddColorModifiers} from "@/assets/scripts/functions/maybeAddColorModifiers";
import {maybeAddHorizontalAlignmentModifiers} from "@/assets/scripts/functions/alignment/maybeAddHorizontalAlignmentModifiers";
import {maybeAddVerticalAlignmentModifiers} from "@/assets/scripts/functions/alignment/maybeAddVerticalAlignmentModifiers";
import {maybeAddPaddingModifiers} from "@/assets/scripts/functions/spacing/maybeAddPaddingModifiers";
import {maybeAddMarginBottomModifiers} from "@/assets/scripts/functions/spacing/maybeAddMarginBottomModifiers";
import {maybeAddVerticalPaddingModifiers} from "@/assets/scripts/functions/spacing/maybeAddVerticalPaddingModifiers";
import {maybeAddHorizontalPaddingModifiers} from "@/assets/scripts/functions/spacing/maybeAddHorizontalPaddingModifiers";

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