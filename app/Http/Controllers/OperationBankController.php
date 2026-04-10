<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepositeRequest;
use App\Http\Requests\ModeyWithdrawalRequest;
use App\Http\Requests\TransfertRequest;
use App\Http\Requests\VerifyAmountRequest;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationBankController extends Controller
{
    //cette fonction permet a un client de faire un depot d'argent dans son autre compte
    public function depositeInMyAccount(DepositeRequest $request){
        $compte = $request->user()->Accounts()->where("status","actif")->first();
        if(!$compte){
            return response()->json(["message"=>"aucun compte actif trouvé"]);
        }

        $compte->solde = $compte->solde + $request->montant;
        $compte->save();

        DB::transaction(function () use ($compte, $request) {
            
            Transaction::create([
                "account_id"   => $compte->id,
                "type"         => "depot",
                "amount"       => $request->montant,
                "solde_avant"  => $compte->solde - $request->montant,
                "solde_apres"  => $compte->solde,
                "description"  => $request->description,
                "status"       => "validee"
            ]);
            
        });

        return response()->json([
            "message"     => "depot effectué avec success !",
            "account_id"  => $compte->id,
            "new_balance" => $compte->solde,
        ]);
    }

    //cette fonction permet a un client de retirer de l'agent dans son compte
    public function MoneyWithdrawal(ModeyWithdrawalRequest $request){
        $compte=$request->user()->Accounts()->where("status","actif")->first();
        if(!$compte){
            return response()->json(["message"=>"aucun compte actif trouvé"]);
        }

        if($compte->solde<=0){
            return response()->json(["message"=>"votre solde est épuisé"]);
        }

        if($compte->solde<$request->montant){
            return response()->json(["message"=>"le compte est insuffisant"]);
        }
       
        $compte->solde=$compte->solde-$request->montant;
        $compte->save();

        DB::transaction(function () use ($compte, $request) {
            
            Transaction::create([
                "account_id"   => $compte->id,
                "type"         => "retrait",           // ← Correction importante
                "amount"       => $request->montant,
                "solde_avant"  => $compte->solde + $request->montant,
                "solde_apres"  => $compte->solde,
                "description"  => $request->description,
                "status"       => "validee"
            ]);
            
        });

        return response()->json([
            "message"=>"depot effectué avec success !",   // ← Tu peux aussi corriger ce message
            "account_id"=>$compte->id,
            "new_balance"=>$compte->solde,
        ]);
    }

    //cette fonction permet a un client de faire un virement depuis un compte vers un autre
    public function bankTransfert(TransfertRequest $request){
    // Déterminer le compte source
    if ($request->has('source_account_id')) {
        // L'utilisateur a choisi un compte spécifique parmi les siens
        $sourceAccount = $request->user()->accounts()
            ->where('id', $request->source_account_id)
            ->where('status', 'actif')
            ->first();
    } else {
        // Sinon, prendre le premier compte actif
        $sourceAccount = $request->user()->accounts()
            ->where('status', 'actif')
            ->first();
    }

    if (!$sourceAccount) {
        return response()->json(['message' => 'Compte source introuvable ou inactif'], 404);
    }

    // Récupérer le compte destinataire
    $targetAccount = Account::where('account_number', $request->target_account_number)
        ->where('status', 'actif')
        ->first();

    if (!$targetAccount) {
        return response()->json(['message' => 'Compte destinataire introuvable ou inactif'], 404);
    }

    // Vérifier que ce n'est pas le même compte
    if ($sourceAccount->id === $targetAccount->id) {
        return response()->json(['message' => 'Virement vers le même compte interdit'], 422);
    }

    $amount = $request->amount;
    $balanceBeforeSource = $sourceAccount->solde; // colonne 'solde'

    if ($balanceBeforeSource < $amount) {
        return response()->json(['message' => 'Solde insuffisant'], 422);
    }

    $balanceAfterSource = $balanceBeforeSource - $amount;
    $balanceBeforeTarget = $targetAccount->solde;
    $balanceAfterTarget = $balanceBeforeTarget + $amount;

    // Exécuter la transaction (atomique)
    DB::transaction(function () use ($sourceAccount, $targetAccount, $amount, $balanceBeforeSource, $balanceAfterSource, $balanceBeforeTarget, $balanceAfterTarget, $request) {
        // Mettre à jour les soldes
        $sourceAccount->solde = $balanceAfterSource;
        $sourceAccount->save();

        $targetAccount->solde = $balanceAfterTarget;
        $targetAccount->save();

        // Enregistrer la transaction pour le compte source (débit)
        Transaction::create([
            'id' => $sourceAccount->id,
            'account_id' => $targetAccount->id,
            'type' => 'transfert',
            'amount' => $amount,
            'solde_avant' => $balanceBeforeSource,
            'solde_apres' => $balanceAfterSource,
            'description' => $request->description,
            'status' => 'validee', // ou 'valide' selon votre enum
        ]);
    });

    return response()->json([
        'message' => 'Virement effectué avec succès',
        'source_account_id' => $sourceAccount->id,
        'new_source_balance' => $balanceAfterSource,
        'target_account_number' => $targetAccount->account_number,
        'amount' => $amount,
    ]);
}

    public function verifyAmount(VerifyAmountRequest $request){
        $compte=$request->user()->Accounts()->first();
        if($compte->user_id !=$request->user()->id){
            return response()->json(["message","accès non autorisé"]);
        }
        return response()->json(["message"=>$compte->solde]);

    }
}
