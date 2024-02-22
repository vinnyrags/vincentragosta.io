export function maybeAddVerticalAlignmentModifiers(props) {
    let modifiers = [];

    if (props.valignStart) {
        modifiers.push('valign-start');
    }

    if (props.valignCenter) {
        modifiers.push('valign-center');
    }

    if (props.valignEnd) {
        modifiers.push('valign-end');
    }

    if (props.absCenter) {
        modifiers.push('abs-center');
    }

    return modifiers;
}