import { computed } from "vue";
import { DefaultPropertyStructure } from "@/directives/properties";
import { toKebabCase } from "@/utils";

export function hasBackground(props: DefaultPropertyStructure): boolean {
  return computed(() => !!props.video || !!props.image).value;
}

export function useModifiers(props: DefaultPropertyStructure, prefix: string) {
  const hasBg = hasBackground(props);
  return computed(() => {
    const mods: Record<string, boolean> = {};
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
