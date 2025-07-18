<?php

namespace App\Http\Controllers;

use App\Http\Resources\EvidenceResource;
use App\Models\Evidence;
use App\Models\EvidenceFile;
use App\Service\StrUtil;
use App\Traits\ImageHandler;
use Illuminate\Http\Request;

class EvidenceController extends Controller
{
    use ImageHandler;
    private const QTY_LAST_DAYS = 1;

    public function index(Request $request)
    {
        $searchEvidence = $request->search_evidence ?? null;
        $lastDays = $request->last_days ?? self::QTY_LAST_DAYS;
        $lastDays = !is_numeric($lastDays) || $lastDays < 1 ? self::QTY_LAST_DAYS : $lastDays;

        $operation = $request->operation??"";
        $reference = $request->reference??"";

        $evidence = null;
        $evidences = EvidenceResource::collection(Evidence::latest()->where('reference','like','%'.$operation.'%'.$reference.'%')->where('created_at', '>', now()->subDays($lastDays)->endOfDay())->orderBy('id', 'DESC')->get());
        $filesEvidence = [];
        if ($searchEvidence) {
            $evidence = Evidence::find($searchEvidence);
            $filesEvidence = EvidenceFile::where('evidence_id', $searchEvidence)->orderBy('id', 'DESC')->get();
        }

        $messageCharNotAccepted = StrUtil::getMessageCharNotAccepted();
        return view('evidence.index', compact(['evidence', 'evidences', 'searchEvidence', 'filesEvidence','lastDays','operation','reference','messageCharNotAccepted']));
    }

    public function create()
    {
        return back()->with('success', "SUCCESS");
    }

    public function store(Request $request)
    {
        $inputs['reference'] = date("dmY_", strtotime($request->date_process)) . $request->operation . $request->reference;
        $inputs['created_at'] = $request->date_process;
        try {
            $evidence = Evidence::Create($inputs);
            $traceabilityController = new TraceabilityController;
            $traceabilityController->store("CREATE", "Foi criado o agrupador $evidence->reference");
            return redirect()->route('evidence.index', ['search_evidence' => $evidence])->with('success', "Cadastrei o agrupador $request->reference");
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            if ($th->getCode() == 23000) {
                $message = "Agrupador " . $inputs['reference'] . " já cadastrado.";
            }
            return redirect()->route('evidence.index')->with('error', "ERRO: $message");
        }
    }

    public function show(Evidence $evidence)
    {
        return redirect()->route('evidence.index', ['search_evidence' => $evidence->id]);
    }

    public function edit(Evidence $evidence)
    {
        return redirect()->route('evidence.index', ['search_evidence' => $evidence->id]);
    }


    public function update(Request $request, Evidence $evidence)
    {
        $evidenceOld = $evidence->reference;
        $inputs['reference'] = date("dmY_", strtotime($request->date_process)) . $request->reference;
        $inputs['created_at'] = $request->date_process;
        try {
            $evidence->fill($inputs);
            $evidence->save();

            if ($this->renameDir($evidenceOld, $evidence->reference)) {
                $traceabilityController = new TraceabilityController;
               $traceabilityController->store("UPDATE", "O agrupador $evidenceOld mudou para $evidence->reference");
                $evidencesFiles = EvidenceFile::where('evidence_id', $evidence->id)->where('file', 'LIKE', $evidenceOld . "%")->get();
                foreach ($evidencesFiles as $evidenceFile) {
                    $evidenceFile->file = \Str::replace($evidenceOld, $evidence->reference, $evidenceFile->file);
                    $evidenceFile->save();
                }
            }
            return redirect()->route('evidence.index', ['search_evidence' => $evidence->id])->with('success', "SUCESSO: Agrupador $evidenceOld alterado para $evidence->reference");
        } catch (\Throwable $th) {
            if ($th->getCode() == 23000) {
                return back()->with('error', "Alteração não realizada, já existe o agrupador " . $inputs['reference']);
            }
            $message = $th->getMessage();
            return redirect()->route('evidence.index')->with('error', "ERRO: $message");
        }
    }

    public function destroy(Evidence $evidence)
    {
        $nameEvidence = $evidence->reference;
        if($evidence->delete()){
            $traceabilityController = new TraceabilityController;
            $traceabilityController->store("DELETE", "Foi excluido o agrupador $nameEvidence e seus arquivos");
            $this->deleteAllFiles($nameEvidence);
        }
        return redirect()->route('evidence.index')->with('success', "Excluir o agrupador $nameEvidence.");
    }

    public function downloadFiles(Evidence $evidence)
    {
        $evidenceFiles = EvidenceFile::where('evidence_id', $evidence->id)->get();
        if ($evidenceFiles) {
            $traceabilityController = new TraceabilityController;
            $traceabilityController->store("DOWN", "Foram baixados os arquivos do agrupador $evidence->reference");
            return $this->downloadAll($evidence->reference);
        }
        return redirect()->back()->with('error', "Não existe arquivos para download.");
    }

    public function carousel(Evidence $evidence, EvidenceFile $evidenceFile)
    {
        $evidenceFiles = EvidenceFile::where('evidence_id', $evidence->id)->orderBy('id', 'DESC')->get();
        return view('evidence.carousel', compact('evidenceFiles', 'evidenceFile', 'evidence'));
    }

    public function printOccurrence(Evidence $evidence)
    {
        $evidenceFiles = EvidenceFile::where('evidence_id', $evidence->id)->whereNotNull('description')->get();
        return view('evidence.print_occurrence', compact('evidenceFiles','evidence'));
    }

}
