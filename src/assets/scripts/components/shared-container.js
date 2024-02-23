import colors from "@/assets/scripts/props/colors";
import bg from "@/assets/scripts/props/bg";
import spacing from '@/assets/scripts/props/spacing';
import {modifiers} from "@/assets/scripts/functions/modifiers";

export default {
    props: {
        ...colors,
        ...bg,
        ...spacing
    },
    methods: {
        modifiers
    },
}