<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponsesController;
use App\Models\Document;
use Dotenv\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator as FacadesValidator;

class DocumentsController extends ResponsesController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $documents = Document::all();
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

        $totalRecords = Document::count();

        $totalRecordswithFilter = Document::where(function ($query) use ($searchValue) {
                    $query
                        ->where('name', 'like', '%' . $searchValue . '%')
                        ->orWhere('description', 'like', '%' . $searchValue . '%');
                })->count();

        // Fetch records
        $records = Document::where(function ($query) use ($searchValue) {
                $query
                    ->where('name', 'like', '%' . $searchValue . '%')
                    ->orWhere('description', 'like', '%' . $searchValue . '%');
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

        $validator = FacadesValidator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        // Save document
        $document = new Document;
        $document->name = $request->get('name');
        $document->description = $request->get('description');
        $document->save();

        $this->saveToLog('Documents', 'Create document with name: ' . $request->get('name'));
        return $this->sendResponse([], 'Document has been created!');
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
        $validator = FacadesValidator::make($request->all(), [
            'name' => 'required',
            'description' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('Validation fails', $validator->errors(), 401);

        // Save document
        $document = Document::find($id);
        $document->name = $request->get('name');
        $document->description = $request->get('description');
        $document->save();

        $this->saveToLog('Documents', 'Updated document with name: ' . $request->get('name'));
        return $this->sendResponse([], 'Document has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $document = Document::find($id);
        $documentName = $document->name;
        Document::destroy($id);
        $this->saveToLog('Documents', 'Deleted document: ' . $documentName);
        return $this->sendResponse([], 'Document has been deleted!');
    }
}
