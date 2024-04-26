import {
  viewports,
  multipliers,
  alignment,
} from "@/components/directives/properties/fixtures";

export interface DefaultPropertyStructure {
  [key: string]: boolean | string | undefined;
}

export const createProperties = (
  options: string[]
): DefaultPropertyStructure => {
  const properties: DefaultPropertyStructure = {};
  options.forEach((option) => {
    properties[option] = undefined;
  });
  return properties;
};

export const createPropertiesFromViewports = (
  prefix: string
): DefaultPropertyStructure => {
  const properties: DefaultPropertyStructure = {};
  viewports.forEach((viewport) => {
    const propName = `${prefix}${viewport}`;
    properties[propName] = undefined;
  });
  return properties;
};

export const createPropertiesFromViewportsAndAlignment = (
  prefix: string
): DefaultPropertyStructure => {
  const properties: DefaultPropertyStructure = {};
  alignment.forEach((alignment) => {
    const propName = `${prefix}${alignment}`;
    properties[propName] = undefined;
  });
  viewports.forEach((viewport) => {
    alignment.forEach((alignment) => {
      const propName = `${prefix}${alignment}${viewport}`;
      properties[propName] = undefined;
    });
  });
  return properties;
};

export const createPropertiesFromViewportAndMultipliers = (
  prefix: string
): DefaultPropertyStructure => {
  const properties: DefaultPropertyStructure = {};
  multipliers.forEach((multiplier) => {
    const propName = `${prefix}${multiplier}`;
    properties[propName] = undefined;
  });
  viewports.forEach((viewport) => {
    multipliers.forEach((multiplier) => {
      const propName = `${prefix}${multiplier}${viewport}`;
      properties[propName] = undefined;
    });
  });
  return properties;
};
