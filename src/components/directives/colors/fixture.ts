export const themeColorFixture: string[] = [
  "primary",
  "primaryDark",
  "primaryLight",
  "secondary",
  "secondaryDark",
  "secondaryLight",
  "tertiary",
  "tertiaryDark",
  "tertiaryLight",
  "grayDark",
  "gray",
  "grayLight",
  "offWhite",
  "white",
  "black",
];

const transformColors = (colors: string[]): string[] => {
  return colors.map(
    (color) => `color${color.charAt(0).toUpperCase()}${color.slice(1)}`
  );
};

// Generate colorFixture array using the transformed colors
export const colorFixture: string[] = transformColors(themeColorFixture);
