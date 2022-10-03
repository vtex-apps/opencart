<?php if (!defined('DIR_SYSTEM')) exit;

/**
 * PDF Invoice by opencart-templates
 *
 * Settings: library/shared/tcpdf/config
 */
require_once(modification(DIR_SYSTEM . 'library/shared/tcpdf/tcpdf.php'));
require_once(modification(DIR_SYSTEM . 'library/shared/tcpdf/include/tcpdf.EasyTable.php'));
require_once(modification(DIR_SYSTEM . 'library/shared/tcpdf/include/tcpdf.PDFImage.php'));

class vtex_pdf {

    public $data = array();

    /**
     * @var Invoice_TCPDF_EasyTable
     */
    public $tcpdf;

    public function __construct(Registry $registry) {
        if ($this->tcpdf === null) {
            $this->tcpdf = new Invoice_TCPDF_EasyTable('P', 'mm', 'A4');
        }
        return $this->tcpdf;
    }

    /**
     * Sets PDF Config
     *
     * @param array $data
     * @return invoicePdf
     */
    public function Build() {
        $this->tcpdf->SetAuthor('opencart-templates');
        $this->tcpdf->SetCreator('tdpdf');
        $this->tcpdf->SetSubject($this->data['store']['config_name']);
        $this->tcpdf->SetTitle($this->data['store']['config_name']);
        //$this->tcpdf->SetKeywords();
        //$this->tcpdf->SetProtection(array('modify', 'copy'), '', null, 1, null);

        // Set Font
        if (!empty($this->data['config']['module_pdf_invoice_font'])) {
            $this->tcpdf->fontFamily = $this->data['config']['module_pdf_invoice_font'];
        }

        if (in_array($this->tcpdf->fontFamily, array('dejavusans'))) {
            $subset = false;
        } else {
            $subset = 'default';
        }

        $this->tcpdf->AddFont($this->tcpdf->fontFamily, '', $this->tcpdf->fontFamily, $subset);
        $this->tcpdf->AddFont($this->tcpdf->fontFamily, 'B', $this->tcpdf->fontFamily, $subset);

        $this->tcpdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // remove default header/footer
        $this->tcpdf->setPrintHeader(false);
        $this->tcpdf->setPrintFooter(false);

        // set margins
        $this->tcpdf->SetMargins(PDF_MARGIN_LEFT, 15, PDF_MARGIN_RIGHT);
        $this->tcpdf->SetAutoPageBreak(false, PDF_MARGIN_BOTTOM);
        $this->tcpdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $this->tcpdf->SetFillImageCell(false);
        $this->tcpdf->SetTableHeaderPerPage(true);

        return $this->tcpdf;
    }

    /**
     * Main method responsible for drawing the sections onto the page
     * Group products into page(s)?
     *
     * WriteHTML($html, $ln=true, $fill=false, $reseth=false, $cell=false, $align='')
     * writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=0, $reseth=true, $align='', $autopadding=true)
     *
     * @return invoicePdf
     */
    public function Draw() {
        $this->Build();

        if (!$this->tcpdf->PageNo()) {
            $this->DrawBefore();
        } else {
            $this->tcpdf->AddPage();
        }

        $this->_setDefaultOptions();

        if ($this->data['html']) {
            $html = $this->_fixHtml($this->data['html']);
            if ($html) {
                $this->tcpdf->writeHTMLCell('', '', '', '', $html, 0, 1, false, true, '', false);
            }
        }

        $this->AddOrderTable();

        return $this->tcpdf;
    }

    public function DrawBefore(){
        $this->_setDefaultOptions();

        if (!empty($this->data['config']['module_pdf_invoice_prepend_' . $this->data['language_id']])) {
            $html = $this->_fixHtml($this->data['config']['module_pdf_invoice_prepend_' . $this->data['language_id']]);
            if ($html) {
                $this->tcpdf->AddPage();
                $this->tcpdf->SetFont($this->tcpdf->fontFamily, '', 7);
                $this->tcpdf->setCellHeightRatio(1);
                $this->tcpdf->writeHTMLCell('', '', '', '', $html, 0, 1, 0, true, '', false);
            }
        }

        $this->tcpdf->AddPage();

        if (!empty($this->data['config']['module_pdf_invoice_header_' . $this->data['language_id']])) {
            $html = $this->_fixHtml($this->data['config']['module_pdf_invoice_header_' . $this->data['language_id']]);
            if ($html) {
                $this->tcpdf->writeHTMLCell('', '', '', '', $html, 0, 1, 0, true, 'C', false);
            }
        }
    }

