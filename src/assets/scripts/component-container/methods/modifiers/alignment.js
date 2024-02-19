export function maybeAddAlignmentModifiers(props) {
    let modifiers = [];

    if (props.start) {
        modifiers.push('start');
    }

    if (props.center) {
        modifiers.push('center');
    }

    if (props.end) {
        modifiers.push('end');
    }

    return modifiers;
}