<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import FormErrors from '../Components/FormErrors.vue';
import { useBusiness } from '../composables';
import type { Order } from '../types';
const props = defineProps<{ order: Order }>();
const { branch, can, money } = useBusiness();
const pending = computed(() => props.order.items.filter((i) => i.received_quantity < i.quantity));
const form = useForm({
  warehouse_id: branch.value?.warehouses[0]?.id ?? '',
  request_key: crypto.randomUUID(),
  items: props.order.items
    .filter((i) => i.received_quantity < i.quantity)
    .map((i) => ({
      item_id: i.id,
      quantity: i.quantity - i.received_quantity,
      lot_number: '',
      expires_at: '',
    })),
});
function receive() {
  if (!confirm('Receive these goods and post the supplier liability?')) return;
  form
    .transform((data) => ({
      ...data,
      items: data.items
        .filter((i) => i.quantity > 0)
        .map((i) => ({ ...i, expires_at: i.expires_at || null })),
    }))
    .post('/purchases/' + props.order.id + '/receive', {
      onSuccess: () => {
        form.request_key = crypto.randomUUID();
        form.items = props.order.items
          .filter((i) => i.received_quantity < i.quantity)
          .map((i) => ({
            item_id: i.id,
            quantity: i.quantity - i.received_quantity,
            lot_number: '',
            expires_at: '',
          }));
      },
    });
}
</script>
<template>
  <AppLayout
    :title="'Purchase order #' + order.id"
    :subtitle="order.supplier.name + ' · ' + order.status"
    ><template #actions
      ><Link href="/purchases" class="btn secondary">All purchases</Link></template
    >
    <section class="panel">
      <div class="panel-heading">
        <h2>Ordered items</h2>
        <span class="badge amber">{{ order.status }}</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Medicine</th>
              <th>Ordered</th>
              <th>Received</th>
              <th class="number">Unit cost</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.items" :key="item.id">
              <td class="strong">{{ item.medicine.name }}</td>
              <td>{{ item.quantity }}</td>
              <td>{{ item.received_quantity }}</td>
              <td class="number">{{ money(item.unit_cost) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <section v-if="pending.length && can('inventory.receive')" class="panel form-section">
      <h2>Receive goods</h2>
      <p class="muted">
        Only received quantities become stock. Set quantity to zero to skip an item. Leave expiry
        blank only for products without an expiry date.
      </p>
      <form @submit.prevent="receive">
        <FormErrors :errors="form.errors" /><label
          >Receiving warehouse<select v-model="form.warehouse_id" required>
            <option v-for="w in branch?.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select></label
        >
        <div v-for="item in form.items" :key="item.item_id" class="receipt-line">
          <strong>{{ order.items.find((i) => i.id === item.item_id)?.medicine.name }}</strong
          ><label
            >Quantity<input
              v-model.number="item.quantity"
              type="number"
              min="0"
              :max="
                pending.find((i) => i.id === item.item_id)!.quantity -
                pending.find((i) => i.id === item.item_id)!.received_quantity
              " /></label
          ><label
            >Batch / lot<input v-model="item.lot_number" :required="item.quantity > 0" /></label
          ><label>Expiry date<input v-model="item.expires_at" type="date" /></label>
        </div>
        <button
          class="btn primary"
          :disabled="form.processing || !form.items.some((i) => i.quantity > 0)"
        >
          {{ form.processing ? 'Receiving…' : 'Confirm receipt' }}
        </button>
      </form>
    </section>
    <section class="panel form-section">
      <h2>Goods receipts</h2>
      <div v-for="receipt in order.receipts" :key="receipt.id" class="receipt-summary">
        <strong>GR-{{ receipt.id }} · {{ money(receipt.total) }}</strong>
        <p v-for="b in receipt.batches" :key="b.id">
          Batch {{ b.lot_number }} · {{ b.received_quantity ?? b.quantity }} units
        </p>
      </div>
      <p v-if="!order.receipts.length" class="empty">No stock has been received for this order.</p>
    </section></AppLayout
  >
</template>
