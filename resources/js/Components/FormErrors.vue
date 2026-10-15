<script setup lang="ts">
defineProps<{ errors: object }>();
</script>
<template>
  <div v-if="Object.keys(errors).length" class="notice error" role="alert">
    <p v-for="(message, key) in errors" :key="key">{{ message }}</p>
  </div>
</template>
