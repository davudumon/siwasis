<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * GET /api/documents
     * Mengambil daftar dokumen (filter: page, q, from, to)
     */
    public function index(Request $request)
    {
        $query = Document::with('admin')->latest();

        // 🔍 Filter pencarian (berdasarkan judul / deskripsi)
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->q}%")
                  ->orWhere('description', 'like', "%{$request->q}%");
            });
        }

        // 📅 Filter tanggal upload
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('uploaded_at', [$request->from, $request->to]);
        }

        // 🔢 Pagination opsional
        $documents = $request->filled('page')
            ? $query->paginate(10)
            : $query->get();

        return response()->json([
            'message' => 'Daftar dokumen berhasil diambil',
            'filters' => [
                'q' => $request->q,
                'from' => $request->from,
                'to' => $request->to,
                'page' => $request->page,
            ],
            'data' => $documents,
        ]);
    }

    /**
     * POST /api/documents
     * Upload file baru (FormData)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file_path' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:5120',
        ]);

        $path = $request->file('file_path')->store('documents', 'public');

        $document = Document::create([
            'admin_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $path,
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Dokumen berhasil diupload',
            'data' => $document,
        ], 201);
    }

    /**
     * POST /api/documents/{id} + _method=PUT
     * Update file dan metadata
     */
    public function update(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file_path' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:5120',
        ]);

        // Jika ada file baru, hapus file lama
        if ($request->hasFile('file_path')) {
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
            $document->file_path = $request->file('file_path')->store('documents', 'public');
        }

        $document->update([
            'title' => $request->title,
            'description' => $request->description,
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Dokumen berhasil diperbarui',
            'data' => $document,
        ]);
    }

    /**
     * DELETE /api/documents/{id}
     * Hapus file dan record
     */
    public function destroy($id)
    {
        $document = Document::findOrFail($id);

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'message' => 'Dokumen berhasil dihapus',
        ]);
    }

    /**
     * GET /api/documents/{id}/download
     * Download file dokumen
     */
    public function download($id)
    {
        $document = Document::findOrFail($id);

        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File tidak ditemukan'], 404);
        }

        $filePath = Storage::disk('public')->path($document->file_path);
        $fileName = basename($filePath);

        return new StreamedResponse(function () use ($filePath) {
            $stream = fopen($filePath, 'r');
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => mime_content_type($filePath),
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ]);
    }
}
