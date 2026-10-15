<script setup lang="ts">
import { ref, onUnmounted } from 'vue';
import { getJson } from '../composables';
import type { Medicine } from '../types';
const emit = defineEmits<{ select: [medicine: Medicine] }>();
const q = ref('');
const results = ref<Medicine[]>([]);
const error = ref('');
let timer: ReturnType<typeof setTimeout>;
let sequence = 0;
async function search() {
  const current = ++sequence;
  try {
    const data = await getJson<Medicine[]>('/catalog-search?q=' + encodeURIComponent(q.value));
    if (current === sequence) results.value = data;
    error.value = '';
  } catch (e) {
    error.value = (e as Error).message;
  }
}
function input() {
  clearTimeout(timer);
  timer = setTimeout(search, 200);
}
onUnmounted(() => clearTimeout(timer));
</script>
<template>
  <div class="picker">
    <label
      >Find medicine<input
        v-model="q"
        placeholder="Name, SKU or barcode"
        @input="input"
        @keydown.enter.prevent="search"
    /></label>
    <p v-if="error" class="field-error">{{ error }}</p>
    <div v-if="results.length" class="picker-results">
      <button
        v-for="m in results"
        :key="m.id"
        type="button"
        @click="
          emit('select', m);
          results = [];
          q = '';
        "
      >
        {{ m.name }} <small>{{ m.sku }}</small>
      </button>
    </div>
  </div>
</template>
