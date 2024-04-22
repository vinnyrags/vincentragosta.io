import colorProperties from "@/directives/colors";
import backgroundProperties from "@/directives/background";
import borderProperties from "@/directives/border";
import paddingProperties from "@/directives/padding";
import marginProperties from "@/directives/margin";
import alignmentProperties from "@/directives/alignment";

export default {
  ...colorProperties,
  ...backgroundProperties,
  ...borderProperties,
  ...paddingProperties,
  ...marginProperties,
  ...alignmentProperties,
};
