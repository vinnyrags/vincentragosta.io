<template>
  <article>
    <Section video="https://devanimecards.test/wp-content/uploads/2024/01/city.mp4">
      <Column sm="6" md="5" offset-md="8">
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
    </Section>
    <Section size="0" fluid>
      <Column>
        <ProjectCardGrid v-if="projectCollection" :cards="projectCollection ? projectCollection : []"/>
      </Column>
    </Section>
    <Section bg-primary>
      <Column align-center>
        <Heading level="2" size="large">Let's build something great</Heading>
        <Button href="#" white variant="outline">Hire Us</Button>
      </Column>
    </Section>
    <Section>
      <Column>
        <Button>Test</Button>
        <Button primary>Test</Button>
        <Button primary-dark>Test</Button>
        <Button primary-light>Test</Button>
        <Button secondary>Test</Button>
        <Button secondary-dark>Test</Button>
        <Button secondary-light>Test</Button>
        <Button tertiary>Test</Button>
        <Button tertiary-dark>Test</Button>
        <Button tertiary-light>Test</Button>
        <Button gray>Test</Button>
        <Button gray-dark>Test</Button>
        <Button gray-light>Test</Button>
        <Button off-white>Test</Button>
        <Button white>Test</Button>
        <Button black>Test</Button>
      </Column>
    </Section>
    <Section bg-color="green">
      <Column>
        <Button variant="outline">Test</Button>
        <Button variant="outline" primary>Test</Button>
        <Button variant="outline" primary-dark>Test</Button>
        <Button variant="outline" primary-light>Test</Button>
        <Button variant="outline" secondary>Test</Button>
        <Button variant="outline" secondary-dark>Test</Button>
        <Button variant="outline" secondary-light>Test</Button>
        <Button variant="outline" tertiary>Test</Button>
        <Button variant="outline" tertiary-dark>Test</Button>
        <Button variant="outline" tertiary-light>Test</Button>
        <Button variant="outline" gray>Test</Button>
        <Button variant="outline" gray-dark>Test</Button>
        <Button variant="outline" gray-light>Test</Button>
        <Button variant="outline" off-white>Test</Button>
        <Button variant="outline" white>Test</Button>
        <Button variant="outline" black>Test</Button>
      </Column>
    </Section>
    <Section bg-color="red">
      <Column>
        <Button variant="ghost">Test</Button>
        <Button variant="ghost" primary>Test</Button>
        <Button variant="ghost" primary-dark>Test</Button>
        <Button variant="ghost" primary-light>Test</Button>
        <Button variant="ghost" secondary>Test</Button>
        <Button variant="ghost" secondary-dark>Test</Button>
        <Button variant="ghost" secondary-light>Test</Button>
        <Button variant="ghost" tertiary>Test</Button>
        <Button variant="ghost" tertiary-dark>Test</Button>
        <Button variant="ghost" tertiary-light>Test</Button>
        <Button variant="ghost" gray>Test</Button>
        <Button variant="ghost" gray-dark>Test</Button>
        <Button variant="ghost" gray-light>Test</Button>
        <Button variant="ghost" off-white>Test</Button>
        <Button variant="ghost" white>Test</Button>
        <Button variant="ghost" black>Test</Button>
      </Column>
    </Section>
    <Section>
      <Column>
        <Heading level="2">Testing</Heading>
        <Heading size="default" level="2" primary>Testing 1</Heading>
        <Heading size="medium" level="2">Testing</Heading>
        <Heading size="large" level="2">Testing</Heading>
        <Heading size="xlarge" level="2">Testing</Heading>
        <Heading size="large" level="2" primary><a href="#">Test Link</a></Heading>
      </Column>
    </Section>
    <!--    <Section>-->
    <!--      <Column lines="6" bg-color="pink" color="white" offset-md="7" md="1">-->
    <!--        <Heading level="2">Testing!</Heading>-->
    <!--      </Column>-->
    <!--      <Column lines="3" bg-color="green" color="white" sm>-->
    <!--        <Heading level="2">Testing!</Heading>-->
    <!--      </Column>-->
    <!--      <Column lines="2" bg-color="cyan" color="white" sm>-->
    <!--        <Heading level="2">Testing!</Heading>-->
    <!--      </Column>-->
    <!--      <Column lines="7" bg-color="purple" color="white" lg="5">-->
    <!--        <Heading level="2">Testing!</Heading>-->
    <!--      </Column>-->
    <!--      <Column lines="3" bg-color="blue" color="white" sm>-->
    <!--        <Heading level="2">Testing!</Heading>-->
    <!--      </Column>-->
    <!--      <Column lines="2" bg-color="green" color="white" sm>-->
    <!--        <Heading level="2">Testing!</Heading>-->
    <!--      </Column>-->
    <!--      <Column lines="2" bg-color="red" color="white" sm>-->
    <!--        <Heading level="2">Testing!</Heading>-->
    <!--      </Column>-->
    <!--      <Column lines="5" bg-color="blue" color="white" sm>-->
    <!--        <Heading level="2">Testing!</Heading>-->
    <!--      </Column>-->
    <!--      <Column lines="3" bg-color="brown" color="white" sm>-->
    <!--        <Heading level="2">Testing!</Heading>-->
    <!--      </Column>-->
    <!--    </Section>-->
  </article>
