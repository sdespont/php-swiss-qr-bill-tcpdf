<?php

namespace Sdespont\SwissQrBillTcpdf\PaymentPart\Output;

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

final class TcPdfOutput extends AbstractOutput implements OutputInterface
{
    // TCPDF constants
    private const TCPDF_BORDER = 0;
    private const TCPDF_ALIGN_BELOW = 2;
    private const TCPDF_ALIGN_LEFT = 'L';
    private const TCPDF_ALIGN_RIGHT = 'R';
    private const TCPDF_FONT = 'Helvetica';

    // Ratio constants
    private const TCPDF_LEFT_CELL_HEIGHT_RATIO_COMMON = 1.2;
    private const TCPDF_RIGHT_CELL_HEIGHT_RATIO_COMMON = 1.1;
    private const TCPDF_LEFT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT = 1.5;
    private const TCPDF_RIGHT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT = 1.5;

    // Location constants
    private const TCPDF_CURRENCY_AMOUNT_Y = 259;
    private const TCPDF_LEFT_PART_X = 4;
    private const TCPDF_RIGHT_PART_X = 66;
    private const TCPDF_RIGHT_PAR_X_INFO = 117;
    private const TCPDF_TITLE_Y = 195;

    // Line spacing constants
    private const TCPDF_9PT = 3.5;
    private const TCPDF_11PT = 4.8;

    /** @var  string */
    protected $language;

    /** @var QrBill */
    protected $qrBill;

    /* @var TCPDF $tcPdf */
    private $tcPdf;

    /* @var int $offsetX */
    private $offsetX;

    /* @var int $offsetY */
    private $offsetY;

    /* @var string $qrCodeImageFormat */
    private $qrCodeImageFormat;

    /**
     * TcPdfOutput constructor.
     *
     * @param QrBill $qrBill
     * @param string $language
     * @param TCPDF $tcPdf
     * @param int $offsetX
     * @param int $offsetY
     * @param string $qrCodeImageFormat
     */
    public function __construct(
        QrBill $qrBill,
        string $language,
        TCPDF $tcPdf,
        int $offsetX = 0,
        int $offsetY = 0,
        string $qrCodeImageFormat = QrCode::FILE_FORMAT_PNG
    ) {
        parent::__construct($qrBill, $language);
        $this->tcPdf = $tcPdf;
        $this->offsetX = $offsetX;
        $this->offsetY = $offsetY;
        $this->qrCodeImageFormat = $qrCodeImageFormat;
    }

    /**
     * @return string
     * @throws UnsupportedFileExtensionException
     */
    public function getPaymentPart(): string
    {
        $this->tcPdf->SetAutoPageBreak(false);

        $this->addPrintableContent();

        // Receipt part
        $this->addInformationContentReceipt();
        $this->addCurrencyContentReceipt();
        $this->addAmountContentReceipt();

        // Payment part
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

        switch($this->qrCodeImageFormat) {
            case QrCode::FILE_FORMAT_SVG:
                $format = QrCode::FILE_FORMAT_SVG;
                $method = "ImageSVG";
                throw new UnsupportedFileExtensionException("At this time, TCPDF doesn't permit to print embedded image in SVG image");
                break;
            case QrCode::FILE_FORMAT_PNG:
            default:
                $format = QrCode::FILE_FORMAT_PNG;
                $method = "Image";
        }

        $qrCode->setWriterByExtension($format);
        $img = base64_decode(preg_replace('#^data:image/[^;]+;base64,#', '', $qrCode->writeDataUri()));
        $this->tcPdf->$method("@".$img, self::TCPDF_RIGHT_PART_X + 1 + $this->offsetX, 209.5 + $this->offsetY, 46, 46);

    }