    public function DrawAfter(){
        $this->_setDefaultOptions();

        if (!empty($this->data['config']['module_pdf_invoice_after_' . $this->data['language_id']])) {
            $html = $this->_fixHtml($this->data['config']['module_pdf_invoice_after_' . $this->data['language_id']]);
            if ($html) {
                $this->tcpdf->Ln(2);

                $this->tcpdf->writeHTMLCell('', '', '', '', $html, 0, 1, 0, true, 'C', false);
            }
        }

        if (!empty($this->data['config']['module_pdf_invoice_footer_' . $this->data['language_id']])) {
            $html = $this->_fixHtml($this->data['config']['module_pdf_invoice_footer_' . $this->data['language_id']]);

            // Hack for summernote empty content
            if ($html) {
                // Push footer to bottom if there's space
                $footerHeight = $this->tcpdf->GetCellHeightFixed($html, $this->tcpdf->GetInnerPageWidth()) * 2;
                $pageDims = $this->tcpdf->GetPageDimensions();
                $remainingSpacing = ($pageDims['hk'] - $pageDims['bm']) - $this->tcpdf->GetY();

                if ($remainingSpacing > $footerHeight) {
                    $ySpacing = $this->tcpdf->GetY() + ($remainingSpacing - $footerHeight);
                } else {
                    $ySpacing = '';
                }

                $this->tcpdf->SetAutoPageBreak(false, 0);
                $this->tcpdf->writeHTMLCell('', '', '', $ySpacing, $html, 0, 1, 0, true, 'C', false);
            }
        }

        if (!empty($this->data['config']['module_pdf_invoice_append_' . $this->data['language_id']])) {
            $html = $this->_fixHtml($this->data['config']['module_pdf_invoice_append_' . $this->data['language_id']]);
            if ($html) {
                $pageDims = $this->tcpdf->GetPageDimensions();
                $this->tcpdf->lastPage();
                $this->tcpdf->AddPage();
                $this->tcpdf->SetFont($this->tcpdf->fontFamily, '', 7);
                $this->tcpdf->setCellHeightRatio(1);
                $this->tcpdf->writeHTMLCell('', '', '', $pageDims['tm'], $html, 0, 1, 0, true, '', false);
            }
        }

        // Paging numbers - do this manually due ot center alignment issue: https://stackoverflow.com/q/22089305/560287
        if (!empty($this->data['config']['module_pdf_invoice_paging'])) {
            $orig_y = $this->tcpdf->GetY();
            $footer_height = 20;
            $this->tcpdf->SetY($orig_y - $footer_height);
            $this->tcpdf->SetFont($this->tcpdf->fontFamily, '', 7);
            $w_page = isset($this->data['text_paging']) ? $this->data['text_paging'] : 'Page %s of %s';

            $numPages = $this->tcpdf->getNumPages();

            for($i=1;$i <= $numPages; $i++) {
                $this->tcpdf->setPage($i);
                $this->tcpdf->SetY(-15);
                $html = sprintf($w_page, $i, $numPages);
                $this->tcpdf->writeHTMLCell('', '', '', '', $html, 0, 1, 0, true, 'C', true);
            }

            $this->tcpdf->SetX(PDF_MARGIN_LEFT);
            $this->tcpdf->SetY($orig_y);
        }
    }

    public function Output($name='doc.pdf', $dest='I') {
        $this->DrawAfter();

        $this->tcpdf->Output($name, $dest);
    }

