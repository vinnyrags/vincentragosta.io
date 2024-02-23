import media from '@/assets/scripts/shared-container/props/media';
import colors from "@/assets/scripts/shared-container/props/colors";
import bgColors from "@/assets/scripts/shared-container/props/bg-colors";
import padding from "@/assets/scripts/shared-container/props/padding";
import marginBottom from "@/assets/scripts/shared-container/props/margin-bottom";
import {modifiers} from "@/assets/scripts/shared-container/methods/modifiers";

export default {
    props: {
        ...media,
        ...colors,
        ...bgColors,
        ...padding,
        ...marginBottom

    },
    methods: {
        modifiers
    },
}