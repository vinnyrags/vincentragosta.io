import {
  DefaultPropertyStructure,
  createPropertiesFromViewportAndMultipliers,
} from "@/components/directives/properties";

export const horizontalPaddingProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("hpad");

export const leftPaddingProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("lpad");

export const rightPaddingProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("rpad");

export default {
  ...horizontalPaddingProperties,
  ...leftPaddingProperties,
  ...rightPaddingProperties,
};
