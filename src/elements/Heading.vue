<script>
import { h } from 'vue'

const DynamicHeading = (props, context) => {
  context.attrs.class = 'heading';

  console.log(props);

  if (props.size) {
    context.attrs.class += ' heading--' + props.size;
  }

  if (props.class) {
    context.attrs.class += (' ' + props.class);
  }

  if (props.primary !== undefined) {
    context.attrs.class += ' heading--primary';
  }

  if (props.primaryDark !== undefined) {
    context.attrs.class += ' heading--primary-dark';
  }

  if (props.primaryLight !== undefined) {
    context.attrs.class += ' heading--primary-light';
  }

  if (props.secondary !== undefined) {
    context.attrs.class += ' heading--secondary';
  }

  if (props.secondaryDark !== undefined) {
    context.attrs.class += ' heading--secondary-dark';
  }

  if (props.secondaryLight !== undefined) {
    context.attrs.class += ' heading--secondary-light';
  }

  if (props.tertiary !== undefined) {
    context.attrs.class += ' heading--tertiary';
  }

  if (props.tertiaryDark !== undefined) {
    context.attrs.class += ' heading--tertiary-dark';
  }

  if (props.tertiaryLight !== undefined) {
    context.attrs.class += ' heading--tertiary-light';
  }

  if (props.gray !== undefined) {
    context.attrs.class += ' heading--gray';
  }

  if (props.grayDark !== undefined) {
    context.attrs.class += ' heading--gray-dark';
  }

  if (props.grayLight !== undefined) {
    context.attrs.class += ' heading--gray-light';
  }

  if (props.offWhite !== undefined) {
    context.attrs.class += ' heading--off-white';
  }

  if (props.white !== undefined) {
    context.attrs.class += ' heading--white';
  }

  if (props.black !== undefined) {
    context.attrs.class += ' heading--black';
  }

  return h(`h${props.level ? props.level : '2'}`, context.attrs, context.slots)
}

DynamicHeading.props = ['level', 'size', 'class', 'primary', 'primaryDark', 'primaryLight', 'secondary', 'secondaryDark', 'secondaryLight', 'tertiary', 'tertiaryDark', 'tertiaryLight', 'offWhite', 'white', 'black']

export default DynamicHeading;
</script>