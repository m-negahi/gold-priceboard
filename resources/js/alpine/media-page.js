import { panelRequest } from '../lib/request.js';

/** Modal haye ketabkhane az hamun API e picker estefade mikonan. */
export default ({ urls }) => ({
    urls,
    uploadOpen: false,
    editOpen: false,
    edit: {},
    saving: false,
    errors: {},

    openUpload() { this.uploadOpen = true; },
    uploaded() {
        this.uploadOpen = false;
        this.$dispatch('datatable-reload');
    },
    openEdit(row) {
        this.edit = { ...row, title: row.title ?? '' };
        this.errors = {};
        this.editOpen = true;
    },
    async saveEdit() {
        if (this.saving) return;
        this.saving = true;
        try {
            const result = await panelRequest(this.urls.update.replace('{id}', this.edit.id), {
                method: 'PUT', body: { alt: this.edit.alt, title: this.edit.title },
            });
            this.errors = Object.fromEntries(Object.entries(result.errors).map(([key, value]) => [`edit.${key}`, value]));
            if (result.ok) {
                this.editOpen = false;
                this.$dispatch('datatable-reload');
            }
        } finally {
            this.saving = false;
        }
    },
    async remove(row) {
        if (!(await this.$store.confirm.ask({ title: 'تصویر حذف شود؟', body: row.alt, danger: true }))) return;
        const { ok } = await panelRequest(this.urls.destroy.replace('{id}', row.id), { method: 'DELETE' });
        if (ok) this.$dispatch('datatable-reload');
    },
});
