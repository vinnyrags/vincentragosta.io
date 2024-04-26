import {
  createProperties,
  DefaultPropertyStructure,
} from "@/components/directives/properties";
import { backgroundColorFixture } from "@/components/directives/background/colors/fixture";

const backgroundColorProperties: DefaultPropertyStructure = createProperties(
  backgroundColorFixture
);
export default backgroundColorProperties;
