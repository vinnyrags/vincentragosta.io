import {
  DefaultPropertyStructure,
  createPropertiesFromViewportAndMultipliers,
} from "@/components/directives/properties";
import horizontalPaddingProperties from "@/components/directives/padding/horizontal";
import verticalPaddingProperties from "@/components/directives/padding/vertical";

const paddingDefaults: DefaultPropertyStructure =
  createPropertiesFromViewportAndMultipliers("pad");
export default {
  ...paddingDefaults,
  ...horizontalPaddingProperties,
  ...verticalPaddingProperties,
};