    /**
     * Displays the available information in the left part
     */
    private function addInformationContentReceipt(): void
    {
        $x = self::TCPDF_LEFT_PART_X;
        $this->tcPdf->setCellHeightRatio(self::TCPDF_LEFT_CELL_HEIGHT_RATIO_COMMON);

        // Title
        $this->tcPdf->SetFont(self::TCPDF_FONT, 'B', 11);
        $this->SetY(self::TCPDF_TITLE_Y);
        $this->SetX($x);
        $this->printCell(Translation::get('receipt', $this->language), 0, 7);

        // Elements
        $this->SetY(204);
        foreach ($this->getInformationElementsOfReceipt() as $informationElement) {
            $this->SetX($x);
            $this->setContentElement($informationElement, true);
        }

        // Acceptance section
        $this->tcPdf->SetFont(self::TCPDF_FONT, 'B', 6);
        $this->SetY(273);
        $this->SetX($x);
        $this->printCell(Translation::get('acceptancePoint', $this->language), 54, 0, self::TCPDF_ALIGN_BELOW, self::TCPDF_ALIGN_RIGHT);
    }

    /**
     * Displays the available information in the right part
     */
    private function addInformationContent(): void
    {
        $x = self::TCPDF_RIGHT_PAR_X_INFO;
        $this->tcPdf->setCellHeightRatio(self::TCPDF_RIGHT_CELL_HEIGHT_RATIO_COMMON);

        // Title
        $this->tcPdf->SetFont(self::TCPDF_FONT, 'B', 11);
        $this->SetY(self::TCPDF_TITLE_Y);
        $this->SetX(self::TCPDF_RIGHT_PART_X);
        $this->printCell(Translation::get('paymentPart', $this->language), 48, 7);

        // Elements
        $this->SetY(197);
        foreach ($this->getInformationElements() as $informationElement) {
            $this->SetX($x);
            $this->setContentElement($informationElement, false);
        }
    }

    /**
     * Displays the currency in the left part
     */
    private function addCurrencyContentReceipt(): void
    {
        $x = self::TCPDF_LEFT_PART_X;
        $this->tcPdf->setCellHeightRatio(self::TCPDF_LEFT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT);
        $this->SetY(self::TCPDF_CURRENCY_AMOUNT_Y);
        foreach ($this->getCurrencyElements() as $currencyElement) {
            $this->SetX($x);
            $this->setContentElement($currencyElement, true);
        }
    }

    /**
     * Displays the amount in the left part
     */
    private function addAmountContentReceipt(): void
    {
        $x = 16;
        $this->tcPdf->setCellHeightRatio(self::TCPDF_LEFT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT);
        $this->SetY(self::TCPDF_CURRENCY_AMOUNT_Y);
        foreach ($this->getAmountElementsReceipt() as $amountElement) {
            $this->SetX($x);
            $this->setContentElement($amountElement, true);
        }
    }

    /**
     * Displays the currency in the right part
     */
    private function addCurrencyContent(): void
    {
        $x = self::TCPDF_RIGHT_PART_X;
        $this->tcPdf->setCellHeightRatio(self::TCPDF_RIGHT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT);
        $this->SetY(self::TCPDF_CURRENCY_AMOUNT_Y);
        foreach ($this->getCurrencyElements() as $currencyElement) {
            $this->SetX($x);
            $this->setContentElement($currencyElement, false);
        }
    }

    /**
     * Displays the amount in the right part
     */
    private function addAmountContent(): void
    {
        $x = 80;
        $this->tcPdf->setCellHeightRatio(self::TCPDF_RIGHT_CELL_HEIGHT_RATIO_CURRENCY_AMOUNT);
        $this->SetY(self::TCPDF_CURRENCY_AMOUNT_Y);
        foreach ($this->getAmountElements() as $amountElement) {
            $this->SetX($x);
            $this->setContentElement($amountElement, false);
        }
    }

    /**
     * Displays the alternative schemes
     */
    private function addFurtherInformationContent(): void
    {
        $x = self::TCPDF_RIGHT_PART_X;
        $this->tcPdf->setCellHeightRatio(self::TCPDF_RIGHT_CELL_HEIGHT_RATIO_COMMON);
        $this->SetY(286);
        $this->tcPdf->SetFont(self::TCPDF_FONT, '', 7);
        foreach ($this->getFurtherInformationElements() as $furtherInformationElement) {
            $this->SetX($x);
            $this->setContentElement($furtherInformationElement, true);
        }
    }