    protected function AddOrderTable()
    {
        $this->tcpdf->SetHeaderCellsFillColor($this->data['config']['module_pdf_invoice_color']);
        $this->tcpdf->SetHeaderCellsFontColor(255, 255, 255);
        $this->tcpdf->SetHeaderCellsFontStyle('B');
        $this->tcpdf->SetHeaderCellFixedHeight(8);
        $this->tcpdf->SetHeaderCellHeightRatio(2);

        $this->tcpdf->SetFont($this->tcpdf->fontFamily, '', 9);
        $this->tcpdf->SetCellPaddings(1.5, 2, 1.5, 2);

        if ($this->data['order']['products']) {
            $header_settings = array(
                array('label' => $this->data['column_product'], 'align' => 'L', 'width' => 45),
                array('label' => $this->data['column_model'], 'align' => 'L', 'width' => 15),
                array('label' => $this->data['column_quantity'], 'align' => 'C', 'width' => 12.5),
                array('label' => $this->data['column_price'], 'align' => 'R', 'width' => 12.5),
                array('label' => $this->data['column_total'], 'align' => 'R', 'width' => 15)
            );

            if (!empty($this->data['config']['module_pdf_invoice_order_image'])) {
                $body_settings = array(
                    array('align' => 'C', 'width' => 15),
                    array('align' => 'L', 'width' => 30),
                );
            } else {
                $body_settings = array(
                    array('align' => 'L', 'width' => 45)
                );
            }
            array_push($body_settings,
                array('align' => 'L', 'width' => 15),
                array('align' => 'C', 'width' => 12.5),
                array('align' => 'R', 'width' => 12.5),
                array('align' => 'R', 'width' => 15)
            );

            $rows = array();
            foreach ($this->data['order']['products'] as $product) {
                $row_data = array(
                    '<a href="' . $product['url'] . '" style="text-decoration:none; color:#000000;">' . $product['name'] . '</a>' . $product['option'],
                    $product['model'] . (isset($product['barcode']) ? $product['barcode'] : ''),
                    $product['quantity'],
                    $product['price'],
                    $product['total']
                );

                if (!empty($this->data['config']['module_pdf_invoice_order_image'])) {
                    $image = '';
                    if ($product['image']) {
                        $str_replace = array();
                        // Admin area
                        if (defined('HTTP_CATALOG')) {
                            $str_replace[] = HTTP_CATALOG;
                            if (defined('HTTPS_CATALOG')) {
                                $str_replace[] = HTTPS_CATALOG;
                            }
                        } else {
                            $str_replace[] = HTTP_SERVER;
                            if (defined('HTTPS_SERVER')) {
                                $str_replace[] = HTTPS_SERVER;
                            }
                        }
                        $image_uri = str_replace($str_replace, '', $product['image']);
                        $image_path = str_replace('\\', '/', realpath(DIR_SYSTEM . '../' . dirname($image_uri))) . '/';
                        $image_filename = str_replace('\\', '/', basename($image_uri));
                        $image_width = isset($this->data['config']['module_pdf_invoice_order_image_width']) ? $this->data['config']['module_pdf_invoice_order_image_width'] : 50;
                        $image_height = isset($this->data['config']['module_pdf_invoice_order_image_height']) ? $this->data['config']['module_pdf_invoice_order_image_height'] : 50;
                        $pdf_image = new PDFImage($image_filename, $image_path, $image_width, $image_height);
                        if ($pdf_image) {
                            $image = $pdf_image;
                        }
                    }
                    array_unshift($row_data, $image);
                }

                $rows[] = $row_data;
            }

            $this->_WriteTable($rows, array('header' => $header_settings, 'body' => $body_settings));
        }

        if ($this->data['order']['vouchers']) {
            $body_settings = array(
                array('align' => 'R', 'width' => 85),
                array('align' => 'R', 'width' => 15)
            );

            $rows = array();

            foreach ($this->data['order']['vouchers'] as $voucher) {
                $rows[] = array(
                    $voucher['description'],
                    $voucher['amount'],
                    $voucher['amount']
                );
            }

            $this->_WriteTable($rows, array('body' => $body_settings));
        }

        if ($this->data['order']['totals']) {
            $this->tcpdf->SetFillColor(255, 255, 255);

            $w1 = $this->tcpdf->GetInnerPageWidth() / 100;
            $this->tcpdf->SetCellWidths(array($w1 * 85, $w1 * 15));
            $this->tcpdf->SetCellAlignment(array('R', 'R'));

            $body_settings = array(
                array('align' => 'R', 'width' => 85),
                array('align' => 'R', 'width' => 15)
            );

            $rows = array();

            foreach ($this->data['order']['totals'] as $total) {
                $rows[] = array(
                    $total['title'],
                    $total['text']
                );
            }

            $this->_WriteTable($rows, array('body' => $body_settings));
        }
    }

