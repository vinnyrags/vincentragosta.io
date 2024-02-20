<template>
  <div class="flex-column" :class="additionalClasses()" :style="cssVars">
    <div class="flex-column__bg" v-if="hasBg(this.$props)">
      <video v-if="video" class="flex-column__video" type="video/mp4" :src="video" autoplay muted loop></video>
      <img v-if="image" class="flex-column__image" :src="image"/>
    </div>
    <div class="flex-column__wrap">
      <slot></slot>
    </div>
  </div>
</template>

<script>

import componentContainerProps from "@/assets/scripts/component-container/props/props";
import alignmentProps from '@/assets/scripts/component-container/props/alignment';
import {hasBg} from "@/assets/scripts/component-container/methods/has-bg";
import {cssVars} from "@/assets/scripts/component-container/computed/css-vars";
import {componentContainerModifiers} from "@/assets/scripts/component-container/methods/modifiers";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'FlexColumn',
  props: {
    width: {
      type: String,
      default: '12'
    },

    xs: [Boolean, String],
    sm: [Boolean, String],
    md: [Boolean, String],
    lg: [Boolean, String],
    xl: [Boolean, String],

    offsetXs: String,
    offsetSm: String,
    offsetMd: String,
    offsetLg: String,
    offsetXl: String,

    ...componentContainerProps,
    ...alignmentProps
  },
  components: {},
  methods: {
    hasBg,
    modifiers() {
      let modifiers = [];

      if (this.lineOffsetBreakpoints && this.lineOffsetBreakpoints.length) {
        modifiers.push('has-offset');
      }

      return [...modifiers, ...(componentContainerModifiers(this.$props))].map((modifier) => {
        return 'flex-column--' + modifier;
      });
    },
    extraClasses() {
      let classes = [];

      // TODO right now we are using alignment being ported in from component container, is that the way to go?
      // if (this.center) {
      //   classes.push('text-center');
      // }

      // TODO consider making modifiers (maybe not though)
      // console.log(this.width);
      if (this.width) {
        classes.push('flex-column-' + this.width);
      }

      const responsiveBreakpoints = this.responsiveBreakpoints;
      if (responsiveBreakpoints && responsiveBreakpoints.length) {
        // TODO consider making modifier
        responsiveBreakpoints.forEach((breakpoint) => {
          classes.push('flex-column-' + breakpoint.breakpoint + '-' + breakpoint.width);
          // classes.push('flex-column-' + responsiveBreakpoints[index].breakpoint + '-' + responsiveBreakpoints[index].width);
        });
      }

      // TODO is this still relevant?
      // const lineOffsetBreakpoints = this.lineOffsetBreakpoints;
      // if (lineOffsetBreakpoints && lineOffsetBreakpoints.length) {
      //   lineOffsetBreakpoints.forEach((lineOffsetBreakpoint, index) => {
      //     classes.push('flex-column-offset-' + lineOffsetBreakpoints[index].breakpoint + '-' + lineOffsetBreakpoints[index].line);
      //   });
      // }

      return classes;
    },
    additionalClasses() {
      return [...this.modifiers(), ...this.extraClasses()].join(' ');
    },
  },
  computed: {
    cssVars,
    // TODO is this still relevant?
    // cssVars() {
    //   let cssVars = {};
    //   if (this.bgColor) {
    //     Object.assign(cssVars, {
    //       '--flex-column-bg-color': this.bgColor,
    //     });
    //   }
    //   if (this.color) {
    //     Object.assign(cssVars, {
    //       '--flex-column-color': this.color,
    //     });
    //   }
    //
    //   // const lineBreakpoints = this.lineBreakpoints;
    //   // if (lineBreakpoints) {
    //   //   let offsetVars = {};
    //   //   for (let i = 0; i < lineBreakpoints.length; i++) {
    //   //     offsetVars['--flex-column-offset-' + lineBreakpoints[i].breakpoint + '-start'] = lineBreakpoints[i].line;
    //   //   }
    //   //   Object.assign(cssVars, offsetVars);
    //   // }
    //
    //
    //   // const lineOffsetBreakpoints = this.lineOffsetBreakpoints;
    //   // if (lineOffsetBreakpoints) {
    //   //   let offsetVars = {};
    //   //   for (let i = 0; i < lineOffsetBreakpoints.length; i++) {
    //   //     offsetVars['--flex-column-offset-' + lineOffsetBreakpoints[i].breakpoint + '-start'] = lineOffsetBreakpoints[i].line;
    //   //     if (lineBreakpoints) {
    //   //       for (let c = 0; c < lineBreakpoints.length; c++) {
    //   //         offsetVars['--flex-column-offset-' + lineBreakpoints[c].breakpoint + '-end'] = parseInt(lineOffsetBreakpoints[i].line) + parseInt(lineBreakpoints[c].line);
    //   //       }
    //   //     } else {
    //   //       offsetVars['--flex-column-offset-' + lineOffsetBreakpoints[i].breakpoint + '-end'] = parseInt(lineOffsetBreakpoints[i].line) + parseInt(this.width);
    //   //     }
    //   //     offsetVars['--flex-column-breakpoint-offset-' + lineOffsetBreakpoints[i].breakpoint + '-start'] = lineOffsetBreakpoints[i].line;
    //   //   }
    //   //   Object.assign(cssVars, offsetVars);
    //   // }
    //
    //   return cssVars;
    // },
    responsiveBreakpoints() {
      let breakpoint = [];
      if (this.xs) {
        breakpoint.push({
          breakpoint: 'xs',
          width: typeof this.xs === 'string' ? this.xs : this.width
        })
      }
      if (this.sm) {
        breakpoint.push({
          breakpoint: 'sm',
          width: typeof this.sm === 'string' ? this.sm : this.width
        })
      }
      if (this.md) {
        breakpoint.push({
          breakpoint: 'md',
          width: typeof this.md === 'string' ? this.md : this.width
        })
      }
      if (this.lg) {
        breakpoint.push({
          breakpoint: 'lg',
          width: typeof this.lg === 'string' ? this.lg : this.width
        })
      }
      if (this.xl) {
        breakpoint.push({
          breakpoint: 'xl',
          width: typeof this.xl === 'string' ? this.xl : this.width
        })
      }
      return breakpoint;
    },
    lineOffsetBreakpoints() {
      let offsetBreakpoint = [];
      if (this.offsetXs) {
        offsetBreakpoint.push({
          breakpoint: 'xs',
          line: typeof this.offsetXs === 'string' ? this.offsetXs : this.width
        });
      }
      if (this.offsetSm) {
        offsetBreakpoint.push({
          breakpoint: 'sm',
          line: typeof this.offsetSm === 'string' ? this.offsetSm : this.width
        });
      }
      if (this.offsetMd) {
        offsetBreakpoint.push({
          breakpoint: 'md',
          line: typeof this.offsetMd === 'string' ? this.offsetMd : this.width
        });
      }
      if (this.offsetLg) {
        offsetBreakpoint.push({
          breakpoint: 'lg',
          line: typeof this.offsetLg === 'string' ? this.offsetLg : this.width
        });
      }
      if (this.offsetXl) {
        offsetBreakpoint.push({
          breakpoint: 'xl',
          line: typeof this.offsetXl === 'string' ? this.offsetXl : this.width
        });
      }

      return offsetBreakpoint;
    },
  },
  mounted() {
  }
}
</script>