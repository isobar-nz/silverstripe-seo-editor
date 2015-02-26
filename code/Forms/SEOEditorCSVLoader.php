<?php

/**
 * Class SEOEditorCSVLoader
 */
class SEOEditorCSVLoader extends CsvBulkLoader
{

    /**
     * @var array
     */
    public $duplicateChecks = array(
        'ID' => 'ID',
    );

    /**
     * Update the columns needed when importing from CSV
     *
     * @param array $record
     * @param array $columnMap
     * @param BulkLoader_Result $results
     * @param bool $preview
     * @return bool|int
     */
    public function processRecord($record, $columnMap, &$results, $preview = false)
    {
        $page = $this->findExistingObject($record, $columnMap);

        if (!$page || !$page->exists()) {
            return false;
        }

        foreach ($record as $fieldName => $val) {
            if ($fieldName == 'MetaTitle' || $fieldName == 'MetaDescription') {
                $sqlValue = Convert::raw2sql($val);
                DB::query("UPDATE SiteTree SET {$fieldName} = '{$sqlValue}' WHERE ID = {$page->ID}");
                if ($page->isPublished()) {
                    DB::query("UPDATE SiteTree_Live SET {$fieldName} = '{$sqlValue}' WHERE ID = {$page->ID}");
                }
            }
        }

        return $page->ID;
    }

}