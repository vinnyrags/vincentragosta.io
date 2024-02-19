<template>
  <div class="flex-column" :class="additionalClasses()" :style="cssVars">
    <div class="flex-column__bg" v-if="hasBackground()">
      <video v-if="video" class="flex-column__video" type="video/mp4" :src="video" autoplay muted loop></video>
      <img v-if="image" class="flex-column__image" :src="image"/>
    </div>
    <div class="flex-column__wrap">
      <slot></slot>
    </div>
  </div>
</template>

<script>
export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'FlexColumn',
  props: {
    width: {
      type: String,
      default: '12'
    },

    image: String,
    video: String,
    bgColor: String,
    color: String,

    center: Boolean,

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

    bgPrimary: Boolean,
    bgPrimaryDark: Boolean,
    bgPrimaryLight: Boolean,
    bgSecondary: Boolean,
    bgSecondaryDark: Boolean,
    bgSecondaryLight: Boolean,
    bgTertiary: Boolean,
    bgTertiaryDark: Boolean,
    bgTertiaryLight: Boolean,
    bgGrayDark: Boolean,
    bgGray: Boolean,
    bgGrayLight: Boolean,
    bgOffWhite: Boolean,
    bgWhite: Boolean,
    bgBlack: Boolean,

    primary: Boolean,
    primaryDark: Boolean,
    primaryLight: Boolean,
    secondary: Boolean,
    secondaryDark: Boolean,
    secondaryLight: Boolean,
    tertiary: Boolean,
    tertiaryDark: Boolean,
    tertiaryLight: Boolean,
    grayDark: Boolean,
    gray: Boolean,
    grayLight: Boolean,
    offWhite: Boolean,
    white: Boolean,
    black: Boolean,
  },
  components: {},
  methods: {
    modifiers() {
      let modifiers = [];
      if (this.hasBackground()) {
        modifiers.push('has-bg');
      }

      if (this.lineOffsetBreakpoints && this.lineOffsetBreakpoints.length) {
        modifiers.push('has-offset');
      }

      if (this.bgPrimary) {
        modifiers.push('bg-primary');
      }

      if (this.bgPrimaryDark) {
        modifiers.push('bg-primary-dark');
      }

      if (this.bgPrimaryLight) {
        modifiers.push('bg-primary-light');
      }

      if (this.bgSecondary) {
        modifiers.push('bg-secondary');
      }

      if (this.bgSecondaryDark) {
        modifiers.push('bg-secondary-dark');
      }

      if (this.bgSecondaryLight) {
        modifiers.push('bg-secondary-light');
      }

      if (this.bgTertiary) {
        modifiers.push('bg-tertiary');
      }

      if (this.bgTertiaryDark) {
        modifiers.push('bg-tertiary-dark');
      }

      if (this.bgTertiaryLight) {
        modifiers.push('bg-tertiary-light');
      }

      if (this.bgGrayDark) {
        modifiers.push('bg-gray-dark');
      }

      if (this.bgGray) {
        modifiers.push('bg-gray');
      }

      if (this.bgGrayLight) {
        modifiers.push('bg-gray-light');
      }

      if (this.bgOffWhite) {
        modifiers.push('bg-off-white');
      }

      if (this.bgWhite) {
        modifiers.push('bg-white');
      }

      if (this.bgBlack) {
        modifiers.push('bg-black');
      }

      if (this.primary) {
        modifiers.push('primary');
      }

      if (this.primaryDark) {
        modifiers.push('primary-dark');
      }

      if (this.primaryLight) {
        modifiers.push('primary-light');
      }

      if (this.secondary) {
        modifiers.push('secondary');
      }

      if (this.secondaryDark) {
        modifiers.push('secondary-dark');
      }

      if (this.secondaryLight) {
        modifiers.push('secondary-light');
      }

      if (this.tertiary) {
        modifiers.push('tertiary');
      }

      if (this.tertiaryDark) {
        modifiers.push('tertiary-dark');
      }

      if (this.tertiaryLight) {
        modifiers.push('tertiary-light');
      }

      if (this.grayDark) {
        modifiers.push('gray-dark');
      }

      if (this.gray) {
        modifiers.push('gray');
      }

      if (this.grayLight) {
        modifiers.push('gray-light');
      }

      if (this.offWhite) {
        modifiers.push('off-white');
      }

      if (this.white) {
        modifiers.push('white');
      }

      if (this.black) {
        modifiers.push('black');
      }

      return modifiers.map((modifier) => {
        return 'flex-column--' + modifier;
      });
    },
    extraClasses() {
      let classes = [];

      if (this.center) {
        classes.push('text-center');
      }

      if (this.width !== '12') {
        classes.push('flex-column-' + this.width);
      }

      const responsiveBreakpoints = this.responsiveBreakpoints;
      if (responsiveBreakpoints && responsiveBreakpoints.length) {
        responsiveBreakpoints.forEach((breakpoint) => {
          classes.push('flex-column-' + breakpoint.breakpoint + '-' + breakpoint.width);
          // classes.push('flex-column-' + responsiveBreakpoints[index].breakpoint + '-' + responsiveBreakpoints[index].width);
        });
      }

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
    hasBackground() {
      return this.video || this.image || this.bgColor || this.bgPrimary || this.bgPrimaryDark || this.bgPrimaryLight || this.bgSecondary || this.bgSecondaryDark || this.bgSecondaryLight || this.bgTertiary || this.bgTertiaryDark || this.bgTertiaryLight || this.bgGray || this.bgGrayDark || this.bgGrayLight || this.bgOffWhite || this.bgWhite || this.bgBlack;
    },
  },
  computed: {
    cssVars() {
      let cssVars = {};
      if (this.bgColor) {
        Object.assign(cssVars, {
          '--flex-column-bg-color': this.bgColor,
        });
      }
      if (this.color) {
        Object.assign(cssVars, {
          '--flex-column-color': this.color,
        });
      }

      // const lineBreakpoints = this.lineBreakpoints;
      // if (lineBreakpoints) {
      //   let offsetVars = {};
      //   for (let i = 0; i < lineBreakpoints.length; i++) {
      //     offsetVars['--flex-column-offset-' + lineBreakpoints[i].breakpoint + '-start'] = lineBreakpoints[i].line;
      //   }
      //   Object.assign(cssVars, offsetVars);
      // }


      // const lineOffsetBreakpoints = this.lineOffsetBreakpoints;
      // if (lineOffsetBreakpoints) {
      //   let offsetVars = {};
      //   for (let i = 0; i < lineOffsetBreakpoints.length; i++) {
      //     offsetVars['--flex-column-offset-' + lineOffsetBreakpoints[i].breakpoint + '-start'] = lineOffsetBreakpoints[i].line;
      //     if (lineBreakpoints) {
      //       for (let c = 0; c < lineBreakpoints.length; c++) {
      //         offsetVars['--flex-column-offset-' + lineBreakpoints[c].breakpoint + '-end'] = parseInt(lineOffsetBreakpoints[i].line) + parseInt(lineBreakpoints[c].line);
      //       }
      //     } else {
      //       offsetVars['--flex-column-offset-' + lineOffsetBreakpoints[i].breakpoint + '-end'] = parseInt(lineOffsetBreakpoints[i].line) + parseInt(this.width);
      //     }
      //     offsetVars['--flex-column-breakpoint-offset-' + lineOffsetBreakpoints[i].breakpoint + '-start'] = lineOffsetBreakpoints[i].line;
      //   }
      //   Object.assign(cssVars, offsetVars);
      // }

      return cssVars;
    },
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
  mounted() {}
}
</script>