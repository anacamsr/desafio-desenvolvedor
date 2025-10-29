<?php

namespace App\Http\Controllers;

use App\Models\FileContent;
use App\Models\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:131072|mimes:csv,txt,xlsx,xls,ods',
            ], [
                'file.mimes' => 'O arquivo deve ser um CSV, TXT, XLS, XLSX ou ODS válido.',
            ]);

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            
            $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $storageDir = 'uploads';

            $uploadedFilePath = $file->storeAs($storageDir, $fileName, 'local'); 

            $fullPathForHash = Storage::path($uploadedFilePath);

            $uploadedFile = UploadedFile::create([
                'original_name' => $originalName,
                'storage_path' => $uploadedFilePath,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'file_hash' => hash_file('sha256', $fullPathForHash), 
            ]);

            $this->importCsvToDatabase($uploadedFilePath);

            return response()->json([
                'message' => 'Upload e importação de arquivo bem-sucedidos!',
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

    public function importCsvToDatabase($uploadedFilePath)
    {
        $fullPath = Storage::path($uploadedFilePath);

        if (!file_exists($fullPath)) {
            \Log::error("Arquivo não encontrado para importação: " . $fullPath);
            return;
        }

        $allCsvLines = file($fullPath);
        $csvData = array_map('str_getcsv', $allCsvLines);

        if (count($csvData) < 1) {
            return; 
        }

        $columnMap = [
            'RptDt' => ['RptDt', 'RPT_DT', 'REPORTDATE', 'DATA', 'DATE'],
            'TckrSymb' => ['TckrSymb', 'TICKER'],
            'MktNm' => ['MktNm', 'MARKETNAME'],
            'SctyCtgyNm' => ['SctyCtgyNm', 'CATEGORY'],
            'ISIN' => ['ISIN'],
            'CrpnNm' => ['CrpnNm', 'CORPNAME', 'COMPANY'],
        ];

        $requiredColumn = 'RptDt';
        $foundHeader = null; 
        $dataStartIndex = -1; 
        
        for ($i = 0; $i < min(5, count($csvData)); $i++) {
            $possibleHeader = array_map('trim', $csvData[$i]);
            $normalizedHeader = array_map('strtoupper', $possibleHeader);
            
            $requiredFound = false;
            foreach ($columnMap[$requiredColumn] as $name) {
                if (in_array(strtoupper($name), $normalizedHeader)) {
                    $requiredFound = true;
                    break;
                }
            }
            
            if ($requiredFound) {
                $foundHeader = $possibleHeader;
                $dataStartIndex = $i + 1;
                break;
            }
        }

        if (!$foundHeader) {
            \Log::error("Importação falhou: A coluna obrigatória '{$requiredColumn}' não foi encontrada nas primeiras 5 linhas do CSV.");
            return; 
        }
        
        $header = $foundHeader;
        
        $matchedColumns = [];
        foreach ($columnMap as $dbColumn => $possibleNames) {
            foreach ($possibleNames as $possibleName) {
                $normalizedName = trim(strtoupper($possibleName));
                $index = array_search($normalizedName, array_map('strtoupper', $header));
                
                if ($index !== false) {
                    $matchedColumns[$dbColumn] = $header[$index]; 
                    break;
                }
            }
        }
        
        for ($i = $dataStartIndex; $i < count($csvData); $i++) {
            $row = $csvData[$i];
            
            if (count($row) < count($header)) {
                 \Log::warning("Linha de dados pulada: contagem de colunas insuficiente. Linha: " . json_encode($row));
                 continue;
            }
            
            $csvRow = @array_combine($header, $row);

            if ($csvRow === false) {
                 \Log::warning("Linha pulada: Falha ao combinar cabeçalho/linha. Linha: " . json_encode($row));
                 continue;
            }

            $rptDtKey = $matchedColumns['RptDt'];
            if (empty($csvRow[$rptDtKey])) {
                \Log::warning("Linha do CSV pulada: Coluna 'RptDt' é obrigatória e está nula. Linha: " . json_encode($row));
                continue; 
            }

            try {
                FileContent::create([
                    'RptDt' => $csvRow[$rptDtKey],
                    'TckrSymb' => $csvRow[$matchedColumns['TckrSymb']] ?? null,
                    'MktNm' => $csvRow[$matchedColumns['MktNm']] ?? null,
                    'SctyCtgyNm' => $csvRow[$matchedColumns['SctyCtgyNm']] ?? null,
                    'ISIN' => $csvRow[$matchedColumns['ISIN']] ?? null,
                    'CrpnNm' => $csvRow[$matchedColumns['CrpnNm']] ?? null,
                ]);
            } catch (\Exception $e) {
                \Log::warning("ERRO DE DADOS CSV: Falha ao inserir linha. Motivo: " . $e->getMessage() . " | Linha CSV: " . json_encode($csvRow));
                continue; 
            }
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

    public function search(Request $request)
    {
        try {
            $query = FileContent::query();

            if ($request->filled('TckrSymb')) {
                $query->where('TckrSymb', $request->TckrSymb);
            }

            if ($request->filled('RptDt')) {
                $query->where('RptDt', $request->RptDt);
            }

            if (!$request->filled('TckrSymb') && !$request->filled('RptDt')) {
                $results = $query->paginate(20);
            } else {
                $results = $query->get();
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Falha ao buscar dados',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json($results);
    }
}