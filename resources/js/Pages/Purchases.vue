<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import AppLayout from '../Components/AppLayout.vue';
import Pagination from '../Components/Pagination.vue';
import FormErrors from '../Components/FormErrors.vue';
import MedicinePicker from '../Components/MedicinePicker.vue';
import { useBusiness } from '../composables';
import type { Order, Pagination as Paged, Party, Medicine } from '../types';
defineProps<{ orders: Paged<Order>; suppliers: Party[] }>();
const { can, date, money } = useBusiness();
const open = ref(false);
const form = useForm({
  supplier_id: '',
  items: [] as { medicine_id: number; name: string; quantity: number; unit_cost: string }[],
});
function add(m: Medicine) {
  if (!form.items.some((i) => i.medicine_id === m.id))
    form.items.push({ medicine_id: m.id, name: m.name, quantity: 1, unit_cost: '0.00' });
}
</script>
<template>
  <AppLayout title="Purchases" subtitle="Order with clarity. Receive with confidence."
    ><template #actions
      ><Link v-if="can('accounting.view')" href="/supplier-payments" class="btn secondary"
        >Supplier payments</Link
      ><button v-if="can('purchases.create')" class="btn primary" @click="open = !open">
        <Plus :size="17" /> New purchase order
      </button></template
    >
    <section v-if="open" class="panel form-section">
      <h2>Create purchase order</h2>
      <form @submit.prevent="form.post('/purchases')">
        <FormErrors :errors="form.errors" /><label
          >Supplier<select v-model="form.supplier_id" required>
            <option value="">Select supplier</option>
            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select></label
        ><MedicinePicker @select="add" />
        <div v-for="(item, index) in form.items" :key="item.medicine_id" class="line-form">
          <strong>{{ item.name }}</strong
          ><label
            >Quantity<input v-model.number="item.quantity" type="number" min="1" required /></label
          ><label
            >Unit cost<input
              v-model="item.unit_cost"
              type="number"
              min="0.01"
              step="0.01"
              required /></label
          ><button
            type="button"
            class="icon-button"
            aria-label="Remove item"
            @click="form.items.splice(index, 1)"
          >
            <Trash2 :size="17" />
          </button>
        </div>
        <button class="btn primary" :disabled="form.processing || !form.items.length">
          Create order
        </button>
      </form>
    </section>
    <section class="panel">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Order</th>
              <th>Supplier</th>
              <th>Created</th>
              <th>Receiving status</th>
              <th class="number">Paid</th>
              <th class="number">Remaining</th>
              <th>Payment status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders.data" :key="order.id">
              <td class="strong">PO-{{ String(order.id).padStart(6, '0') }}</td>
              <td>{{ order.supplier.name }}</td>
              <td>{{ date(order.created_at) }}</td>
              <td>
                <span class="badge" :class="order.status === 'received' ? 'green' : 'amber'">{{
                  order.status
                }}</span>
              </td>
              <td class="number">{{ money(order.payment_summary.paid) }}</td>
              <td class="number">{{ money(order.payment_summary.due) }}</td>
              <td>{{ order.payment_summary.status }}</td>
              <td>
                <Link class="text-link" :href="'/purchases/' + order.id"
                  >View / receive / pay →</Link
                >
              </td>
            </tr>
            <tr v-if="!orders.data.length">
              <td colspan="8" class="empty">
                No purchase orders yet. Create an order to start receiving stock.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="orders.links" :total="orders.total" /></section
  ></AppLayout>
</template>
