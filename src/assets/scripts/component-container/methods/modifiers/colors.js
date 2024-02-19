export function maybeAddColorModifiers(props) {
    let modifiers = [];

    if (props.primary) {
        modifiers.push('primary');
    }

    if (props.primaryDark) {
        modifiers.push('primary-dark');
    }

    if (props.primaryLight) {
        modifiers.push('primary-light');
    }

    if (props.secondary) {
        modifiers.push('secondary');
    }

    if (props.secondaryDark) {
        modifiers.push('secondary-dark');
    }

    if (props.secondaryLight) {
        modifiers.push('secondary-light');
    }

    if (props.tertiary) {
        modifiers.push('tertiary');
    }

    if (props.tertiaryDark) {
        modifiers.push('tertiary-dark');
    }

    if (props.tertiaryLight) {
        modifiers.push('tertiary-light');
    }

    if (props.grayDark) {
        modifiers.push('gray-dark');
    }

    if (props.gray) {
        modifiers.push('gray');
    }

    if (props.grayLight) {
        modifiers.push('gray-light');
    }

    if (props.offWhite) {
        modifiers.push('off-white');
    }

    if (props.white) {
        modifiers.push('white');
    }

    if (props.black) {
        modifiers.push('black');
    }

    return modifiers;
}