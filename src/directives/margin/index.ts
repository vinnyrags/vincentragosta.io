import {
  DefaultPropertyStructure,
  createPropertiesFromViewportAndMultipliers,
} from "@/directives/properties";

export const marginBottomProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("bottomMargin");

export default {
  ...marginBottomProperties,
};
