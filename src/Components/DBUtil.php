<?php


namespace WP_SMS\Components;
class DBUtil
{
    /**
     * Table name structure in the database
     *
     * @var string
     */
    private static $tableStructure = '[prefix]sms_[name]';

    /**
     * Get WordPress Table Prefix
     */
    public static function prefix()
    {
        global $wpdb;
        return $wpdb->prefix;
    }

    /**
     * Get formatted table name
     *
     * @param string $table
     * @return string
     */
    private static function getTableName($table)
    {
        return str_ireplace(['[prefix]', '[name]'], [self::prefix(), $table], self::$tableStructure);
    }

    /**
     * Get all tables that match the WP_SMS prefix
     *
     * @return array
     */
    private static function getAllTables()
    {
        global $wpdb;
        $prefix = self::prefix() . 'sms_';
        $tables = $wpdb->get_col("SHOW TABLES LIKE '{$prefix}%'");
        return array_map(fn($table) => str_replace($prefix, '', $table), $tables);
    }

    /**
     * Get the list of tables in WP_SMS dynamically
     *
     * @param string $export ("all" to return all existing tables, or a specific table name)
     * @param array|string $except (tables to exclude)
     * @return array|null|string
     */
    public static function table($export = 'all', $except = [])
    {
        $list   = [];
        $tables = self::getAllTables();

        if (is_string($except)) {
            $except = [$except];
        }

        $availableTables = array_diff($tables, $except);

        foreach ($availableTables as $tbl) {
            $tableName = self::getTableName($tbl);

            if ($export === "all") {
                $list[$tbl] = $tableName;
            } else {
                return isset($list[$export]) ? $list[$export] : null;
            }
        }

        return $export === 'all' ? $list : null;
    }
}
