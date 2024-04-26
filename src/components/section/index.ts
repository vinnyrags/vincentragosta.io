import colorProperties from "@/components/directives/colors";
import backgroundProperties from "@/components/directives/background";
import borderProperties from "@/components/directives/border";
import gridProperties from "@/components/directives/grid";
import paddingProperties from "@/components/directives/padding";
import marginProperties from "@/components/directives/margin";
import horizontalAlignmentProperties from "@/components/directives/alignment/horizontal";
import containerProperties from "@/components/directives/container";

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
