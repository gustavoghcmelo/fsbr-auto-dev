<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Abstrai o acesso a arquivos independente do driver (local, S3, R2).
 * Todos os módulos do sistema que precisam persistir arquivos devem
 * passar por aqui para centralizar convenção de path e geração de URL.
 */
class FileStorageService
{
    public function __construct(
        private readonly ?string $defaultDisk = null
    ) {}

    public function disk(?string $disk = null): Filesystem
    {
        return Storage::disk($disk ?? $this->defaultDisk ?? config('filesystems.default'));
    }

    /**
     * Armazena o arquivo enviado dentro do diretório informado,
     * gerando um nome único para evitar colisões.
     *
     * @return array{disk: string, path: string, filename: string, size: int, mime: string}
     */
    public function storeUpload(UploadedFile $file, string $directory, ?string $disk = null): array
    {
        $disk ??= $this->defaultDisk ?? config('filesystems.default');

        $filename = Str::uuid().'-'.Str::slug(
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
        ).'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs($directory, $filename, $disk);

        return [
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
        ];
    }

    public function get(string $path, ?string $disk = null): string
    {
        return $this->disk($disk)->get($path);
    }

    public function absolutePath(string $path, ?string $disk = null): string
    {
        return $this->disk($disk)->path($path);
    }

    /**
     * Garante um path local para leitura. Para discos remotos (s3/r2)
     * baixa o arquivo para um tmp, chama $callback e remove ao final.
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    public function withLocalCopy(string $path, callable $callback, ?string $disk = null): mixed
    {
        $disk ??= $this->defaultDisk ?? config('filesystems.default');
        $driver = config("filesystems.disks.{$disk}.driver");

        if ($driver === 'local') {
            return $callback($this->absolutePath($path, $disk));
        }

        $contents = $this->disk($disk)->get($path);
        $tmpPath = tempnam(sys_get_temp_dir(), 'fsbr-');
        file_put_contents($tmpPath, $contents);

        try {
            return $callback($tmpPath);
        } finally {
            @unlink($tmpPath);
        }
    }

    public function delete(string $path, ?string $disk = null): bool
    {
        return $this->disk($disk)->delete($path);
    }

    /**
     * Retorna uma URL temporária quando o driver suporta (s3/r2),
     * ou uma URL pública/local equivalente.
     */
    public function temporaryUrl(string $path, int $minutes = 10, ?string $disk = null): ?string
    {
        $fs = $this->disk($disk);

        try {
            return $fs->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\RuntimeException) {
            return method_exists($fs, 'url') ? $fs->url($path) : null;
        }
    }
}
