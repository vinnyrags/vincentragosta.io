export function maybeAddBgColorModifiers(props) {
    let modifiers = [];

    if (props.bgPrimary) {
        modifiers.push('bg-primary');
    }

    if (props.bgPrimaryDark) {
        modifiers.push('bg-primary-dark');
    }

    if (props.bgPrimaryLight) {
        modifiers.push('bg-primary-light');
    }

    if (props.bgSecondary) {
        modifiers.push('bg-secondary');
    }

    if (props.bgSecondaryDark) {
        modifiers.push('bg-secondary-dark');
    }

    if (props.bgSecondaryLight) {
        modifiers.push('bg-secondary-light');
    }

    if (props.bgTertiary) {
        modifiers.push('bg-tertiary');
    }

    if (props.bgTertiaryDark) {
        modifiers.push('bg-tertiary-dark');
    }

    if (props.bgTertiaryLight) {
        modifiers.push('bg-tertiary-light');
    }

    if (props.bgGrayDark) {
        modifiers.push('bg-gray-dark');
    }

    if (props.bgGray) {
        modifiers.push('bg-gray');
    }

    if (props.bgGrayLight) {
        modifiers.push('bg-gray-light');
    }

    if (props.bgOffWhite) {
        modifiers.push('bg-off-white');
    }

    if (props.bgWhite) {
        modifiers.push('bg-white');
    }

    if (props.bgBlack) {
        modifiers.push('bg-black');
    }

    return modifiers;
}