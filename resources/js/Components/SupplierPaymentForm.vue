<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FormErrors from './FormErrors.vue';
import { useBusiness } from '../composables';
const props = defineProps<{ supplierId: number; orderId?: number; due: string }>();
const { money } = useBusiness();
const payment = useForm({
  supplier_id: props.supplierId,
  purchase_order_id: props.orderId ?? null,
  amount: '',
  method: 'bank',
  request_key: crypto.randomUUID(),
});
function submit() {
  if (!confirm('Record this supplier payment? Confirm that the payment has actually been made.'))
    return;
  payment
    .transform((data) => ({
      ...data,
      supplier_id: props.supplierId,
      purchase_order_id: props.orderId ?? null,
    }))
    .post('/supplier-payments', {
      preserveScroll: true,
      onSuccess: () => {
        payment.reset('amount');
        payment.request_key = crypto.randomUUID();
      },
    });
}
</script>
<template>
  <form @submit.prevent="submit">
    <FormErrors :errors="payment.errors" />
    <p class="muted">
      Remaining balance: {{ money(due) }}. Pay any part now and record the rest later.
    </p>
    <div class="form-grid">
      <label
        >Payment amount<input
          v-model="payment.amount"
          type="number"
          min="0.01"
          :max="due"
          step="0.01"
          required
      /></label>
      <label
        >Payment method<select v-model="payment.method">
          <option value="bank">Bank</option>
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="wallet">Wallet</option>
        </select></label
      >
    </div>
    <div class="modal-actions">
      <button type="button" class="btn secondary" @click="payment.amount = due">
        Pay remaining balance
      </button>
      <button class="btn primary" :disabled="payment.processing || Number(due) <= 0">
        Record supplier payment
      </button>
    </div>
  </form>
</template>
