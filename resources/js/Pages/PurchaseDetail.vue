<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import FormErrors from '../Components/FormErrors.vue';
import { useBusiness } from '../composables';
import SupplierPaymentForm from '../Components/SupplierPaymentForm.vue';
import PurchaseReturnForm from '../Components/PurchaseReturnForm.vue';
import type { Order } from '../types';
const props = defineProps<{
  order: Order;
  paymentHistory: { id: number; amount: string; method: string; created_at: string }[];
  purchaseReturns: {
    id: number;
    reason: string;
    total: string;
    credit_amount: string;
    refund_amount: string;
    refund_method: string | null;
    created_at: string;
  }[];
  supplierCredit: string;
}>();
const { branch, can, money, date } = useBusiness();
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
    <div class="metric-grid three">
      <div class="metric-card">
        <span>Received value</span><strong>{{ money(order.payment_summary.received) }}</strong
        ><small>Purchase returns: {{ money(order.payment_summary.returned) }}</small>
      </div>
      <div class="metric-card">
        <span>Payments recorded</span><strong>{{ money(order.payment_summary.paid) }}</strong
        ><small>Supplier credit applied: {{ money(order.payment_summary.credit_applied) }}</small>
      </div>
      <div class="metric-card">
        <span>Remaining to pay</span><strong>{{ money(order.payment_summary.due) }}</strong
        ><small>{{ order.payment_summary.status }}</small>
      </div>
    </div>
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
    </section>
    <section id="payments" class="panel form-section">
      <h2>Supplier payment</h2>
      <p>
        Supplier: {{ order.supplier.name }}. Available supplier credit: {{ money(supplierCredit) }}.
      </p>
      <SupplierPaymentForm
        v-if="can('accounting.manage') && Number(order.payment_summary.due) > 0"
        :supplier-id="order.supplier.id"
        :order-id="order.id"
        :due="order.payment_summary.due"
      />
      <p v-else class="muted">
        {{
          Number(order.payment_summary.due) > 0
            ? 'An accountant can record partial or full payment for this purchase.'
            : 'No outstanding payment for the goods received so far.'
        }}
      </p>
      <h3>Payment history for this purchase</h3>
      <p v-for="p in paymentHistory" :key="p.id">
        SP-{{ p.id }} ? {{ money(p.amount) }} ? {{ p.method }} ? {{ date(p.created_at) }}
      </p>
      <p v-if="!paymentHistory.length" class="muted">No payments allocated to this purchase.</p>
    </section>
    <section v-if="can('purchases.return') && order.receipts.length" class="panel form-section">
      <h2>Return goods to supplier</h2>
      <PurchaseReturnForm
        :order-id="order.id"
        :batches="order.receipts.flatMap((r) => r.batches)"
        :due="order.payment_summary.due"
      />
    </section>
    <section class="panel form-section">
      <h2>Purchase return history</h2>
      <div v-for="r in purchaseReturns" :key="r.id" class="receipt-summary">
        <strong>PR-{{ r.id }} ? {{ money(r.total) }}</strong>
        <p>{{ r.reason }} ? {{ date(r.created_at) }}</p>
        <p>
          Supplier credit issued: {{ money(r.credit_amount) }} ? Refund received:
          {{ money(r.refund_amount) }} {{ r.refund_method || '' }}
        </p>
      </div>
      <p v-if="!purchaseReturns.length" class="muted">
        No goods returned to this supplier for this purchase.
      </p>
    </section>
  </AppLayout>
</template>
