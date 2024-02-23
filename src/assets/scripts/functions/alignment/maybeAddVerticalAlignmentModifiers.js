export function maybeAddVerticalAlignmentModifiers(props) {
    let modifiers = [];

    if (props.valignStart) {
        modifiers.push('valign-start');
    }

    if (props.valignXsStart) {
        modifiers.push('valign-start-xs');
    }

    if (props.valignSmStart) {
        modifiers.push('valign-start-sm');
    }

    if (props.valignMdStart) {
        modifiers.push('valign-start-md');
    }

    if (props.valignLgStart) {
        modifiers.push('valign-start-lg');
    }

    if (props.valignXlStart) {
        modifiers.push('valign-start-xl');
    }

    if (props.valignCenter) {
        modifiers.push('valign-center');
    }

    if (props.valignXsCenter) {
        modifiers.push('valign-center-xs');
    }

    if (props.valignSmCenter) {
        modifiers.push('valign-center-sm');
    }

    if (props.valignMdCenter) {
        modifiers.push('valign-center-md');
    }

    if (props.valignLgCenter) {
        modifiers.push('valign-center-lg');
    }

    if (props.valignXlCenter) {
        modifiers.push('valign-center-xl');
    }

    if (props.valignEnd) {
        modifiers.push('valign-end');
    }

    if (props.valignXsEnd) {
        modifiers.push('valign-end-xs');
    }

    if (props.valignSmEnd) {
        modifiers.push('valign-end-sm');
    }

    if (props.valignMdEnd) {
        modifiers.push('valign-end-md');
    }

    if (props.valignLgEnd) {
        modifiers.push('valign-end-lg');
    }

    if (props.valignXlEnd) {
        modifiers.push('valign-end-xl');
    }

    if (props.absCenter) {
        modifiers.push('abs-center');
    }

    if (props.absCenterXs) {
        modifiers.push('abs-center-xs');
    }

    if (props.absCenterSm) {
        modifiers.push('abs-center-sm');
    }

    if (props.absCenterMd) {
        modifiers.push('abs-center-md');
    }

    if (props.absCenterLg) {
        modifiers.push('abs-center-lg');
    }

    if (props.absCenterXl) {
        modifiers.push('abs-center-xl');
    }

    return modifiers;
}