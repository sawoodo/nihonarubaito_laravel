<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ImageController extends Controller
{
    public function index(Request $request)
    {
        // JS gallery modal calls GET admin/images expecting JSON
        if ($request->ajax() || $request->wantsJson()) {
            $images = Image::orderByDesc('id')->limit(100)->get();

            $data = [];
            foreach ($images as $img) {
                $data[] = [
                    'id'          => $img->id,
                    'name'        => $img->name,
                    'ext'         => $img->ext,
                    'title'       => $img->title ?? '',
                    'description' => $img->description ?? '',
                ];
            }

            return response()->json(['status' => 'ok', 'data' => $data]);
        }

        // Server-side rendered gallery
        $images = Image::orderByDesc('id')->limit(200)->get();

        return view('admin.images.index', [
            'activeSideMenu' => 'images',
            'images'         => $images,
        ]);
    }

    public function list(Request $request)
    {
        $search = $request->input('search', '');

        $query = Image::orderByDesc('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $images = $query->limit(50)->get();

        $data = [];
        foreach ($images as $img) {
            $fileName = $img->name . $img->ext;
            $data[] = [
                'id'          => $img->id,
                'file_name'   => $fileName,
                'title'       => $img->title ?? '',
                'description' => $img->description ?? '',
                'thumb_url'   => url('frontend/images/jobs/' . $fileName),
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:gif,jpg,jpeg,png|max:2048',
        ]);

        $file = $request->file('file');
        $name = time();
        $ext = '.' . $file->getClientOriginalExtension();
        $fileName = $name . $ext;

        $uploadPath = public_path('frontend/images/jobs');

        if (!File::isDirectory($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }

        $file->move($uploadPath, $fileName);

        // Resize to max 1024x768
        $this->resizeImage($uploadPath . '/' . $fileName, 1024, 768);

        // Create thumbnail
        $thumbPath = public_path('frontend/images/jobs/thumbnails');
        if (!File::isDirectory($thumbPath)) {
            File::makeDirectory($thumbPath, 0755, true);
        }
        copy($uploadPath . '/' . $fileName, $thumbPath . '/' . $fileName);
        $this->resizeImage($thumbPath . '/' . $fileName, 237, 202);

        $image = Image::create([
            'path' => public_path('frontend/images/jobs/'),
            'name' => (string) $name,
            'ext'  => $ext,
        ]);

        // CI3 Dropzone JS expects: { success: 'ture', image_data: { id, name } }
        return response()->json([
            'success'    => 'ture',
            'image_data' => [
                'id'   => $image->id,
                'name' => $fileName,
            ],
        ]);
    }

    public function updateInfo(Request $request, $id)
    {
        $image = Image::find($id);

        if (!$image) {
            return response()->json(['success' => false, 'message' => 'Image not found'], 404);
        }

        $image->update([
            'title'       => $request->input('title', ''),
            'description' => $request->input('description', ''),
        ]);

        return response()->json(['status' => 'ok', 'success' => true]);
    }

    private function resizeImage(string $path, int $maxWidth, int $maxHeight): void
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return;
        }

        $info = getimagesize($path);
        if (!$info) {
            return;
        }

        [$origWidth, $origHeight] = $info;

        if ($origWidth <= $maxWidth && $origHeight <= $maxHeight) {
            return;
        }

        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = (int) ($origWidth * $ratio);
        $newHeight = (int) ($origHeight * $ratio);

        $src = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/gif'  => imagecreatefromgif($path),
            default      => null,
        };

        if (!$src) {
            return;
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);

        if ($info['mime'] === 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        match ($info['mime']) {
            'image/jpeg' => imagejpeg($dst, $path, 90),
            'image/png'  => imagepng($dst, $path),
            'image/gif'  => imagegif($dst, $path),
        };

        imagedestroy($src);
        imagedestroy($dst);
    }
}
