import {
  createProperties,
  DefaultPropertyStructure,
} from "@/directives/properties";
import { backgroundColorFixture } from "@/directives/background/colors/fixture";

const backgroundColorProperties: DefaultPropertyStructure = createProperties(
  backgroundColorFixture
);
export default backgroundColorProperties;
