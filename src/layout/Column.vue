<template>
  <div class="grid-col" :class="additionalClasses()" :style="cssVars">
    <div class="grid-col__bg" v-if="hasBackground">
      <video v-if="video" class="grid-col__video" type="video/mp4" :src="video" autoplay muted loop></video>
      <img v-if="image" class="grid-col__image" :src="image"/>
    </div>
    <div class="grid-col__container">
      <slot></slot>
    </div>
  </div>
</template>

<script>
export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Column',
  props: {
    lines: {
      type: String,
      default: '12'
    },

    image: String,
    video: String,
    bgColor: String,
    color: String,

    alignCenter: Boolean,

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
  },
  components: {},
  methods: {
    modifiers() {
      let modifiers = [];
      if (this.hasBackground) {
        modifiers.push('has-bg');
      }

      if (this.lineOffsetBreakpoints && this.lineOffsetBreakpoints.length) {
        modifiers.push('has-offset');
      }
      return modifiers.map((modifier) => {
        return 'grid-col--' + modifier;
      });
    },
    extraClasses() {
      let classes = [];

      if (this.alignCenter) {
        classes.push('text-center');
      }

      if (this.lines !== '12') {
        classes.push('grid-col-' + this.lines);
      }

      const lineBreakpoints = this.lineBreakpoints;
      if (lineBreakpoints && lineBreakpoints.length) {
        lineBreakpoints.forEach((lineBreakpoint, index) => {
          classes.push('grid-col-' + lineBreakpoints[index].breakpoint + '-' + lineBreakpoints[index].line);
        });
      }

      const lineOffsetBreakpoints = this.lineOffsetBreakpoints;
      if (lineOffsetBreakpoints && lineOffsetBreakpoints.length) {
        lineOffsetBreakpoints.forEach((lineOffsetBreakpoint, index) => {
          classes.push('grid-col-offset-' + lineOffsetBreakpoints[index].breakpoint + '-' + lineOffsetBreakpoints[index].line);
        });
      }

      return classes;
    },
    additionalClasses() {
      return [...this.modifiers(), ...this.extraClasses()].join(' ');
    }
  },
  computed: {
    hasBackground() {
      return this.image || this.video || this.bgColor;
    },
    cssVars() {
      let cssVars = {};
      if (this.bgColor) {
        Object.assign(cssVars, {
          '--grid-col-bg-color': this.bgColor,
        });
      }
      if (this.color) {
        Object.assign(cssVars, {
          '--grid-col-color': this.color,
        });
      }

      const lineBreakpoints = this.lineBreakpoints;
      if (lineBreakpoints) {
        let offsetVars = {};
        for(let i = 0; i < lineBreakpoints.length; i++) {
          offsetVars['--grid-col-offset-' + lineBreakpoints[i].breakpoint + '-start'] = lineBreakpoints[i].line;
        }
        Object.assign(cssVars, offsetVars);
      }


      const lineOffsetBreakpoints = this.lineOffsetBreakpoints;
      if (lineOffsetBreakpoints) {
        let offsetVars = {};
        for (let i = 0; i < lineOffsetBreakpoints.length; i++) {
          offsetVars['--grid-col-offset-' + lineOffsetBreakpoints[i].breakpoint + '-start'] = lineOffsetBreakpoints[i].line;
          if (lineBreakpoints) {
            for (let c = 0; c < lineBreakpoints.length; c++) {
              offsetVars['--grid-col-offset-' + lineBreakpoints[c].breakpoint + '-end'] = parseInt(lineOffsetBreakpoints[i].line) + parseInt(lineBreakpoints[c].line);
            }
          } else {
            offsetVars['--grid-col-offset-' + lineOffsetBreakpoints[i].breakpoint + '-end'] = parseInt(lineOffsetBreakpoints[i].line) + parseInt(this.lines);
          }
          offsetVars['--grid-col-breakpoint-offset-' + lineOffsetBreakpoints[i].breakpoint + '-start'] = lineOffsetBreakpoints[i].line;
        }
        Object.assign(cssVars, offsetVars);
      }

      return cssVars;
    },
    lineBreakpoints() {
      let lineBreakpoints = [];
      if (this.xs) {
        lineBreakpoints.push({
          breakpoint: 'xs',
          line: typeof this.xs === 'string' ? this.xs : this.lines
        })
      }
      if (this.sm) {
        lineBreakpoints.push({
          breakpoint: 'sm',
          line: typeof this.sm === 'string' ? this.sm : this.lines
        })
      }
      if (this.md) {
        lineBreakpoints.push({
          breakpoint: 'md',
          line: typeof this.md === 'string' ? this.md : this.lines
        })
      }
      if (this.lg) {
        lineBreakpoints.push({
          breakpoint: 'lg',
          line: typeof this.lg === 'string' ? this.lg : this.lines
        })
      }
      if (this.xl) {
        lineBreakpoints.push({
          breakpoint: 'xl',
          line: typeof this.xl === 'string' ? this.xl : this.lines
        })
      }
      return lineBreakpoints;
    },
    lineOffsetBreakpoints() {
      let lineOffsetBreakpoints = [];
      if (this.offsetXs) {
        lineOffsetBreakpoints.push({
          breakpoint: 'xs',
          line: typeof this.offsetXs === 'string' ? this.offsetXs : this.lines
        });
      }
      if (this.offsetSm) {
        lineOffsetBreakpoints.push({
          breakpoint: 'sm',
          line: typeof this.offsetSm === 'string' ? this.offsetSm : this.lines
        });
      }
      if (this.offsetMd) {
        lineOffsetBreakpoints.push({
          breakpoint: 'md',
          line: typeof this.offsetMd === 'string' ? this.offsetMd : this.lines
        });
      }
      if (this.offsetLg) {
        lineOffsetBreakpoints.push({
          breakpoint: 'lg',
          line: typeof this.offsetLg === 'string' ? this.offsetLg : this.lines
        });
      }
      if (this.offsetXl) {
        lineOffsetBreakpoints.push({
          breakpoint: 'xl',
          line: typeof this.offsetXl === 'string' ? this.offsetXl : this.lines
        });
      }

      return lineOffsetBreakpoints;
    }
  }
}
</script>