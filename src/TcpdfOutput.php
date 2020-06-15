<?php

namespace Sdespont\SwissQrBillTcpdf;

use Sprain\SwissQrBill\PaymentPart\Output\AbstractOutput;
use Sprain\SwissQrBill\PaymentPart\Output\Element\OutputElementInterface;
use Sprain\SwissQrBill\PaymentPart\Output\Element\Placeholder;
use Sprain\SwissQrBill\PaymentPart\Output\Element\Text;
use Sprain\SwissQrBill\PaymentPart\Output\Element\Title;
use Sprain\SwissQrBill\PaymentPart\Output\OutputInterface;
use Sprain\SwissQrBill\QrCode\Exception\UnsupportedFileExtensionException;
use Sprain\SwissQrBill\QrCode\QrCode;
use Sprain\SwissQrBill\PaymentPart\Translation\Translation;
use Sprain\SwissQrBill\QrBill;
use TCPDF;

final class TcpdfOutput extends AbstractOutput implements OutputInterface
{
    private const TCPDF_BORDER = 0;
    private const TCPDF_ALIGN_BELOW = 2;
    private const TCPDF_ALIGN_LEFT = 'L';
    private const TCPDF_ALIGN_RIGHT = 'R';
    private const TCPDF_MULTILINE_MIN_SIZE = 2;
    private const TCPDF_FONT = 'Helvetica';
    private const TCPDF_LEFT_CELL_HEIGHT_RATIO_COMMON = 1.2;
    private const TCPDF_RIGHT_CELL_HEIGHT_RATIO_COMMON = 1.1;
    private const TCPDF_LEFT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT = 1.5;
    private const TCPDF_RIGHT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT = 1.5;
    private const TCPDF_CURRENCY_AMOUNT_Y = 259;
    private const TCPDF_LEFT_PART_X = 4;
    private const TCPDF_RIGHT_PART_X = 66;
    private const TCPDF_RIGHT_PAR_X_INFO = 117;
    private const TCPDF_TITLE_Y = 195;
    private const TCPDF_9PT = 3.5;
    private const TCPDF_11PT = 4.8;

    /** @var  string */
    protected $language;

    /** @var QrBill */
    protected $qrBill;

    /* @var TCPDF $tcpdf */
    private $tcpdf;

    /**
     * @param TCPDF $tcpdf
     */
    public function setTcPdf(TCPDF $tcpdf)
    {
        $this->tcpdf = $tcpdf;
    }

    /**
     * @return string
     * @throws UnsupportedFileExtensionException
     */
    public function getPaymentPart(): string
    {
        $this->tcpdf->SetAutoPageBreak(false);

        $this->addPrintableContent();

        // Left part
        $this->addInformationContentReceipt();
        $this->addCurrencyContentReceipt();
        $this->addAmountContentReceipt();

        // Right part
        $this->addSwissQrCodeImage();
        $this->addInformationContent();
        $this->addCurrencyContent();
        $this->addAmountContent();
        $this->addFurtherInformationContent();

        return "OK";
    }

    /**
     * Display the QR Code
     *
     * @throws UnsupportedFileExtensionException
     */
    private function addSwissQrCodeImage(): void
    {
        $qrCode = $this->getQrCode();
        $qrCode->setWriterByExtension(QrCode::FILE_FORMAT_PNG);
        $img = base64_decode(preg_replace('#^data:image/[^;]+;base64,#', '', $qrCode->writeDataUri()));
        $this->tcpdf->Image("@".$img, self::TCPDF_RIGHT_PART_X+1, 209.5, 46, 46);
    }

