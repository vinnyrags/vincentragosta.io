import {
  DefaultPropertyStructure,
  createPropertiesFromViewportAndMultipliers,
} from "@/directives/properties";
import horizontalPaddingProperties from "@/directives/padding/horizontal";
import verticalPaddingProperties from "@/directives/padding/vertical";

const paddingDefaults: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("padding");
export default {
  ...paddingDefaults,
  ...horizontalPaddingProperties,
  ...verticalPaddingProperties,
};
