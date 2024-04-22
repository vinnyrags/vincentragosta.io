import colorProperties from "@/directives/colors";
import backgroundProperties from "@/directives/background";
import borderProperties from "@/directives/border";
import gridProperties from "@/directives/grid";
import paddingProperties from "@/directives/padding";
import marginProperties from "@/directives/margin";
import horizontalAlignmentProperties from "@/directives/alignment/horizontal";
import containerProperties from "@/directives/container";

export default {
  ...colorProperties,
  ...backgroundProperties,
  ...borderProperties,
  ...gridProperties,
  ...paddingProperties,
  ...marginProperties,
  ...horizontalAlignmentProperties,
  ...containerProperties,
};
