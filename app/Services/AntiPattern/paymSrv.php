<?php
namespace App\Services\AntiPattern;
use App\Models\Account; use App\Models\Payment; use Illuminate\Support\Facades\DB;

class paymSrv{
    function do($a,$b,$c){
    $acc = Account::find($a['acc_id']); if(!$acc){throw new \Exception('no acc');}
    if($acc->b<$a['amt']){throw new \Exception('no money');}
    $cr=0; if($a['amt']>1000){$cr=$a['amt']*0.01;}
    return DB::transaction(function()use($a,$acc,$cr)
    {$p=Payment::create(['account_id'=>$acc->id,'amount'=>$a['amt'],'currency'=>$a['cur'],'descr'=>$a['d']??'','commission'=>$cr,'status'=>'ok']);$acc->b=$acc->b-($a['amt']+$cr);$acc->save();return $p;});
    }
}
