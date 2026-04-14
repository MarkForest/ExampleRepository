<?php
namespace App\DTO\AntiPattern;
final class createpaymentdto{
public int $acc; public string $am; public string $cur; public string $d;
public function __construct($acc,$am,$cur,$d=''){
    $this->acc=$acc; $this->am=$am; $this->cur=$cur; $this->d=$d;
}}
