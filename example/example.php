<?php

use Sdespont\SwissQrBillTcpdf\TcpdfOutput;
use Sprain\SwissQrBill as QrBill;
use Sprain\SwissQrBill\DataGroup\Element\AdditionalInformation;
use Sprain\SwissQrBill\DataGroup\Element\AlternativeScheme;

require __DIR__ . '/../vendor/autoload.php';

// Create a new instance of QrBill, containing default headers with fixed values
$qrBill = QrBill\QrBill::create();

// Add creditor information
// Who will receive the payment and to which bank account?
$qrBill->setCreditor(
    QrBill\DataGroup\Element\CombinedAddress::create(
        'Robert Schneider AG',
        'Rue du Lac 1268',
        '2501 Biel',
        'CH'
    )
);

$qrBill->setCreditorInformation(
    QrBill\DataGroup\Element\CreditorInformation::create(
        'CH4431999123000889012' // Note that this is a special QR-IBAN which are only available since of June 2020
    )
);

// Add debtor information
// Who has to pay the invoice? This part is optional.
//
// Notice how you can use two different styles of addresses: CombinedAddress or StructuredAddress.
// They are interchangeable for creditor as well as debtor.
$qrBill->setUltimateDebtor(
    QrBill\DataGroup\Element\StructuredAddress::createWithStreet(
        'Pia-Maria Rutschmann-Schnyder',
        'Grosse Marktgasse',
        '28',
        '9400',
        'Rorschach',
        'CH'
    )
);

// Add payment amount information
// What amount is to be paid?
$qrBill->setPaymentAmountInformation(
    QrBill\DataGroup\Element\PaymentAmountInformation::create(
        'CHF',
        2500.25
    )
);

// Add payment reference
// This is what you will need to identify incoming payments.
$referenceNumber = QrBill\Reference\QrPaymentReferenceGenerator::generate(
    '210000',  // you receive this number from your bank
    '313947143000901' // a number to match the payment with your other data, e.g. an invoice number
);

$qrBill->setPaymentReference(
    QrBill\DataGroup\Element\PaymentReference::create(
        QrBill\DataGroup\Element\PaymentReference::TYPE_QR,
        $referenceNumber
    )
);

// Add additional information about the payment
$additionalInformation = AdditionalInformation::create('Invoice 1234568');
$qrBill->setAdditionalInformation($additionalInformation);

// Add alternative scheme
$qrBill->addAlternativeScheme(AlternativeScheme::create('Name AV1: UV;UltraPay005;12345'));
$qrBill->addAlternativeScheme(AlternativeScheme::create('Name AV2: XY;XYService;54321'));

// Example with TCPDF
$tcPdf = new TCPDF('P', 'mm', 'A4', true, 'ISO-8859-1');
$tcPdf->setPrintHeader(false);
$tcPdf->setPrintFooter(false);

// Page 1 : ISR standard
$tcPdf->AddPage();
$output = new TcpdfOutput($qrBill, 'en');
$output->setTcPdf($tcPdf);
$output->setPrintable(true)->getPaymentPart();

// Page 2 : 0CHF
$tcPdf->AddPage();
$additionalInformation = AdditionalInformation::create(QrBill\PaymentPart\Translation\Translation::get('doNotUseForPayment', 'en'));
$qrBill->setAdditionalInformation($additionalInformation);
$qrBill->setPaymentAmountInformation(QrBill\DataGroup\Element\PaymentAmountInformation::create('CHF', 0));
$output = new TcpdfOutput($qrBill, 'en');
$output->setTcPdf($tcPdf);
$output->setPrintable(true)->getPaymentPart();

// Page 3 ISR+
$tcPdf->AddPage();
$additionalInformation = AdditionalInformation::create("Thanks for your donation", null);
$qrBill->setAdditionalInformation($additionalInformation);
$qrBill->setPaymentAmountInformation(QrBill\DataGroup\Element\PaymentAmountInformation::create('CHF', 0));
$output = new TcpdfOutput($qrBill, 'en');
$output->setTcPdf($tcPdf);
$output->setPrintable(true)->getPaymentPart();

$tcPdf->Output(__DIR__ . "/tcpdf_example.pdf", 'F');
