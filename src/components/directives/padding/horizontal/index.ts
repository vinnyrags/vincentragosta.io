import {
  DefaultPropertyStructure,
  createPropertiesFromViewportAndMultipliers,
} from "@/components/directives/properties";

export const horizontalPaddingProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("horizontalPadding");

export const leftPaddingProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("leftPadding");

export const rightPaddingProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("rightPadding");

export default {
  ...horizontalPaddingProperties,
  ...leftPaddingProperties,
  ...rightPaddingProperties,
};
