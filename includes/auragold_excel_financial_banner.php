<?php

/**
 * Jewelstep-style Excel header bands (merged title, license row, pastel period strip).
 */

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Applies rows 1–4 branding. Period row uses light salmon / peach with dark centred text (reference layouts).
 *
 * Optional $palette keys: title_blue_rgb, period_fill_rgb, title_font (merged into title style),
 * license_font (merged; default red), period_font (merged), title_row_height (float, row 1).
 *
 * @return int Column header row number (always 5)
 */
function auragold_excel_financial_banner_layout(
    Worksheet $sheet,
    string $lastCol,
    string $titleUpper,
    string $businessLicenseFullLine,
    string $periodLineCentered,
    array $palette = []
): int {
    $blue = $palette['title_blue_rgb'] ?? '4472C4';
    $periodFill = $palette['period_fill_rgb'] ?? 'F8CBAD';

    $thinBorder = [
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
        ],
    ];

    $fillTitleBlue = [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $blue],
    ];
    $fillRowWhite = [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'FFFFFF'],
    ];
    $fillPeriodBand = [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $periodFill],
    ];

    $fontTitleWhite = array_merge(
        ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
        $palette['title_font'] ?? []
    );
    $fontLicense = array_merge(
        ['color' => ['rgb' => 'C62828'], 'size' => 11],
        $palette['license_font'] ?? []
    );
    $fontPeriodDark = array_merge(
        ['bold' => true, 'color' => ['rgb' => '1F2937'], 'size' => 12],
        $palette['period_font'] ?? []
    );

    $row1Height = isset($palette['title_row_height']) ? (float) $palette['title_row_height'] : 28.0;
    if ($row1Height < 20) {
        $row1Height = 28.0;
    }

    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->setCellValue('A1', $titleUpper);
    $sheet->getStyle('A1')->getFont()->applyFromArray($fontTitleWhite);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A1')->getFill()->applyFromArray($fillTitleBlue);
    $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($thinBorder);
    $sheet->getRowDimension(1)->setRowHeight($row1Height);

    $sheet->mergeCells('A2:' . $lastCol . '2');
    $sheet->getStyle('A2:' . $lastCol . '2')->getFill()->applyFromArray($fillRowWhite);
    $sheet->getStyle('A2:' . $lastCol . '2')->applyFromArray($thinBorder);
    $sheet->getRowDimension(2)->setRowHeight(10);

    $sheet->mergeCells('A3:' . $lastCol . '3');
    $sheet->setCellValue('A3', $businessLicenseFullLine);
    $sheet->getStyle('A3')->getFont()->applyFromArray($fontLicense);
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A3:' . $lastCol . '3')->getFill()->applyFromArray($fillRowWhite);
    $sheet->getStyle('A3:' . $lastCol . '3')->applyFromArray($thinBorder);

    $sheet->mergeCells('A4:' . $lastCol . '4');
    $sheet->setCellValue('A4', $periodLineCentered);
    $sheet->getStyle('A4')->getFont()->applyFromArray($fontPeriodDark);
    $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A4')->getFill()->applyFromArray($fillPeriodBand);
    $sheet->getStyle('A4:' . $lastCol . '4')->applyFromArray($thinBorder);
    $sheet->getRowDimension(4)->setRowHeight(22);

    return 5;
}