    /**
     * @param array $rows
     * @param array $columns
     */
    private function _WriteTable($rows = array(), $columns = array())
    {
        // Reverse for RTL
        if (!empty($this->data['config']['module_pdf_invoice_rtl_' . $this->data['language_id']])) {
            $rows = array_map('array_reverse', $rows);

            if (isset($columns['header'])) {
                $columns['header'] = array_reverse($columns['header']);
            }
            if (isset($columns['body'])) {
                $columns['body'] = array_reverse($columns['body']);
            }
        }

        return $this->tcpdf->EasyTable($rows, $columns);
    }

    /**
     * @param $html
     * @return string|string[]
     */
    public function parseShortcodes($html)
    {
        $data = $this->data;

        if (isset($data['store'])) {
            foreach ($data['store'] as $key => $var) {
                if (is_string($var) || is_int($var)) {
                    if (substr($key, 0, 7) == 'config_') { // Replace config_ with store_
                        $key = 'store_' . substr($key, 7);
                    }
                    $find[] = '{$' . $key . '}';
                    $replace[] = $var;
                }
            }
            unset($data['store']);
        }

        foreach ($data as $key => $var) {
            if (is_array($var)) {
                foreach ($var as $key2 => $var2) {
                    if (is_string($var2) || is_int($var2)) {
                        $find[] = '{$' . $key . '.' . $key2 . '}';
                        $replace[] = $var2;
                    }
                }
            } elseif (is_string($var) || is_int($var)) {
                $find[] = '{$' . $key . '}';
                $replace[] = $var;
            }
        }

        return str_replace($find, $replace, $html);
    }

    /**
     * Set Default Options
     */
    private function _setDefaultOptions()
    {
        $this->tcpdf->SetLineWidth(0.1);

        $this->tcpdf->SetCellFillStyle(2);
        $this->tcpdf->SetFillColor(255, 255, 255);
        $this->tcpdf->SetDrawColor(150, 150, 150);

        $this->tcpdf->SetTableRowFillColors(array(array(255, 255, 255)));

        $this->tcpdf->SetFont($this->tcpdf->fontFamily, '', 9);
        $this->tcpdf->SetTextColor(0, 0, 0);

        $this->tcpdf->SetCellPadding(0);
        $this->tcpdf->setCellHeightRatio(1.5);
    }



    /**
     * Fix HTML and parse shortcodes
     * @param $html
     */
    private function _fixHtml($html) {
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        if (trim(strip_tags($html)) != '') {
            $html = preg_replace('~>\s*\n\s*<~', '><', $html);

            $html = $this->parseShortcodes($html);

            return $html;
        }
    }
}

/**
 * Class Invoice_TCPDF_EasyTable
 */
class Invoice_TCPDF_EasyTable extends TCPDF_EasyTable {

    var $fontFamily = 'helvetica'; // 'dejavusans' for utf8 support

    /**
     *
     * @see tFPDF::Header()
     */
    function Header() {

    }

    /**
     * @see TCPDF::Footer()
     */
    function Footer() {

    }

    /**
     * @return int
     */
    function GetInnerPageWidth() {
        return $this->getPageWidth()-(PDF_MARGIN_LEFT+PDF_MARGIN_RIGHT);
    }

