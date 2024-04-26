import colorProperties from "@/components/directives/colors";
import backgroundProperties from "@/components/directives/background";
import borderProperties from "@/components/directives/border";
import paddingProperties from "@/components/directives/padding";
import marginProperties from "@/components/directives/margin";
import alignmentProperties from "@/components/directives/alignment";
import { viewports } from "@/components/directives/properties/fixtures";
import { DefaultPropertyStructure } from "@/components/directives/properties";

const viewportProperties: DefaultPropertyStructure = viewports.reduce(
  (accumulator, viewport) => {
    accumulator[viewport.toLowerCase()] = "";
    return accumulator;
  },
  {} as DefaultPropertyStructure
);

export const handleViewportProps = (props: DefaultPropertyStructure) => {
  const mods: Record<string, boolean> = {};
  viewports
    .map((viewport) => viewport.toLowerCase())
    .forEach((viewport) => {
      if (props[viewport]) {
        mods[`column--${viewport}-${props[viewport]}`] = true;
      }
    });
  return mods;
};

export default {
  ...viewportProperties,
  ...colorProperties,
  ...backgroundProperties,
  ...borderProperties,
  ...paddingProperties,
  ...marginProperties,
  ...alignmentProperties,
};
