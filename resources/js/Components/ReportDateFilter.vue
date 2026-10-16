<script setup lang="ts">
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import FormErrors from './FormErrors.vue';
const props = defineProps<{ from: string; to: string; url: string }>();
const page = usePage();
const from = ref(props.from),
  to = ref(props.to);
watch(
  () => [props.from, props.to],
  () => {
    from.value = props.from;
    to.value = props.to;
  },
);
</script>
<template>
  <form
    class="form-section"
    @submit.prevent="router.get(url, { from: from || undefined, to: to || undefined })"
  >
    <FormErrors :errors="page.props.errors" />
    <div class="table-toolbar">
      <label>From<input v-model="from" type="date" /></label>
      <label>To<input v-model="to" type="date" :min="from || undefined" /></label>
      <button class="btn secondary">Apply dates</button>
      <button type="button" class="btn secondary" @click="router.get(url)">Reset dates</button>
    </div>
  </form>
</template>
