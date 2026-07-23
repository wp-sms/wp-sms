<?php

namespace WP_SMS\Utils;

if (!defined('ABSPATH')) exit;

class CsvHelper
{
    /**
     * Prefixes a leading control character so spreadsheet software treats the
     * cell as text instead of a formula (CSV/formula injection).
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function neutralizeFormula($value)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        if (!in_array($value[0], array('=', '+', '-', '@', "\t", "\r"), true)) {
            return $value;
        }

        // Leave plain phone numbers and numeric values untouched (e.g.
        // +491234567, -12, +1 (555) 123-4567); only genuine formula-like strings
        // are prefixed so a spreadsheet treats them as text.
        if (preg_match('/^[+-]?[0-9][0-9\s().\/-]*$/', $value)) {
            return $value;
        }

        return "'" . $value;
    }

    /**
     * Convert data to csv format and send download header
     *
     * @param $fileName
     * @param $data
     * @param bool $header_included
     */
    public function array2csv($fileName, $data, $header_included = false)
    {
        // Downloads file - no return
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header('Content-Description: File Transfer');
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename={$fileName}");
        header("Expires: 0");
        header("Pragma: public");

        $file_data = fopen('php://output', 'w');
        foreach ($data as $line) {
            // Add a header row if not included
            if (!$header_included) {
                // Use the keys as titles
                fputcsv($file_data, array_map(array(self::class, 'neutralizeFormula'), array_keys($line)));
            }
            fputcsv($file_data, array_map(array(self::class, 'neutralizeFormula'), $line));
        }
        fclose($file_data); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }
}