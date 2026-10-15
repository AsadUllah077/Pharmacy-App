<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import Pagination from '../Components/Pagination.vue';
import FormErrors from '../Components/FormErrors.vue';
import { useBusiness } from '../composables';
import type { Pagination as Paged } from '../types';
defineProps<{
  records: Paged<{
    id: number;
    category: string;
    description: string;
    amount: string;
    method: string;
    created_at: string;
  }>;
}>();
const { can, money, date } = useBusiness();
const open = ref(false);
const form = useForm({
  category: '',
  description: '',
  amount: '',
  method: 'cash',
  request_key: crypto.randomUUID(),
});
function save() {
  if (!confirm('Record this paid expense and post its journal?')) return;
  form.post('/expenses', {
    onSuccess: () => {
      form.reset();
      form.request_key = crypto.randomUUID();
      open.value = false;
    },
  });
}
</script>
<template>
  <AppLayout title="Expenses" subtitle="Track the cost of running your pharmacy."
    ><template #actions
      ><button v-if="can('accounting.manage')" class="btn primary" @click="open = !open">
        Record paid expense
      </button></template
    >
    <form v-if="open" class="panel form-section" @submit.prevent="save">
      <h2>Paid expense</h2>
      <FormErrors :errors="form.errors" />
      <div class="form-grid">
        <label>Category<input v-model="form.category" required /></label
        ><label>Description<input v-model="form.description" required /></label
        ><label
          >Amount<input
            v-model="form.amount"
            type="number"
            min="0.01"
            step="0.01"
            required /></label
        ><label
          >Method<select v-model="form.method">
            <option value="cash">Cash</option>
            <option value="bank">Bank</option>
            <option value="card">Card</option>
            <option value="wallet">Wallet</option>
          </select></label
        >
      </div>
      <button class="btn primary" :disabled="form.processing">Post expense</button>
    </form>
    <section class="panel">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Category</th>
              <th>Description</th>
              <th>Method</th>
              <th>Date</th>
              <th class="number">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in records.data" :key="r.id">
              <td>{{ r.category }}</td>
              <td>{{ r.description }}</td>
              <td>{{ r.method }}</td>
              <td>{{ date(r.created_at) }}</td>
              <td class="number">{{ money(r.amount) }}</td>
            </tr>
            <tr v-if="!records.data.length">
              <td colspan="5" class="empty">No expenses recorded.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="records.links" :total="records.total" /></section
  ></AppLayout>
</template>
