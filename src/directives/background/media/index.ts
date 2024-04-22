import {
  createProperties,
  DefaultPropertyStructure,
} from "@/directives/properties";
import { backgroundMediaFixture } from "@/directives/background/media/fixture";

const backgroundMediaProperties: DefaultPropertyStructure = createProperties(
  backgroundMediaFixture
);
export default backgroundMediaProperties;
