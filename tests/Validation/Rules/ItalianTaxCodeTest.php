<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use ITuoiProfessionistiDigitali\Connector\Validation\Rules\ItalianTaxCode;

it('M4: checks the check character of a tax code', function (mixed $value, bool $personOnly, bool $isValid): void {
    expect(Validator::make(['cf' => $value], ['cf' => [new ItalianTaxCode($personOnly)]])->passes())->toBe($isValid);
})->with([
    'person' => ['RSSMRA80A01H501U', true, true],
    'person, omocodia' => ['RSSMRA80A01H50MM', true, true],
    'woman born in Milan' => ['BNCLRA85T41F205Y', true, true],
    'wrong check character' => ['RSSMRA80A01H501A', true, false],
    'lower case' => ['rssmra80a01h501u', true, false],
    'organization' => ['01234567897', false, true],
    'organization where only persons are allowed' => ['01234567897', true, false],
    'organization with wrong check digit' => ['01234567890', false, false],
    'not a string' => [null, false, false],
]);