    /**
     * @param $cellText
     * @param $cellWidth
     * @return int
     */
    public function GetCellHeightFixed($cellText, $cellWidth)
    {
        $this->startTransaction();
        $this->SetY(0);
        $this->MultiCell($cellWidth, 1, (string)$cellText, 1, "L", 0, 2, 0, 0, true, 0, true);
        $cellBottomY = $this->GetY();
        $cellHeight = $cellBottomY;
        $this->rollbackTransaction($this);
        return ($cellHeight);
    }

    /**
     * Overload to allow HEX color
     * @see TCPDF::SetDrawColor()
     */
    function SetDrawColor($col1=0, $col2=-1, $col3=-1, $col4=-1, $ret=false, $name='') {
        if ($col1 && is_string($col1)) {
            if (substr($col1, 0, 1) == '#') {
                list($col1, $col2, $col3) = $this->_hex2rbg($col1);
            } elseif (substr($col1, 0, 3) == 'rgb') {
                list($col1, $col2, $col3) = sscanf($col1, "rgb(%d, %d, %d)");
            }
        }
        return parent::SetDrawColor($col1, $col2, $col3, $col4, $ret, $name);
    }

    /**
     * Overload to allow HEX color
     * @see TCPDF::SetTextColor()
     */
    function SetTextColor($col1=0, $col2=-1, $col3=-1, $col4=-1, $ret=false, $name='') {
        if ($col1 && is_string($col1)) {
            if (substr($col1, 0, 1) == '#') {
                list($col1, $col2, $col3) = $this->_hex2rbg($col1);
            } elseif (substr($col1, 0, 3) == 'rgb') {
                list($col1, $col2, $col3) = sscanf($col1, "rgb(%d, %d, %d)");
            }
        }
        return parent::SetTextColor($col1, $col2, $col3, $col4, $ret, $name);
    }

    /**
     * Overload to allow HEX color
     * @see FPDF::SetFillColor()
     */
    function SetFillColor($col1=0, $col2=-1, $col3=-1, $col4=-1, $ret=false, $name='') {
        if ($col1 && is_string($col1)) {
            if (substr($col1, 0, 1) == '#') {
                list($col1, $col2, $col3) = $this->_hex2rbg($col1);
            } elseif (substr($col1, 0, 3) == 'rgb') {
                list($col1, $col2, $col3) = sscanf($col1, "rgb(%d, %d, %d)");
            }
        }
        return parent::SetFillColor($col1, $col2, $col3, $col4, $ret, $name);
    }

    /**
     * Overload to allow HEX color
     * @see TCPDF_EasyTable::SetHeaderCellsFillColor()
     */
    function SetHeaderCellsFillColor($R, $G=-1, $B=-1) {
        if ($R && $R[0] == '#') {
            list($R, $G, $B) = $this->_hex2rbg($R);
        } elseif (substr($R, 0, 3) == 'rgb') {
            list($R, $G, $B) = sscanf($R, "rgb(%d, %d, %d)");
        }
        return parent::SetHeaderCellsFillColor($R, $G, $B);
    }

    /**
     * Overload to allow HEX color
     * @see TCPDF_EasyTable::SetCellFontColor()
     */
    function SetCellFontColor($R, $G=-1, $B=-1) {
        if ($R && $R[0] == '#') {
            list($R, $G, $B) = $this->_hex2rbg($R);
        } elseif (substr($R, 0, 3) == 'rgb') {
            list($R, $G, $B) = sscanf($R, "rgb(%d, %d, %d)");
        }
        return parent::SetCellFontColor($R, $G, $B);
    }

    # HEX to RGB
    function _hex2rbg($hex) {
        $hex = substr($hex, 1);
        if (strlen($hex) == 6) {
            list($col1, $col2, $col3) = array($hex[0].$hex[1], $hex[2].$hex[3], $hex[4].$hex[5]);
        } elseif (strlen($hex) == 3) {
            list($col1, $col2, $col3) = array($hex[0].$hex[0], $hex[1].$hex[1], $hex[2].$hex[2]);
        } else {
            return false;
        }
        return array(hexdec($col1), hexdec($col2), hexdec($col3));
    }

    # pixel -> millimeter in 72 dpi
    function _px2mm($px) {
        return $px*25.4/72;
    }
}
