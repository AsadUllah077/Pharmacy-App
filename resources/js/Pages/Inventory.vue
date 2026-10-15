<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import AppLayout from '../Components/AppLayout.vue';
import Pagination from '../Components/Pagination.vue';
import FormErrors from '../Components/FormErrors.vue';
import { useBusiness } from '../composables';
import type { Batch, Pagination as Paged } from '../types';
const props = defineProps<{ batches: Paged<Batch>; filter: string; q: string; today: string }>();
const { can, money } = useBusiness();
const q = ref(props.q);
const form = useForm({ reason: '' });
const selected = ref<Batch | null>(null);
function quarantine() {
  if (!selected.value || !confirm('Move all available units of this batch into quarantine?'))
    return;
  form.post('/inventory/' + selected.value.id + '/quarantine', {
    onSuccess: () => {
      selected.value = null;
      form.reset();
    },
  });
}
</script>
<template>
  <AppLayout title="Inventory & expiry" subtitle="Know what you have. Protect what you dispense."
    ><template #actions
      ><Link href="/movements" class="btn secondary">Stock movements</Link
      ><Link v-if="can('purchases.view')" href="/purchases" class="btn primary"
        >Receive stock</Link
      ></template
    >
    <div class="filter-tabs">
      <Link
        v-for="[value, label] in [
          ['all', 'All batches'],
          ['near', 'Near expiry'],
          ['expired', 'Expired'],
          ['quarantine', 'Quarantined'],
        ]"
        :key="value"
        :href="'/inventory?filter=' + value"
        :class="{ active: filter === value }"
        >{{ label }}</Link
      >
    </div>
    <section class="panel">
      <form class="table-toolbar" @submit.prevent="router.get('/inventory', { q, filter })">
        <div class="search-field">
          <Search :size="18" /><input
            v-model="q"
            placeholder="Medicine or lot number"
            aria-label="Search stock"
          />
        </div>
        <button class="btn secondary">Search</button>
      </form>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Medicine / batch</th>
              <th>Warehouse</th>
              <th>Expiry</th>
              <th class="number">Available balance</th>
              <th class="number">Quarantine</th>
              <th class="number">Unit cost</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in batches.data" :key="b.id">
              <td class="strong">
                {{ b.medicine.name }}<small class="cell-small">{{ b.lot_number }}</small>
              </td>
              <td>{{ b.warehouse.name }}</td>
              <td>{{ b.expires_at?.slice(0, 10) || 'Not applicable' }}</td>
              <td class="number">{{ b.quantity }}</td>
              <td class="number">{{ b.quarantined_quantity }}</td>
              <td class="number">{{ money(b.unit_cost) }}</td>
              <td>
                <span
                  class="badge"
                  :class="
                    b.expires_at && b.expires_at.slice(0, 10) <= today
                      ? 'red'
                      : b.status === 'available'
                        ? 'green'
                        : 'amber'
                  "
                  >{{
                    b.expires_at && b.expires_at.slice(0, 10) <= today ? 'Expired' : b.status
                  }}</span
                >
              </td>
              <td>
                <button
                  v-if="can('inventory.adjust') && b.quantity > 0"
                  class="text-link"
                  @click="selected = b"
                >
                  Quarantine
                </button>
              </td>
            </tr>
            <tr v-if="!batches.data.length">
              <td colspan="8" class="empty">No batches match this view.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="batches.links" :total="batches.total" />
    </section>
    <p class="muted note">
      Available balance is the recorded unquarantined quantity. Expired batches are blocked from
      checkout regardless of their balance.
    </p>
    <div v-if="selected" class="modal-backdrop">
      <form class="modal" @submit.prevent="quarantine">
        <h2>Quarantine {{ selected.lot_number }}</h2>
        <FormErrors :errors="form.errors" /><label
          >Reason<textarea v-model="form.reason" required minlength="3" />
        </label>
        <div class="modal-actions">
          <button class="btn secondary" type="button" @click="selected = null">Cancel</button
          ><button class="btn danger" :disabled="form.processing">Quarantine batch</button>
        </div>
      </form>
    </div></AppLayout
  >
</template>
