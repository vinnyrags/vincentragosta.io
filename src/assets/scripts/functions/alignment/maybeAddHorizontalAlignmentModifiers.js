export function maybeAddHorizontalAlignmentModifiers(props) {
    let modifiers = [];

    if (props.alignStart) {
        modifiers.push('align-start');
    }

    if (props.alignXsStart) {
        modifiers.push('align-start-xs');
    }

    if (props.alignSmStart) {
        modifiers.push('align-start-sm');
    }

    if (props.alignMdStart) {
        modifiers.push('align-start-md');
    }

    if (props.alignLgStart) {
        modifiers.push('align-start-lg');
    }

    if (props.alignXlStart) {
        modifiers.push('align-start-xl');
    }

    if (props.alignCenter) {
        modifiers.push('align-center');
    }

    if (props.alignXsCenter) {
        modifiers.push('align-center-xs');
    }

    if (props.alignSmCenter) {
        modifiers.push('align-center-sm');
    }

    if (props.alignMdCenter) {
        modifiers.push('align-center-md');
    }

    if (props.alignLgCenter) {
        modifiers.push('align-center-lg');
    }

    if (props.alignXlCenter) {
        modifiers.push('align-center-xl');
    }

    if (props.alignEnd) {
        modifiers.push('align-end');
    }

    if (props.alignXsEnd) {
        modifiers.push('align-end-xs');
    }

    if (props.alignSmEnd) {
        modifiers.push('align-end-sm');
    }

    if (props.alignMdEnd) {
        modifiers.push('align-end-md');
    }

    if (props.alignLgEnd) {
        modifiers.push('align-end-lg');
    }

    if (props.alignXlEnd) {
        modifiers.push('align-end-xl');
    }

    return modifiers;
}