<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import Pagination from '../Components/Pagination.vue';
import FormErrors from '../Components/FormErrors.vue';
import { useBusiness } from '../composables';
import type { Branch, Pagination as Paged } from '../types';
interface Role {
  id: number;
  name: string;
  permissions: string[];
}
interface User {
  id: number;
  name: string;
  email: string;
  active: boolean;
  roles: Role[];
  branches: Branch[];
}
defineProps<{ users: Paged<User>; roles: Role[]; branches: Branch[] }>();
const { can, page } = useBusiness();
const open = ref(false);
const form = useForm({
  id: null as number | null,
  name: '',
  email: '',
  password: '',
  active: true,
  role_ids: [] as number[],
  branch_ids: [] as number[],
});
const organization = useForm({ name: '', code: '', timezone: 'Asia/Karachi', warehouse: '' });
function edit(user?: User) {
  form.reset();
  form.clearErrors();
  if (user) {
    form.id = user.id;
    form.name = user.name;
    form.email = user.email;
    form.active = user.active;
    form.role_ids = user.roles.map((r) => r.id);
    form.branch_ids = user.branches.map((b) => b.id);
  }
  open.value = true;
}
</script>
<template>
  <AppLayout title="Administration" subtitle="Control access across your pharmacy network."
    ><template #actions><button class="btn primary" @click="edit()">Add user</button></template>
    <form
      v-if="open"
      class="panel form-section"
      @submit.prevent="
        form.post('/administration/users', {
          onSuccess: () => {
            open = false;
            form.reset();
          },
        })
      "
    >
      <h2>{{ form.id ? 'Edit user' : 'New user' }}</h2>
      <FormErrors :errors="form.errors" />
      <div class="form-grid">
        <label>Name<input v-model="form.name" required /></label
        ><label>Email<input v-model="form.email" type="email" required /></label
        ><label
          >{{ form.id ? 'New password (optional)' : 'Password'
          }}<input
            v-model="form.password"
            type="password"
            minlength="12"
            :required="!form.id"
            autocomplete="new-password" /></label
        ><label class="check-label"><input v-model="form.active" type="checkbox" />Active</label>
      </div>
      <h3>Roles</h3>
      <div class="check-grid">
        <label v-for="r in roles" :key="r.id" class="check-label"
          ><input v-model="form.role_ids" type="checkbox" :value="r.id" />{{ r.name }}</label
        >
      </div>
      <h3>Branch access</h3>
      <div class="check-grid">
        <label v-for="b in branches" :key="b.id" class="check-label"
          ><input v-model="form.branch_ids" type="checkbox" :value="b.id" />{{ b.name }}</label
        >
      </div>
      <div class="modal-actions">
        <button type="button" class="btn secondary" @click="open = false">Cancel</button
        ><button class="btn primary" :disabled="form.processing">Save user</button>
      </div>
    </form>
    <section class="panel">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Roles</th>
              <th>Branches</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users.data" :key="u.id">
              <td class="strong">
                {{ u.name }}<small class="cell-small">{{ u.email }}</small>
              </td>
              <td>{{ u.roles.map((r) => r.name).join(', ') }}</td>
              <td>{{ u.branches.map((b) => b.name).join(', ') }}</td>
              <td>
                <span class="badge" :class="u.active ? 'green' : 'neutral'">{{
                  u.active ? 'Active' : 'Inactive'
                }}</span>
              </td>
              <td>
                <button v-if="u.id !== page.props.auth.user.id" class="text-link" @click="edit(u)">
                  Edit access</button
                ><span v-else class="muted">Your account</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="users.links" :total="users.total" />
    </section>
    <section class="panel form-section">
      <h2>Branches & warehouses</h2>
      <div class="branch-cards">
        <div v-for="b in branches" :key="b.id" class="branch-card">
          <span class="badge neutral">{{ b.code }}</span>
          <h3>{{ b.name }}</h3>
          <p v-for="w in b.warehouses" :key="w.id">{{ w.name }}</p>
        </div>
      </div>
      <form
        v-if="can('settings.manage')"
        @submit.prevent="
          organization.post('/administration/branches', { onSuccess: () => organization.reset() })
        "
      >
        <h3>Add branch with warehouse</h3>
        <FormErrors :errors="organization.errors" />
        <div class="form-grid">
          <label>Branch name<input v-model="organization.name" required /></label
          ><label>Code<input v-model="organization.code" required /></label
          ><label>Timezone<input v-model="organization.timezone" required /></label
          ><label>Warehouse name<input v-model="organization.warehouse" required /></label>
        </div>
        <button class="btn primary" :disabled="organization.processing">Create branch</button>
      </form>
    </section>
    <section class="panel form-section">
      <h2>Role permissions</h2>
      <details v-for="r in roles" :key="r.id">
        <summary>{{ r.name }}</summary>
        <p class="muted">{{ r.permissions.join(' · ') }}</p>
      </details>
    </section></AppLayout
  >
</template>