    /**
     * Create the separation lines between receipt and payment parts
     */
    private function addPrintableContent(): void
    {
        if ($this->isPrintable()) {
            $this->tcPdf->SetLineStyle(array('width' => 0.1, 'dash' => 4, 'color' => array(0, 0, 0)));
            $this->printLine(2, 193, 208, 193);
            $this->printLine(62, 193, 62, 296);
            $this->tcPdf->SetFont(self::TCPDF_FONT, '', 7);
            $this->SetY(188);
            $this->SetX(self::TCPDF_RIGHT_PART_X);
            $this->printCell(Translation::get('separate', $this->language), 0, 0);
        }
    }

    /**
     * Displays the element in the TCPDF document
     *
     * @param OutputElementInterface $element
     * @param bool $isReceiptPart
     */
    private function setContentElement(OutputElementInterface $element, bool $isReceiptPart)
    {
        if ($element instanceof Title) {
            $this->tcPdf->SetFont(self::TCPDF_FONT, 'B', $isReceiptPart ? 6 : 8);
            $this->printCell(Translation::get(str_replace("text.", "", $element->getTitle()), $this->language), 0, 0, self::TCPDF_ALIGN_BELOW);
        }

        if ($element instanceof Text) {
            $this->tcPdf->SetFont(self::TCPDF_FONT, '', $isReceiptPart ? 8 : 10);
            $this->printMultiCell(
                str_replace("text.", "", $element->getText()),
                0,
                0,
                self::TCPDF_ALIGN_BELOW,
                self::TCPDF_ALIGN_LEFT
            );
            $this->tcPdf->Ln($isReceiptPart ? self::TCPDF_9PT : self::TCPDF_11PT);
        }

        if ($element instanceof Placeholder) {
            $this->tcPdf->SetLineStyle(array('width' => 0.3, 'dash' => 0, 'color' => array(0, 0, 0)));

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
            $this->printLine($tlx, $tly, $tlx+$lineLength, $tly);
            $this->printLine($tlx, $tly, $tlx, $tly+$lineLength);

            // Top right
            $trx = $boxRightPos;
            $try = $boxHeightPos;
            $this->printLine($trx, $try, $trx-$lineLength, $try);
            $this->printLine($trx, $try, $trx, $try+$lineLength);

            // Bottom left
            $blx = $boxLeftPos;
            $bly = $boxHeightPos+$boxHeight;
            $this->printLine($blx, $bly, $blx+$lineLength, $bly);
            $this->printLine($blx, $bly, $blx, $bly-$lineLength);

            // Bottom right
            $brx = $boxRightPos;
            $bry = $boxHeightPos+$boxHeight;
            $this->printLine($brx, $bry, $brx-$lineLength, $bry);
            $this->printLine($brx, $bry, $brx, $bry-$lineLength);
        }
    }

    /**
     * @param int $x
     */
    private function setX(int $x) : void
    {
        $this->tcPdf->SetX($x+$this->offsetX);
    }

    /**
     * @param int $y
     */
    private function setY(int $y) : void
    {
        $this->tcPdf->SetY($y+$this->offsetY);
    }

    /**
     * @param string $text
     * @param int $w
     * @param int $h
     * @param int $nextLineAlign
     * @param string $textAlign
     */
    private function printCell(
        string $text,
        int $w = 0,
        int $h = 0,
        int $nextLineAlign = 0,
        string $textAlign = self::TCPDF_ALIGN_LEFT
    ) : void {
        $this->tcPdf->Cell($w, $h, $text, self::TCPDF_BORDER, $nextLineAlign, $textAlign);
    }

    /**
     * @param string $text
     * @param int $w
     * @param int $h
     * @param int $nextLineAlign
     * @param string $textAlign
     */
    private function printMultiCell(
        string $text,
        int $w = 0,
        int $h = 0,
        int $nextLineAlign = 0,
        string $textAlign = self::TCPDF_ALIGN_LEFT
    ) : void {
        $this->tcPdf->MultiCell($w, $h, $text, self::TCPDF_BORDER, $textAlign, false, $nextLineAlign);
    }

    /**
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     */
    private function printLine(int $x1, int $y1, int $x2, int $y2) : void
    {
        $this->tcPdf->Line($x1+$this->offsetX, $y1+$this->offsetY, $x2+$this->offsetX, $y2+$this->offsetY);
    }
}
