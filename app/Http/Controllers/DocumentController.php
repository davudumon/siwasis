<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index()
    {
        $document = Document::with('admin')->latest()->get();

        return response()->json($document, 201);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:225',
            'description' => 'required',
            'file_path' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:5120',
            'uploaded_at' => 'nullable|date'
        ]);

        $path = $request->file('file_path')->store('documents', 'public');

        $document = Document::create([
            'admin_id'   => $request->user()->id,
            'title'      => $request->input('title'),
            'description' => $request->input('description'),
            'file_path'  => $path,
            'uploaded_at' => $request->input('uploaded_at', now()),
        ]);

        return response()->json([
            'message' => 'Dokumen berhasil diupload',
            'data'    => $document,
        ], 201);
    }

    public function show($id){
        $document = Document::findOrFail($id);

        return response()->json($document, 201);
    }

    public function update(Request $request, $id){
        $document = Document::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:225',
            'description' => 'required',
            'file_path' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:5120',
            'uploaded_at' => 'nullable|date'
        ]);

        if ($request->hasFile('file_path')) {
            
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
            $document->file_path = $request->file('file_path')->store('documents', 'public');
        }

        $document->update($request->only(['title', 'description', 'uploaded_at']));

        return response()->json([
            'message' => 'Dokumen berhasil diupdate',
            'data' => $document
        ], 201);

    }

    public function destroy($id)
    {
        $document = Document::findOrFail($id);

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'message' => 'Artikel berhasil dihapus',
        ]);
    }
}
