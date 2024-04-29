import { computed, ComputedRef } from "vue";
import { DefaultPropertyStructure } from "@/components/directives/properties";
import { toKebabCase } from "@/utils";
import { backgroundMediaFixture } from "@/components/directives/background/media/fixture";
import { backgroundColorFixture } from "@/components/directives/background/colors/fixture";

export function hasBackground(props: DefaultPropertyStructure): boolean {
  return computed(() => {
    for (const media of backgroundMediaFixture) {
      if (props[media] || props[media] === "") {
        return true;
      }
    }
    for (const color of backgroundColorFixture) {
      if (props[color] || props[color] === "") {
        return true;
      }
    }
    return false;
  }).value;
}

export function useModifiers(
  props: DefaultPropertyStructure,
  prefix: string,
  callback?: PropertiesCallback
): ComputedRef<Record<string, boolean>> {
  const hasBg = hasBackground(props);
  return computed(() => {
    const mods: Record<string, boolean> = {};
    if (callback) {
      Object.assign(mods, callback(props));
    }
    for (const prop in props) {
      if (props[prop as keyof DefaultPropertyStructure] !== undefined) {
        const kebabCaseProp = toKebabCase(prop);
        mods[`${prefix}--${kebabCaseProp}`] = true;
      }
    }
    if (hasBg) {
      mods[`${prefix}--has-bg`] = true;
    }
    return mods;
  });
}
