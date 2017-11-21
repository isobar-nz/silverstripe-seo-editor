<?php

namespace LittleGiant\SEOEditor;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\DB;


/**
 * Class SEOEditorMetaTitleColumn
 */
class SEOEditorMetaTitleColumn extends GridFieldDataColumns implements
    GridField_ColumnProvider,
    GridField_HTMLProvider,
    GridField_URLHandler
{

    /**
     * Modify the list of columns displayed in the table.
     *
     * @see {@link GridFieldDataColumns->getDisplayFields()}
     * @see {@link GridFieldDataColumns}.
     *
     * @param GridField $gridField
     * @param array - List reference of all column names.
     */
    public function augmentColumns($gridField, &$columns)
    {
        $columns[] = 'MetaTitle';
    }

    /**
     * Names of all columns which are affected by this component.
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField)
    {
        return array('MetaTitle');
    }

    /**
     * Attributes for the element containing the content returned by {@link getColumnContent()}.
     *
     * @param  GridField $gridField
     * @param  DataObject $record displayed in this row
     * @param  string $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        $errors = $this->getErrors($record);
        return array(
            'class' => count($errors)
                    ? 'seo-editor-error ' . implode(' ', $errors)
                    : 'seo-editor-valid'
        );
    }

    /**
     * HTML for the column, content of the <td> element.
     *
     * @param  GridField $gridField
     * @param  DataObject $record - Record displayed in this row
     * @param  string $columnName
     * @return string - HTML for the column. Return NULL to skip.
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        $field = new TextField('MetaTitle');
        $value = $gridField->getDataFieldValue($record, $columnName);
        $value = $this->formatValue($gridField, $record, $columnName, $value);
        $field->setName($this->getFieldName($field->getName(), $gridField, $record));
        $field->setValue($value);

        return $field->Field() . $this->getErrorMessages();
    }

    /**
     * Additional metadata about the column which can be used by other components,
     * e.g. to set a title for a search column header.
     *
     * @param GridField $gridField
     * @param string $column
     * @return array - Map of arbitrary metadata identifiers to their values.
     */
    public function getColumnMetadata($gridField, $column)
    {
        return array(
            'title' => 'MetaTitle',
        );
    }

    /**
     * Get the errors which are specific to MetaTitle
     *
     * @param DataObject $record
     * @return array
     */
    public function getErrors(DataObject $record)
    {
        $errors = array();

        if (strlen($record->MetaTitle) < 10) {
            $errors[] = 'seo-editor-error-too-short';
        }
        if (strlen($record->MetaTitle) > 55) {
            $errors[] = 'seo-editor-error-too-long';
        }
        if (strlen(SiteTree::get()->filter('MetaTitle', $record->MetaTitle)->count() > 1)) {
            $errors[] = 'seo-editor-error-duplicate';
        }

        return $errors;
    }

    /**
     * Return all the error messages
     *
     * @return string
     */
    public function getErrorMessages()
    {
            return '<div class="seo-editor-errors">' .
                        '<span class="seo-editor-message seo-editor-message-too-short">This title is too short. It should be greater than 10 characters long.</span>' .
                        '<span class="seo-editor-message seo-editor-message-too-long">This title is too long. It should be less than 55 characters long.</span>' .
                        '<span class="seo-editor-message seo-editor-message-duplicate">This title is a duplicate. It should be unique.</span>' .
                    '</div>'
                ;
    }

    /**
     * Add a class to the gridfield
     *
     * @param $gridField
     * @return array|void
     */
    public function getHTMLFragments($gridField)
    {
        $gridField->addExtraClass('ss-seo-editor');
    }

    /**
     * @param $name
     * @param GridField $gridField
     * @param DataObjectInterface $record
     * @return string
     */
    protected function getFieldName($name, GridField $gridField, DataObjectInterface $record)
    {
        return sprintf(
            '%s[%s][%s]', $gridField->getName(), $record->ID, $name
        );
    }

    /**
     * Return URLs to be handled by this grid field, in an array the same form as $url_handlers.
     * Handler methods will be called on the component, rather than the grid field.
     *
     * @param $gridField
     * @return array
     */
    public function getURLHandlers($gridField)
    {
        return array(
            'update/$ID' => 'handleAction',
        );
    }

    /**
     * @param $gridField
     * @param $request
     * @return string
     */
    public function handleAction($gridField, $request)
    {
        $data = $request->postVar($gridField->getName());

        foreach ($data as $id => $params) {
            $page = $gridField->getList()->byId((int)$id);

            foreach ($params as $fieldName => $val) {
                $sqlValue = Convert::raw2sql($val);
                $page->$fieldName = $sqlValue;
                DB::query("UPDATE SiteTree SET {$fieldName} = '{$sqlValue}' WHERE ID = {$page->ID}");
                if ($page->isPublished()) {
                    DB::query("UPDATE SiteTree_Live SET {$fieldName} = '{$sqlValue}' WHERE ID = {$page->ID}");
                }
            }

            return json_encode(
                array(
                    'type' => 'good',
                    'message' => $fieldName . ' saved',
                    'errors' => $this->getErrors($page)
                )
            );
        }

        return json_encode(
            array(
                'type' => 'bad',
                'message' => 'An error occurred while saving'
            )
        );
    }
}