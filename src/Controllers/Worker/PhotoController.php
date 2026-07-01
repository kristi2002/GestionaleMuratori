<?php
declare(strict_types=1);

namespace App\Controllers\Worker;

use App\Http\Middleware\AuthGuard;
use App\Http\Middleware\InterventionOwnerGuard;
use App\Models\InterventionModel;
use App\Models\PhotoModel;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Storage\LocalStorage;
use App\Support\View;

final class PhotoController
{
    private const ALLOWED_TYPES   = ['before', 'during', 'after'];
    private const MAX_BYTES       = 8 * 1024 * 1024;
    private const THUMB_MAX_DIM   = 480;

    public function upload(Request $request, string $id): void
    {
        AuthGuard::require($request, ['worker']);
        $intervention = InterventionOwnerGuard::require($request, $id, (int) Auth::id());
        if ($intervention === null) {
            return;
        }

        $type = (string) $request->input('type', '');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            Response::fail(Lang::get('worker.photo_upload_failed'), 422);
            return;
        }

        $file = $_FILES['photo'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::fail(Lang::get('worker.photo_upload_failed'), 422);
            return;
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            Response::fail(Lang::get('worker.photo_too_large'), 422);
            return;
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            Response::fail(Lang::get('worker.photo_invalid_type'), 422);
            return;
        }

        $ext        = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        $projectId  = (int) $intervention['project_id'];
        $baseName   = $type . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $relOriginal = "{$projectId}/{$id}/{$baseName}.{$ext}";
        $relThumb    = "{$projectId}/{$id}/thumb_{$baseName}.jpg";

        $storage = new LocalStorage((string) Config::get('storage.uploads_path'));
        $storage->putUploadedFile($relOriginal, $file['tmp_name']);
        $this->makeThumbnail($storage->absolutePath($relOriginal), (int) $info[2], $storage->absolutePath($relThumb));
        if (!$storage->exists($relThumb)) {
            $relThumb = null; // GD failed — stream() falls back to the original
        }

        $photoId = (new PhotoModel())->create([
            'intervention_id' => (int) $id,
            'project_id'      => $projectId,
            'type'            => $type,
            'file_path'       => $relOriginal,
            'thumb_path'      => $relThumb,
            'uploaded_by'     => Auth::id(),
        ]);

        Response::ok(['id' => $photoId]);
    }

    public function show(Request $request, string $id): void
    {
        $this->stream($request, $id, true);
    }

    public function thumb(Request $request, string $id): void
    {
        $this->stream($request, $id, false);
    }

    private function stream(Request $request, string $id, bool $original): void
    {
        AuthGuard::require($request, ['worker']);

        $photo = (new PhotoModel())->find((int) $id);
        if ($photo === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }
        $intervention = InterventionOwnerGuard::require($request, (string) $photo['intervention_id'], (int) Auth::id());
        if ($intervention === null) {
            return;
        }

        $storage = new LocalStorage((string) Config::get('storage.uploads_path'));
        $relPath = $original ? $photo['file_path'] : ($photo['thumb_path'] ?? $photo['file_path']);
        if (!$storage->exists($relPath)) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        header('Content-Type: ' . (str_ends_with($relPath, '.png') ? 'image/png' : 'image/jpeg'));
        header('Cache-Control: private, max-age=86400');
        echo $storage->get($relPath);
    }

    private function makeThumbnail(string $srcAbsPath, int $srcImageType, string $destAbsPath): void
    {
        $src = $srcImageType === IMAGETYPE_PNG ? imagecreatefrompng($srcAbsPath) : imagecreatefromjpeg($srcAbsPath);
        if ($src === false) {
            return; // best-effort; photo stream() falls back to the original when no thumb exists
        }

        $width  = imagesx($src);
        $height = imagesy($src);
        $scale  = min(1.0, self::THUMB_MAX_DIM / max($width, $height));
        $thumbW = max(1, (int) round($width * $scale));
        $thumbH = max(1, (int) round($height * $scale));

        $thumb = imagecreatetruecolor($thumbW, $thumbH);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumbW, $thumbH, $width, $height);

        $dir = dirname($destAbsPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        imagejpeg($thumb, $destAbsPath, 80);

        imagedestroy($src);
        imagedestroy($thumb);
    }
}
