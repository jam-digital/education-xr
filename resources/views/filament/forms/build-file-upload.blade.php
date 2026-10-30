<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="xrBuildUpload({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            kind: @js($getKind()),
            appId: @js($getAppId()),
            urls: {
                init: @js(route('admin.uploads.init')),
                partUrl: @js(route('admin.uploads.part-url')),
                chunk: @js(route('admin.uploads.chunk')),
                complete: @js(route('admin.uploads.complete')),
                abort: @js(route('admin.uploads.abort')),
            },
            csrf: @js(csrf_token()),
        })"
        class="xr-upload"
        wire:ignore
    >
        <template x-if="!busy && !state">
            <label class="xr-upload__drop">
                <input type="file" class="xr-sr-only" :accept="'.' + kind" @change="start($event.target.files[0])">
                <span class="xr-upload__icon">⬆</span>
                <span>Оберіть файл <b x-text="'.' + kind"></b> (до 10 ГБ). Файл іде частинами напряму в сховище, обрив зв’язку не зупиняє завантаження.</span>
            </label>
        </template>

        <template x-if="busy">
            <div class="xr-upload__progress">
                <div class="xr-upload__row">
                    <b x-text="file?.name"></b>
                    <span x-text="pct + '%'"></span>
                </div>
                <div class="xr-bar xr-bar--lg"><span :style="'width:' + pct + '%'"></span></div>
                <div class="xr-upload__row xr-upload__meta">
                    <span x-text="fmt(sent) + ' з ' + fmt(file?.size || 0)"></span>
                    <span x-text="speedLabel"></span>
                    <button type="button" class="xr-link" @click="cancel()">Скасувати</button>
                </div>
                <div class="xr-upload__error" x-show="retryMsg" x-text="retryMsg"></div>
            </div>
        </template>

        <template x-if="!busy && state">
            <div class="xr-upload__done">
                <span>✓ <b x-text="state.name"></b> · <span x-text="fmt(state.size)"></span></span>
                <button type="button" class="xr-link" @click="state = null">Замінити</button>
            </div>
        </template>

        <div class="xr-upload__error" x-show="error" x-text="error"></div>
    </div>
</x-dynamic-component>
