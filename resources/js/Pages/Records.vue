<script setup lang="ts">
import AppLayout from '../Components/AppLayout.vue';
import Pagination from '../Components/Pagination.vue';
import type { Pagination as Paged } from '../types';
defineProps<{ title: string; columns: string[]; records: Paged<Record<string, unknown>> }>();
function format(value: unknown) {
  return typeof value === 'boolean' ? (value ? 'Yes' : 'No') : (value ?? '—');
}
</script>
<template>
  <AppLayout :title="title" subtitle="A traceable record of branch operations."
    ><section class="panel">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th v-for="column in columns" :key="column">{{ column.replaceAll('_', ' ') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(record, index) in records.data" :key="index">
              <td v-for="column in columns" :key="column">{{ format(record[column]) }}</td>
            </tr>
            <tr v-if="!records.data.length">
              <td :colspan="columns.length" class="empty">No records yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="records.links" :total="records.total" /></section
  ></AppLayout>
</template>
