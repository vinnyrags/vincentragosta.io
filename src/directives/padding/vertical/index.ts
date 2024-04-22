import {
  DefaultPropertyStructure,
  createPropertiesFromViewportAndMultipliers,
} from "@/directives/properties";

export const verticalPaddingProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("verticalPadding");

export const topPaddingProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("topPadding");

export const bottomPaddingProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("bottomPadding");

export default {
  ...verticalPaddingProperties,
  ...topPaddingProperties,
  ...bottomPaddingProperties,
};
