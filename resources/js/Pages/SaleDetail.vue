<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import AppLayout from '../Components/AppLayout.vue';
import FormErrors from '../Components/FormErrors.vue';
import { useBusiness } from '../composables';
import type { Sale } from '../types';
const props = defineProps<{ sale: Sale }>();
const { money, date, can } = useBusiness();
const open = ref(false);
const quantities = ref<Record<number, number>>({});
const form = useForm({
  request_key: crypto.randomUUID(),
  reason: '',
  items: [] as { allocation_id: number; quantity: number }[],
});
const allocations = computed(() =>
  props.sale.items.flatMap((item) =>
    item.allocations.map((a) => ({ ...a, description: item.description })),
  ),
);
function refund() {
  form.items = allocations.value
    .filter((a) => Number(quantities.value[a.id]) > 0)
    .map((a) => ({ allocation_id: a.id, quantity: Number(quantities.value[a.id]) }));
  if (!form.items.length) return;
  if (!confirm('Confirm the cash refund? Returned medicines will be quarantined.')) return;
  form.post('/sales/' + props.sale.id + '/returns', {
    onSuccess: () => {
      form.reset();
      form.request_key = crypto.randomUUID();
      quantities.value = {};
      open.value = false;
    },
  });
}
function print() {
  window.print();
}
</script>
<template>
  <AppLayout :title="sale.invoice_number" subtitle="Completed sale · immutable transaction"
    ><template #actions
      ><Link href="/sales" class="btn secondary">All sales</Link
      ><button class="btn secondary" @click="print"><Printer :size="16" /> Print invoice</button
      ><button v-if="can('sales.refund')" class="btn primary" @click="open = !open">
        Return items
      </button></template
    >
    <section class="panel invoice">
      <div class="invoice-header">
        <div>
          <span class="eyebrow">PHARMAFLOW · {{ sale.branch.name }}</span>
          <h2>Sales invoice</h2>
          <strong>{{ sale.invoice_number }}</strong>
        </div>
        <div>
          <p>{{ date(sale.created_at) }}</p>
          <p>Cashier: {{ sale.cashier.name }}</p>
          <p>Customer: {{ sale.customer?.name || 'Walk-in customer' }}</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Medicine</th>
              <th>Batch allocations</th>
              <th class="number">Quantity</th>
              <th class="number">Unit price</th>
              <th class="number">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in sale.items" :key="item.id">
              <td class="strong">{{ item.description }}</td>
              <td>
                <small v-for="a in item.allocations" :key="a.id" class="cell-small"
                  >{{ a.batch.lot_number }} · {{ a.quantity }} units ·
                  {{ a.returned_quantity }} returned</small
                >
              </td>
              <td class="number">{{ item.quantity }}</td>
              <td class="number">{{ money(item.unit_price) }}</td>
              <td class="number">{{ money(item.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="invoice-bottom">
        <div>
          <h3>Payments</h3>
          <p v-for="p in sale.payments" :key="p.id">{{ p.method }} · {{ money(p.amount) }}</p>
          <p>Cash change: {{ money(sale.change) }}</p>
        </div>
        <div class="invoice-totals">
          <p>
            <span>Subtotal</span><strong>{{ money(sale.subtotal) }}</strong>
          </p>
          <p>
            <span>Discount</span><strong>− {{ money(sale.discount) }}</strong>
          </p>
          <p>
            <span>Tax</span><strong>{{ money(sale.tax) }}</strong>
          </p>
          <p class="total">
            <span>Total</span><strong>{{ money(sale.total) }}</strong>
          </p>
        </div>
      </div>
      <p class="invoice-thanks">Thank you for choosing {{ sale.branch.name }}.</p>
    </section>
    <section v-if="open" class="panel form-section print:hidden">
      <h2>Return & cash refund</h2>
      <p class="muted">
        Only unreturned quantities can be refunded. Returned units enter quarantine and cannot be
        sold.
      </p>
      <form @submit.prevent="refund">
        <FormErrors :errors="form.errors" />
        <div v-for="a in allocations" :key="a.id" class="return-line">
          <span
            ><strong>{{ a.description }}</strong
            ><small class="cell-small"
              >Batch {{ a.batch.lot_number }} ·
              {{ a.quantity - a.returned_quantity }} returnable</small
            ></span
          ><label
            >Return quantity<input
              v-model.number="quantities[a.id]"
              type="number"
              min="0"
              :max="a.quantity - a.returned_quantity"
              placeholder="0"
          /></label>
        </div>
        <label>Return reason<textarea v-model="form.reason" required minlength="3" /></label
        ><button
          class="btn danger"
          :disabled="form.processing || !Object.values(quantities).some((q) => q > 0)"
        >
          {{ form.processing ? 'Processing…' : 'Confirm cash refund' }}
        </button>
      </form>
    </section>
    <section v-if="sale.returns.length" class="panel form-section">
      <h2>Return history</h2>
      <p v-for="r in sale.returns" :key="r.id">
        Return #{{ r.id }} · {{ money(r.total) }} · {{ r.reason }}
      </p>
    </section></AppLayout
  >
</template>