</template>

<script>
import Section from '@/layout/Section.vue';
import Column from '@/layout/Column.vue';
import Heading from '@/elements/Heading.vue';
import Button from '@/elements/Button.vue';
import ButtonGroup from "@/components/ButtonGroup.vue";
import ProjectCardGrid from "@/components/ProjectCardGrid.vue";
import IconPause from "@/elements/icons/IconPause.vue";

export default {
  name: 'FrontPage',
  data() {
    return {
      heroVideoTrigger: null,
      projectCollection: [
        {
          title: '10glo',
          url: '#',
          image: 'https://devanimecards.test/wp-content/uploads/2024/01/10glo.png'
        },
        {
          title: 'AMASafety',
          url: '#',
          image: 'https://devanimecards.test/wp-content/uploads/2024/01/amasafety.jpeg'
        },
        {
          title: 'Analytic Partners',
          url: '#',
          image: 'https://devanimecards.test/wp-content/uploads/2024/01/analytic-partners.jpeg'
        },
        {
          title: 'Bershlaw',
          url: '#',
          image: 'https://devanimecards.test/wp-content/uploads/2024/01/bershlaw.jpeg'
        },
        {
          title: 'Holiday Afterpay',
          url: '#',
          image: 'https://devanimecards.test/wp-content/uploads/2024/01/holiday-afterpay.jpeg'
        },
        {
          title: 'IRC Events',
          url: '#',
          image: 'https://devanimecards.test/wp-content/uploads/2024/01/irc-events-scaled.jpeg'
        }
      ]
    }
  },
  components: {
    IconPause,
    Section,
    Column,
    Heading,
    Button,
    // IconPause,
    ButtonGroup,
    ProjectCardGrid
  },
  methods: {
    setHeroVideoTrigger() {
      this.heroVideoTrigger = document.getElementById('hero-video-trigger');
      this.heroSection = this.heroVideoTrigger.closest('.section');
      if (!this.heroVideoTrigger) {
        return;
      }

      this.heroVideoTrigger.addEventListener('click', (e) => {
        e.preventDefault();
        const trigger = e.currentTarget;
        let video = this.heroSection.querySelector('.section__video');
        if (!video) {
          return;
        }
        if (!video.paused) {
          video.pause();
          trigger.querySelector('span.text').innerHTML = 'Resume Background';
        } else {
          video.play();
          trigger.querySelector('span.text').innerHTML = 'Pause Background';
        }
      });
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