// Менеджер білдів: завантаження APK/OBB частинами (див. resources/views/filament/forms/build-file-upload.blade.php)
    window.xrBuildUpload = function (cfg) {
        return {
            ...cfg, file: null, busy: false, sent: 0, error: '', retryMsg: '', speedLabel: '', session: null, aborted: false,
            get pct() { return this.file ? Math.floor(this.sent / this.file.size * 100) : 0 },
            fmt(b) {
                const u = ['Б', 'КБ', 'МБ', 'ГБ']; let i = 0;
                while (b >= 1024 && i < u.length - 1) { b /= 1024; i++ }
                return b.toFixed(i >= 2 ? 1 : 0).replace('.', ',') + ' ' + u[i];
            },
            async api(url, body) {
                const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf }, body: JSON.stringify(body) });
                const j = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(j.message || ('HTTP ' + r.status));
                return j;
            },
            // повтор частини з паузою 2, 4, 8… с — дозавантаження після обриву Wi-Fi
            async retry(fn) {
                for (let attempt = 0; ; attempt++) {
                    try { const v = await fn(); this.retryMsg = ''; return v }
                    catch (e) {
                        if (this.aborted || attempt >= 8) throw e;
                        const wait = Math.min(60, 2 ** (attempt + 1));
                        this.retryMsg = 'Зв’язок перервано, повтор через ' + wait + ' с…';
                        await new Promise(r => setTimeout(r, wait * 1000));
                    }
                }
            },
            async start(file) {
                if (!file) return;
                if (!file.name.toLowerCase().endsWith('.' + this.kind)) { this.error = 'Потрібен файл .' + this.kind; return }
                if (!this.appId) { this.error = 'Спершу збережіть додаток.'; return }
                Object.assign(this, { file, busy: true, sent: 0, error: '', aborted: false });
                const t0 = Date.now();
                try {
                    const s = this.session = await this.api(this.urls.init, { app_id: this.appId, kind: this.kind, filename: file.name, size: file.size });
                    const parts = [];
                    for (let off = 0, n = 1; off < file.size; off += s.part_size, n++) {
                        if (this.aborted) return;
                        const blob = file.slice(off, Math.min(off + s.part_size, file.size));
                        if (s.mode === 's3') {
                            const etag = await this.retry(async () => {
                                const { url } = await this.api(this.urls.partUrl, { key: s.key, upload_id: s.upload_id, part: n });
                                const r = await fetch(url, { method: 'PUT', body: blob });
                                if (!r.ok) throw new Error('HTTP ' + r.status);
                                return r.headers.get('ETag');
                            });
                            parts.push({ PartNumber: n, ETag: etag });
                        } else {
                            await this.retry(async () => {
                                const r = await fetch(this.urls.chunk + '?upload_id=' + s.upload_id + '&offset=' + off, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/octet-stream', 'Accept': 'application/json' }, body: blob });
                                if (!r.ok) throw new Error('HTTP ' + r.status);
                            });
                        }
                        this.sent = off + blob.size;
                        const sec = (Date.now() - t0) / 1000;
                        const bps = this.sent / Math.max(sec, 0.1);
                        this.speedLabel = this.fmt(bps) + '/с · ще ~' + Math.ceil((file.size - this.sent) / bps / 60) + ' хв';
                    }
                    const done = await this.api(this.urls.complete, { key: s.key, upload_id: s.upload_id, parts });
                    this.state = { path: done.path, size: done.size, name: file.name };
                } catch (e) {
                    if (!this.aborted) this.error = 'Не вдалося завантажити: ' + e.message;
                } finally {
                    this.busy = false;
                }
            },
            async cancel() {
                this.aborted = true; this.busy = false;
                if (this.session) await this.api(this.urls.abort, { key: this.session.key, upload_id: this.session.upload_id }).catch(() => {});
            },
        };
    };
