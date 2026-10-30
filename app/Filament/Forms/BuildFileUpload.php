<?php

namespace App\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;

/**
 * Поле завантаження APK/OBB частинами з прогрес-баром, швидкістю й дозавантаженням.
 * Стан поля: ['path' => 'builds/…', 'size' => 123, 'name' => 'game.apk'] або null.
 */
class BuildFileUpload extends Field
{
    protected string $view = 'filament.forms.build-file-upload';

    protected string $kind = 'apk';

    protected string|Closure|null $appId = null;

    public function kind(string $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function appId(string|Closure|null $id): static
    {
        $this->appId = $id;

        return $this;
    }

    public function getAppId(): ?string
    {
        return $this->evaluate($this->appId);
    }
}
