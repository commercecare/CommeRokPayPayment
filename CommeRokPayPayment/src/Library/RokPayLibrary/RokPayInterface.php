<?php

namespace CommeRokPayPayment\Library\RokPayLibrary;


interface RokPayInterface
{
    public function makePayment(array $data);

    public function setProcessingInfo(array $data);

    public function callToApi($data, $header = []);

}
