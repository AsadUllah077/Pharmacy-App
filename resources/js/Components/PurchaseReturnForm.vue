<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormErrors from './FormErrors.vue';
import { useBusiness } from '../composables';
import type { Batch } from '../types';
const props = defineProps<{ orderId: number; batches: Batch[]; due: string }>();
const { money, can } = useBusiness();
const quantities = ref<Record<number, number>>({});
const buckets = ref<Record<number, string>>(
  Object.fromEntries(props.batches.map((b) => [b.id, 'available'])),
);
const form = useForm({
  reason: '',
  settlement: 'credit',
  method: 'bank',
  request_key: crypto.randomUUID(),
  items: [] as { batch_id: number; stock_bucket: string; quantity: number }[],
});
const total = computed(
  () =>
    props.batches.reduce(
      (sum, b) => sum + Math.round(Number(b.unit_cost) * 100) * Number(quantities.value[b.id] || 0),
      0,
    ) / 100,
);
const excess = computed(() => Math.max(0, total.value - Number(props.due)));
function submit() {
  form.items = props.batches
    .filter((b) => Number(quantities.value[b.id]) > 0)
    .map((b) => ({
      batch_id: b.id,
      stock_bucket: buckets.value[b.id] || 'available',
      quantity: Number(quantities.value[b.id]),
    }));
  if (
    !form.items.length ||
    !confirm(
      'Return these goods to the supplier and reverse their inventory value? Confirm quantities, lots and settlement first.',
    )
  )
    return;
  form.post('/purchases/' + props.orderId + '/returns', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      form.request_key = crypto.randomUUID();
      quantities.value = {};
      buckets.value = Object.fromEntries(props.batches.map((b) => [b.id, 'available']));
    },
  });
}
</script>
<template>
  <form @submit.prevent="submit">
    <FormErrors :errors="form.errors" />
    <p class="muted">
      Return only goods physically handed back to the supplier. Values use original batch cost.
      Available and quarantined stock are tracked separately.
    </p>
    <div v-for="b in batches" :key="b.id" class="receipt-line">
      <span
        ><strong>{{ b.medicine.name }}</strong
        ><small class="cell-small"
          >Lot {{ b.lot_number }} · {{ money(b.unit_cost) }} / unit</small
        ></span
      >
      <label
        >Stock to return<select
          :value="buckets[b.id] || 'available'"
          @change="buckets[b.id] = ($event.target as HTMLSelectElement).value"
        >
          <option value="available">Available ({{ b.quantity }})</option>
          <option value="quarantine">Quarantine ({{ b.quarantined_quantity }})</option>
        </select></label
      >
      <label
        >Return quantity<input
          v-model.number="quantities[b.id]"
          type="number"
          min="0"
          :max="buckets[b.id] === 'quarantine' ? b.quarantined_quantity : b.quantity"
          placeholder="0"
      /></label>
    </div>
    <label
      >Return reason<textarea v-model="form.reason" required minlength="3" maxlength="1000" />
    </label>
    <div class="form-grid">
      <label
        >Settlement of any paid portion<select v-model="form.settlement">
          <option value="credit">Keep as supplier credit</option>
          <option v-if="can('accounting.manage')" value="refund">Supplier refund received</option>
        </select></label
      >
      <label v-if="form.settlement === 'refund'"
        >Refund received by<select v-model="form.method">
          <option value="bank">Bank</option>
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="wallet">Wallet</option>
        </select></label
      >
    </div>
    <p class="notice">
      Return value: {{ money(total) }}. Reduces this order's balance by
      {{ money(Math.min(total, Number(due))) }}.
      {{ form.settlement === 'credit' ? 'Supplier credit' : 'Refund received' }}:
      {{ money(excess) }}.
    </p>
    <p class="muted">
      Supplier credits automatically settle other outstanding or future purchases from this supplier
      in this branch. Select refund received only after receiving that money.
    </p>
    <button class="btn danger" :disabled="form.processing || total <= 0">
      Confirm purchase return
    </button>
  </form>
</template>
