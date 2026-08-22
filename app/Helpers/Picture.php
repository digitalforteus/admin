<?php

namespace App\Helpers;

use BackedEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A picture a record is shown by, addressed by the row and the column that holds it.
 *
 * Nothing here is bound to one kind of record: the column a case names is the whole
 * of the association, so a second table wanting a picture declares a column and a
 * directory and reads the same rules, the same disk and the same url. What is stored
 * is a path inside the disk and never a url, so the disk alone decides how a file is
 * addressed. Replacing or removing a picture discards the file it replaces, because
 * nothing else ever will — and a directory shared by two columns lets one of them
 * delete what the other still points at.
 */
final readonly class Picture
{
    public const int kilobytes = 2048;

    private function __construct(
        private Model $Model,
        private BackedEnum $Column,
        private Directory $Directory,
    ) {}

    public static function of(Model $Model, BackedEnum $BackedEnum, Directory $Directory): self
    {
        return new self($Model, $BackedEnum, $Directory);
    }

    /** @return list<string|ValidationRule> The rules an uploaded picture answers to. */
    public static function rules(): array
    {
        return [
            Rule::required->value,
            Rule::image->value,
            Rule::mimes(...Extension::imageValues()),
            Rule::max(self::kilobytes),
        ];
    }

    public function put(?UploadedFile $UploadedFile): void
    {
        $path = $UploadedFile?->store($this->Directory->value, Disk::public->value);

        $this->discard();

        $this->Model->update([$this->column() => is_string($path) ? $path : null]);
    }

    public function clear(): void
    {
        $this->discard();

        $this->Model->update([$this->column() => null]);
    }

    public function url(): ?string
    {
        $path = $this->path();

        return $path !== null ? Disk::public->url($path) : null;
    }

    private function column(): string
    {
        return (string) $this->Column->value;
    }

    private function path(): ?string
    {
        $path = $this->Model->getAttribute($this->column());

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function discard(): void
    {
        $path = $this->path();

        if ($path !== null) {
            Storage::disk(Disk::public->value)->delete($path);
        }
    }
}
