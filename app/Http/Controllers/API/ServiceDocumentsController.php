<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\ServiceDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ServiceDocumentsController extends ResponsesController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $documents = $this->fetchServiceDocuments()->get();
        $this->saveToLog('Documents', 'Getting list of documents');
        return $this->sendResponse($documents, '');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDocuments(Request $request)
    {
        // Datatable search & pagination parameters
        $dt = $this->dtResponse($request);
        $searchValue = $dt->searchValue;

        $totalRecords = count($this->fetchServiceDocuments()->get());

        $totalRecordswithFilter =
            count(
                $this->fetchServiceDocuments()
                ->where(function ($query) use ($searchValue) {
                    $query
                        ->where('d.name', 'like', '%' . $searchValue . '%')
                        ->orWhere('d.description', 'like', '%' . $searchValue . '%');
                })->get()
            );

        // Fetch records
        $records = $this->fetchServiceDocuments()
                ->where(function ($query) use ($searchValue) {
                    $query
                        ->where('d.name', 'like', '%' . $searchValue . '%')
                        ->orWhere('d.description', 'like', '%' . $searchValue . '%');
                })
                ->skip($dt->start)
                ->take($dt->rowPerPage)
                ->get();

        $this->saveToLog('Documents', 'Getting list of documents');
        return $this->sendDTResponse($records, $totalRecords, $totalRecordswithFilter, $dt->draw);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Update document through post
        if ($request->has('id'))
            return $this->update($request, $request->get('id'));

        $validator = Validator::make($request->all(), [
            'documentId' => 'required',
            'filePath' => 'required|mimes:pdf',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $path = 'documents';

        if ($request->hasFile('filePath')) {
            $fileNameWithExt = $request->file('filePath')->getClientOriginalName();
            $filename = pathinfo($fileNameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('filePath')->getClientOriginalExtension();
            $savedDocument = $filename . '_' . time() . '.' . $extension;
            $request->file('filePath')->storeAs('public/' . $path, $savedDocument);
        }

        // Save document
        $document = new ServiceDocument;
        $document->user_id = auth()->user()->id;
        $document->document_id = $request->get('documentId');
        $document->file_path = $path . "/" . $savedDocument;
        $document->save();

        $this->saveToLog('Documents', 'Uploaded service document with name: ' . $request->get('name'));
        return $this->sendResponse([], 'Service document has been uploaded!');
    }

    public static function fetchServiceDocuments()
    {
        return DB::table('service_documents as sd')
            ->join('documents as d', 'd.id', '=', 'sd.document_id')
            ->selectRaw('
                            sd.id,
                            d.id as document_id,
                            d.name,
                            d.description,
                            sd.file_path
            ')
            ->whereRaw('sd.user_id = ?', [ auth()->user()->id ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'documentId' => 'required',
            'filePath' => 'required|mimes:pdf',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        $path = 'documents';

        if ($request->hasFile('filePath')) {
            $fileNameWithExt = $request->file('filePath')->getClientOriginalName();
            $filename = pathinfo($fileNameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('filePath')->getClientOriginalExtension();
            $savedDocument = $filename . '_' . time() . '.' . $extension;
            $request->file('filePath')->storeAs('public/' . $path, $savedDocument);
        }

        // Save document
        $document = ServiceDocument::find($id);
        $document->user_id = auth()->user()->id;
        $document->document_id = $request->get('documentId');
        $document->file_path = $path . "/" . $savedDocument;
        $document->save();

        $this->saveToLog('Documents', 'Uploaded service document with name: ' . $request->get('name'));
        return $this->sendResponse([], 'Service document has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $sdocument = ServiceDocument::find($id);
        $documentName = $sdocument->doc->name;
        ServiceDocument::destroy($id);
        $this->saveToLog('Documents', 'Deleted document: ' . $documentName);
        return $this->sendResponse([], 'Document has been deleted!');
    }

    
}
