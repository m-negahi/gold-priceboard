/**
 * Form-e upload-e yek tasvir (ketabkhane-ye rasane): drag-drop ya entekhab, pish-namayesh, alt-e
 * ejbari, progress-e vaghei ba XHR (fetch progress-e upload nadare). Natije ro ba event-e
 * `media-uploaded` (row-e hamun shekl-e jadval) be parent mide; ghavanin (mime, hajm, alt) hame
 * server-and va inja faghat neshun dade mishan (ADR-0020).
 */
export default ({ url, maxBytes = 5 * 1024 * 1024 }) => ({
    url,
    maxBytes,
    file: null,
    preview: null,
    alt: '',
    title: '',
    errors: {},
    progress: 0,
    uploading: false,
    dragging: false,

    destroy() { this.releasePreview(); },

    releasePreview() {
        if (this.preview) URL.revokeObjectURL(this.preview);
        this.preview = null;
    },

    reset() {
        this.releasePreview();
        this.file = null;
        this.preview = null;
        this.alt = '';
        this.title = '';
        this.errors = {};
        this.progress = 0;
        this.uploading = false;
        this.dragging = false;
        if (this.$refs.file) this.$refs.file.value = '';
    },

    pick(event) {
        this.take(event.target.files?.[0] ?? null);
    },

    drop(event) {
        this.dragging = false;
        this.take(event.dataTransfer?.files?.[0] ?? null);
    },

    take(file) {
        this.releasePreview();
        this.errors = {};
        this.file = file;
        this.preview = null;

        if (!file) return;

        if (file.size > this.maxBytes) {
            this.errors = { 'media.file': 'حجم فایل باید حداکثر ۵ مگابایت باشد.' };
            this.file = null;

            return;
        }

        // Title-e pishfarz az esm-e file (bedoone pasvand) — alt ro karbar khodesh minevise.
        if (!this.title) this.title = file.name.replace(/\.[a-z0-9]+$/i, '');

        if (file.type.startsWith('image/')) this.preview = URL.createObjectURL(file);
    },

    get canSubmit() {
        return !!this.file && this.alt.trim() !== '' && !this.uploading;
    },

    submit() {
        if (!this.file) {
            this.errors = { 'media.file': 'فایلی انتخاب نشده است.' };

            return;
        }

        if (this.alt.trim() === '') {
            this.errors = { 'media.alt': 'متن جایگزین (alt) الزامی است.' };

            return;
        }

        if (this.uploading) return;

        this.uploading = true;
        this.progress = 0;
        this.errors = {};

        const body = new FormData();
        body.append('file', this.file);
        body.append('alt', this.alt.trim());
        body.append('title', this.title.trim());

        const xhr = new XMLHttpRequest();
        const toasts = window.Alpine.store('toasts');

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100);
        });

        xhr.addEventListener('load', () => {
            this.uploading = false;

            let json = {};
            try { json = JSON.parse(xhr.responseText || '{}'); } catch { json = {}; }

            if (xhr.status === 419) {
                window.location.reload();

                return;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                toasts?.success(json.message ?? 'تصویر بارگذاری شد.');
                this.$dispatch('media-uploaded', json.data);
                this.reset();

                return;
            }

            // Key-haye server (`alt`, `file`) → esm-e namespaced-e field ha (`media.alt`) ta x-form.field peyda-shun kone.
            this.errors = Object.fromEntries(Object.entries(json.errors ?? {}).map(([key, value]) => [`media.${key}`, value]));
            const first = Object.values(this.errors).flat()[0] ?? json.message ?? 'بارگذاری انجام نشد.';
            toasts?.error(first);
        });

        xhr.addEventListener('error', () => {
            this.uploading = false;
            toasts?.error('اتصال برقرار نشد');
        });

        xhr.open('POST', this.url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
        xhr.send(body);
    },
});
