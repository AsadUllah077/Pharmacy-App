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
const payment = useForm({ amount: '', method: 'cash', request_key: crypto.randomUUID() });
function collectPayment() {
  if (!confirm('Record this customer payment? Confirm the money has been received.')) return;
  payment.post('/sales/' + props.sale.id + '/payments', {
    preserveScroll: true,
    onSuccess: () => {
      payment.reset('amount');
      payment.request_key = crypto.randomUUID();
    },
  });
}
const quantities = ref<Record<number, number>>({});
const form = useForm({
  request_key: crypto.randomUUID(),
  reason: '',
  items: [] as { allocation_id: number; quantity: number }[],
});
const allocations = computed(() =>
  props.sale.items.flatMap((item) =>
    item.allocations.map((a) => ({
      ...a,
      description: item.description,
      returnPrice: Number(item.unit_price) - Number(item.unit_discount) + Number(item.unit_tax),
    })),
  ),
);
const returnValue = computed(
  () =>
    allocations.value.reduce(
      (sum, a) => sum + Math.round(a.returnPrice * 100) * Number(quantities.value[a.id] || 0),
      0,
    ) / 100,
);
function refund() {
  form.items = allocations.value
    .filter((a) => Number(quantities.value[a.id]) > 0)
    .map((a) => ({ allocation_id: a.id, quantity: Number(quantities.value[a.id]) }));
  if (!form.items.length) return;
  if (
    !confirm(
      'Confirm this return? Its value reduces the unpaid balance first; only the remainder is refunded in cash. Returned medicines will be quarantined.',
    )
  )
    return;
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
  <AppLayout :title="sale.invoice_number" subtitle="Sales invoice · immutable transaction"
    ><template #actions
      ><Link href="/sales" class="btn secondary">All sales</Link
      ><button class="btn secondary" @click="print"><Printer :size="16" /> Print invoice</button
      ><button v-if="can('sales.refund')" class="btn primary" @click="open = !open">
        Return items
      </button></template
    >
    <section
      v-if="Number(sale.payment_summary.due) > 0 && can('sales.create')"
      class="panel form-section print:hidden"
    >
      <h2>Receive customer payment</h2>
      <p>
        Remaining invoice balance: <strong>{{ money(sale.payment_summary.due) }}</strong
        >. Record a partial payment or settle the remaining amount.
      </p>
      <form @submit.prevent="collectPayment">
        <FormErrors :errors="payment.errors" />
        <div class="form-grid">
          <label
            >Payment amount<input
              v-model="payment.amount"
              type="number"
              min="0.01"
              :max="sale.payment_summary.due"
              step="0.01"
              required
          /></label>
          <label
            >Payment method<select v-model="payment.method">
              <option value="cash">Cash</option>
              <option value="bank">Bank</option>
              <option value="card">Card</option>
              <option value="wallet">Wallet</option>
            </select></label
          >
        </div>
        <div class="modal-actions">
          <button
            type="button"
            class="btn secondary"
            @click="payment.amount = sale.payment_summary.due"
          >
            Pay remaining balance</button
          ><button class="btn primary" :disabled="payment.processing">
            Record customer payment
          </button>
        </div>
      </form>
    </section>
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
          <p v-for="p in sale.payments" :key="p.id">
            {{ date(p.created_at) }} ? {{ p.method }} · {{ money(p.amount) }}
          </p>
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
          <p>
            <span>Returned value</span><strong>{{ money(sale.payment_summary.returned) }}</strong>
          </p>
          <p>
            <span>Net invoice</span><strong>{{ money(sale.payment_summary.net_total) }}</strong>
          </p>
          <p>
            <span>Total payments</span><strong>{{ money(sale.payment_summary.paid) }}</strong>
          </p>
          <p>
            <span>Cash refunded</span><strong>{{ money(sale.payment_summary.refunded) }}</strong>
          </p>
          <p>
            <span>Remaining balance</span
            ><strong
              >{{ money(sale.payment_summary.due) }} ? {{ sale.payment_summary.status }}</strong
            >
          </p>
          <p class="total">
            <span>Total</span><strong>{{ money(sale.total) }}</strong>
          </p>
        </div>
      </div>
      <p class="invoice-thanks">Thank you for choosing {{ sale.branch.name }}.</p>
    </section>
    <section v-if="open" class="panel form-section print:hidden">
      <h2>Return items</h2>
      <p class="muted">
        Only unreturned quantities can be returned. The value reduces the outstanding invoice
        balance first, then any remainder is refunded in cash. Returned units enter quarantine and
        cannot be sold.
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
        <p class="notice">
          Return value: {{ money(returnValue) }}. Balance reduction:
          {{ money(Math.min(returnValue, Number(sale.payment_summary.due))) }}. Cash refund:
          {{ money(Math.max(0, returnValue - Number(sale.payment_summary.due))) }}.
        </p>
        <label>Return reason<textarea v-model="form.reason" required minlength="3" /></label
        ><button
          class="btn danger"
          :disabled="form.processing || !Object.values(quantities).some((q) => q > 0)"
        >
          {{ form.processing ? 'Processing…' : 'Confirm return' }}
        </button>
      </form>
    </section>
    <section v-if="sale.returns.length" class="panel form-section">
      <h2>Return history</h2>
      <p v-for="r in sale.returns" :key="r.id">
        Return #{{ r.id }} · {{ money(r.total) }} · {{ r.reason }} ? Balance reduced:
        {{ money(r.balance_reduction) }}
      </p>
    </section></AppLayout
  >
</template>
