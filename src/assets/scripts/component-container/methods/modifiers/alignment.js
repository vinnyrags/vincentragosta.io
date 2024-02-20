export function maybeAddAlignmentModifiers(props) {
    let modifiers = [];

    if (props.alignStart) {
        modifiers.push('align-start');
    }

    if (props.alignCenter) {
        modifiers.push('align-center');
    }

    if (props.alignEnd) {
        modifiers.push('align-end');
    }

    return modifiers;
}