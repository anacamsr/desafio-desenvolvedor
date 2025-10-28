<?php

namespace App\Http\Controllers;

use App\Models\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:32768|mimes:csv,txt,xlsx,xls,ods', 
            ], [
                'file.mimes' => 'O arquivo deve ser um CSV, TXT, XLS, XLSX ou ODS válido.',
            ]);

            $file = $request->file('file');
            
            $fileHash = 'TESTE_' . time() . rand(100, 999);
            
            $path = 'uploads/teste_temp_' . time() . '.mock';
            $uploadedFile = UploadedFile::create([
                'original_name' => $file->getClientOriginalName() . ' (MOCK)', 
                'storage_path' => $path, 
                'mime_type' => $file->getMimeType(), 
                'size' => $file->getSize(), 
                'file_hash' => $fileHash, 
            ]);

            return response()->json([
                'message' => 'TESTE DE BANCO DE DADOS BEM SUCEDIDO!', 
                'file' => [
                    'id' => $uploadedFile->id,
                    'original_name' => $uploadedFile->original_name,
                    'mime_type' => $uploadedFile->mime_type,
                    'size' => $uploadedFile->size,
                    'path_reference' => $uploadedFile->storage_path,
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error("Erro no upload: " . $e->getMessage() . " | " . $e->getFile() . ":" . $e->getLine());
            
            return response()->json([
                'message' => 'Ocorreu um erro interno. Verifique o log do Laravel.'
            ], 500);
        }
    }

    public function history(Request $request)
    {
        $query = UploadedFile::query();

        if ($request->has('file_name')) {
            $query->where('original_name', 'like', '%' . $request->file_name . '%');
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $uploads = $query->orderBy('created_at', 'desc')->get();

        return response()->json($uploads);
    }


}