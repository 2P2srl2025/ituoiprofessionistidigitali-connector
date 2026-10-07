<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use ITuoiProfessionistiDigitali\Connector\Validation\Rules\ItalianVatNumber;

it('M4: checks the check digit of a VAT number', function (mixed $value, bool $isValid): void {
    expect(Validator::make(['vat' => $value], ['vat' => [new ItalianVatNumber]])->passes())->toBe($isValid);
})->with([
    'valid' => ['01234567897', true],
    'valid with doubled digits over nine' => ['09876543217', true],
    'wrong check digit' => ['01234567890', false],
    'ten digits' => ['0123456789', false],
    'letters' => ['0123456789A', false],
    'not a string' => [1234567897, false],
]);
