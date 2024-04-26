import {
  DefaultPropertyStructure,
  createPropertiesFromViewportAndMultipliers,
} from "@/components/directives/properties";

export const marginBottomProperties: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("bottomMargin");

export default {
  ...marginBottomProperties,
};
