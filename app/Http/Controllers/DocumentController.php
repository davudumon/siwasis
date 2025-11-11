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
        $perPage = $request->input('per_page', 10);
        $query = Document::with('admin')->orderByDesc('uploaded_at');

        // 🔍 Filter pencarian (judul / deskripsi)
        if ($request->filled('q')) {
            $search = "%{$request->q}%";
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        // 📅 Filter tanggal upload (rentang)
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('uploaded_at', [$request->from, $request->to]);
        }

        // 🔢 Pagination opsional
        $documents = $request->filled('page')
            ? $query->paginate($perPage)
            : $query->get();

        // 🧭 Response JSON dengan metadata lengkap
        return response()->json([
            'message' => 'Daftar dokumen berhasil diambil',
            'filters' => [
                'q' => $request->q ?? null,
                'from' => $request->from ?? null,
                'to' => $request->to ?? null,
                'page' => $request->page ?? null,
                'per_page' => $perPage,
            ],
            'pagination' => $request->filled('page') ? [
                'current_page' => $documents->currentPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
                'last_page' => $documents->lastPage(),
            ] : null,
            'data' => $request->filled('page') ? $documents->items() : $documents,
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