    /**
     * Displays the available information in the left part
     */
    private function addInformationContentReceipt(): void
    {
        $x = self::TCPDF_LEFT_PART_X;
        $this->tcpdf->setCellHeightRatio(self::TCPDF_LEFT_CELL_HEIGHT_RATIO_COMMON);

        // Title
        $this->tcpdf->SetXY($x, self::TCPDF_TITLE_Y);
        $this->tcpdf->SetFont(self::TCPDF_FONT, 'B', 11);
        $this->tcpdf->Cell(0, 7, Translation::get('receipt', $this->language), self::TCPDF_BORDER);

        // Elements
        $this->tcpdf->SetY(204);
        foreach ($this->getInformationElementsOfReceipt() as $informationElement) {
            $this->tcpdf->SetX($x);
            $this->setContentElement($informationElement, true);
        }

        // Acceptance section
        $this->tcpdf->SetFont(self::TCPDF_FONT, 'B', 6);
        $this->tcpdf->SetXY($x, 273);
        $this->tcpdf->Cell(54, 0, Translation::get('acceptancePoint', $this->language), self::TCPDF_BORDER, self::TCPDF_ALIGN_BELOW, self::TCPDF_ALIGN_RIGHT);
    }

    /**
     * Displays the available information in the right part
     */
    private function addInformationContent(): void
    {
        $x = self::TCPDF_RIGHT_PAR_X_INFO;
        $this->tcpdf->setCellHeightRatio(self::TCPDF_RIGHT_CELL_HEIGHT_RATIO_COMMON);

        // Title
        $this->tcpdf->SetFont(self::TCPDF_FONT, 'B', 11);
        $this->tcpdf->SetXY(self::TCPDF_RIGHT_PART_X, self::TCPDF_TITLE_Y);
        $this->tcpdf->Cell(48, 7, Translation::get('paymentPart', $this->language), self::TCPDF_BORDER);

        // Elements
        $this->tcpdf->SetY(197);
        foreach ($this->getInformationElements() as $informationElement) {
            $this->tcpdf->SetX($x);
            $this->setContentElement($informationElement, false);
        }
    }

    /**
     * Displays the currency in the left part
     */
    private function addCurrencyContentReceipt(): void
    {
        $x = self::TCPDF_LEFT_PART_X;
        $this->tcpdf->setCellHeightRatio(self::TCPDF_LEFT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT);
        $this->tcpdf->SetY(self::TCPDF_CURRENCY_AMOUNT_Y);
        foreach ($this->getCurrencyElements() as $currencyElement) {
            $this->tcpdf->SetX($x);
            $this->setContentElement($currencyElement, true);
        }
    }

    /**
     * Displays the amount in the left part
     */
    private function addAmountContentReceipt(): void
    {
        $x = 16;
        $this->tcpdf->setCellHeightRatio(self::TCPDF_LEFT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT);
        $this->tcpdf->SetY(self::TCPDF_CURRENCY_AMOUNT_Y);
        foreach ($this->getAmountElementsReceipt() as $amountElement) {
            $this->tcpdf->SetX($x);
            $this->setContentElement($amountElement, true);
        }
    }

    /**
     * Displays the currency in the right part
     */
    private function addCurrencyContent(): void
    {
        $x = self::TCPDF_RIGHT_PART_X;
        $this->tcpdf->setCellHeightRatio(self::TCPDF_RIGHT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT);
        $this->tcpdf->SetXY($x, self::TCPDF_CURRENCY_AMOUNT_Y);
        foreach ($this->getCurrencyElements() as $currencyElement) {
            $this->tcpdf->SetX($x);
            $this->setContentElement($currencyElement, false);
        }
    }

    /**
     * Displays the amount in the right part
     */
    private function addAmountContent(): void
    {
        $x = 80;
        $this->tcpdf->setCellHeightRatio(self::TCPDF_RIGHT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT);
        $this->tcpdf->SetY(self::TCPDF_CURRENCY_AMOUNT_Y);
        foreach ($this->getAmountElements() as $amountElement) {
            $this->tcpdf->SetX($x);
            $this->setContentElement($amountElement, false);
        }
    }

    /**
     * Displays the alternative schemes
     */
    private function addFurtherInformationContent(): void
    {
        $x = self::TCPDF_RIGHT_PART_X;
        $this->tcpdf->setCellHeightRatio(self::TCPDF_RIGHT_CELL_HEIGHT_RATIO_COMMON);
        $this->tcpdf->SetY(286);
        $this->tcpdf->SetFont(self::TCPDF_FONT, '', 7);
        foreach ($this->getFurtherInformationElements() as $furtherInformationElement) {
            $this->tcpdf->SetX($x);
            $this->setContentElement($furtherInformationElement, true);
        }
    }

