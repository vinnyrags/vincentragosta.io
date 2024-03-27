<template>
  <article>
    <Section>
      <Row>
        <Column>
          <Blockquote :quote="'The wildest part ever!'" :attribution-source="'Kelly Anne'"
                      :attribution-cite="'New York Times'" xlarge/>
        </Column>
      </Row>
    </Section>
    <Section video="https://devanimecards.test/wp-content/uploads/2024/01/city.mp4" vpad-md-quadruple>
      <Row align-sm-end>
        <Column sm="6" md="5">
          <Heading level="2">We make the web better by <strong>finely crafting</strong> websites & tools for content
            creators.
          </Heading>
          <ButtonGroup>
            <Button variant="outline">Let's Collaborate</Button>
            <Button variant="ghost" class="d-none d-md-flex" id="hero-video-trigger">
              <template #left>
                <IconPause/>
              </template>
              <span class="text">Pause Background</span>
            </Button>
          </ButtonGroup>
        </Column>
      </Row>
    </Section>
    <!--    <Section vpad-none fluid vborder-primary v-if="projectCollection">-->
    <!--      <Row>-->
    <!--        <Column>-->
    <!--          <ProjectCardGrid v-if="projectCollection" :cards="projectCollection ? projectCollection : []"/>-->
    <!--        </Column>-->
    <!--      </Row>-->
    <!--    </Section>-->
    <Section v-if="highlights">
      <Row align-center>
        <Column v-for="highlight in highlights" :key="highlight.title + encodeURI(highlight.url)">
          <HightlightCard :title="highlight.title" :url="highlight.url" :excerpt="highlight.excerpt"
                          :image="highlight.image" horizontal
                          sleek tertiary></HightlightCard>
        </Column>
      </Row>
      <Row align-center>
        <Column sm="6" md="4" v-for="highlight in highlights" :key="highlight.title + encodeURI(highlight.url)">
          <HightlightCard :title="highlight.title" :url="highlight.url" :excerpt="highlight.excerpt"
                          :image="highlight.image"
                          sleek tertiary></HightlightCard>
        </Column>
      </Row>
    </Section>
    <Section bg-primary>
      <Row>
        <Column align-center>
          <Heading level="2" size="large">Let's build something great</Heading>
          <Button href="#" variant="outline" white>Hire Me</Button>
        </Column>
      </Row>
    </Section>
  </article>
</template>

<script>

// DAILY @TODO
// work on project card grid, then determine fate of grid, and grid-col scss files (scratch file)
// set fallback image for video? yeah probably
// consider removing pause background
// blockquote
// slider
// lists

// HOLD @TODO
// create card infrastructure, abstract highlight-card (sleek)
// add row modifier to flush column content?
// create padding modifiers on button?
// consider moving section__bg to --has-bg
// remove column file comments to scratch file

import data from "@/test/data";
import Section from '@/components/Section.vue';
import Row from "@/components/Row.vue";
import Column from "@/components/Column.vue";
import Heading from '@/elements/Heading.vue';
import Button from '@/elements/Button.vue';
import ButtonGroup from "@/components/ButtonGroup.vue";
// import ProjectCardGrid from "@/components/ProjectCardGrid.vue";
import IconPause from "@/elements/icons/IconPause.vue";
import HightlightCard from "@/components/HighlightCard.vue";
import Blockquote from "@/components/Blockquote.vue";

export default {
  name: 'FrontPage',
  data,
  components: {
    Blockquote,
    Section,
    Row,
    Column,
    HightlightCard,
    IconPause,
    Heading,
    Button,
    ButtonGroup,
    // ProjectCardGrid
  },
  methods: {
    setHeroVideoTrigger() {
      // this.heroVideoTrigger = document.getElementById('hero-video-trigger');
      // this.heroSection = this.heroVideoTrigger.closest('.section');
      // if (!this.heroVideoTrigger) {
      //   return;
      // }

      // this.heroVideoTrigger.addEventListener('click', (e) => {
      //   e.preventDefault();
      //   const trigger = e.currentTarget;
      //   let video = this.heroSection.querySelector('.section__video');
      //   if (!video) {
      //     return;
      //   }
      //   if (!video.paused) {
      //     video.pause();
      //     trigger.querySelector('span.text').innerHTML = 'Resume Background';
      //   } else {
      //     video.play();
      //     trigger.querySelector('span.text').innerHTML = 'Pause Background';
      //   }
      // });
    }
  },
  mounted() {
    this.setHeroVideoTrigger();
  }
}
</script>

<style lang="scss">
//
</style>