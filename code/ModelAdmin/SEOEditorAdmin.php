<?php

/**
 * Class SEOEditorAdmin
 */
class SEOEditorAdmin extends ModelAdmin
{

    /**
     * @var string
     */
    private static $menu_title = 'SEO Editor';
    /**
     * @var string
     */
    private static $url_segment = 'seo-editor';
    /**
     * @var string
     */
    private static $menu_icon = 'silverstripe-seo-editor/images/seo-editor-icon.png';
    /**
     * @var array
     */
    private static $managed_models = array(
        'SiteTree'
    );
    /**
     * @var array
     */
    private static $model_importers = array(
        'SiteTree' => 'SEOEditorCSVLoader'
    );
    /**
     * @var array
     */
    private static $allowed_actions = array(
        'ImportForm'
    );

    /**
     * @return SS_HTTPResponse|string|void
     */
    public function init()
    {
        parent::init();
        Requirements::css('silverstripe-seo-editor/css/seo-editor.css');
        Requirements::javascript('silverstripe-seo-editor/javascript/seo-editor.js');
    }

    /**
     * @return SearchContext
     */
    public function getSearchContext()
    {
        $context = parent::getSearchContext();

        $fields = FieldList::create(
            TextField::create('Title', 'Title'),
            TextField::create('MetaTitle', 'MetaTitle'),
            TextField::create('MetaDescription', 'MetaDescription'),
            CheckboxField::create('DuplicatesOnly', 'Duplicates Only'),
            CheckboxField::create('RemoveEmptyMetaTitles', 'Remove Empty MetaTitles'),
            CheckboxField::create('RemoveEmptyMetaDescriptions', 'Remove Empty MetaDescriptions')
        );

        $context->setFields($fields);
        $filters = array(
            'Title' => new PartialMatchFilter('Title'),
            'MetaTitle' => new PartialMatchFilter('MetaTitle'),
            'MetaDescription' => new PartialMatchFilter('MetaDescription')
        );

        $context->setFilters($filters);

        // Namespace fields, for easier detection if a search is present
        foreach ($context->getFields() as $field) $field->setName(sprintf('q[%s]', $field->getName()));
        foreach ($context->getFilters() as $filter) $filter->setFullName(sprintf('q[%s]', $filter->getFullName()));

        return $context;
    }

    /**
     * @param null $id
     * @param null $fields
     * @return mixed
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $grid = $form->Fields()->dataFieldByName('SiteTree');
        if ($grid) {
            $config = $grid->getConfig();
            $config->removeComponentsByType('GridFieldAddNewButton');
            $config->removeComponentsByType('GridFieldPrintButton');
            $config->removeComponentsByType('GridFieldEditButton');
            $config->removeComponentsByType('GridFieldExportButton');
            $config->removeComponentsByType('GridFieldDeleteAction');

            $config->getComponentByType('GridFieldDataColumns')->setDisplayFields(
                array(
                    'ID' => 'ID',
                    'Title' => 'Title',
                )
            );

            $config->addComponent(
                new GridFieldExportButton(
                    'before',
                    array(
                        'ID' => 'ID',
                        'Title' => 'Title',
                        'MetaTitle' => 'MetaTitle',
                        'MetaDescription' => 'MetaDescription'
                    )
                )
            );

            $config->addComponent(new SEOEditorMetaTitleColumn());
            $config->addComponent(new SEOEditorMetaDescriptionColumn());

        }

        return $form;
    }

    /**
     * @return Form
     */
    public function ImportForm()
    {
        $form = parent::ImportForm();
        $modelName = $this->modelClass;

        if ($form) {        
            $form->Fields()->removeByName("SpecFor{$modelName}");
            $form->Fields()->removeByName("EmptyBeforeImport");
        }

        return $form;
    }

    /**
     * Get the list for the GridField
     *
     * @return SS_List
     */
    public function getList()
    {
        $list = parent::getList();
        $params = $this->request->requestVar('q');

        if (isset($params['RemoveEmptyMetaTitles']) && $params['RemoveEmptyMetaTitles']) {
            $list = $this->removeEmptyAttributes($list, 'MetaTitle');
        }

        if (isset($params['RemoveEmptyMetaDescriptions']) && $params['RemoveEmptyMetaDescriptions']) {
            $list = $this->removeEmptyAttributes($list, 'MetaDescription');
        }

        $list = $this->markDuplicates($list);

        if (isset($params['DuplicatesOnly']) && $params['DuplicatesOnly']) {
            $list = $list->filter('IsDuplicate', true);
        }

        $list = $list->sort('ID');

        return $list;
    }

    /**
     * Mark duplicate attributes
     *
     * @param SS_List $list
     * @return SS_List
     */
    private function markDuplicates($list)
    {
        $duplicates = $this->findDuplicates($list, 'MetaTitle')->map('ID', 'ID')->toArray();
        $duplicateList = new ArrayList();

        foreach ($list as $item) {
            if (in_array($item->ID, $duplicates)) {
                $item->IsDuplicate = true;
                $duplicateList->push($item);
            }
        }

        $duplicates = $this->findDuplicates($list, 'MetaDescription')->map('ID', 'ID')->toArray();
        foreach ($list as $item) {
            if (in_array($item->ID, $duplicates)) {
                $item->IsDuplicate = true;
                if (!$list->byID($item->ID)) {
                    $duplicateList->push($item);
                }
            }
        }

        $duplicateList->merge($list);
        $duplicateList->removeDuplicates();
        return $duplicateList;
    }

    /**
     * Find duplicate attributes within a list
     *
     * @param SS_List $list
     * @param string $type
     * @return SS_List
     */
    private function findDuplicates(SS_List $list, $type)
    {
        $pageAttributes = $list->map('ID', $type)->toArray();

        $potentialDuplicateAttributes = array_unique(
            array_diff_assoc(
                $pageAttributes,
                array_unique($pageAttributes)
            )
        );
        $duplicateAttributes = array_filter($pageAttributes, function ($value) use ($potentialDuplicateAttributes) {
            return in_array($value, $potentialDuplicateAttributes);
        });

        if (!count($duplicateAttributes)) {
            return $list;
        }

        return $list->filter(
            array(
                'ID' => array_keys($duplicateAttributes),
            )
        );
    }

    /**
     * Remove pages with empty attributes
     *
     * @param SS_List $list
     * @param string $type
     * @return SS_List
     */
    private function removeEmptyAttributes(SS_List $list, $type)
    {
        $pageAttributes = $list->map('ID', $type)->toArray();

        $emptyAttributess = array_map(function ($value) {
            return $value == '';
        }, $pageAttributes);

        if (!count($emptyAttributess)) {
            return $list;
        }
        
        return $list->filter(
            array(
                'ID:not' => array_keys(
                    array_filter($emptyAttributess, function ($value) {
                            return $value == 1;
                        }
                    )
                )
            )
        );
    }


}
