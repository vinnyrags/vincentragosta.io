import colorProperties from "@/components/directives/colors";
import backgroundProperties from "@/components/directives/background";
import borderProperties from "@/components/directives/border";
import paddingProperties from "@/components/directives/padding";
import marginProperties from "@/components/directives/margin";
import horizontalAlignmentProperties from "@/components/directives/alignment/horizontal";

export default {
  ...colorProperties,
  ...backgroundProperties,
  ...borderProperties,
  ...paddingProperties,
  ...marginProperties,
  ...horizontalAlignmentProperties,
};
