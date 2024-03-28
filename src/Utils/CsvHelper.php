<?php

namespace WP_SMS\Utils;

class CsvHelper
{
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
                fputcsv($file_data, array_keys($line));
            }
            fputcsv($file_data, $line);
        }
        fclose($file_data); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose	
        exit;
    }
}