    /**
     * Create the separation lines between receipt and payment parts
     */
    private function addPrintableContent(): void
    {
        if ($this->isPrintable()) {
            $this->tcpdf->SetLineStyle(array('width' => 0.1, 'dash' => 4, 'color' => array(0, 0, 0)));
            $this->tcpdf->Line(2, 193, 208, 191);
            $this->tcpdf->Line(62, 193, 62, 296);
            $this->tcpdf->SetFont(self::TCPDF_FONT, '', 7);
            $this->tcpdf->SetXY(self::TCPDF_RIGHT_PART_X, 188);
            $this->tcpdf->Cell(0, 0, Translation::get('separate', $this->language), self::TCPDF_BORDER);
        }
    }

    /**
     * Displays the element in the TCPDF document
     *
     * @param OutputElementInterface $element
     * @param bool $isReceiptPart
     * @throws \Exception
     */
    private function setContentElement(OutputElementInterface $element, bool $isReceiptPart)
    {
        if ($element instanceof Title) {
            $this->tcpdf->SetFont(self::TCPDF_FONT, 'B', $isReceiptPart ? 6 : 8);
            $text = Translation::get(str_replace("text.", "", $element->getTitle()), $this->language);
            $this->tcpdf->Cell(0, 0, $text, self::TCPDF_BORDER, self::TCPDF_ALIGN_BELOW);
        }

        if ($element instanceof Text) {
            $this->tcpdf->SetFont(self::TCPDF_FONT, '', $isReceiptPart ? 8 : 10);
            $text = str_replace("text.", "", $element->getText());
            $this->tcpdf->MultiCell(0, self::TCPDF_MULTILINE_MIN_SIZE, $text, self::TCPDF_BORDER, self::TCPDF_ALIGN_LEFT, false, self::TCPDF_ALIGN_BELOW);
            $this->tcpdf->Ln($isReceiptPart ? self::TCPDF_9PT : self::TCPDF_11PT);
        }

        if ($element instanceof Placeholder) {
            $this->tcpdf->SetLineStyle(array('width' => 0.3, 'dash' => 0, 'color' => array(0, 0, 0)));

            $lineLength = 3;

            // Not implemented
            if ($isReceiptPart) {
                $boxLeftPos = 27;
                $boxHeightPos = self::TCPDF_CURRENCY_AMOUNT_Y+2;
                $boxHeight = 10;
                $boxWidth = 30;
                $boxRightPos = $boxLeftPos+$boxWidth;
            } else {
                $boxLeftPos = 77;
                $boxHeightPos = 265;
                $boxHeight = 15;
                $boxWidth = 40;
                $boxRightPos = $boxLeftPos+$boxWidth;
            }

            // Top left
            $tlx = $boxLeftPos;
            $tly = $boxHeightPos;
            $this->tcpdf->Line($tlx, $tly, $tlx+$lineLength, $tly);
            $this->tcpdf->Line($tlx, $tly, $tlx, $tly+$lineLength);

            // Top right
            $trx = $boxRightPos;
            $try = $boxHeightPos;
            $this->tcpdf->Line($trx, $try, $trx-$lineLength, $try);
            $this->tcpdf->Line($trx, $try, $trx, $try+$lineLength);

            // Bottom left
            $blx = $boxLeftPos;
            $bly = $boxHeightPos+$boxHeight;
            $this->tcpdf->Line($blx, $bly, $blx+$lineLength, $bly);
            $this->tcpdf->Line($blx, $bly, $blx, $bly-$lineLength);

            // Bottom right
            $brx = $boxRightPos;
            $bry = $boxHeightPos+$boxHeight;
            $this->tcpdf->Line($brx, $bry, $brx-$lineLength, $bry);
            $this->tcpdf->Line($brx, $bry, $brx, $bry-$lineLength);
        }
    }
}
