import colors from "@/assets/scripts/shared-container/props/colors";
import bgColors from "@/assets/scripts/shared-container/props/bg-colors";
import padding from "@/assets/scripts/shared-container/props/padding";
import {modifiers} from "@/assets/scripts/shared-container/methods/modifiers";

export default {
    props: {
        ...colors,
        ...bgColors,
        ...padding
    },
    methods: {
        modifiers
    },
}