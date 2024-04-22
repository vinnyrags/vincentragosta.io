import {
  DefaultPropertyStructure,
  createPropertiesFromViewportsAndAlignment,
} from "@/directives/properties";

const verticalAlignmentProperties: DefaultPropertyStructure =
  createPropertiesFromViewportsAndAlignment("verticalAlignment");
export default verticalAlignmentProperties;
