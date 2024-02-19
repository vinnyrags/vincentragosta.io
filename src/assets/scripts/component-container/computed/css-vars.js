export function cssVars($props) {
    let cssVars = {};
    if ($props.bgColor) {
        Object.assign(cssVars, {
            '--bg-color': $props.bgColor,
        });
    }
    if ($props.color) {
        Object.assign(cssVars, {
            '--color': $props.color,
        });
    }
    return cssVars;
}