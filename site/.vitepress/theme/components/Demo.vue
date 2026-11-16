<script setup>
// A live demo: site/public/demos/<name>.html (rendered by site/demos/build.php) in an
// iframe, so Bootstrap's styles never reach the documentation pages. It follows the
// site's dark mode and grows with its content.
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useData, withBase } from 'vitepress';

const props = defineProps({
  name: { type: String, required: true },
  title: { type: String, default: '' },
  minHeight: { type: Number, default: 160 },
});

const { isDark } = useData();
const frame = ref(null);
const height = ref(props.minHeight);
const label = computed(() => props.title || props.name.replace(/-/g, ' ').replace(/^./, (c) => c.toUpperCase()));
const src = computed(() => withBase(`/demos/${props.name}.html?mode=${isDark.value ? 'dark' : 'light'}`));

let observer = null;

function measure() {
  const doc = frame.value?.contentDocument;
  if (doc?.documentElement) {
    height.value = Math.max(props.minHeight, Math.ceil(doc.documentElement.scrollHeight));
  }
}

function onLoad() {
  observer?.disconnect();
  const doc = frame.value?.contentDocument;
  if (!doc) {
    return;
  }
  observer = new ResizeObserver(measure);
  observer.observe(doc.documentElement);
  measure();
}

watch(src, () => {
  height.value = Math.max(props.minHeight, height.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
  <figure class="celerik-demo">
    <div class="celerik-demo__bar">
      <a :href="src" target="_blank" rel="noopener">Open the demo in a new tab<span class="visually-hidden"> ({{ label }})</span></a>
    </div>
    <iframe
      ref="frame"
      :src="src"
      :title="`Demo: ${label}`"
      :style="{ height: `${height}px` }"
      loading="lazy"
      @load="onLoad"
    />
    <figcaption>{{ label }} demo. Data is embedded and nothing is saved.</figcaption>
  </figure>
</template>
