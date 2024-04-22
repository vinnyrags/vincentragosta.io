import {
  createProperties,
  DefaultPropertyStructure,
} from "@/directives/properties";
import { colorFixture } from "@/directives/colors/fixture";

const colorProperties: DefaultPropertyStructure =
  createProperties(colorFixture);
export default colorProperties;
